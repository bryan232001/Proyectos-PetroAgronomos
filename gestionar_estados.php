<?php
session_start();
$pageTitle = 'Gestión de Estados - EP Petroecuador';
$is_dashboard = true;
require_once __DIR__ . '/includes/header.php';

// Verificar si el usuario está logueado y es administrador
if (!isset($_SESSION['usuario'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/includes/db.php';

// Verificar si es administrador (id_rol = 1)
$stmt = $pdo->prepare("SELECT id_rol FROM usuarios WHERE usuario = ?");
$stmt->execute([$_SESSION['usuario']]);
$user_data = $stmt->fetch();

if (!$user_data || $user_data['id_rol'] != 1) {
    echo "<div class='alert alert-danger'>Acceso denegado. Solo los administradores pueden gestionar estados.</div>";
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

// Procesar acciones
$mensaje = '';
$tipo_mensaje = '';

if ($_POST) {
    $accion = $_POST['accion'] ?? '';
    
    try {
        switch ($accion) {
            case 'crear':
                $nombre = trim($_POST['nombre']);
                $descripcion = trim($_POST['descripcion']);
                $color = $_POST['color'];
                $icono = $_POST['icono'];
                $orden = (int)$_POST['orden'];
                
                $stmt = $pdo->prepare("INSERT INTO proyecto_estados (nombre, descripcion, color, icono, orden) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$nombre, $descripcion, $color, $icono, $orden]);
                
                $mensaje = "Estado creado exitosamente";
                $tipo_mensaje = "success";
                break;
                
            case 'editar':
                $id_estado = (int)$_POST['id_estado'];
                $nombre = trim($_POST['nombre']);
                $descripcion = trim($_POST['descripcion']);
                $color = $_POST['color'];
                $icono = $_POST['icono'];
                $orden = (int)$_POST['orden'];
                $activo = isset($_POST['activo']) ? 1 : 0;
                
                $stmt = $pdo->prepare("UPDATE proyecto_estados SET nombre = ?, descripcion = ?, color = ?, icono = ?, orden = ?, activo = ? WHERE id_estado = ?");
                $stmt->execute([$nombre, $descripcion, $color, $icono, $orden, $activo, $id_estado]);
                
                $mensaje = "Estado actualizado exitosamente";
                $tipo_mensaje = "success";
                break;
                
            case 'eliminar':
                $id_estado = (int)$_POST['id_estado'];
                
                // Verificar si hay proyectos usando este estado
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM proyectos WHERE id_estado = ?");
                $stmt->execute([$id_estado]);
                $proyectos_usando = $stmt->fetchColumn();
                
                if ($proyectos_usando > 0) {
                    throw new Exception("No se puede eliminar el estado porque $proyectos_usando proyecto(s) lo están usando");
                }
                
                $stmt = $pdo->prepare("DELETE FROM proyecto_estados WHERE id_estado = ?");
                $stmt->execute([$id_estado]);
                
                $mensaje = "Estado eliminado exitosamente";
                $tipo_mensaje = "success";
                break;
        }
    } catch (Exception $e) {
        $mensaje = "Error: " . $e->getMessage();
        $tipo_mensaje = "danger";
    }
}

// Obtener lista de estados
$stmt = $pdo->query("
    SELECT 
        pe.*,
        COUNT(p.id) as proyectos_usando
    FROM proyecto_estados pe
    LEFT JOIN proyectos p ON pe.id_estado = p.id_estado
    GROUP BY pe.id_estado
    ORDER BY pe.orden, pe.nombre
");
$estados = $stmt->fetchAll();
?>

<div class="dashboard-grid">
    <?php require_once __DIR__ . '/includes/sidebar.php'; ?>

    <main class="dashboard-main">
        <div class="dashboard-header">
            <h1>Gestión de Estados de Proyecto</h1>
            <button class="btn btn-primary" onclick="mostrarModalCrear()">
                <i class="fa-solid fa-plus"></i> Nuevo Estado
            </button>
        </div>

        <?php if ($mensaje): ?>
            <div class="alert alert-<?= $tipo_mensaje ?>" id="mensaje-alert">
                <?= htmlspecialchars($mensaje) ?>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header">
                <h3>Estados Configurados</h3>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Orden</th>
                                <th>Estado</th>
                                <th>Descripción</th>
                                <th>Color</th>
                                <th>Proyectos</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($estados as $estado): ?>
                            <tr>
                                <td><?= $estado['orden'] ?></td>
                                <td>
                                    <span class="estado-badge" style="background-color: <?= $estado['color'] ?>">
                                        <i class="fa-solid <?= $estado['icono'] ?>"></i>
                                        <?= htmlspecialchars($estado['nombre']) ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars($estado['descripcion']) ?></td>
                                <td>
                                    <span class="color-preview" style="background-color: <?= $estado['color'] ?>"></span>
                                    <?= $estado['color'] ?>
                                </td>
                                <td>
                                    <span class="badge badge-info"><?= $estado['proyectos_usando'] ?></span>
                                </td>
                                <td>
                                    <span class="badge badge-<?= $estado['activo'] ? 'success' : 'secondary' ?>">
                                        <?= $estado['activo'] ? 'Activo' : 'Inactivo' ?>
                                    </span>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-outline-primary" onclick="editarEstado(<?= htmlspecialchars(json_encode($estado)) ?>)">
                                        <i class="fa-solid fa-edit"></i>
                                    </button>
                                    <?php if ($estado['proyectos_usando'] == 0): ?>
                                    <button class="btn btn-sm btn-outline-danger" onclick="eliminarEstado(<?= $estado['id_estado'] ?>, '<?= htmlspecialchars($estado['nombre']) ?>')">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>
</div>

<script>
function mostrarModalCrear() {
    // Implementar modal básico
    const nombre = prompt('Nombre del estado:');
    if (nombre) {
        const descripcion = prompt('Descripción:') || '';
        const color = prompt('Color (hex):', '#6c757d') || '#6c757d';
        const orden = prompt('Orden:', '<?= count($estados) + 1 ?>') || <?= count($estados) + 1 ?>;
        
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="accion" value="crear">
            <input type="hidden" name="nombre" value="${nombre}">
            <input type="hidden" name="descripcion" value="${descripcion}">
            <input type="hidden" name="color" value="${color}">
            <input type="hidden" name="icono" value="fa-circle">
            <input type="hidden" name="orden" value="${orden}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

function editarEstado(estado) {
    const nombre = prompt('Nombre del estado:', estado.nombre);
    if (nombre) {
        const descripcion = prompt('Descripción:', estado.descripcion || '') || '';
        const color = prompt('Color (hex):', estado.color) || estado.color;
        const orden = prompt('Orden:', estado.orden) || estado.orden;
        const activo = confirm('¿Estado activo?');
        
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="accion" value="editar">
            <input type="hidden" name="id_estado" value="${estado.id_estado}">
            <input type="hidden" name="nombre" value="${nombre}">
            <input type="hidden" name="descripcion" value="${descripcion}">
            <input type="hidden" name="color" value="${color}">
            <input type="hidden" name="icono" value="${estado.icono}">
            <input type="hidden" name="orden" value="${orden}">
            ${activo ? '<input type="hidden" name="activo" value="1">' : ''}
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

function eliminarEstado(id_estado, nombre) {
    if (confirm(`¿Estás seguro de que deseas eliminar el estado "${nombre}"?`)) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="accion" value="eliminar">
            <input type="hidden" name="id_estado" value="${id_estado}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
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

.color-preview {
    display: inline-block;
    width: 20px;
    height: 20px;
    border-radius: 3px;
    margin-right: 0.5rem;
    vertical-align: middle;
    border: 1px solid #dee2e6;
}

.badge {
    padding: 0.25rem 0.5rem;
    font-size: 0.75rem;
    border-radius: 0.375rem;
    font-weight: 500;
}

.badge-success { background-color: #198754; color: white; }
.badge-secondary { background-color: #6c757d; color: white; }
.badge-info { background-color: #0dcaf0; color: black; }

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
</style>

<?php require_once __DIR__ . '/includes/footer.php'; ?>