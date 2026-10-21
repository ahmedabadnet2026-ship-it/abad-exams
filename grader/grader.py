#!/usr/bin/env python3
"""Abad EVE lab grader (read-only). Stdlib only.
POST /grade  body JSON {seat,lab,uid,attempt,ts,nonce}  header X-Abad-Sig: hex HMAC-SHA256(GRADER_HMAC_SECRET, raw body)
Reads node consoles via telnet on the seat's EVE VM; ONLY allowlisted commands are ever sent."""
import json, os, re, hmac, hashlib, time, socket, threading, http.cookiejar, urllib.request, sys
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
import grader_ext as GX   # CCNA 02-15 rubric kinds: match, ping expect=fail, IPv6 ping, IOS hosts, stages
D = os.path.dirname(os.path.abspath(__file__))
CFG = json.load(open(os.environ.get('GRADER_CFG', os.path.join(D, 'config.json'))))  # {secret, seats:{"1":{ip,user,pass}}}
ALLOW = re.compile(r'^(show [\w ./|:-]+|ping (ipv6 )?[\da-fA-F.: a-z-]+|terminal length 0)$')
DENY = re.compile(r'\b(conf|configure|write|wr|reload|copy|erase|delete|debug|clear|set|save|clock|no|enable)\b', re.I)
LOCKS, NONCES = {}, {}

def safe(cmd):
    """Allowlist: show …, ping …, terminal length 0. Nothing else is ever sent to a device."""
    head = cmd.split('|')[0]
    if not ALLOW.match(cmd) or (not cmd.startswith('terminal') and DENY.search(head.split(' ', 1)[1])):
        raise ValueError('command not allowed: ' + cmd)
    return cmd

class Console:
    """Minimal telnet client: strips IAC negotiation, waits for a prompt."""
    PROMPT = re.compile(rb'(\n|^)[\w.-]+(\(config[^)]*\))?[>#] ?$|VPCS> ?$|\n[\w-]+> ?$')
    def __init__(s, host, port, t=8):
        s.k = socket.create_connection((host, port), timeout=t); s.k.settimeout(t)
    def _read(s, until, t):
        buf, end = b'', time.time() + t
        while time.time() < end:
            try: c = s.k.recv(4096)
            except socket.timeout: break
            if not c: break
            out = bytearray(); i = 0
            while i < len(c):
                if c[i] == 255 and i + 1 < len(c):
                    cmd = c[i+1]
                    if cmd in (251, 252, 253, 254) and i + 2 < len(c):
                        s.k.send(bytes([255, 254 if cmd in (251, 252) else 252, c[i+2]])) if cmd in (253,) else None
                        i += 3; continue
                    i += 2; continue
                out.append(c[i]); i += 1
            buf += bytes(out)
            buf = re.sub(rb'\x1b\][^\x07]*\x07|\x1b\[[0-9;?]*[A-Za-z]', b'', buf)   # vIOS OSC title / ANSI escapes break prompt detection
            if until.search(buf.rstrip(b' ') + b' ') or until.search(buf): break
        return buf.decode('utf-8', 'replace').replace('\r', '')
    def prompt(s):
        s.k.send(b'\r'); time.sleep(.4); s.k.send(b'\r')
        return s._read(s.PROMPT, 6)
    def run(s, cmd, t=15):
        s.k.send(safe(cmd).encode() + b'\r')
        return s._read(s.PROMPT, t)
    def close(s):
        try: s.k.close()
        except Exception: pass

def eve_nodes(seat):
    """Return {name: (type, host, port, running)} for the seat user's open lab, via EVE API."""
    sc = CFG['seats'][str(seat)]
    base = 'http://%s' % sc['ip']
    cj = http.cookiejar.CookieJar(); op = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(cj))
    def call(path, data=None):
        r = urllib.request.Request(base + path, data=json.dumps(data).encode() if data else None, method='POST' if data else 'GET')
        with op.open(r, timeout=10) as f: return json.load(f)
    call('/api/auth/login', {'username': sc['user'], 'password': sc['pass'], 'html5': '-1'})
    me = call('/api/auth').get('data', {})
    lab = me.get('lab') or ''
    return me, lab, call

def is_ext(c, hosts=()):
    return c['kind'] == 'match' or (c['kind'] == 'ping' and (c.get('expect') == 'fail' or ':' in c['target'] or c['node'] in hosts))

