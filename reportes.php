<?php
session_start();
$pageTitle = 'Reportes y Estadísticas - EP Petroecuador';
$is_dashboard = true;
require_once __DIR__ . '/includes/header.php';

// Verificar si el usuario está logueado
if (!isset($_SESSION['usuario'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/includes/db.php';

// --- Manejo de Filtros ---
$fecha_inicio = $_GET['fecha_inicio'] ?? '';
$fecha_fin = $_GET['fecha_fin'] ?? '';

$params = [];
$where_clauses = [
    'proyectos' => '',
    'asignaciones' => ''
];

$proyectos_params = [];
$asignaciones_params = [];

if ($fecha_inicio && preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_inicio)) {
    $where_clauses['proyectos'] .= " AND p.created_at >= ?";
    $where_clauses['asignaciones'] .= " AND pa.fecha_asignacion >= ?";
    $proyectos_params[] = $fecha_inicio . ' 00:00:00';
    $asignaciones_params[] = $fecha_inicio . ' 00:00:00';
}
if ($fecha_fin && preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_fin)) {
    $where_clauses['proyectos'] .= " AND p.created_at <= ?";
    $where_clauses['asignaciones'] .= " AND pa.fecha_asignacion <= ?";
    $proyectos_params[] = $fecha_fin . ' 23:59:59';
    $asignaciones_params[] = $fecha_fin . ' 23:59:59';
}


// Obtener estadísticas generales
try {
    // Estadísticas de proyectos
    $stmt = $pdo->prepare("
        SELECT
            COUNT(*) as total_proyectos,
            COUNT(DISTINCT p.creado_por) as usuarios_creadores
        FROM proyectos p
        WHERE 1=1 {$where_clauses['proyectos']}
    ");
    $stmt->execute($proyectos_params);
    $stats_proyectos = $stmt->fetch();

    // Estadísticas de asignaciones
    $stmt = $pdo->prepare("
        SELECT
            COUNT(*) as total_asignaciones,
            SUM(beneficiarios) as total_beneficiarios,
            COUNT(DISTINCT id_tecnico) as tecnicos_activos,
            COUNT(DISTINCT bloque) as bloques_asignados
        FROM proyecto_asignacion pa
        WHERE 1=1 {$where_clauses['asignaciones']}
    ");
    $stmt->execute($asignaciones_params);
    $stats_asignaciones = $stmt->fetch();

    // Proyectos por usuario (top 10)
    $stmt = $pdo->prepare("
        SELECT
            u.nombre,
            u.usuario,
            COUNT(p.id) as total_proyectos
        FROM usuarios u
        LEFT JOIN proyectos p ON u.usuario = p.creado_por
        WHERE 1=1 {$where_clauses['proyectos']}
        GROUP BY u.id_usuario, u.nombre, u.usuario
        HAVING total_proyectos > 0
        ORDER BY total_proyectos DESC
        LIMIT 10
    ");
    $stmt->execute($proyectos_params);
    $proyectos_por_usuario = $stmt->fetchAll();


    // Asignaciones por técnico
    $stmt = $pdo->prepare("
        SELECT
            u.nombre,
            COUNT(pa.id_asignacion) as total_asignaciones,
            SUM(pa.beneficiarios) as total_beneficiarios
        FROM usuarios u
        INNER JOIN proyecto_asignacion pa ON u.id_usuario = pa.id_tecnico
        WHERE 1=1 {$where_clauses['asignaciones']}
        GROUP BY u.id_usuario, u.nombre
        ORDER BY total_asignaciones DESC
        LIMIT 10
    ");
    $stmt->execute($asignaciones_params);
    $asignaciones_por_tecnico = $stmt->fetchAll();

    // Asignaciones por bloque
    $stmt = $pdo->prepare("
        SELECT
            bloque,
            COUNT(*) as total_asignaciones,
            SUM(beneficiarios) as total_beneficiarios
        FROM proyecto_asignacion pa
        WHERE 1=1 {$where_clauses['asignaciones']}
        GROUP BY bloque
        ORDER BY total_asignaciones DESC
        LIMIT 10
    ");
    $stmt->execute($asignaciones_params);
    $asignaciones_por_bloque = $stmt->fetchAll();

    // Actividad reciente (no se filtra por fecha)
    $actividad_reciente = [];
    $stmt = $pdo->query("SHOW TABLES LIKE 'user_logs'");
    if ($stmt->rowCount() > 0) {
        $actividad_reciente = $pdo->query("
            SELECT
                ul.accion,
                ul.descripcion,
                ul.fecha,
                u.nombre as usuario_nombre
            FROM user_logs ul
            LEFT JOIN usuarios u ON ul.id_usuario = u.id_usuario
            ORDER BY ul.fecha DESC
            LIMIT 20
        ")->fetchAll();
    }

    // Estadísticas por mes
    $query_mensual = "
        SELECT
            DATE_FORMAT(fecha_asignacion, '%Y-%m') as mes,
            COUNT(*) as asignaciones,
            SUM(beneficiarios) as beneficiarios
        FROM proyecto_asignacion pa
        WHERE 1=1 " . ($fecha_inicio && $fecha_fin ? " AND pa.fecha_asignacion BETWEEN ? AND ? " : " AND pa.fecha_asignacion >= DATE_SUB(NOW(), INTERVAL 12 MONTH) ") . "
        GROUP BY DATE_FORMAT(fecha_asignacion, '%Y-%m')
        ORDER BY mes ASC
    ";
    $stmt = $pdo->prepare($query_mensual);
    if ($fecha_inicio && $fecha_fin) {
        $stmt->execute([$fecha_inicio, $fecha_fin]);
    } else {
        $stmt->execute();
    }
    $stats_mensuales = $stmt->fetchAll();


    // Estados de proyectos para el gráfico de dona
    $stmt = $pdo->prepare("
        SELECT
            pe.nombre as estado,
            pe.color,
            COUNT(p.id) as cantidad
        FROM proyecto_estados pe
        LEFT JOIN proyectos p ON pe.id_estado = p.id_estado
        WHERE 1=1 {$where_clauses['proyectos']}
        GROUP BY pe.id_estado, pe.nombre, pe.color
        ORDER BY pe.orden
    ");
    $stmt->execute($proyectos_params);
    $estadosProyectos = $stmt->fetchAll();


} catch (Exception $e) {
    $error_message = "Error al obtener estadísticas: " . $e->getMessage();
    $stats_proyectos = [];
    $stats_asignaciones = [];
    $proyectos_por_usuario = [];
    $asignaciones_por_tecnico = [];
    $asignaciones_por_bloque = [];
    $actividad_reciente = [];
    $stats_mensuales = [];
    $estadosProyectos = [];
}
?>

<div class="dashboard-grid">
    <?php require_once __DIR__ . '/includes/sidebar.php'; ?>

    <main class="dashboard-main">
        <div class="dashboard-header">
            <h1>Reportes y Estadísticas</h1>
        </div>

        <!-- Filtros -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" action="reportes.php" class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <label for="fecha_inicio" class="form-label">Fecha de Inicio</label>
                        <input type="date" class="form-control" id="fecha_inicio" name="fecha_inicio" value="<?= htmlspecialchars($fecha_inicio) ?>">
                    </div>
                    <div class="col-md-4">
                        <label for="fecha_fin" class="form-label">Fecha de Fin</label>
                        <input type="date" class="form-control" id="fecha_fin" name="fecha_fin" value="<?= htmlspecialchars($fecha_fin) ?>">
                    </div>
                    <div class="col-md-4 d-flex flex-column">
                        <button type="submit" class="btn btn-primary w-100">Filtrar</button>
                        <a href="reportes.php" class="btn btn-secondary w-100 mt-2">Limpiar Filtros</a>
                    </div>
                </form>
            </div>
        </div>


        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error_message) ?></div>
        <?php endif; ?>

        <!-- Estadísticas Generales -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon-wrapper" style="background-color: #e0f2fe;">
                    <i class="fa-solid fa-folder-open" style="color: #0ea5e9;"></i>
                </div>
                <div class="stat-info">
                    <h3 class="stat-title">Total Proyectos</h3>
                    <p class="stat-value"><?= number_format($stats_proyectos['total_proyectos'] ?? 0) ?></p>
                    <small class="stat-subtitle"><?= $stats_proyectos['usuarios_creadores'] ?? 0 ?> usuarios creadores</small>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon-wrapper" style="background-color: #dcfce7;">
                    <i class="fa-solid fa-users" style="color: #22c55e;"></i>
                </div>
                <div class="stat-info">
                    <h3 class="stat-title">Total Beneficiarios</h3>
                    <p class="stat-value"><?= number_format($stats_asignaciones['total_beneficiarios'] ?? 0) ?></p>
                    <small class="stat-subtitle"><?= $stats_asignaciones['total_asignaciones'] ?? 0 ?> asignaciones</small>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon-wrapper" style="background-color: #fef3c7;">
                    <i class="fa-solid fa-user-tie" style="color: #f59e0b;"></i>
                </div>
                <div class="stat-info">
                    <h3 class="stat-title">Técnicos Activos</h3>
                    <p class="stat-value"><?= number_format($stats_asignaciones['tecnicos_activos'] ?? 0) ?></p>
                    <small class="stat-subtitle">Con asignaciones</small>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon-wrapper" style="background-color: #ede9fe;">
                    <i class="fa-solid fa-map-location-dot" style="color: #8b5cf6;"></i>
                </div>
                <div class="stat-info">
                    <h3 class="stat-title">Bloques Asignados</h3>
                    <p class="stat-value"><?= number_format($stats_asignaciones['bloques_asignados'] ?? 0) ?></p>
                    <small class="stat-subtitle">Bloques con proyectos</small>
                </div>
            </div>
        </div>

        <!-- Gráficos y Tablas -->
        <div class="reports-grid">
            <!-- Estadísticas Mensuales -->
            <?php if (!empty($stats_mensuales)): ?>
            <div class="card">
                <div class="card-header">
                    <h3>Tendencia Mensual</h3>
                </div>
                <div class="card-body">
                    <canvas id="chartMensual" width="400" height="200"></canvas>
                </div>
            </div>
            <?php endif; ?>

            <!-- Gráfico de Estados de Proyectos -->
            <?php if (!empty($estadosProyectos)): ?>
            <div class="card">
                <div class="card-header">
                    <h3>Distribución de Proyectos por Estado</h3>
                </div>
                <div class="card-body" style="display: flex; justify-content: center; align-items: center; height: 250px;">
                    <canvas id="chartEstados" style="max-width: 250px; max-height: 250px;"></canvas>
                </div>
            </div>
            <?php endif; ?>

            <!-- Top Bloques por Asignaciones -->
            <?php if (!empty($asignaciones_por_bloque)): ?>
            <div class="card">
                <div class="card-header">
                    <h3>Top 10 Bloques por Asignaciones</h3>
                </div>
                <div class="card-body">
                    <canvas id="chartBloques" width="400" height="200"></canvas>
                </div>
            </div>
            <?php endif; ?>

            <!-- Top Usuarios por Proyectos -->
            <?php if (!empty($proyectos_por_usuario)): ?>
            <div class="card">
                <div class="card-header">
                    <h3>Top Usuarios por Proyectos Creados</h3>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Usuario</th>
                                    <th>Proyectos</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($proyectos_por_usuario as $item): ?>
                                <tr>
                                    <td><?= htmlspecialchars($item['nombre']) ?></td>
                                    <td><span class="badge badge-primary"><?= $item['total_proyectos'] ?></span></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Top Técnicos por Asignaciones -->
            <?php if (!empty($asignaciones_por_tecnico)): ?>
            <div class="card">
                <div class="card-header">
                    <h3>Top Técnicos por Asignaciones</h3>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Técnico</th>
                                    <th>Asignaciones</th>
                                    <th>Beneficiarios</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($asignaciones_por_tecnico as $item): ?>
                                <tr>
                                    <td><?= htmlspecialchars($item['nombre']) ?></td>
                                    <td><span class="badge badge-info"><?= $item['total_asignaciones'] ?></span></td>
                                    <td><span class="badge badge-success"><?= number_format($item['total_beneficiarios']) ?></span></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Actividad Reciente -->
            <?php if (!empty($actividad_reciente)): ?>
            <div class="card">
                <div class="card-header">
                    <h3>Actividad Reciente del Sistema</h3>
                </div>
                <div class="card-body">
                    <div class="activity-list">
                        <?php foreach ($actividad_reciente as $actividad): ?>
                        <div class="activity-item">
                            <div class="activity-icon">
                                <i class="fa-solid fa-<?= getIconoActividad($actividad['accion']) ?>"></i>
                            </div>
                            <div class="activity-content">
                                <div class="activity-title"><?= htmlspecialchars($actividad['descripcion']) ?></div>
                                <div class="activity-meta">
                                    <?= htmlspecialchars($actividad['usuario_nombre'] ?? 'Sistema') ?> • 
                                    <?= date('d/m/Y H:i', strtotime($actividad['fecha'])) ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<?php if (!empty($stats_mensuales)): ?>
<script>
// Datos para el gráfico mensual
const datosMenuales = <?= json_encode($stats_mensuales) ?>;

// Configurar gráfico mensual
const ctx = document.getElementById('chartMensual').getContext('2d');
const chartMensual = new Chart(ctx, {
    type: 'line',
    data: {
        labels: datosMenuales.map(item => {
            const [year, month] = item.mes.split('-');
            return new Date(year, month - 1).toLocaleDateString('es-ES', { month: 'short', year: 'numeric' });
        }),
        datasets: [{
            label: 'Asignaciones',
            data: datosMenuales.map(item => item.asignaciones),
            borderColor: '#0ea5e9',
            backgroundColor: 'rgba(14, 165, 233, 0.1)',
            tension: 0.4
        }, {
            label: 'Beneficiarios',
            data: datosMenuales.map(item => item.beneficiarios),
            borderColor: '#22c55e',
            backgroundColor: 'rgba(34, 197, 94, 0.1)',
            tension: 0.4,
            yAxisID: 'y1'
        }]
    },
    options: {
        responsive: true,
        scales: {
            y: {
                type: 'linear',
                display: true,
                position: 'left',
            },
            y1: {
                type: 'linear',
                display: true,
                position: 'right',
                grid: {
                    drawOnChartArea: false,
                },
            }
        }
    }
});
</script>
<?php endif; ?>

<?php if (!empty($estadosProyectos)): ?>
<script>
// Datos para el gráfico de estados
const datosEstados = <?= json_encode($estadosProyectos) ?>;

// Configurar gráfico de estados
const ctxEstados = document.getElementById('chartEstados').getContext('2d');
const chartEstados = new Chart(ctxEstados, {
    type: 'doughnut',
    data: {
        labels: datosEstados.map(item => item.estado),
        datasets: [{
            label: 'Proyectos',
            data: datosEstados.map(item => item.cantidad),
            backgroundColor: datosEstados.map(item => item.color),
            hoverOffset: 4
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'top',
            },
            title: {
                display: false,
                text: 'Distribución de Proyectos por Estado'
            }
        }
    }
});
</script>
<?php endif; ?>

<?php if (!empty($asignaciones_por_bloque)): ?>
<script>
// Datos para el gráfico de bloques
const datosBloques = <?= json_encode($asignaciones_por_bloque) ?>;

// Configurar gráfico de bloques
const ctxBloques = document.getElementById('chartBloques').getContext('2d');
const chartBloques = new Chart(ctxBloques, {
    type: 'bar',
    data: {
        labels: datosBloques.map(item => item.bloque),
        datasets: [{
            label: 'Asignaciones',
            data: datosBloques.map(item => item.total_asignaciones),
            backgroundColor: 'rgba(245, 158, 11, 0.6)',
            borderColor: 'rgba(245, 158, 11, 1)',
            borderWidth: 1
        }]
    },
    options: {
        indexAxis: 'y',
        responsive: true,
        plugins: {
            legend: {
                display: false
            }
        }
    }
});
</script>
<?php endif; ?>

<style>
.reports-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
    gap: 1.5rem;
    margin-top: 2rem;
}

.activity-list {
    max-height: 400px;
    overflow-y: auto;
}

.activity-item {
    display: flex;
    align-items: flex-start;
    padding: 0.75rem 0;
    border-bottom: 1px solid #e5e7eb;
}

.activity-item:last-child {
    border-bottom: none;
}

.activity-icon {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background-color: #f3f4f6;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 0.75rem;
    flex-shrink: 0;
}

.activity-content {
    flex: 1;
}

.activity-title {
    font-weight: 500;
    color: #111827;
    margin-bottom: 0.25rem;
}

.activity-meta {
    font-size: 0.875rem;
    color: #6b7280;
}

.header-actions {
    display: flex;
    gap: 0.5rem;
}

.badge {
    padding: 0.25rem 0.5rem;
    font-size: 0.75rem;
    border-radius: 0.375rem;
    font-weight: 500;
}

.badge-primary { background-color: #dbeafe; color: #1e40af; }
.badge-success { background-color: #dcfce7; color: #166534; }
.badge-info { background-color: #e0f2fe; color: #0c4a6e; }
.badge-warning { background-color: #fef3c7; color: #92400e; }
.badge-secondary { background-color: #6c757d; color: white; }

.stat-subtitle {
    color: #6b7280;
    font-size: 0.875rem;
}

.alert {
    padding: 0.75rem 1.25rem;
    margin-bottom: 1rem;
    border: 1px solid transparent;
    border-radius: 0.375rem;
}

.alert-danger {
    color: #721c24;
    background-color: #f8d7da;
    border-color: #f5c6cb;
}
</style>

<?php
function getIconoActividad($accion) {
    switch ($accion) {
        case 'login': return 'sign-in-alt';
        case 'logout': return 'sign-out-alt';
        case 'create': return 'plus';
        case 'update': return 'edit';
        case 'delete': return 'trash';
        default: return 'info-circle';
    }
}

require_once __DIR__ . '/includes/footer.php';
?>