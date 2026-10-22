<?php
// GET api/lectures.php → {ok, logged_in, now, early_min, courses:[{id,title_ar,total,sessions:[{no,start,end,has_link,join_url,passcode}]}]}
// Signed-in users only; trainees see only courses they are enrolled in, and join_url/passcode only from 15 min before start until the end.
declare(strict_types=1);
require_once __DIR__ . '/_lectures_lib.php';

abd_api(function () {
  if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') abd_err('method', 405);
  $u = abd_user();
  if (!$u) abd_out(['ok' => true, 'logged_in' => false, 'courses' => []], 401);
  if (!abd_rate('lectures', (string)$u['id'], 240, 3600)) abd_err('rate', 429);
  abd_out(['ok' => true, 'logged_in' => true, 'now' => (int)round(microtime(true) * 1000), 'early_min' => ABD_LEC_EARLY_MIN,
    'staff' => in_array($u['role'], ['admin', 'staff', 'zoom_staff'], true), 'courses' => abd_lec_for_user($u)]);
});
