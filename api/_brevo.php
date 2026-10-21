<?php
// Brevo (marketing contacts) helper. Config OUTSIDE the web root: …/private_html/abad-config/brevo.php (fallback abad-data)
//   <?php return ['API_KEY' => 'xkeysib-…', 'LIST_ID' => 123];
// Every call is best-effort: short timeouts, errors are logged and returned, never thrown — registration/verification
// must never fail or hang because of Brevo. Only users with marketing_opt_in = 1 AND a verified email are ever sent.
// Unsubscribes: a contact that is emailBlacklisted, or unsubscribed from our list, is never re-added and never un-blacklisted
// (we never send emailBlacklisted; we skip such contacts entirely).
declare(strict_types=1);
if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__)) { http_response_code(404); exit; }
require_once __DIR__ . '/_db.php';

function abd_brevo_cfg(): array {
  static $c = null; if ($c !== null) return $c;
  $c = [];
  $pos = strpos(__DIR__, '/public_html');
  $root = $pos !== false ? substr(__DIR__, 0, $pos) : dirname(__DIR__, 2);
  foreach (['abad-config', 'abad-data'] as $d) { $f = $root . '/private_html/' . $d . '/brevo.php'; if (is_file($f) && is_readable($f)) { $x = require $f; if (is_array($x)) $c = $x; break; } }
  if (($e = getenv('ABAD_BREVO_BASE')) !== false && $e !== '') $c['BASE'] = $e;   // local mock only
  return $c;
}
function abd_brevo_enabled(): bool { $c = abd_brevo_cfg(); return !empty($c['API_KEY']) && (int)($c['LIST_ID'] ?? 0) > 0; }

/** @return array{0:int,1:?array} [http status (0 = transport error), decoded body] */
function abd_brevo_req(string $method, string $path, ?array $body = null): array {
  $c = abd_brevo_cfg();
  if (empty($c['API_KEY'])) return [0, ['code' => 'not_configured']];
  $ch = curl_init(rtrim((string)($c['BASE'] ?? 'https://api.brevo.com/v3'), '/') . $path);
  curl_setopt_array($ch, [CURLOPT_CUSTOMREQUEST => $method, CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 3, CURLOPT_TIMEOUT => 5,
    CURLOPT_HTTPHEADER => ['api-key: ' . $c['API_KEY'], 'accept: application/json', 'content-type: application/json']]);
  if ($body !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_UNICODE));
  $raw = curl_exec($ch); $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); $err = curl_error($ch); curl_close($ch);
  if ($raw === false) { error_log('[abad-brevo] ' . $method . ' ' . $path . ' transport: ' . $err); return [0, null]; }
  $j = $raw === '' ? [] : json_decode((string)$raw, true);
  if ($code >= 400 && $code !== 404) error_log('[abad-brevo] ' . $method . ' ' . $path . ' HTTP ' . $code . ' ' . (is_array($j) ? (($j['code'] ?? '') . ' ' . ($j['message'] ?? '')) : ''));
  return [$code, is_array($j) ? $j : null];
}

/**
 * Opt-out: remove the user's email from our Brevo list (contact itself kept; Brevo's own blacklist untouched).
 * @return string 'removed' | 'disabled' | 'error'   (404 / "not in list" count as removed)
 */
