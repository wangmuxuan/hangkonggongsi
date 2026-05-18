<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';

$db = ps_db();
$rows = $db->query('SELECT id, name, url, notes, node_count, last_fetch_at, created_at, updated_at FROM subscriptions WHERE enabled = 1 ORDER BY id DESC')->fetchAll(PDO::FETCH_ASSOC);

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
  'ok' => true,
  'count' => count($rows),
  'items' => $rows,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