def collect(seat, lab_req, rubric, fixture=None):
    """Gather raw outputs per (node, cmd). fixture = dict for mocked demos."""
    hosts = tuple(rubric.get('ios_hosts', ()))
    need = GX.needs(rubric, None, hosts)                   # ext kinds (empty for base-only rubrics such as 01)
    for c in GX.active(rubric):
        if is_ext(c, hosts): continue
        n = c['node']; L = need.setdefault(n, [])
        if c['kind'] == 'iface':
            for x in ('show ip interface brief', 'show ip interface %s | include Internet address' % c['intf']):
                if x not in L: L.append(x)
        elif c['kind'] == 'route':
            if 'show ip route static' not in L: L.append('show ip route static')
        elif c['kind'] == 'pc':
            if 'show ip' not in L: L.append('show ip')
        elif c['kind'] == 'ping':
            L.append(('ping %s -c 3' % c['target']) if n.startswith('PC') else
                     ('ping %s source %s repeat 3' % (c['target'], c['source']) if c.get('source') else 'ping %s repeat 3' % c['target']))
    for n in need:
        for x in need[n]: safe(x)                               # validate everything before touching a device
    if fixture is not None:
        return {n: {x: fixture.get(n, {}).get(x) for x in need[n]} for n in need}, {}
    me, lab, call = eve_nodes(seat)
    errs = {}
    lab_open = lab.lstrip('/')
    if not lab_open: return {}, {'_lab': 'not_open'}
    base = lambda p: re.sub(r'_\d{10,}$', '', p.rsplit('/', 1)[-1].replace('.unl', ''))
    if lab_req and base(lab_open) != base(lab_req): return {}, {'_lab': 'other', '_open': lab_open}
    errs['_open'] = lab_open
    nodes = {}
    for pth in ([lab_req, lab_open] if lab_req else [lab_open]):       # requested path first (open-lab pointer may be stale after a move)
        try: nodes = call('/api/labs/%s/nodes' % urllib.request.quote(pth.lstrip('/'))).get('data', {}) or {}
        except Exception: nodes = {}
        if nodes: break
    byname = {v['name']: v for v in nodes.values()}
    out = {}
    for n, cmds in need.items():
        v = byname.get(n); out[n] = {}
        if not v: errs[n] = 'missing'; continue
        if int(v.get('status', 0)) != 2: errs[n] = 'stopped'; continue
        m = re.match(r'telnet://([^:/]+):(\d+)', v.get('url', ''))
        if not m: errs[n] = 'noconsole'; continue
        try:
            con = Console(CFG['seats'][str(seat)]['ip'], int(m.group(2)))
            p = con.prompt()
            if '(config' in p: errs[n] = 'configmode'; con.close(); continue
            if 'initial configuration dialog' in p: errs[n] = 'setup'; con.close(); continue
            if not n.startswith('PC') or n in hosts: con.run('terminal length 0', 6)
            for x in cmds: out[n][x] = con.run(x, 20 if x.startswith('ping') else 10)
            con.close()
        except Exception as e:
            errs[n] = 'console:' + type(e).__name__
    return out, errs

LEN2MASK = lambda l: '.'.join(str((0xffffffff << (32 - l) >> i) & 255) for i in (24, 16, 8, 0))
NODE_ERR_AR = {'stopped': 'الجهاز %s متوقف (غير مُشغَّل)', 'missing': 'الجهاز %s غير موجود في اللاب المفتوح',
               'configmode': 'الجهاز %s في وضع configuration — اخرج بـ end ثم أعد التسليم',
               'noconsole': 'تعذّر الوصول لكونسول %s',
               'setup': 'الجهاز %s ينتظر إجابة setup dialog (اكتب no) — لم يُعدّ بعد'}

