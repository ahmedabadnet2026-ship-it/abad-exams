<?php
// CRM API (staff/admin only; mutations need X-CSRF-Token)
//   GET  ?id=N | ?tasks=open[&mine=1] | ?reports=1 | ?staff=1 | (default) contacts list: ?q=&stage=&source=&owner_id=&tag=&page=&per_page=
//   POST {action: create | update | stage | activity | task_add | task_done | delete}
declare(strict_types=1);
require_once __DIR__ . '/_crm.php';

abd_api(function () {
  $me = abd_require_staff();
  $uid = (int)$me['id'];
  $m = $_SERVER['REQUEST_METHOD'] ?? 'GET';
  if ($m === 'GET') {
    if (isset($_GET['id'])) { $c = abd_crm_get((int)$_GET['id']); $c ? abd_out(['ok' => true, 'contact' => $c]) : abd_err('not_found', 404); }
    if (($_GET['tasks'] ?? '') === 'open') abd_out(['ok' => true, 'items' => abd_crm_tasks_open(!empty($_GET['mine']) ? $uid : null, 300)]);
    if (!empty($_GET['reports'])) abd_out(['ok' => true, 'reports' => abd_crm_reports()]);
    if (!empty($_GET['staff'])) abd_out(['ok' => true, 'items' => abd_crm_staff()]);
    abd_out(['ok' => true] + abd_crm_list($_GET, (int)($_GET['page'] ?? 1), (int)($_GET['per_page'] ?? 50)));
  }
  if ($m !== 'POST') abd_err('method', 405);
  abd_require_csrf();
  $b = abd_body();
  $id = (int)($b['id'] ?? 0);
  switch (abd_str($b, 'action', 20)) {
    case 'create':
      [$d, $bad] = abd_crm_validate($b);
      if ($bad) abd_err('invalid', 422, ['fields' => $bad]);
      if (($dup = abd_crm_find($d['phone'] ?? null, $d['email'] ?? null)) && empty($b['allow_duplicate'])) abd_err('duplicate', 409, ['id' => $dup]);
      abd_out(['ok' => true, 'id' => abd_crm_create($d, $uid)], 201);
    case 'update':
    case 'stage':
      $b2 = abd_str($b, 'action', 20) === 'stage' ? array_intersect_key($b, ['stage' => 1, 'lost_reason' => 1]) : $b;
      [$d, $bad] = abd_crm_validate($b2, true);
      if ($bad) abd_err('invalid', 422, ['fields' => $bad]);
      abd_crm_update($id, $d, $uid) ? abd_out(['ok' => true, 'id' => $id]) : abd_err('not_found', 404);
    case 'activity':
      $type = abd_str($b, 'type', 20); $body = abd_str($b, 'body', 4000);
      if (!in_array($type, ['call', 'whatsapp', 'note', 'meeting', 'email'], true) || $body === '') abd_err('invalid', 422);
      if (!abd_q('SELECT 1 FROM abd_crm_contacts WHERE id = ?', [$id])->fetchColumn()) abd_err('not_found', 404);
      abd_crm_log($id, $type, $body, $uid);
      abd_q('UPDATE abd_crm_contacts SET updated_at = NOW() WHERE id = ?', [$id]);
      abd_out(['ok' => true], 201);
    case 'task_add':
      $r = abd_crm_task_add($id > 0 ? $id : null, abd_str($b, 'title', 255), abd_str($b, 'due_at', 20),
        (int)($b['assigned_to'] ?? 0) > 0 ? (int)$b['assigned_to'] : null, $uid);
      $r[0] ? abd_out(['ok' => true, 'id' => $r[1]], 201) : abd_err($r[1] === 'not_found' ? 'not_found' : 'invalid', $r[1] === 'not_found' ? 404 : 422, ['fields' => [$r[1]]]);
    case 'task_done':
      abd_crm_task_done((int)($b['task_id'] ?? 0), !isset($b['done']) || (bool)$b['done'], $uid) ? abd_out(['ok' => true]) : abd_err('not_found', 404);
    case 'delete':
      if ($me['role'] !== 'admin') abd_err('forbidden', 403);
      abd_q('DELETE FROM abd_crm_contacts WHERE id = ?', [$id])->rowCount() ? abd_out(['ok' => true]) : abd_err('not_found', 404);
  }
  abd_err('action', 400);
});
