<?php
// CLI only: php api/bin/migrate.php  — applies api/migrations/*.sql in order, once each.
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/_db.php';

$pdo = abd_db();
$pdo->exec("CREATE TABLE IF NOT EXISTS abd_migrations (
  name VARCHAR(190) NOT NULL PRIMARY KEY,
  applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
$done = array_flip($pdo->query('SELECT name FROM abd_migrations')->fetchAll(PDO::FETCH_COLUMN));
$files = glob(dirname(__DIR__) . '/migrations/*.sql') ?: [];
sort($files, SORT_STRING);
$n = 0;
foreach ($files as $f) {
  $name = basename($f);
  if (isset($done[$name])) { echo "skip  $name\n"; continue; }
  $sql = (string)file_get_contents($f);
  $sql = preg_replace('/^\s*--.*$/m', '', $sql);           // strip line comments
  foreach (array_filter(array_map('trim', explode(';', $sql))) as $stmt) $pdo->exec($stmt);
  abd_q('INSERT INTO abd_migrations (name) VALUES (?)', [$name]);
  echo "apply $name\n"; $n++;
}
echo "done ($n applied)\n";
foreach ($pdo->query("SHOW TABLES LIKE 'abd\\_%'")->fetchAll(PDO::FETCH_COLUMN) as $t) echo "  table $t\n";
