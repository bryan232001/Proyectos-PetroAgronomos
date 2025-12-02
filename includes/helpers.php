<?php
require_once __DIR__ . '/config.php';

function e($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }

/** URL absoluta para assets (css/js/img) – apunta a /assets desde la raíz */
function asset(string $path): string {
  return rtrim(APP_URL, '/') . '/assets/' . ltrim($path, '/');
}

/** URL absoluta a una ruta del sitio (index.php, login.php, etc) */
function url_to(string $path = ''): string {
  return rtrim(APP_URL, '/') . '/' . ltrim($path, '/');
}
