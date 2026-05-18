<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

function ps_fetch_and_cache(int $id): void {
  $db = ps_db();
  $stmt = $db->prepare('SELECT id, url FROM subscriptions WHERE id = :id');
  $stmt->execute([':id' => $id]);
  $row = $stmt->fetch(PDO::FETCH_ASSOC);
  if (!$row) return;

  $url = (string)$row['url'];
  $res = ps_http_get($url);
  $content = $res['body'];
  $contentType = $res['content_type'];
  $nodeCount = ps_guess_node_count($content, $contentType);
  $status = isset($res['status']) ? (int)$res['status'] : null;
  $err = isset($res['error']) ? (string)$res['error'] : '';

  // 非 2xx 时不覆盖已有缓存，避免把有效缓存刷成空
  $setCache = ($status !== null && $status >= 200 && $status < 300 && $content !== '');
  $sql = $setCache
    ? 'UPDATE subscriptions SET cached_content = :c, cached_content_type = :ct, node_count = :nc, last_fetch_at = :lf, last_fetch_status = :st, last_fetch_error = :er, updated_at = :now WHERE id = :id'
    : 'UPDATE subscriptions SET node_count = :nc, last_fetch_at = :lf, last_fetch_status = :st, last_fetch_error = :er, updated_at = :now WHERE id = :id';
  $stmt2 = $db->prepare($sql);
  $params = [
    ':nc' => $nodeCount,
    ':lf' => gmdate('Y-m-d H:i:s'),
    ':st' => $status,
    ':er' => $err,
    ':now' => gmdate('Y-m-d H:i:s'),
    ':id' => $id,
  ];
  if ($setCache) {
    $params[':c'] = $content;
    $params[':ct'] = $contentType;
  }
  $stmt2->execute($params);
}

function ps_http_get(string $url): array {
  if (!function_exists('curl_init')) {
    $ctx = stream_context_create([
      'http' => [
        'method' => 'GET',
        'timeout' => 25,
        'header' => "User-Agent: proxystore/1.0\r\n",
      ],
      'ssl' => [
        'verify_peer' => false,
        'verify_peer_name' => false,
      ],
    ]);
    $body = @file_get_contents($url, false, $ctx);
    if ($body === false) {
      return ['body' => '', 'content_type' => 'text/plain; charset=utf-8', 'error' => 'file_get_contents_failed'];
    }
    return ['body' => (string)$body, 'content_type' => 'text/plain; charset=utf-8'];
  }

  $ch = curl_init();
  curl_setopt_array($ch, [
    CURLOPT_URL => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_MAXREDIRS => 5,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_TIMEOUT => 25,
    CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/123 Safari/537.36',
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => 0,
    CURLOPT_HEADER => true,
  ]);
  $raw = curl_exec($ch);
  if ($raw === false) {
    $err = curl_error($ch);
    curl_close($ch);
    return ['body' => '', 'content_type' => 'text/plain; charset=utf-8', 'error' => $err, 'status' => 0];
  }
  $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
  $headerSize = (int)curl_getinfo($ch, CURLINFO_HEADER_SIZE);
  $contentType = (string)(curl_getinfo($ch, CURLINFO_CONTENT_TYPE) ?: '');
  curl_close($ch);

  $header = substr($raw, 0, $headerSize);
  $body = substr($raw, $headerSize);

  if ($contentType === '') {
    if (preg_match('/^Content-Type:\s*([^\r\n]+)/im', $header, $m)) {
      $contentType = trim($m[1]);
    }
  }
  if ($contentType === '') $contentType = 'text/plain; charset=utf-8';

  $err = '';
  if ($status < 200 || $status >= 300) {
    $err = 'http_status_' . $status;
  }
  return ['body' => (string)$body, 'content_type' => $contentType, 'status' => $status, 'error' => $err];
}

function ps_guess_node_count(string $body, string $contentType): ?int {
  $b = trim($body);
  if ($b === '') return 0;

  // 明文一行一个节点
  if (preg_match('/^(vmess|vless|trojan|ss|ssr|hysteria2|tuic):\/\//im', $b)) {
    $lines = preg_split('/\r\n|\r|\n/', $b);
    $n = 0;
    foreach ($lines as $line) {
      $line = trim((string)$line);
      if ($line === '') continue;
      if (preg_match('/^(vmess|vless|trojan|ss|ssr|hysteria2|tuic):\/\//i', $line)) $n++;
    }
    return $n ?: null;
  }

  // Clash YAML 一般包含 proxies:
  if (stripos($contentType, 'yaml') !== false || preg_match('/^\s*(port:|proxies:|proxy-groups:)/mi', $b)) {
    if (preg_match('/^\s*proxies:\s*$/mi', $b)) {
      // 粗略统计以 "- name:" 开头的条目
      preg_match_all('/^\s*-\s*name\s*:/mi', $b, $m);
      return count($m[0]);
    }
    return null;
  }

  // Base64 订阅（vmess/vless/trojan/ss 等），尝试解码后统计行数
  if (preg_match('/^[A-Za-z0-9+\/=\r\n]+$/', $b) && strlen($b) > 48) {
    $decoded = base64_decode(preg_replace('/\s+/', '', $b), true);
    if (is_string($decoded) && $decoded !== '') {
      $decodedTrim = trim($decoded);
      // 有些订阅会在解码后还是 YAML/JSON
      if (preg_match('/^\s*(port:|proxies:|proxy-groups:)/mi', $decodedTrim)) {
        preg_match_all('/^\s*-\s*name\s*:/mi', $decodedTrim, $m);
        return count($m[0]) ?: null;
      }
      if (preg_match('/^(vmess|vless|trojan|ss|ssr|hysteria2|tuic):\/\//im', $decodedTrim)) {
        $lines = preg_split('/\r\n|\r|\n/', $decodedTrim);
        $n = 0;
        foreach ($lines as $line) {
          $line = trim((string)$line);
          if ($line === '') continue;
          if (preg_match('/^(vmess|vless|trojan|ss|ssr|hysteria2|tuic):\/\//i', $line)) $n++;
        }
        return $n ?: null;
      }
      // 兜底：按非空行数估计（避免 0）
      $lines = preg_split('/\r\n|\r|\n/', $decodedTrim);
      $nonEmpty = 0;
      foreach ($lines as $line) {
        if (trim((string)$line) !== '') $nonEmpty++;
      }
      return $nonEmpty ?: null;
    }
  }

  return null;
}
