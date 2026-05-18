<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/../lib/auth.php';

ps_require_login();

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

$saved = false;
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $siteName = trim((string)($_POST['site_name'] ?? ''));
  $keywords = trim((string)($_POST['seo_keywords'] ?? ''));
  $desc = trim((string)($_POST['seo_description'] ?? ''));
  $footer = trim((string)($_POST['footer_html'] ?? ''));
  $minutes = trim((string)($_POST['auto_fetch_minutes'] ?? '0'));
  if ($siteName === '') $siteName = '中国航空';

  if ($minutes === '') $minutes = '0';
  if (!preg_match('/^\d+$/', $minutes)) {
    $error = '自动抓取间隔必须是分钟数（0 表示关闭）';
  } else {
    $m = (int)$minutes;
    if ($m < 0) $m = 0;
    if ($m > 10080) $m = 10080; // max 7 days
    ps_set_setting('site_name', $siteName);
    ps_set_setting('seo_keywords', $keywords);
    ps_set_setting('seo_description', $desc);
    ps_set_setting('footer_html', $footer);
    ps_set_setting('auto_fetch_minutes', (string)$m);
    $saved = true;
  }
}

$siteName = ps_setting('site_name', '中国航空');
$keywords = ps_setting('seo_keywords', '');
$desc = ps_setting('seo_description', '');
$footer = ps_setting('footer_html', '');
$minutes = ps_setting('auto_fetch_minutes', '0');

$cfg = ps_config();
$cronToken = (string)($cfg['cron_token'] ?? '');
$cronUrl = ps_base_url() . '/admin/cron.php?token=' . rawurlencode($cronToken);

?>
<!doctype html>
<html lang="zh-CN">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>系统设置 - <?=h(ps_site_name())?></title>
  <link rel="stylesheet" href="../<?=h(ps_asset('static/app.css'))?>">
</head>
<body>
  <div class="container">
    <header class="header">
      <div>
        <h1>系统设置</h1>
        <div class="muted">站点名称 / SEO / 底部友链与联系方式 / 自动抓取</div>
      </div>
      <div class="header-actions">
        <a class="btn" href="./">返回后台</a>
      </div>
    </header>

    <?php if ($saved): ?>
      <div class="card" style="margin-bottom:12px;"><div class="muted">已保存</div></div>
    <?php endif; ?>
    <?php if ($error): ?>
      <div class="alert"><?=h($error)?></div>
    <?php endif; ?>

    <section class="card">
      <form method="post" class="form">
        <div class="field">
          <label class="label">站点名称</label>
          <input class="input" name="site_name" value="<?=h($siteName)?>">
        </div>
        <div class="field">
          <label class="label">SEO 关键词（keywords）</label>
          <input class="input" name="seo_keywords" value="<?=h($keywords)?>" placeholder="逗号分隔">
        </div>
        <div class="field">
          <label class="label">SEO 描述（description）</label>
          <textarea class="input" name="seo_description" rows="2"><?=h($desc)?></textarea>
        </div>
        <div class="field">
          <label class="label">底部内容（支持简单 HTML：a/b/br/strong/em）</label>
          <textarea class="input" name="footer_html" rows="3"><?=h($footer)?></textarea>
        </div>
        <div class="field">
          <label class="label">自动抓取间隔（分钟，0=关闭）</label>
          <input class="input" name="auto_fetch_minutes" value="<?=h($minutes)?>" inputmode="numeric">
          <div class="muted">设置后需要在服务器 crontab 里每分钟调用一次下面的 URL（我可以帮你写入）。</div>
        </div>
        <div class="row">
          <button class="btn primary" type="submit">保存</button>
        </div>
      </form>
    </section>

    <section class="card" style="margin-top:12px;">
      <div class="title">定时抓取 URL</div>
      <div class="muted">把这个 URL 放到服务器计划任务里即可（带 token，别泄露）。</div>
      <input class="input" readonly value="<?=h($cronUrl)?>" onclick="this.select()">
    </section>
  </div>
</body>
</html>

