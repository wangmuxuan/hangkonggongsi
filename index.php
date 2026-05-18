<?php
declare(strict_types=1);

require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/lib/render.php';

$db = ps_db();
$rows = $db->query('SELECT id, name, url, enabled, notes, node_count, last_fetch_at, created_at, updated_at FROM subscriptions WHERE enabled = 1 ORDER BY id DESC')->fetchAll(PDO::FETCH_ASSOC);

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

?>
<!doctype html>
<html lang="zh-CN">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?=h(ps_site_name())?></title>
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
        <h1><?=h(ps_site_name())?></h1>
        <div class="muted">订阅列表（含详细信息、二维码与节点列表）</div>
      </div>
      <div class="header-actions">
        <a class="btn" href="admin/">后台管理</a>
      </div>
    </header>

    <?php if (!$rows): ?>
      <div class="card">
        <div class="muted">暂无订阅，请到后台添加。</div>
      </div>
    <?php else: ?>
      <div class="grid">
        <?php foreach ($rows as $r): ?>
          <?php
            $id = (int)$r['id'];
            $rawUrl = (string)$r['url'];
            $cachedUrl = ps_base_url() . '/sub.php?id=' . rawurlencode((string)$id);
            $shareUrl = ps_base_url() . '/share.php?id=' . rawurlencode((string)$id);
            $lastFetch = $r['last_fetch_at'] ? (string)$r['last_fetch_at'] : '未抓取';
            $nodeCount = $r['node_count'] !== null ? (string)$r['node_count'] : '未知';
          ?>
          <article class="card">
            <div class="row">
              <div class="title"><?=h($r['name'] ?: ('订阅 #' . $id))?></div>
              <div class="badge"><?=h($nodeCount)?> 节点</div>
            </div>
            <div class="muted">最后更新：<?=h($lastFetch)?></div>
            <?php if (!empty($r['notes'])): ?>
              <div class="notes"><?=nl2br(h($r['notes']))?></div>
            <?php endif; ?>

            <div class="two-cols">
              <div>
                <div class="label">原始订阅</div>
                <div class="linkline">
                  <input class="input" readonly value="<?=h($rawUrl)?>" onclick="this.select()">
                </div>
                <div class="qr" data-qr="<?=h($rawUrl)?>"></div>
              </div>
              <div>
                <div class="label">缓存订阅（本站）</div>
                <div class="linkline">
                  <input class="input" readonly value="<?=h($cachedUrl)?>" onclick="this.select()">
                </div>
                <div class="qr" data-qr="<?=h($cachedUrl)?>"></div>
              </div>
            </div>

            <div class="actions">
              <a class="btn primary" href="<?=h($cachedUrl)?>" target="_blank" rel="noreferrer">打开缓存订阅</a>
              <a class="btn" href="<?=h($shareUrl)?>">分享/二维码</a>
              <button class="btn" type="button" data-copy="<?=h($rawUrl)?>">复制原始链接</button>
              <button class="btn" type="button" data-copy="<?=h($cachedUrl)?>">复制缓存链接</button>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
    <footer class="footer muted">
      <div>提示：二维码在浏览器生成；如订阅源限制服务器 IP，抓取会失败。</div>
      <?php if (ps_footer_html() !== ''): ?>
        <div style="margin-top:8px;"><?=ps_footer_html()?></div>
      <?php endif; ?>
    </footer>
  </div>

  <script src="<?=h(ps_asset('static/qrcode.min.js'))?>"></script>
  <script src="<?=h(ps_asset('static/app.js'))?>"></script>
</body>
</html>
