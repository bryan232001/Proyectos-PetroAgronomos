<?php
// includes/header.php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/helpers.php';

// Para mostrar el botón de menú en páginas del dashboard
$is_dashboard = $is_dashboard ?? false;
echo "<!-- is_dashboard: " . ($is_dashboard ? 'true' : 'false') . " -->"; // Debugging line
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($pageTitle ?? APP_NAME) ?></title>

  <!-- CSS principal -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="assets/css/estilos.css?v=10">

  <?php if (basename($_SERVER['PHP_SELF']) == 'mapa_proyectos.php'): ?>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.7.1/dist/leaflet.css" />
    <link rel="stylesheet" href="assets/css/mapa_proyectos.css">
  <?php endif; ?>

  <!-- Metadatos básicos -->
  <meta name="theme-color" content="#1e3a8a">
  <meta name="color-scheme" content="light">
</head>

<body>
  <?php if ($is_dashboard): ?>
    <header class="main-header header-dashboard">
      <div class="header-container">
        <div class="header-left">
          <a href="dashboard.php" class="header-logo">
            <div class="logo-icon">PA</div>
            <div class="logo-text">
              <div class="logo-title">Proyectos Agronomos Petroecuador</div>
              <div class="logo-subtitle">Sistema de Gestión de Proyectos Agrícolas</div>
            </div>
          </a>
        </div>
        <div class="header-right">
          <p class="welcome-message">
            Bienvenido, <strong><?php echo htmlspecialchars($_SESSION['usuario_nombre'] ?? 'Usuario'); ?></strong>
          </p>
        </div>
      </div>
    </header>
  <?php else: ?>
    <header class="main-header header-public">
      <div class="header-container">
        <div class="header-left">
          <a href="index.php" class="header-logo">
            <div class="logo-icon">EP</div>
            <div class="logo-text">
              <div class="logo-title">Sistema</div>
              <div class="logo-subtitle">Gestión Comunitaria</div>
            </div>
          </a>
        </div>
        <div class="header-right">
          <!-- Botones dinámicos -->
          <?php if (isset($_SESSION['usuario'])): ?>
            <a href="dashboard.php" class="btn-header">
              <i class="fas fa-tachometer-alt"></i> Dashboard
            </a>
            <a href="logout.php" class="btn-header">
              <i class="fas fa-sign-out-alt"></i> Cerrar Sesión
            </a>
          <?php else: ?>
            <a href="login.php" class="btn-header">
              <i class="fas fa-sign-in-alt"></i> Iniciar Sesión
            </a>
          <?php endif; ?>
        </div>
      </div>
    </header>
  <?php endif; ?>
  