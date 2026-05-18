<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

function ps_footer_html(): string {
  $html = ps_setting('footer_html', '');
  if ($html === '') return '';
  // allow basic html only; strip scripts
  $html = preg_replace('/<\s*script\b[^>]*>.*?<\s*\/\s*script\s*>/is', '', $html);
  return (string)$html;
}

function ps_meta_keywords(): string {
  return ps_setting('seo_keywords', '');
}

function ps_meta_description(): string {
  return ps_setting('seo_description', '');
}

