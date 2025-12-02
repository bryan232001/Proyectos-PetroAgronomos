<?php
session_start();
if (!isset($_SESSION['usuario'])) { header('Location: login.php'); exit; }

$pageTitle = 'Mapa de Proyectos';
$is_dashboard = true;
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/db.php';

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// Lógica para obtener filtros (zona, bloque) si es necesario más adelante
$zonas = $pdo->query("SELECT DISTINCT zona FROM catalogo_bloques ORDER BY zona")->fetchAll(PDO::FETCH_COLUMN);
$bloques = $pdo->query("SELECT DISTINCT bloque FROM catalogo_bloques ORDER BY bloque")->fetchAll(PDO::FETCH_COLUMN);

?>

<div class="dashboard-grid">
    <?php require_once __DIR__ . '/includes/sidebar.php'; ?>

    <main class="dashboard-main">
        <div class="dashboard-header">
            <h1 class="dashboard-welcome">Mapa de Proyectos por Provincia</h1>
        </div>

        <div class="map-container card-form">
            <div class="card-form-header">
                <h2 class="card-form-title">Visualización Geográfica</h2>
            </div>
            <div class="card-form-body">
                <div class="map-filters">
                    <div class="form-group">
                        <label for="filtro_zona">Filtrar por Zona</label>
                        <select id="filtro_zona" class="nice-input">
                            <option value="">Todas las Zonas</option>
                            <?php foreach ($zonas as $zona): ?>
                                <option value="<?= h($zona) ?>"><?= h($zona) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="filtro_bloque">Filtrar por Bloque</label>
                        <select id="filtro_bloque" class="nice-input">
                            <option value="">Todos los Bloques</option>
                            <?php foreach ($bloques as $bloque): ?>
                                <option value="<?= h($bloque) ?>"><?= h($bloque) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div id="mapid"></div>
            </div>
        </div>
    </main>
</div>

<script src="https://unpkg.com/leaflet@1.7.1/dist/leaflet.js"></script>
<script src="assets/js/mapa_proyectos.js"></script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
