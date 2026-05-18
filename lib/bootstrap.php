<?php
declare(strict_types=1);

function ps_data_dir(): string {
  $dir = dirname(__DIR__, 2) . '/.data/proxystore';
  return $dir;
}

function ps_config(): array {
  $path = ps_data_dir() . '/config.php';
  if (is_file($path)) {
    /** @var array $cfg */
    $cfg = require $path;
    if (!is_array($cfg)) $cfg = [];
    if (empty($cfg['cron_token'])) {
      $cfg['cron_token'] = bin2hex(random_bytes(16));
      @file_put_contents($path, "<?php\nreturn " . var_export($cfg, true) . ";\n");
    }
    return $cfg;
  }
  // 默认账号密码：admin / admin123（首次部署后请立刻修改该文件）
  $cfg = [
    'admin_user' => 'admin',
    'admin_pass_hash' => password_hash('admin123', PASSWORD_DEFAULT),
    'session_name' => 'proxystore_sess',
    'cron_token' => bin2hex(random_bytes(16)),
  ];
  @mkdir(ps_data_dir(), 0700, true);
  @file_put_contents($path, "<?php\nreturn " . var_export($cfg, true) . ";\n");
  return $cfg;
}

function ps_site_name(): string {
  $v = ps_setting('site_name');
  return $v !== '' ? $v : '中国航空';
}

function ps_setting(string $key, string $default = ''): string {
  try {
    $db = ps_db();
    $stmt = $db->prepare('SELECT value FROM settings WHERE key = :k');
    $stmt->execute([':k' => $key]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row && isset($row['value'])) return (string)$row['value'];
  } catch (Throwable $e) {
    // ignore
  }
  return $default;
}

function ps_set_setting(string $key, string $value): void {
  $db = ps_db();
  $stmt = $db->prepare('INSERT INTO settings (key, value, updated_at) VALUES (:k, :v, :u) ON CONFLICT(key) DO UPDATE SET value = excluded.value, updated_at = excluded.updated_at');
  $stmt->execute([':k' => $key, ':v' => $value, ':u' => gmdate('Y-m-d H:i:s')]);
}

function ps_base_url(): string {
  $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
  $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
  $script = $_SERVER['SCRIPT_NAME'] ?? '/proxystore/index.php';
  $base = rtrim(str_replace('\\', '/', dirname($script)), '/');
  if ($base === '') $base = '/';
  return $scheme . '://' . $host . $base;
}

function ps_asset(string $path): string {
  $path = ltrim($path, '/');
  $fs = dirname(__DIR__) . '/' . $path;
  $v = is_file($fs) ? (string)filemtime($fs) : (string)time();
  return $path . '?v=' . rawurlencode($v);
}

function ps_db(): PDO {
  static $pdo = null;
  if ($pdo instanceof PDO) return $pdo;

  $dataDir = ps_data_dir();
  if (!is_dir($dataDir)) {
    @mkdir($dataDir, 0700, true);
  }
  // 防止通过 Web 直接访问数据目录
  $deny = $dataDir . '/.htaccess';
  if (!is_file($deny)) {
    @file_put_contents($deny, "Deny from all\n");
  }
  $dbPath = $dataDir . '/proxystore.sqlite';
  $needInit = !is_file($dbPath);

  $pdo = new PDO('sqlite:' . $dbPath, null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  ]);

  if ($needInit) {
    ps_db_init($pdo);
  } else {
    ps_db_migrate($pdo);
  }

  return $pdo;
}

function ps_db_init(PDO $db): void {
  $db->exec('
    CREATE TABLE IF NOT EXISTS settings (
      key TEXT PRIMARY KEY,
      value TEXT NOT NULL DEFAULT "",
      updated_at TEXT NOT NULL
    );
  ');
  $db->exec('
    CREATE TABLE IF NOT EXISTS subscriptions (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      name TEXT NOT NULL DEFAULT "",
      url TEXT NOT NULL,
      notes TEXT NOT NULL DEFAULT "",
      enabled INTEGER NOT NULL DEFAULT 1,
      cached_content TEXT NOT NULL DEFAULT "",
      cached_content_type TEXT NOT NULL DEFAULT "",
      node_count INTEGER NULL,
      last_fetch_at TEXT NULL,
      last_fetch_status INTEGER NULL,
      last_fetch_error TEXT NOT NULL DEFAULT "",
      created_at TEXT NOT NULL,
      updated_at TEXT NOT NULL
    );
  ');
  $db->exec('CREATE INDEX IF NOT EXISTS idx_sub_enabled ON subscriptions(enabled);');

  // 默认站点设置
  $now = gmdate('Y-m-d H:i:s');
  $stmt = $db->prepare('INSERT OR IGNORE INTO settings (key, value, updated_at) VALUES (:k, :v, :u)');
  $defaults = [
    'site_name' => '中国航空',
    'seo_keywords' => '中国航空,订阅,代理,节点,二维码',
    'seo_description' => '中国航空：订阅储存与分享，支持二维码与节点列表展示。',
    'footer_html' => '联系方式：<a href=\"mailto:admin@chinagfw.com\">admin@chinagfw.com</a>',
    'auto_fetch_minutes' => '0',
  ];
  foreach ($defaults as $k => $v) {
    $stmt->execute([':k' => $k, ':v' => $v, ':u' => $now]);
  }
}

function ps_db_migrate(PDO $db): void {
  // best-effort migrations for existing DBs
  $cols = [];
  $tables = [];
  foreach ($db->query("SELECT name FROM sqlite_master WHERE type='table'") as $row) {
    if (isset($row['name'])) $tables[(string)$row['name']] = true;
  }

  if (!isset($tables['settings'])) {
    $db->exec('CREATE TABLE IF NOT EXISTS settings (key TEXT PRIMARY KEY, value TEXT NOT NULL DEFAULT "", updated_at TEXT NOT NULL);');
  }

  $cols = [];
  foreach ($db->query("PRAGMA table_info(subscriptions)") as $row) {
    if (isset($row['name'])) $cols[(string)$row['name']] = true;
  }
  if (!isset($cols['last_fetch_status'])) {
    $db->exec('ALTER TABLE subscriptions ADD COLUMN last_fetch_status INTEGER NULL;');
  }
  if (!isset($cols['last_fetch_error'])) {
    $db->exec("ALTER TABLE subscriptions ADD COLUMN last_fetch_error TEXT NOT NULL DEFAULT '';");
  }

  // settings defaults (idempotent)
  $now = gmdate('Y-m-d H:i:s');
  $stmt = $db->prepare('INSERT OR IGNORE INTO settings (key, value, updated_at) VALUES (:k, :v, :u)');
  $defaults = [
    'site_name' => '中国航空',
    'seo_keywords' => '中国航空,订阅,代理,节点,二维码',
    'seo_description' => '中国航空：订阅储存与分享，支持二维码与节点列表展示。',
    'footer_html' => '联系方式：<a href=\"mailto:admin@chinagfw.com\">admin@chinagfw.com</a>',
    'auto_fetch_minutes' => '0',
  ];
  foreach ($defaults as $k => $v) {
    $stmt->execute([':k' => $k, ':v' => $v, ':u' => $now]);
  }
}