def evaluate(rubric, out, errs, attempt):
    res, score, tot = [], 0, 0
    show_sol = attempt >= int(rubric.get('solution_after', 3))
    hosts = tuple(rubric.get('ios_hosts', ()))
    for c in GX.active(rubric):
        n = c['node']; o = out.get(n, {}); tot += c['pts']
        r = {'id': c['id'], 'node': n, 'title_ar': c['title_ar'], 'max': c['pts'], 'pts': 0, 'ok': False}
        ok, exp, found = False, '', ''
        if n in errs:
            k = errs[n].split(':')[0]; found = (NODE_ERR_AR.get(k, 'تعذّر قراءة %s') % n)
        if c['kind'] == 'iface':
            exp = '%s/%d على %s والواجهة up/up' % (c['ip'], c['len'], c['intf'])
            if n not in errs:
                br = o.get('show ip interface brief') or ''
                ia = o.get('show ip interface %s | include Internet address' % c['intf']) or ''
                ln = re.search(r'^%s\s+(\S+)\s+\S+\s+\S+\s+(administratively down|up|down)\s+(up|down)' % re.escape(c['intf']), br, re.M)
                m = re.search(r'Internet address is ([\d.]+)/(\d+)', ia)
                ip, l = (m.group(1), int(m.group(2))) if m else (ln.group(1) if ln else 'unassigned', None)
                st = '%s/%s' % (ln.group(2), ln.group(3)) if ln else 'غير معروفة'
                if not ln: found = 'الواجهة %s غير موجودة في المخرجات' % c['intf']
                elif ip in ('unassigned', None): found = 'لا يوجد IP على %s (الحالة %s)' % (c['intf'], st)
                elif ip != c['ip']: found = 'IP خاطئ: %s/%s بدل %s/%d' % (ip, l, c['ip'], c['len'])
                elif l != c['len']: found = 'القناع خاطئ: /%s (%s) بدل /%d (%s)' % (l, LEN2MASK(l), c['len'], LEN2MASK(c['len']))
                elif ln.group(2) == 'administratively down': found = 'الواجهة %s مُطفأة (administratively down) — العنوان صحيح' % c['intf']
                elif ln.group(3) != 'up': found = 'الواجهة %s حالتها %s (لا يوجد link)' % (c['intf'], st)
                else: ok = True; found = '%s/%d — %s' % (ip, l, st)
        elif c['kind'] == 'route':
            exp = 'S %s via %s' % (c['prefix'], c['via'])
            if n not in errs:
                rt = o.get('show ip route static') or ''
                net = c['prefix'].split('/')[0]
                ms = re.findall(r'^S\*?\s+([\d.]+)(/\d+)?.*?(?:via ([\d.]+)|is directly connected, (\S+))', rt, re.M)
                # IOS prints "/24 is subnetted" header lines; take mask from header when absent
                hit = [x for x in ms if x[0] == net]
                if not hit: found = 'لا يوجد route ثابت لشبكة %s' % net + ((' (الموجود: %s)' % '، '.join('%s%s via %s' % (a, b, cc or d) for a, b, cc, d in ms)) if ms else '')
                else:
                    a, b, via, ifc = hit[0]
                    if b and b != '/' + c['prefix'].split('/')[1]: found = 'القناع خاطئ: %s%s بدل %s' % (a, b, c['prefix'])
                    elif via and via != c['via']: found = 'next-hop خاطئ: %s بدل %s' % (via, c['via'])
                    elif ifc: found = 'الـ route يشير لواجهة %s بدل next-hop %s' % (ifc, c['via'])
                    else: ok = True; found = 'S %s%s via %s' % (a, b or '', via)
        elif c['kind'] == 'pc':
            exp = '%s/%d gateway %s' % (c['ip'], c['len'], c['gw'])
            if n not in errs:
                s = o.get('show ip') or ''
                m = re.search(r'IP/MASK\s*:\s*([\d.]+)/(\d+)', s); g = re.search(r'GATEWAY\s*:\s*([\d.]+)', s)
                ip, l = (m.group(1), int(m.group(2))) if m else ('0.0.0.0', 0)
                gw = g.group(1) if g else '0.0.0.0'
                if not m or ip == '0.0.0.0': found = 'لا يوجد IP على %s' % n
                elif ip != c['ip']: found = 'IP خاطئ: %s/%d بدل %s/%d' % (ip, l, c['ip'], c['len'])
                elif l != c['len']: found = 'القناع خاطئ: /%d بدل /%d' % (l, c['len'])
                elif gw in ('0.0.0.0', ''): found = 'لا يوجد default gateway على %s' % n
                elif gw != c['gw']: found = 'Gateway خاطئ: %s بدل %s' % (gw, c['gw'])
                else: ok = True; found = '%s/%d gw %s' % (ip, l, gw)
        elif is_ext(c, hosts):
            if n not in errs:
                ok, exp, found = GX.evaluate_check(c, o, hosts)
                if not ok and re.search(r'% Invalid input', o.get(c.get('cmd', ''), '') or ''):
                    found = 'الأمر يحتاج وضع privileged (#) — اكتب enable على %s ثم أعد التسليم' % n
        elif c['kind'] == 'ping':
            exp = 'ping ناجح إلى %s' % c['target']
            if n not in errs:
                s = next((v for k, v in o.items() if k.startswith('ping')), '') or ''
                m = re.search(r'Success rate is (\d+) percent \((\d+)/(\d+)\)', s)
                if m: ok = int(m.group(2)) > 0; found = 'نسبة النجاح %s%% (%s/%s)' % m.groups()
                elif n.startswith('PC'):
                    rep = len(re.findall(r'bytes from', s)); ok = rep > 0
                    found = ('%d ردود' % rep) if ok else ('host unreachable / timeout' if s else 'لا مخرجات')
                else: found = 'لا مخرجات'
        if ok: r['pts'] = c['pts']; score += c['pts']
        r.update(ok=ok, expected_ar=exp, found_ar=found)
        if not ok:
            r['hint_ar'] = 'شغّل جميع أجهزة اللاب (Start all nodes) وانتظر حتى تكتمل ثم أعد التسليم.' if n in errs else c.get('hint_ar', '')
            if show_sol and c.get('solution_ar'): r['solution_ar'] = c['solution_ar']
        res.append(r)
    out_errs = {k: v for k, v in errs.items() if k.startswith('_')}
    return {'ok': True, 'score': score, 'max': tot, 'pct': round(100 * score / tot) if tot else 0, 'checks': res,
            'notes': out_errs, 'solution_shown': show_sol}

