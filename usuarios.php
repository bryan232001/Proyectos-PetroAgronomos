<?php
session_start();
$pageTitle = 'Gestión de Usuarios - EP Petroecuador';
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
    echo "<div class='alert alert-danger'>
        <i class='fa-solid fa-exclamation-triangle'></i>
        <span>Acceso denegado. Solo los administradores pueden gestionar usuarios.</span>
    </div>";
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
                $usuario = trim($_POST['usuario']);
                $email = trim($_POST['email']);
                $password = $_POST['password'];
                $id_rol = (int)$_POST['id_rol'];
                $activo = isset($_POST['activo']) ? 1 : 0;
                
                // Verificar si el usuario ya existe
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM usuarios WHERE usuario = ? OR email = ?");
                $stmt->execute([$usuario, $email]);
                if ($stmt->fetchColumn() > 0) {
                    throw new Exception("El usuario o email ya existe");
                }
                
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO usuarios (nombre, usuario, email, pass_hash, id_rol, activo, fecha_creacion) VALUES (?, ?, ?, ?, ?, ?, NOW())");
                $stmt->execute([$nombre, $usuario, $email, $password_hash, $id_rol, $activo]);
                
                $mensaje = "Usuario creado exitosamente";
                $tipo_mensaje = "success";
                break;
                
            case 'editar':
                $id_usuario = (int)$_POST['id_usuario'];
                $nombre = trim($_POST['nombre']);
                $email = trim($_POST['email']);
                $id_rol = (int)$_POST['id_rol'];
                $activo = isset($_POST['activo']) ? 1 : 0;
                
                $stmt = $pdo->prepare("UPDATE usuarios SET nombre = ?, email = ?, id_rol = ?, activo = ? WHERE id_usuario = ?");
                $stmt->execute([$nombre, $email, $id_rol, $activo, $id_usuario]);
                
                $mensaje = "Usuario actualizado exitosamente";
                $tipo_mensaje = "success";
                break;
                
            case 'cambiar_password':
                $id_usuario = (int)$_POST['id_usuario'];
                $nueva_password = $_POST['nueva_password'];
                
                $password_hash = password_hash($nueva_password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE usuarios SET pass_hash = ? WHERE id_usuario = ?");
                $stmt->execute([$password_hash, $id_usuario]);
                
                $mensaje = "Contraseña actualizada exitosamente";
                $tipo_mensaje = "success";
                break;
                
            case 'eliminar':
                $id_usuario = (int)$_POST['id_usuario'];
                
                // No permitir eliminar el propio usuario
                $stmt = $pdo->prepare("SELECT usuario FROM usuarios WHERE id_usuario = ?");
                $stmt->execute([$id_usuario]);
                $usuario_a_eliminar = $stmt->fetchColumn();
                
                if ($usuario_a_eliminar === $_SESSION['usuario']) {
                    throw new Exception("No puedes eliminar tu propio usuario");
                }
                
                $stmt = $pdo->prepare("DELETE FROM usuarios WHERE id_usuario = ?");
                $stmt->execute([$id_usuario]);
                
                $mensaje = "Usuario eliminado exitosamente";
                $tipo_mensaje = "success";
                break;
        }
    } catch (Exception $e) {
        $mensaje = "Error: " . $e->getMessage();
        $tipo_mensaje = "error";
    }
}

// Obtener lista de usuarios
$stmt = $pdo->query("SELECT id_usuario, nombre, usuario, email, id_rol, activo, fecha_creacion, ultimo_acceso FROM usuarios ORDER BY nombre");
$usuarios = $stmt->fetchAll();

// Definir roles
$roles = [
    1 => 'Administrador',
    2 => 'Técnico',
    3 => 'Supervisor'
];
?>

