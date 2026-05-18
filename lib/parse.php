<?php
declare(strict_types=1);

function ps_decode_subscription_body(string $body): array {
  $raw = trim($body);
  if ($raw === '') return ['kind' => 'empty', 'text' => ''];

  // 如果已经是明文节点 URI
  if (preg_match('/^(vmess|vless|trojan|ss|ssr|hysteria2|tuic):\/\//im', $raw)) {
    return ['kind' => 'plain', 'text' => $raw];
  }

  // 看起来像 YAML/JSON
  if (preg_match('/^\s*(port:|proxies:|proxy-groups:|\{|\[)/mi', $raw)) {
    return ['kind' => 'structured', 'text' => $raw];
  }

  // 尝试 base64 解码
  $compact = preg_replace('/\s+/', '', $raw);
  if ($compact !== '' && preg_match('/^[A-Za-z0-9+\/=]+$/', $compact) && strlen($compact) > 48) {
    $decoded = base64_decode($compact, true);
    if (is_string($decoded) && trim($decoded) !== '') {
      $decoded = trim($decoded);
      if (preg_match('/^(vmess|vless|trojan|ss|ssr|hysteria2|tuic):\/\//im', $decoded)) {
        return ['kind' => 'decoded', 'text' => $decoded];
      }
      return ['kind' => 'decoded_other', 'text' => $decoded];
    }
  }

  return ['kind' => 'unknown', 'text' => $raw];
}

function ps_extract_node_lines(string $text, int $limit = 500): array {
  $lines = preg_split('/\r\n|\r|\n/', $text);
  $out = [];
  foreach ($lines as $line) {
    $line = trim((string)$line);
    if ($line === '') continue;
    if (preg_match('/^(vmess|vless|trojan|ss|ssr|hysteria2|tuic):\/\//i', $line)) {
      $out[] = $line;
      if (count($out) >= $limit) break;
    }
  }
  return $out;
}