def rubric_for(lab):
    name = re.sub(r'[^\w-]', '_', lab.replace('.unl', '').replace('/', '__'))
    p = os.path.join(D, 'rubrics', name + '.json')
    if not re.fullmatch(r'[\w-]+', name) or not os.path.isfile(p): return None
    return json.load(open(p))

def grade(req, fixture=None):
    rb = rubric_for(req['lab'])
    if not rb: return {'ok': False, 'error': 'no_rubric'}
    seat = int(req['seat'])
    if fixture is None and not os.environ.get('GRADER_FIXTURE') and str(seat) not in CFG['seats']: return {'ok': False, 'error': 'bad_seat'}
    lk = LOCKS.setdefault(seat, threading.Lock())
    if not lk.acquire(blocking=False): return {'ok': False, 'error': 'busy'}
    try:
        fx = fixture
        if fx is None and os.environ.get('GRADER_FIXTURE'):          # demo only: canned device outputs, result flagged mock
            fx = json.load(open(os.environ['GRADER_FIXTURE']))['nodes']
        out, errs = collect(seat, req['lab'], rb, fx)
        if errs.get('_lab') == 'not_open': return {'ok': False, 'error': 'lab_not_open'}
        if errs.get('_lab') == 'other': return {'ok': False, 'error': 'other_lab_open', 'open': errs.get('_open')}
        r = evaluate(rb, out, errs, int(req.get('attempt', 1))); r['lab'] = req['lab']; r['seat'] = seat
        if fx is not None: r['mock'] = True
        return r
    finally: lk.release()

class H(BaseHTTPRequestHandler):
    def log_message(s, f, *a): sys.stderr.write('%s %s\n' % (time.strftime('%F %T'), f % a))
    def _j(s, code, d):
        b = json.dumps(d, ensure_ascii=False).encode(); s.send_response(code)
        s.send_header('Content-Type', 'application/json; charset=utf-8'); s.send_header('Content-Length', str(len(b))); s.end_headers(); s.wfile.write(b)
    def do_GET(s): s._j(200 if s.path == '/health' else 404, {'ok': s.path == '/health'})
    def do_POST(s):
        if s.path != '/grade': return s._j(404, {'ok': False})
        raw = s.rfile.read(min(int(s.headers.get('Content-Length', 0)), 4096))
        sig = s.headers.get('X-Abad-Sig', '')
        if not hmac.compare_digest(hmac.new(CFG['secret'].encode(), raw, hashlib.sha256).hexdigest(), sig): return s._j(401, {'ok': False, 'error': 'sig'})
        try: req = json.loads(raw)
        except Exception: return s._j(400, {'ok': False, 'error': 'json'})
        now = time.time()
        if abs(now - float(req.get('ts', 0))) > 60 or req.get('nonce') in NONCES: return s._j(401, {'ok': False, 'error': 'stale'})
        NONCES[req.get('nonce')] = now
        for k in [k for k, t in NONCES.items() if now - t > 120]: NONCES.pop(k, None)
        try: s._j(200, grade(req))
        except Exception as e: s._j(500, {'ok': False, 'error': 'grader:' + type(e).__name__})

if __name__ == '__main__':
    if len(sys.argv) > 2 and sys.argv[1] == '--fixture':   # offline demo: grader.py --fixture fixtures/x.json [attempt]
        fx = json.load(open(sys.argv[2])); print(json.dumps(grade({'seat': 1, 'lab': fx['lab'], 'attempt': int(sys.argv[3]) if len(sys.argv) > 3 else 1}, fx['nodes']), ensure_ascii=False, indent=1)); sys.exit()
    if len(sys.argv) > 3 and sys.argv[1] == '--live':      # one-shot live run: grader.py --live SEAT LAB
        print(json.dumps(grade({'seat': int(sys.argv[2]), 'lab': sys.argv[3], 'attempt': 1}), ensure_ascii=False, indent=1)); sys.exit()
    ThreadingHTTPServer((os.environ.get('GRADER_BIND', '127.0.0.1'), int(os.environ.get('GRADER_PORT', '8790'))), H).serve_forever()
