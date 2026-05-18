<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/../lib/fetch.php';

// Token auth
$cfg = ps_config();
$expected = (string)($cfg['cron_token'] ?? '');
$got = (string)($_GET['token'] ?? '');
if ($expected === '' || !hash_equals($expected, $got)) {
  http_response_code(403);
  echo "forbidden\n";
  exit;
}

$minutes = (int)ps_setting('auto_fetch_minutes', '0');
if ($minutes <= 0) {
  echo "disabled\n";
  exit;
}

// basic lock (avoid overlap)
$lockFile = ps_data_dir() . '/cron.lock';
@mkdir(ps_data_dir(), 0700, true);
$fp = @fopen($lockFile, 'c+');
if (!$fp || !flock($fp, LOCK_EX | LOCK_NB)) {
  echo "locked\n";
  exit;
}

$db = ps_db();
$rows = $db->query('SELECT id, last_fetch_at FROM subscriptions WHERE enabled = 1 ORDER BY id ASC')->fetchAll(PDO::FETCH_ASSOC);

$now = time();
$due = 0;
$fetched = 0;
foreach ($rows as $r) {
  $id = (int)$r['id'];
  $last = (string)($r['last_fetch_at'] ?? '');
  $lastTs = $last ? strtotime($last . ' UTC') : 0;
  if ($lastTs <= 0 || ($now - $lastTs) >= ($minutes * 60)) {
    $due++;
    ps_fetch_and_cache($id);
    $fetched++;
  }
}

echo "ok due=$due fetched=$fetched\n";
flock($fp, LOCK_UN);
fclose($fp);

