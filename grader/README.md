# Abad EVE lab grader (read-only)
Runs on ubantu-server (reaches EVE VMs 192.168.200.201-212 on ESXi host 1; seats 13-20 = 192.168.200.213-220 on ESXi host 2 — add each to config.json "seats" when it is enabled). Stdlib Python 3, no installs.
- `POST /grade` — body `{seat,lab,uid,attempt,ts,nonce}`, header `X-Abad-Sig` = HMAC-SHA256(secret, raw body); ±60 s, nonce replay rejected.
- Reads the seat user's open lab via the EVE API, then each node's telnet console. Only `show …`, `ping …`, `terminal length 0` are ever sent (allowlist + denylist; nodes in config mode/setup dialog are skipped, never altered).
- Rubrics: `rubrics/<folder>__<lab>.json` — kinds iface / route / pc / ping with `title_ar`, `hint_ar`, `solution_ar` (shown after `solution_after` attempts); the grader reports expected vs. what was found (wrong IP/mask, shutdown, wrong next-hop, missing gateway…).
- Demo: `python3 grader.py --fixture fixtures/MOCK-partial.json 3` (mock outputs) · `python3 grader.py --live 1 CCNA/01-Static-Routing.unl`.
- Service: `GRADER_CFG=config.json GRADER_PORT=8795 python3 grader.py` (binds 127.0.0.1; port 8790 is taken by the stats service).
- CCNP ENCOR: `rubrics/CCNP_ENCOR__<lab>.json` (lab path `CCNP ENCOR/<lab>.unl`); only seat-tested labs are enabled — list in api/lab_grade.php ABD_GRADE_ENCOR + eve-classroom.html options.
