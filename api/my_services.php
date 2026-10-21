<?php
// GET api/my_services.php → {ok, logged_in, user, services:{exams:[…], materials:[…], labs:[…]}}
// Logged-out callers get logged_in:false (200) so the page can show the sign-in prompt.
declare(strict_types=1);
require_once __DIR__ . '/_services_lib.php';

abd_api(function () {
  if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') abd_err('method', 405);
  $u = abd_user();
  if (!$u) abd_out(['ok' => true, 'logged_in' => false, 'services' => ['exams' => [], 'materials' => [], 'labs' => []]]);
  abd_out(['ok' => true, 'logged_in' => true, 'staff' => in_array($u['role'], ['admin', 'staff'], true), 'open_all' => (bool)ABAD_OPEN_ALL_FOR_LOGGED_IN, 'user' => abd_public_user($u), 'services' => abd_services_of($u)]);
});
