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

/**
 * Genera y almacena un token CSRF en la sesión si no existe.
 */
function csrf_token_generate(): void {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
}

/**
 * Genera un campo input oculto con el token CSRF.
 */
function csrf_input(): string {
    return '<input type="hidden" name="csrf_token" value="' . e($_SESSION['csrf_token'] ?? '') . '">';
}

/**
 * Verifica el token CSRF enviado con el de la sesión.
 *
 * @param string|null $token El token enviado desde el formulario.
 * @return bool True si es válido, False en caso contrario.
 */
function csrf_token_verify(?string $token): bool {
    if (empty($token) || empty($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}
