#!/usr/bin/env python3
"""DRAFT extension for the Abad EVE lab grader (does NOT modify grader.py).
New rubric features used by CCNA labs 02-15:
  kind "match"  : {node, cmd, regex, negate?}  run one allowlisted show command, pass if regex found (multiline) [or NOT found if negate]
  ping "expect" : "fail" -> pass when the ping does NOT succeed (ACL / VLAN isolation / port-security)
  ping IPv6     : targets containing ':' (VPCS `ping <v6>` and IOS `ping ipv6 <v6>`)
  "stage"       : optional on any check; rubric["stages"] lists stage ids. grade(..., stage="r1") evaluates
                  checks with no stage + that stage only. Without stage, staged checks are skipped.
Safety: same allowlist/denylist idea as grader.py. Only `show …`, `ping …`, `terminal length 0` are ever sent.
Integration (later, after review): in grader.collect/evaluate delegate unknown kinds to collect_ext/evaluate_ext."""
import re
ALLOW = re.compile(r'^(show [\w ./|:-]+|ping (ipv6 )?[\da-fA-F.: a-z-]+|terminal length 0)$')
DENY = re.compile(r'\b(conf|configure|write|wr|reload|copy|erase|delete|debug|clear|set|save|clock|no|enable)\b', re.I)
def safe(cmd):
    head = cmd.split('|')[0]
    if not ALLOW.match(cmd) or (not cmd.startswith('terminal') and DENY.search(head.split(' ', 1)[1])):
        raise ValueError('command not allowed: ' + cmd)
    return cmd
def active(rubric, stage=None):
    return [c for c in rubric['checks'] if not c.get('stage') or c.get('stage') == stage]
def ping_cmd(c):
    n, t = c['node'], c['target']; v6 = ':' in t
    if n.startswith('PC') and not c.get('ios_host'): return 'ping %s -c 3' % t
    base = ('ping ipv6 %s' if v6 else 'ping %s') % t
    return base + (' source %s' % c['source'] if c.get('source') else '') + ' repeat 3'
def needs(rubric, stage=None, ios_hosts=()):
    """{node: [cmds]} for the ext kinds (match + ext pings). Base kinds stay with grader.collect."""
    need = {}
    for c in active(rubric, stage):
        L = need.setdefault(c['node'], [])
        if c['kind'] == 'match': x = c['cmd']
        elif c['kind'] == 'ping':
            if c['node'] in ios_hosts: c = dict(c, ios_host=True)
            x = ping_cmd(c)
        else: continue
        safe(x)
        if x not in L: L.append(x)
    return need
def ping_ok(s):
    m = re.search(r'Success rate is (\d+) percent \((\d+)/(\d+)\)', s or '')
    if m: return int(m.group(2)) > 0, 'نسبة النجاح %s%% (%s/%s)' % m.groups()
    rep = len(re.findall(r'bytes from|icmp6_seq=\d+ ttl', s or ''))
    return rep > 0, ('%d ردود' % rep) if rep else ('لا ردود / timeout' if s else 'لا مخرجات')
def evaluate_check(c, outs, ios_hosts=()):
    """outs = {cmd: text} for c['node']. Returns (ok, expected_ar, found_ar) for ext kinds, or None for base kinds."""
    if c['kind'] == 'match':
        s = outs.get(c['cmd']) or ''
        hit = re.search(c['regex'], s, re.M) is not None
        ok = (not hit) if c.get('negate') else hit
        exp = ('لا يجب أن يظهر في %s' if c.get('negate') else 'مطابقة في مخرجات %s') % c['cmd']
        found = ('وُجدت المطابقة' if hit else 'لا توجد مطابقة') + ((' — ' + s.strip().splitlines()[-1][:120]) if s.strip() and not ok else '')
        return ok, exp, found
    if c['kind'] == 'ping' and (c.get('expect') == 'fail' or ':' in c['target'] or c['node'] in ios_hosts):
        cc = dict(c, ios_host=c['node'] in ios_hosts)
        ok, found = ping_ok(outs.get(ping_cmd(cc)))
        if c.get('expect') == 'fail': return (not ok), 'ping يجب أن يفشل إلى %s' % c['target'], found
        return ok, 'ping ناجح إلى %s' % c['target'], found
    return None
def evaluate_ext(rubric, outs_by_node, errs, attempt=1, stage=None, ios_hosts=(), base_eval=None):
    """Score all active checks. base_eval(check, outs) handles iface/route/pc (e.g. a thin wrapper over grader.evaluate)."""
    show_sol = attempt >= int(rubric.get('solution_after', 3)); res = []; score = tot = 0
    for c in active(rubric, stage):
        n = c['node']; tot += c['pts']; r = {'id': c.get('id', ''), 'node': n, 'title_ar': c['title_ar'], 'max': c['pts'], 'pts': 0}
        if n in errs: ok, exp, found = False, '', 'تعذّر قراءة %s (%s)' % (n, errs[n])
        else:
            v = evaluate_check(c, outs_by_node.get(n, {}), ios_hosts)
            if v is None: v = base_eval(c, outs_by_node.get(n, {})) if base_eval else (False, '', 'kind غير مدعوم في الامتداد')
            ok, exp, found = v
        if ok: r['pts'] = c['pts']; score += c['pts']
        r.update(ok=ok, expected_ar=exp, found_ar=found)
        if not ok:
            r['hint_ar'] = c.get('hint_ar', '')
            if show_sol and c.get('solution_ar'): r['solution_ar'] = c['solution_ar']
        res.append(r)
    return {'ok': True, 'score': score, 'max': tot, 'pct': round(100 * score / tot) if tot else 0, 'checks': res, 'stage': stage, 'solution_shown': show_sol}
if __name__ == '__main__':      # offline self-test: every rubric's commands pass the allowlist; mock evaluation
    import json, glob, sys
    bad = 0
    for p in sorted(glob.glob('/workspace/ccna-labs/*/CCNA__*.json')):
        rb = json.load(open(p)); hosts = ('PC1', 'PC2') if '10-Extended' in p else ()
        for st in [None] + list(rb.get('stages', {})):
            try: needs(rb, st, hosts)
            except ValueError as e: bad += 1; print('BLOCKED', p, e)
        for c in rb['checks']:
            if c['kind'] == 'match': re.compile(c['regex'], re.M)
        print('ok', p.split('/')[-1], len(rb['checks']), 'checks')
    t = {'kind': 'match', 'node': 'R1', 'cmd': 'show ip ospf neighbor', 'regex': r'^2\.2\.2\.2\s+\d+\s+FULL', 'pts': 1, 'title_ar': 't'}
    assert evaluate_check(t, {'show ip ospf neighbor': '2.2.2.2           1   FULL/DR         00:00:36    12.0.0.2        GigabitEthernet0/1'})[0]
    f = {'kind': 'ping', 'node': 'PC1', 'target': '10.10.20.11', 'expect': 'fail', 'pts': 1, 'title_ar': 't'}
    assert evaluate_check(f, {'ping 10.10.20.11 -c 3': 'host (10.10.20.11) not reachable'})[0]
    print('self-test passed' if not bad else 'self-test: %d blocked' % bad); sys.exit(1 if bad else 0)