function abd_brevo_unsub_user(int $uid): string {
  try {
    if (!abd_brevo_enabled()) return 'disabled';
    $email = (string)abd_q('SELECT email FROM abd_users WHERE id = ?', [$uid])->fetchColumn();
    if ($email === '') return 'removed';
    $list = (int)abd_brevo_cfg()['LIST_ID'];
    [$c, $j] = abd_brevo_req('POST', '/contacts/lists/' . $list . '/contacts/remove', ['emails' => [$email]]);
    if ($c === 201 || $c === 200 || $c === 204 || $c === 404 || ($c === 400 && stripos((string)($j['message'] ?? ''), 'already removed') !== false)
        || ($c === 400 && stripos((string)($j['message'] ?? ''), 'does not exist') !== false)) {
      abd_q('UPDATE abd_users SET brevo_synced_at = NOW(), brevo_pending = 0 WHERE id = ?', [$uid]);
      return 'removed';
    }
    return 'error';
  } catch (Throwable $e) { error_log('[abad-brevo] unsub ' . $uid . ': ' . $e->getMessage()); return 'error'; }
}
/** Apply the user's current preference to Brevo; on failure flag brevo_pending = 1 so api/bin/brevo_sync.php retries it. */
function abd_brevo_apply_pref(int $uid): string {
  $on = (int)abd_q('SELECT marketing_opt_in FROM abd_users WHERE id = ?', [$uid])->fetchColumn();
  $r = $on ? abd_brevo_sync_user($uid) : abd_brevo_unsub_user($uid);
  try { abd_q('UPDATE abd_users SET brevo_pending = ? WHERE id = ?', [in_array($r, ['error', 'disabled'], true) ? 1 : 0, $uid]); }
  catch (Throwable $e) { error_log('[abad-brevo] pending flag: ' . $e->getMessage()); }
  return $r;
}

/** Split a name into FIRSTNAME / LASTNAME (first word / rest). */
function abd_brevo_split(string $n): array { $p = preg_split('/\s+/u', trim($n)) ?: []; return [(string)array_shift($p), implode(' ', $p)]; }

/**
 * Add/update one consenting, verified user in the Brevo list.
 * @return string 'synced' | 'skipped_no_consent' | 'skipped_unsubscribed' | 'disabled' | 'error'
 */
function abd_brevo_sync_user(int $uid): string {
  try {
    if (!abd_brevo_enabled()) return 'disabled';
    $u = abd_q('SELECT id, email, full_name_ar, full_name_en, phone, email_verified_at, marketing_opt_in, is_active FROM abd_users WHERE id = ?', [$uid])->fetch();
    if (!$u || !(int)$u['marketing_opt_in'] || $u['email_verified_at'] === null || !(int)$u['is_active']) return 'skipped_no_consent';
    $list = (int)abd_brevo_cfg()['LIST_ID'];
    $email = (string)$u['email'];
    // never re-add someone who unsubscribed (globally blacklisted, or unsubscribed from our list)
    [$gc, $existing] = abd_brevo_req('GET', '/contacts/' . rawurlencode($email));
    if ($gc === 200 && is_array($existing)) {
      if (!empty($existing['emailBlacklisted']) || in_array($list, array_map('intval', (array)($existing['listUnsubscribed'] ?? [])), true)) {
        abd_q('UPDATE abd_users SET brevo_synced_at = NOW() WHERE id = ?', [$uid]);
        return 'skipped_unsubscribed';
      }
    } elseif ($gc !== 404) return 'error';
    [$fn, $ln] = abd_brevo_split((string)($u['full_name_en'] ?: $u['full_name_ar']));
    $attr = ['FIRSTNAME' => $fn, 'LASTNAME' => $ln, 'NAME_AR' => (string)$u['full_name_ar']];
    $phone = (string)($u['phone'] ?? '');
    if (preg_match('/^\+\d{8,15}$/', $phone)) $attr['SMS'] = $phone;
    $body = ['email' => $email, 'attributes' => $attr, 'listIds' => [$list], 'updateEnabled' => true];   // no emailBlacklisted key: never override
    [$c, $j] = abd_brevo_req('POST', '/contacts', $body);
    if ($c === 400 && isset($attr['SMS'])) {   // e.g. SMS already used by another contact / invalid → retry without SMS
      unset($body['attributes']['SMS']);
      [$c, $j] = abd_brevo_req('POST', '/contacts', $body);
    }
    if ($c === 201 || $c === 204) { abd_q('UPDATE abd_users SET brevo_synced_at = NOW() WHERE id = ?', [$uid]); return 'synced'; }
    return 'error';
  } catch (Throwable $e) { error_log('[abad-brevo] sync ' . $uid . ': ' . $e->getMessage()); return 'error'; }
}
