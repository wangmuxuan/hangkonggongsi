<?php
declare(strict_types=1);

require_once __DIR__ . '/lib/bootstrap.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
  http_response_code(400);
  echo "missing id\n";
  exit;
}

$db = ps_db();
$stmt = $db->prepare('SELECT name, cached_content, cached_content_type, last_fetch_at FROM subscriptions WHERE id = :id AND enabled = 1');
$stmt->execute([':id' => $id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$row) {
  http_response_code(404);
  echo "not found\n";
  exit;
}

$content = (string)($row['cached_content'] ?? '');
if ($content === '') {
  http_response_code(409);
  echo "no cached content yet; ask admin to fetch\n";
  exit;
}

$name = (string)($row['name'] ?? ('sub-' . $id));
$filename = preg_replace('/[^a-zA-Z0-9._-]+/', '_', $name) ?: ('sub-' . $id);
$contentType = (string)($row['cached_content_type'] ?? '');
if ($contentType === '') $contentType = 'text/plain; charset=utf-8';

header('Content-Type: ' . $contentType);
header('Content-Disposition: inline; filename="' . $filename . '"');
echo $content;

