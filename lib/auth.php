<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

function ps_start_session(): void {
  $cfg = ps_config();
  $name = isset($cfg['session_name']) ? (string)$cfg['session_name'] : 'proxystore_sess';
  if (session_status() === PHP_SESSION_NONE) {
    session_name($name);
    session_start();
  }
}

function ps_is_logged_in(): bool {
  ps_start_session();
  return !empty($_SESSION['ps_admin']);
}

function ps_require_login(): void {
  if (!ps_is_logged_in()) {
    header('Location: login.php');
    exit;
  }
}

function ps_login(string $user, string $pass): bool {
  $cfg = ps_config();
  $u = (string)($cfg['admin_user'] ?? 'admin');
  $hash = (string)($cfg['admin_pass_hash'] ?? '');
  if ($user !== $u) return false;
  if ($hash === '') return false;
  if (!password_verify($pass, $hash)) return false;
  $_SESSION['ps_admin'] = $u;
  return true;
}

function ps_logout(): void {
  ps_start_session();
  $_SESSION = [];
  if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params["path"], $params["domain"], (bool)$params["secure"], (bool)$params["httponly"]);
  }
  session_destroy();
}

