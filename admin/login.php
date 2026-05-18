<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/../lib/auth.php';

ps_start_session();

if (ps_is_logged_in()) {
  header('Location: ./');
  exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $user = (string)($_POST['user'] ?? '');
  $pass = (string)($_POST['pass'] ?? '');
  if (ps_login($user, $pass)) {
    header('Location: ./');
    exit;
  }
  $error = '账号或密码错误';
}

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
?>
<!doctype html>
<html lang="zh-CN">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>登录 - <?=h(ps_site_name())?></title>
  <link rel="stylesheet" href="../<?=h(ps_asset('static/app.css'))?>">
</head>
<body>
  <div class="container">
    <header class="header">
      <div>
        <h1>登录</h1>
        <div class="muted">后台管理入口</div>
      </div>
      <div class="header-actions">
        <a class="btn" href="../">前台</a>
      </div>
    </header>
    <section class="card" style="max-width:520px;margin:0 auto;">
      <?php if ($error): ?>
        <div class="alert"><?=h($error)?></div>
      <?php endif; ?>
      <form method="post" class="form">
        <div class="field">
          <label class="label">账号</label>
          <input class="input" name="user" required autocomplete="username">
        </div>
        <div class="field">
          <label class="label">密码</label>
          <input class="input" name="pass" type="password" required autocomplete="current-password">
        </div>
        <div class="row">
          <button class="btn primary" type="submit">登录</button>
        </div>
      </form>
    </section>
  </div>
</body>
</html>
