<?php
// Zona horaria y nombre de la app
date_default_timezone_set('America/Guayaquil');
define('APP_NAME', 'EP Petroecuador — Proyectos Productivos');

// Detecta URL base automáticamente, incluso si está en subcarpeta
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host   = $_SERVER['HTTP_HOST'] ?? 'localhost';

// dirname('/subcarpeta/index.php') => '/subcarpeta'
$basePath = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
if ($basePath === '/' || $basePath === '.' || $basePath === '') {
  $basePath = '';
}
define('APP_URL', $scheme . '://' . $host . $basePath);

// Ruta física del proyecto (carpeta raíz, no /includes)
define('APP_ROOT', realpath(dirname(__DIR__)));
