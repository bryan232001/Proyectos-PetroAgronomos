<?php
session_start();
$pageTitle = 'Estados de Proyectos - EP Petroecuador';
$is_dashboard = true;
require_once __DIR__ . '/includes/header.php';

// Verificar si el usuario está logueado
if (!isset($_SESSION['usuario'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/includes/db.php';

// Obtener información del usuario actual
$usuario_actual = $_SESSION['usuario'] ?? '';
$rol_actual = $_SESSION['id_rol'] ?? 2;
$es_admin = ($rol_actual == 1);

// Procesar cambio de estado
$mensaje = '';
$tipo_mensaje = '';

if ($_POST && isset($_POST['accion']) && $_POST['accion'] === 'cambiar_estado') {
    try {
        $id_proyecto = (int)$_POST['id_proyecto'];
        $nuevo_estado = (int)$_POST['nuevo_estado'];
        $comentario = trim($_POST['comentario'] ?? '');
        $progreso = (float)($_POST['progreso'] ?? 0);
        $fecha_inicio = $_POST['fecha_inicio'] ?: null;
        $fecha_fin_estimada = $_POST['fecha_fin_estimada'] ?: null;
        $fecha_fin_real = $_POST['fecha_fin_real'] ?: null;
        
        // Verificar permisos
        $stmt = $pdo->prepare("SELECT creado_por, id_estado FROM proyectos WHERE id = ?");
        $stmt->execute([$id_proyecto]);
        $proyecto = $stmt->fetch();
        
        if (!$proyecto) {
            throw new Exception("Proyecto no encontrado");
        }
        
        if (!$es_admin && $proyecto['creado_por'] !== $usuario_actual) {
            throw new Exception("No tienes permisos para modificar este proyecto");
        }
        
        $estado_anterior = $proyecto['id_estado'];
        
        // Actualizar proyecto
        $stmt = $pdo->prepare("
            UPDATE proyectos 
            SET id_estado = ?, progreso = ?, fecha_inicio = ?, fecha_fin_estimada = ?, fecha_fin_real = ?, observaciones_estado = ?
            WHERE id = ?
        ");
        $stmt->execute([$nuevo_estado, $progreso, $fecha_inicio, $fecha_fin_estimada, $fecha_fin_real, $comentario, $id_proyecto]);
        
        // Registrar en historial
        $stmt = $pdo->prepare("
            INSERT INTO proyecto_estado_historial (id_proyecto, id_estado_anterior, id_estado_nuevo, id_usuario, comentario)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$id_proyecto, $estado_anterior, $nuevo_estado, $_SESSION['id_usuario'] ?? null, $comentario]);
        
        $mensaje = "Estado del proyecto actualizado exitosamente";
        $tipo_mensaje = "success";
        
    } catch (Exception $e) {
        $mensaje = "Error: " . $e->getMessage();
        $tipo_mensaje = "danger";
    }
}

// Obtener proyectos según permisos
try {
    if ($es_admin) {
        // Administrador ve todos los proyectos
        $stmt = $pdo->query("
            SELECT 
                p.id,
                p.nombre,
                p.creado_por,
                p.progreso,
                p.fecha_inicio,
                p.fecha_fin_estimada,
                p.fecha_fin_real,
                p.observaciones_estado,
                pe.nombre as estado_nombre,
                pe.color as estado_color,
                pe.icono as estado_icono,
                u.nombre as creador_nombre
            FROM proyectos p
            LEFT JOIN proyecto_estados pe ON p.id_estado = pe.id_estado
            LEFT JOIN usuarios u ON u.usuario = p.creado_por
            ORDER BY p.id DESC
        ");
    } else {
        // Usuario normal solo ve sus proyectos
        $stmt = $pdo->prepare("
            SELECT 
                p.id,
                p.nombre,
                p.creado_por,
                p.progreso,
                p.fecha_inicio,
                p.fecha_fin_estimada,
                p.fecha_fin_real,
                p.observaciones_estado,
                pe.nombre as estado_nombre,
                pe.color as estado_color,
                pe.icono as estado_icono,
                u.nombre as creador_nombre
            FROM proyectos p
            LEFT JOIN proyecto_estados pe ON p.id_estado = pe.id_estado
            LEFT JOIN usuarios u ON u.usuario = p.creado_por
            WHERE p.creado_por = ?
            ORDER BY p.id DESC
        ");
        $stmt->execute([$usuario_actual]);
    }
    
    $proyectos = $stmt->fetchAll();
    
    // Obtener estados disponibles
    $stmt = $pdo->query("SELECT * FROM proyecto_estados WHERE activo = 1 ORDER BY orden");
    $estados_disponibles = $stmt->fetchAll();
    
} catch (Exception $e) {
    $error_message = "Error al cargar proyectos: " . $e->getMessage();
    $proyectos = [];
    $estados_disponibles = [];
}
?>

<div class="dashboard-grid">
    <?php require_once __DIR__ . '/includes/sidebar.php'; ?>

    <main class="dashboard-main">
        <div class="dashboard-header">
            <h1>Estados de Proyectos</h1>
            <div class="header-actions">
                <?php if ($es_admin): ?>
                <a href="gestionar_estados.php" class="btn btn-outline-primary">
                    <i class="fa-solid fa-cog"></i> Gestionar Estados
                </a>
                <?php endif; ?>
                <a href="proyectos_carrito.php" class="btn btn-primary">
                    <i class="fa-solid fa-plus"></i> Nuevo Proyecto
                </a>
            </div>
        </div>

        <?php if ($mensaje): ?>
            <div class="alert alert-<?= $tipo_mensaje ?>" id="mensaje-alert">
                <?= htmlspecialchars($mensaje) ?>
            </div>
        <?php endif; ?>

        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error_message) ?></div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header">
                <h3><?= $es_admin ? 'Todos los Proyectos' : 'Mis Proyectos' ?></h3>
            </div>
            <div class="card-body">
                <?php if (empty($proyectos)): ?>
                    <div class="alert alert-info">
                        No hay proyectos para mostrar.
                        <a href="proyectos_carrito.php">Crear el primer proyecto</a>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Proyecto</th>
                                    <th>Estado</th>
                                    <th>Progreso</th>
                                    <th>Creador</th>
                                    <th>Fechas</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($proyectos as $proyecto): ?>
                                <tr>
                                    <td><?= $proyecto['id'] ?></td>
                                    <td>
                                        <strong><?= htmlspecialchars($proyecto['nombre']) ?></strong>
                                    </td>
                                    <td>
                                        <span class="estado-badge" style="background-color: <?= $proyecto['estado_color'] ?? '#6c757d' ?>">
                                            <i class="fa-solid <?= $proyecto['estado_icono'] ?? 'fa-circle' ?>"></i>
                                            <?= htmlspecialchars($proyecto['estado_nombre'] ?? 'Sin estado') ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="progress-container">
                                            <div class="progress-bar">
                                                <div class="progress-fill" style="width: <?= $proyecto['progreso'] ?>%"></div>
                                            </div>
                                            <span class="progress-text"><?= number_format($proyecto['progreso'], 1) ?>%</span>
                                        </div>
                                    </td>
                                    <td><?= htmlspecialchars($proyecto['creador_nombre'] ?? $proyecto['creado_por']) ?></td>
                                    <td>
                                        <small>
                                            <?php if ($proyecto['fecha_inicio']): ?>
                                                <strong>Inicio:</strong> <?= date('d/m/Y', strtotime($proyecto['fecha_inicio'])) ?><br>
                                            <?php endif; ?>
                                            <?php if ($proyecto['fecha_fin_estimada']): ?>
                                                <strong>Est.:</strong> <?= date('d/m/Y', strtotime($proyecto['fecha_fin_estimada'])) ?><br>
                                            <?php endif; ?>
                                            <?php if ($proyecto['fecha_fin_real']): ?>
                                                <strong>Real:</strong> <?= date('d/m/Y', strtotime($proyecto['fecha_fin_real'])) ?>
                                            <?php endif; ?>
                                        </small>
                                    </td>
                                    <td>
                                        <?php if ($es_admin || $proyecto['creado_por'] === $usuario_actual): ?>
                                        <button class="btn btn-sm btn-outline-primary" onclick="cambiarEstado(<?= htmlspecialchars(json_encode($proyecto)) ?>)">
                                            <i class="fa-solid fa-edit"></i> Cambiar Estado
                                        </button>
                                        <button class="btn btn-sm btn-outline-info" onclick="verHistorial(<?= $proyecto['id'] ?>)">
                                            <i class="fa-solid fa-history"></i> Historial
                                        </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>

<!-- Modal Cambiar Estado -->
<div class="modal fade" id="modalCambiarEstado" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Cambiar Estado del Proyecto</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="accion" value="cambiar_estado">
                    <input type="hidden" name="id_proyecto" id="modal_id_proyecto">
                    
                    <div class="mb-3">
                        <label class="form-label">Proyecto</label>
                        <input type="text" class="form-control" id="modal_nombre_proyecto" readonly>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Nuevo Estado *</label>
                                <select class="form-control" name="nuevo_estado" id="modal_nuevo_estado" required>
                                    <?php foreach ($estados_disponibles as $estado): ?>
                                    <option value="<?= $estado['id_estado'] ?>" data-color="<?= $estado['color'] ?>" data-icono="<?= $estado['icono'] ?>">
                                        <?= htmlspecialchars($estado['nombre']) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Progreso (%)</label>
                                <input type="number" class="form-control" name="progreso" id="modal_progreso" min="0" max="100" step="0.1">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Fecha Inicio</label>
                                <input type="date" class="form-control" name="fecha_inicio" id="modal_fecha_inicio">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Fecha Fin Estimada</label>
                                <input type="date" class="form-control" name="fecha_fin_estimada" id="modal_fecha_fin_estimada">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Fecha Fin Real</label>
                                <input type="date" class="form-control" name="fecha_fin_real" id="modal_fecha_fin_real">
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Comentarios</label>
                        <textarea class="form-control" name="comentario" id="modal_comentario" rows="3" placeholder="Describe los cambios o el estado actual del proyecto..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Actualizar Estado</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function cambiarEstado(proyecto) {
    document.getElementById('modal_id_proyecto').value = proyecto.id;
    document.getElementById('modal_nombre_proyecto').value = proyecto.nombre;
    document.getElementById('modal_progreso').value = proyecto.progreso || 0;
    document.getElementById('modal_fecha_inicio').value = proyecto.fecha_inicio || '';
    document.getElementById('modal_fecha_fin_estimada').value = proyecto.fecha_fin_estimada || '';
    document.getElementById('modal_fecha_fin_real').value = proyecto.fecha_fin_real || '';
    document.getElementById('modal_comentario').value = proyecto.observaciones_estado || '';
    
    const modal = new bootstrap.Modal(document.getElementById('modalCambiarEstado'));
    modal.show();
}

function verHistorial(id_proyecto) {
    // Por ahora, mostrar un alert simple
    alert('Funcionalidad de historial en desarrollo. ID del proyecto: ' + id_proyecto);
    // TODO: Implementar modal con historial de cambios
}

// Auto-ocultar mensajes después de 5 segundos
setTimeout(function() {
    const alert = document.getElementById('mensaje-alert');
    if (alert) {
        alert.style.display = 'none';
    }
}, 5000);
</script>

<style>
.estado-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.375rem 0.75rem;
    border-radius: 0.375rem;
    color: white;
    font-weight: 500;
    font-size: 0.875rem;
}

.progress-container {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.progress-bar {
    flex: 1;
    height: 8px;
    background-color: #e9ecef;
    border-radius: 4px;
    overflow: hidden;
}

.progress-fill {
    height: 100%;
    background-color: #28a745;
    transition: width 0.3s ease;
}

.progress-text {
    font-size: 0.75rem;
    font-weight: 500;
    min-width: 35px;
}

.header-actions {
    display: flex;
    gap: 0.5rem;
}

.alert {
    padding: 0.75rem 1.25rem;
    margin-bottom: 1rem;
    border: 1px solid transparent;
    border-radius: 0.375rem;
}

.alert-success {
    color: #155724;
    background-color: #d4edda;
    border-color: #c3e6cb;
}

.alert-danger {
    color: #721c24;
    background-color: #f8d7da;
    border-color: #f5c6cb;
}

.alert-info {
    color: #0c5460;
    background-color: #d1ecf1;
    border-color: #bee5eb;
}

.card {
    border: 1px solid #dee2e6;
    border-radius: 0.375rem;
    margin-bottom: 1.5rem;
}

.card-header {
    padding: 0.75rem 1.25rem;
    background-color: #f8f9fa;
    border-bottom: 1px solid #dee2e6;
}

.card-body {
    padding: 1.25rem;
}

.modal-content {
    border-radius: 0.5rem;
}

.table-responsive {
    overflow-x: auto;
}
</style>

<?php require_once __DIR__ . '/includes/footer.php'; ?>