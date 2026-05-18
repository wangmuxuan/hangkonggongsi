<?php
declare(strict_types=1);

require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/lib/parse.php';
require_once __DIR__ . '/lib/render.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
  http_response_code(400);
  echo "missing id\n";
  exit;
}

$db = ps_db();
$stmt = $db->prepare('SELECT id, name, url, notes, node_count, last_fetch_at FROM subscriptions WHERE id = :id AND enabled = 1');
$stmt->execute([':id' => $id]);
$r = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$r) {
  http_response_code(404);
  echo "not found\n";
  exit;
}

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

$cachedUrl = ps_base_url() . '/sub.php?id=' . rawurlencode((string)$id);
$rawUrl = (string)$r['url'];
$nodeCount = $r['node_count'] !== null ? (string)$r['node_count'] : '未知';
$lastFetch = $r['last_fetch_at'] ? (string)$r['last_fetch_at'] : '未抓取';
$title = (string)($r['name'] ?: ('订阅 #' . $id));

$cacheStmt = $db->prepare('SELECT cached_content, cached_content_type, last_fetch_status, last_fetch_error FROM subscriptions WHERE id = :id');
$cacheStmt->execute([':id' => $id]);
$cacheRow = $cacheStmt->fetch(PDO::FETCH_ASSOC) ?: [];
$cachedContent = (string)($cacheRow['cached_content'] ?? '');
$cachedType = (string)($cacheRow['cached_content_type'] ?? '');
$lastStatus = $cacheRow['last_fetch_status'] !== null ? (int)$cacheRow['last_fetch_status'] : null;
$lastError = (string)($cacheRow['last_fetch_error'] ?? '');

$decoded = ps_decode_subscription_body($cachedContent);
$nodeLines = ps_extract_node_lines($decoded['text'], 800);
$nodeListText = $nodeLines ? implode("\n", $nodeLines) : '';

?>
<!doctype html>
<html lang="zh-CN">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?=h($title)?> - <?=h(ps_site_name())?></title>
  <?php if (ps_meta_keywords() !== ''): ?>
    <meta name="keywords" content="<?=h(ps_meta_keywords())?>">
  <?php endif; ?>
  <?php if (ps_meta_description() !== ''): ?>
    <meta name="description" content="<?=h(ps_meta_description())?>">
  <?php endif; ?>
  <link rel="stylesheet" href="<?=h(ps_asset('static/app.css'))?>">
</head>
<body>
  <div class="container">
    <header class="header">
      <div>
        <h1><?=h($title)?></h1>
        <div class="muted">节点：<?=h($nodeCount)?> · 最后更新：<?=h($lastFetch)?></div>
      </div>
      <div class="header-actions">
        <a class="btn" href="./">返回列表</a>
      </div>
    </header>

    <section class="card">
      <?php if (!empty($r['notes'])): ?>
        <div class="notes"><?=nl2br(h($r['notes']))?></div>
      <?php endif; ?>

      <div class="share-grid">
        <div class="share-box">
          <div class="label">缓存订阅（推荐分享）</div>
          <div class="linkline">
            <input class="input" readonly value="<?=h($cachedUrl)?>" onclick="this.select()">
          </div>
          <div class="qr" data-qr="<?=h($cachedUrl)?>" data-qr-id="cached"></div>
          <div class="actions">
            <button class="btn primary" type="button" data-copy="<?=h($cachedUrl)?>">复制链接</button>
            <button class="btn" type="button" data-download-qr="cached">下载二维码</button>
          </div>
        </div>

        <div class="share-box">
          <div class="label">原始订阅</div>
          <div class="linkline">
            <input class="input" readonly value="<?=h($rawUrl)?>" onclick="this.select()">
          </div>
          <div class="qr" data-qr="<?=h($rawUrl)?>" data-qr-id="raw"></div>
          <div class="actions">
            <button class="btn" type="button" data-copy="<?=h($rawUrl)?>">复制链接</button>
            <button class="btn" type="button" data-download-qr="raw">下载二维码</button>
          </div>
        </div>
      </div>

      <div style="margin-top:12px;">
        <div class="title">节点列表</div>
        <?php if ($cachedContent === ''): ?>
          <div class="muted">暂无缓存内容。请先在后台对该订阅点“抓取”。</div>
        <?php elseif (!$nodeLines): ?>
          <div class="muted">
            未识别到节点 URI（类型：<?=h($decoded['kind'])?>）。
            <?php if ($lastStatus !== null && ($lastStatus < 200 || $lastStatus >= 300)): ?>
              抓取状态：HTTP <?=h($lastStatus)?> <?= $lastError ? ('，' . h($lastError)) : '' ?>。
            <?php endif; ?>
          </div>
          <details style="margin-top:10px;">
            <summary class="muted">查看缓存原文（可能很长）</summary>
            <textarea class="input" rows="10" readonly onclick="this.select()"><?=h($decoded['text'])?></textarea>
          </details>
        <?php else: ?>
          <div class="muted">已识别 <?=h((string)count($nodeLines))?> 条（最多展示 800 条）。</div>
          <div class="actions" style="margin-top:8px;">
            <button class="btn" type="button" data-copy="<?=h($nodeListText)?>">复制节点列表</button>
          </div>
          <textarea class="input" rows="10" readonly onclick="this.select()"><?=h($nodeListText)?></textarea>
        <?php endif; ?>
      </div>
    </section>

    <footer class="footer muted">
      <div>提示：二维码在浏览器端生成；如需更新节点数，请在后台点“抓取”。</div>
      <?php if (ps_footer_html() !== ''): ?>
        <div style="margin-top:8px;"><?=ps_footer_html()?></div>
      <?php endif; ?>
    </footer>
  </div>

  <script src="<?=h(ps_asset('static/qrcode.min.js'))?>"></script>
  <script src="<?=h(ps_asset('static/app.js'))?>"></script>
</body>
</html>
