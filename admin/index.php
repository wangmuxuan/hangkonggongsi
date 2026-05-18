<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/../lib/auth.php';

ps_require_login();

$db = ps_db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = isset($_POST['action']) ? (string)$_POST['action'] : '';
  if ($action === 'add') {
    $name = trim((string)($_POST['name'] ?? ''));
    $url = trim((string)($_POST['url'] ?? ''));
    $notes = trim((string)($_POST['notes'] ?? ''));
    $enabled = isset($_POST['enabled']) ? 1 : 0;
    $fetchNow = isset($_POST['fetch_now']) ? 1 : 0;
    if ($url !== '') {
      $stmt = $db->prepare('INSERT INTO subscriptions (name, url, notes, enabled, created_at, updated_at) VALUES (:name, :url, :notes, :enabled, :now, :now)');
      $stmt->execute([
        ':name' => $name,
        ':url' => $url,
        ':notes' => $notes,
        ':enabled' => $enabled,
        ':now' => gmdate('Y-m-d H:i:s'),
      ]);
      if ($fetchNow) {
        $newId = (int)$db->lastInsertId();
        if ($newId > 0) {
          require_once __DIR__ . '/../lib/fetch.php';
          ps_fetch_and_cache($newId);
        }
      }
    }
    header('Location: ./');
    exit;
  }

  if ($action === 'toggle') {
    $id = (int)($_POST['id'] ?? 0);
    $enabled = (int)($_POST['enabled'] ?? 0) ? 1 : 0;
    if ($id > 0) {
      $stmt = $db->prepare('UPDATE subscriptions SET enabled = :enabled, updated_at = :now WHERE id = :id');
      $stmt->execute([':enabled' => $enabled, ':now' => gmdate('Y-m-d H:i:s'), ':id' => $id]);
    }
    header('Location: ./');
    exit;
  }

  if ($action === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
      $stmt = $db->prepare('DELETE FROM subscriptions WHERE id = :id');
      $stmt->execute([':id' => $id]);
    }
    header('Location: ./');
    exit;
  }

  if ($action === 'fetch') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
      require_once __DIR__ . '/../lib/fetch.php';
      ps_fetch_and_cache($id);
      $stmtS = $db->prepare('SELECT last_fetch_status, last_fetch_error FROM subscriptions WHERE id = :id');
      $stmtS->execute([':id' => $id]);
      $stRow = $stmtS->fetch(PDO::FETCH_ASSOC) ?: [];
      $st = isset($stRow['last_fetch_status']) ? (int)$stRow['last_fetch_status'] : 0;
      $er = (string)($stRow['last_fetch_error'] ?? '');
      ps_start_session();
      if ($st >= 200 && $st < 300 && $er === '') {
        $_SESSION['ps_flash'] = '抓取成功（HTTP ' . $st . '）';
      } else {
        $_SESSION['ps_flash'] = '抓取失败（HTTP ' . $st . '）' . ($er ? ('：' . $er) : '');
      }
    }
    header('Location: ./');
    exit;
  }
}

$rows = $db->query('SELECT id, name, url, enabled, notes, node_count, last_fetch_at, last_fetch_status, last_fetch_error, created_at, updated_at FROM subscriptions ORDER BY id DESC')->fetchAll(PDO::FETCH_ASSOC);

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

?>
<!doctype html>
<html lang="zh-CN">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>后台管理 - <?=h(ps_site_name())?></title>
  <link rel="stylesheet" href="../<?=h(ps_asset('static/app.css'))?>">
</head>
<body>
  <div class="container">
    <header class="header">
      <div>
        <h1>后台管理</h1>
        <div class="muted">添加/抓取/开关/删除订阅</div>
      </div>
      <div class="header-actions">
        <a class="btn" href="settings.php">系统设置</a>
        <a class="btn" href="../">前台</a>
        <form method="post" action="logout.php" style="display:inline">
          <button class="btn" type="submit">退出</button>
        </form>
      </div>
    </header>

    <?php
      ps_start_session();
      $flash = (string)($_SESSION['ps_flash'] ?? '');
      unset($_SESSION['ps_flash']);
    ?>
    <?php if ($flash): ?>
      <div class="card" style="margin-bottom:12px;">
        <div class="muted"><?=h($flash)?></div>
      </div>
    <?php endif; ?>

    <section class="card">
      <div class="title">添加订阅</div>
      <form method="post" class="form">
        <input type="hidden" name="action" value="add">
        <div class="field">
          <label class="label">名称</label>
          <input class="input" name="name" placeholder="例如：我的订阅 A">
        </div>
        <div class="field">
          <label class="label">订阅链接（必填）</label>
          <input class="input" name="url" required placeholder="https://...">
        </div>
        <div class="field">
          <label class="label">备注</label>
          <textarea class="input" name="notes" rows="2" placeholder="可选"></textarea>
        </div>
        <div class="row">
          <label class="checkbox"><input type="checkbox" name="enabled" checked> 启用</label>
          <label class="checkbox"><input type="checkbox" name="fetch_now" checked> 添加后抓取</label>
          <button class="btn primary" type="submit">添加</button>
        </div>
      </form>
    </section>

    <section class="card">
      <div class="title">订阅列表</div>
      <div class="muted">提示：若订阅源对服务器 IP 限制（返回 403/401），抓取会失败；页面会显示 HTTP 状态码。</div>
      <div class="table-wrap">
        <table class="table">
          <thead>
            <tr>
              <th>ID</th>
              <th>名称</th>
              <th>状态</th>
              <th>节点</th>
              <th>最后抓取</th>
              <th>HTTP</th>
              <th>操作</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($rows as $r): ?>
            <?php
              $id = (int)$r['id'];
              $enabled = (int)$r['enabled'] === 1;
              $nodeCount = $r['node_count'] !== null ? (string)$r['node_count'] : '-';
              $lastFetch = $r['last_fetch_at'] ? (string)$r['last_fetch_at'] : '-';
              $lastStatus = $r['last_fetch_status'] !== null ? (string)$r['last_fetch_status'] : '-';
            ?>
            <tr>
              <td><?=h($id)?></td>
              <td>
                <div class="title" style="font-size:14px;margin:0"><?=h($r['name'] ?: ('订阅 #' . $id))?></div>
                <div class="muted" style="max-width:520px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?=h($r['url'])?></div>
              </td>
              <td><?= $enabled ? '<span class="badge ok">启用</span>' : '<span class="badge">停用</span>' ?></td>
              <td><?=h($nodeCount)?></td>
              <td><?=h($lastFetch)?></td>
              <td><?=h($lastStatus)?></td>
              <td class="td-actions">
                <form method="post" style="display:inline">
                  <input type="hidden" name="action" value="fetch">
                  <input type="hidden" name="id" value="<?=h($id)?>">
                  <button class="btn" type="submit">抓取</button>
                </form>
                <form method="post" style="display:inline">
                  <input type="hidden" name="action" value="toggle">
                  <input type="hidden" name="id" value="<?=h($id)?>">
                  <input type="hidden" name="enabled" value="<?= $enabled ? '0' : '1' ?>">
                  <button class="btn" type="submit"><?= $enabled ? '停用' : '启用' ?></button>
                </form>
                <form method="post" style="display:inline" onsubmit="return confirm('确认删除？');">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?=h($id)?>">
                  <button class="btn danger" type="submit">删除</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </section>
  </div>
</body>
</html>