<div class="dashboard-grid">
    <?php require_once __DIR__ . '/includes/sidebar.php'; ?>

    <main class="dashboard-main">
        <div class="dashboard-header">
            <h1>Gestión de Usuarios</h1>
            <button class="btn btn-primary" onclick="mostrarModalCrear()">
                <i class="fa-solid fa-plus"></i> Nuevo Usuario
            </button>
        </div>

        <?php if ($mensaje): ?>
            <div class="alert alert-<?= $tipo_mensaje ?>" id="mensaje-alert">
                <?= htmlspecialchars($mensaje) ?>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover table-striped">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nombre</th>
                                <th>Usuario</th>
                                <th>Rol</th>
                                <th>Estado</th>
                                <th>Último Acceso</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($usuarios as $usuario): ?>
                            <tr>
                                <td><?= $usuario['id_usuario'] ?></td>
                                <td>
                                    <div class="user-info">
                                        <span class="user-name"><?= htmlspecialchars($usuario['nombre']) ?></span>
                                        <span class="user-email"><?= htmlspecialchars($usuario['email']) ?></span>
                                    </div>
                                </td>
                                <td><?= htmlspecialchars($usuario['usuario']) ?></td>
                                <td>
                                    <span class="badge badge-<?= $usuario['id_rol'] == 1 ? 'danger' : ($usuario['id_rol'] == 2 ? 'primary' : 'warning') ?>">
                                        <?= $roles[$usuario['id_rol']] ?? 'Desconocido' ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge badge-<?= $usuario['activo'] ? 'success' : 'secondary' ?>">
                                        <?= $usuario['activo'] ? 'Activo' : 'Inactivo' ?>
                                    </span>
                                </td>
                                <td><?= $usuario['ultimo_acceso'] ? date('d/m/Y H:i', strtotime($usuario['ultimo_acceso'])) : 'Nunca' ?></td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <button type="button" class="btn btn-outline-primary" data-bs-toggle="tooltip" title="Editar" onclick='editarUsuario(<?= htmlspecialchars(json_encode($usuario), ENT_QUOTES, "UTF-8") ?>)'>
                                            <i class="fa-solid fa-pencil-alt"></i>
                                        </button>
                                        <button type="button" class="btn btn-outline-warning" data-bs-toggle="tooltip" title="Cambiar Contraseña" onclick="cambiarPassword(<?= $usuario['id_usuario'] ?>, '<?= htmlspecialchars($usuario['nombre']) ?>')">
                                            <i class="fa-solid fa-key"></i>
                                        </button>
                                        <?php if ($usuario['usuario'] !== $_SESSION['usuario']): ?>
                                        <button type="button" class="btn btn-outline-danger" data-bs-toggle="tooltip" title="Eliminar" onclick="eliminarUsuario(<?= $usuario['id_usuario'] ?>, '<?= htmlspecialchars($usuario['nombre']) ?>')">
                                            <i class="fa-solid fa-trash-alt"></i>
                                        </button>
                                        <?php endif; ?>
                                    </div>
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

<!-- Modals -->
<div class="modal fade" id="modalCrearUsuario" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Crear Nuevo Usuario</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="accion" value="crear">
                    <div class="mb-3">
                        <label class="form-label">Nombre Completo *</label>
                        <input type="text" class="form-control" name="nombre" required>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Usuario *</label>
                            <input type="text" class="form-control" name="usuario" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Email *</label>
                            <input type="email" class="form-control" name="email" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Contraseña *</label>
                        <input type="password" class="form-control" name="password" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Rol *</label>
                        <select class="form-select" name="id_rol" required>
                            <option value="">Seleccionar rol...</option>
                            <?php foreach ($roles as $id => $nombre): ?>
                            <option value="<?= $id ?>"><?= $nombre ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" name="activo" id="crear_activo" checked>
                        <label class="form-check-label" for="crear_activo">Usuario activo</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Crear Usuario</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="modalEditarUsuario" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Editar Usuario</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="accion" value="editar">
                    <input type="hidden" name="id_usuario" id="edit_id_usuario">
                    <div class="mb-3">
                        <label class="form-label">Nombre Completo *</label>
                        <input type="text" class="form-control" name="nombre" id="edit_nombre" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Usuario</label>
                        <input type="text" class="form-control" id="edit_usuario" readonly disabled>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email *</label>
                        <input type="email" class="form-control" name="email" id="edit_email" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Rol *</label>
                        <select class="form-select" name="id_rol" id="edit_id_rol" required>
                            <?php foreach ($roles as $id => $nombre): ?>
                            <option value="<?= $id ?>"><?= $nombre ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" name="activo" id="edit_activo">
                        <label class="form-check-label" for="edit_activo">Usuario activo</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Actualizar Usuario</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="modalCambiarPassword" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Cambiar Contraseña</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="accion" value="cambiar_password">
                    <input type="hidden" name="id_usuario" id="pass_id_usuario">
                    <p>Estás cambiando la contraseña para <strong id="pass_nombre_usuario"></strong>.</p>
                    <div class="mb-3">
                        <label class="form-label">Nueva Contraseña *</label>
                        <input type="password" class="form-control" name="nueva_password" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-warning">Cambiar Contraseña</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function mostrarModalCrear() {
    const modal = new bootstrap.Modal(document.getElementById('modalCrearUsuario'));
    modal.show();
}

