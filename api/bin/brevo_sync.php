<?php
// CLI only: php api/bin/brevo_sync.php [--all]
// Syncs verified users who opted in to marketing and are not synced yet (or all opted-in with --all) into the Brevo list.
// Also retries queued preference changes from the account toggle (brevo_pending = 1): opt-in → add/update, opt-out → remove from list.
// Users without consent are never sent. Unsubscribed/blacklisted contacts are skipped. Sends no email.
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/_brevo.php';
if (!abd_brevo_enabled()) { fwrite(STDERR, "Brevo not configured (brevo.php API_KEY + LIST_ID)\n"); exit(1); }
$all = in_array('--all', $argv, true);
$ids = abd_q('SELECT id FROM abd_users WHERE marketing_opt_in = 1 AND email_verified_at IS NOT NULL AND is_active = 1' . ($all ? '' : ' AND brevo_synced_at IS NULL') . ' ORDER BY id')->fetchAll(PDO::FETCH_COLUMN);
$tally = [];
try { foreach (abd_q('SELECT id FROM abd_users WHERE brevo_pending = 1 ORDER BY id')->fetchAll(PDO::FETCH_COLUMN) as $id) {
  $r = abd_brevo_apply_pref((int)$id); $tally['pending:' . $r] = ($tally['pending:' . $r] ?? 0) + 1; echo "pending user #$id: $r\n"; usleep(150000); } }
catch (PDOException $e) { fwrite(STDERR, "brevo_pending column missing — run api/bin/migrate.php (010)\n"); }
foreach ($ids as $id) { $r = abd_brevo_sync_user((int)$id); $tally[$r] = ($tally[$r] ?? 0) + 1; echo "user #$id: $r\n"; usleep(150000); }
echo 'done: ' . json_encode($tally) . ' | never consented (not sent): ' . (int)abd_q('SELECT COUNT(*) FROM abd_users WHERE marketing_opt_in = 0')->fetchColumn() . "\n";