function editarUsuario(usuario) {
    document.getElementById('edit_id_usuario').value = usuario.id_usuario;
    document.getElementById('edit_nombre').value = usuario.nombre;
    document.getElementById('edit_usuario').value = usuario.usuario;
    document.getElementById('edit_email').value = usuario.email;
    document.getElementById('edit_id_rol').value = usuario.id_rol;
    document.getElementById('edit_activo').checked = usuario.activo == 1;
    
    const modal = new bootstrap.Modal(document.getElementById('modalEditarUsuario'));
    modal.show();
}

function cambiarPassword(id_usuario, nombre) {
    document.getElementById('pass_id_usuario').value = id_usuario;
    document.getElementById('pass_nombre_usuario').textContent = nombre;
    
    const modal = new bootstrap.Modal(document.getElementById('modalCambiarPassword'));
    modal.show();
}

function eliminarUsuario(id_usuario, nombre) {
    if (confirm(`¿Estás seguro de que deseas eliminar al usuario "${nombre}"? Esta acción no se puede deshacer.`)) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.style.display = 'none';
        form.innerHTML = `
            <input type="hidden" name="accion" value="eliminar">
            <input type="hidden" name="id_usuario" value="${id_usuario}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

// Auto-ocultar mensajes de alerta
document.addEventListener('DOMContentLoaded', function() {
    // Inicializar tooltips de Bootstrap
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl)
    });

    const alert = document.getElementById('mensaje-alert');
    if (alert) {
        setTimeout(() => {
            alert.classList.add('fade-out');
            setTimeout(() => alert.style.display = 'none', 500);
        }, 5000);
    }
});
</script>

<style>
:root {
    --primary-color: #007bff;
    --secondary-color: #6c757d;
    --success-color: #28a745;
    --danger-color: #dc3545;
    --warning-color: #ffc107;
    --info-color: #17a2b8;
    --light-gray: #f8f9fa;
    --dark-gray: #343a40;
    --border-color: #dee2e6;
}

.dashboard-main {
    padding: 2rem;
}

.dashboard-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2rem;
}

.card {
    border: none;
    border-radius: 0.75rem;
    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
    overflow: hidden;
}

.table {
    margin-bottom: 0;
}

.table thead {
    background-color: var(--light-gray);
}

.table th {
    border-bottom-width: 1px;
    font-weight: 600;
    color: var(--dark-gray);
}

.table td, .table th {
    vertical-align: middle;
    padding: 1rem;
}

.user-info {
    display: flex;
    flex-direction: column;
}
.user-name {
    font-weight: 600;
}
.user-email {
    font-size: 0.875rem;
    color: var(--secondary-color);
}

.badge {
    padding: 0.4em 0.7em;
    font-size: 0.75rem;
    font-weight: 600;
    border-radius: 0.5rem;
}

.badge-success { background-color: #d1e7dd; color: #0f5132; }
.badge-danger { background-color: #f8d7da; color: #842029; }
.badge-primary { background-color: #cce5ff; color: #004085; }
.badge-warning { background-color: #fff3cd; color: #664d03; }
.badge-secondary { background-color: #e2e3e5; color: #41464b; }

.btn-group .btn {
    margin-right: 0.3rem;
    border-radius: 0.5rem !important;
}

.modal-content {
    border-radius: 0.75rem;
    border: none;
    box-shadow: 0 8px 24px rgba(0,0,0,0.15);
}

.modal-header {
    border-bottom: 1px solid var(--border-color);
    padding: 1.5rem;
}

.modal-body {
    padding: 1.5rem;
}

.modal-footer {
    border-top: 1px solid var(--border-color);
    padding: 1rem 1.5rem;
}

.form-select, .form-control {
    border-radius: 0.5rem;
}

.alert {
    border-radius: 0.5rem;
    opacity: 1;
    transition: opacity 0.5s ease-out;
}
.alert.fade-out {
    opacity: 0;
}
</style>

<?php require_once __DIR__ . '/includes/footer.php'; ?>