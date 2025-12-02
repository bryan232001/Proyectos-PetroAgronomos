<?php
session_start();
if (!isset($_SESSION['usuario'])) { header('Location: login.php'); exit; }

require_once __DIR__ . '/includes/db.php';

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

if (!isset($pdo)) { die("Error: No hay conexión a la base de datos"); }

try {
    // Obtener información del usuario actual
    $usuario_actual = $_SESSION['usuario'] ?? '';
    $rol_actual = $_SESSION['id_rol'] ?? 2;
    $es_admin = ($rol_actual == 1);
    
    // Construir consulta según permisos
    if ($es_admin) {
        // Administrador ve todas las asignaciones
        $stmt = $pdo->query("
            SELECT
                pa.id_asignacion,
                pa.fecha_asignacion,
                pa.comunidad_nombre,
                pa.bloque,
                pa.beneficiarios,
                p.nombre AS proyecto_nombre,
                p.creado_por,
                u_tecnico.nombre AS tecnico_nombre,
                aer.total_productos,
                aer.productos_entregados,
                aer.porcentaje_entrega,
                aer.completado,
                aer.fecha_completado,
                pa.estado_asignacion
            FROM proyecto_asignacion pa
            LEFT JOIN proyectos p ON p.id = pa.id_proyecto
            LEFT JOIN usuarios u_tecnico ON u_tecnico.id_usuario = pa.id_tecnico
            LEFT JOIN asignacion_entregas_resumen aer ON aer.id_asignacion = pa.id_asignacion
            ORDER BY pa.id_asignacion DESC
        ");
    } else {
        // Usuarios normales solo ven sus propias asignaciones o las que tienen asignadas como técnico
        $stmt = $pdo->prepare("
            SELECT
                pa.id_asignacion,
                pa.fecha_asignacion,
                pa.comunidad_nombre,
                pa.bloque,
                pa.beneficiarios,
                p.nombre AS proyecto_nombre,
                p.creado_por,
                u_tecnico.nombre AS tecnico_nombre,
                aer.total_productos,
                aer.productos_entregados,
                aer.porcentaje_entrega,
                aer.completado,
                aer.fecha_completado,
                pa.estado_asignacion
            FROM proyecto_asignacion pa
            LEFT JOIN proyectos p ON p.id = pa.id_proyecto
            LEFT JOIN usuarios u_tecnico ON u_tecnico.id_usuario = pa.id_tecnico
            LEFT JOIN asignacion_entregas_resumen aer ON aer.id_asignacion = pa.id_asignacion
            WHERE p.creado_por = ? OR u_tecnico.usuario = ?
            ORDER BY pa.id_asignacion DESC
        ");
        $stmt->execute([$usuario_actual, $usuario_actual]);
    }
    
    $asignaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calcular estadísticas
    $total_asignaciones = count($asignaciones);
    $proyectos_completados = count(array_filter($asignaciones, function($a) { return $a['completado']; }));
    $porcentaje_completados = $total_asignaciones > 0 ? ($proyectos_completados / $total_asignaciones) * 100 : 0;
    
} catch (Exception $e) {
    error_log("Error al cargar entregas: " . $e->getMessage());
    $error_msg = $e->getMessage();
    $asignaciones = [];
    $total_asignaciones = 0;
    $proyectos_completados = 0;
    $porcentaje_completados = 0;
}

$pageTitle = 'Gestionar Entregas';
require_once __DIR__ . '/includes/header.php';
?>

<style>
.progress-bar {
    width: 100%;
    height: 20px;
    background-color: #e5e7eb;
    border-radius: 10px;
    overflow: hidden;
    position: relative;
}

.progress-fill {
    height: 100%;
    background: linear-gradient(90deg, #10b981, #059669);
    border-radius: 10px;
    transition: width 0.3s ease;
    position: relative;
}

.progress-text {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    font-size: 0.75rem;
    font-weight: 600;
    color: #374151;
    z-index: 1;
}

.status-badge {
    padding: 0.25rem 0.75rem;
    border-radius: 9999px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
}

.status-completado {
    background-color: #dcfce7;
    color: #166534;
}

.status-en-progreso {
    background-color: #fef3c7;
    color: #92400e;
}

.status-pendiente {
    background-color: #fee2e2;
    color: #991b1b;
}

.stats-panel {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 1.5rem;
    border-radius: 12px;
    margin-bottom: 1.5rem;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}

.stats-grid-mini {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 1rem;
}

.stat-mini {
    text-align: center;
    background: rgba(255,255,255,0.1);
    padding: 1rem;
    border-radius: 8px;
    backdrop-filter: blur(10px);
}

.stat-mini-value {
    font-size: 1.75rem;
    font-weight: 700;
    margin-bottom: 0.25rem;
}

.stat-mini-label {
    font-size: 0.875rem;
    opacity: 0.9;
}

.modal-overlay {
    position: fixed;
    inset: 0;
    background: rgba(15,23,42,.6);
    backdrop-filter: blur(4px);
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
    z-index: 2147483647;
    opacity: 0;
    visibility: hidden;
    transition: all .25s ease;
}

.modal-overlay.show {
    opacity: 1;
    visibility: visible;
}

.modal-content {
    width: 100%;
    max-width: 800px;
    background: #fff;
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 25px 60px rgba(0,0,0,.25);
    transform: translateY(10px) scale(.98);
    transition: transform .25s ease;
    max-height: 90vh;
    overflow-y: auto;
}

.modal-overlay.show .modal-content {
    transform: translateY(0) scale(1);
}

.modal-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 16px 20px;
    background: linear-gradient(135deg,#3b82f6,#10b981);
    color: #fff;
}

.modal-title {
    margin: 0;
    font-weight: 700;
    font-size: 1.05rem;
}

.modal-close {
    border: 0;
    background: transparent;
    color: #fff;
    width: 32px;
    height: 32px;
    border-radius: 999px;
    display: grid;
    place-items: center;
    font-size: 20px;
    cursor: pointer;
}

.modal-close:hover {
    background: rgba(255,255,255,.18);
}

.modal-body {
    padding: 18px 20px;
    color: #0f172a;
}

.producto-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 1rem;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    margin-bottom: 0.5rem;
    background: #f9fafb;
}

.producto-info {
    flex: 1;
}

.producto-nombre {
    font-weight: 600;
    color: #374151;
    margin-bottom: 0.25rem;
}

.producto-detalles {
    font-size: 0.875rem;
    color: #6b7280;
}

.producto-controles {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.checkbox-entrega {
    width: 20px;
    height: 20px;
    cursor: pointer;
}

.observaciones-input {
    width: 150px;
    padding: 0.5rem;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    font-size: 0.875rem;
}

.producto-entregado {
    background: #f0fdf4;
    border-color: #bbf7d0;
}

.alert {
    padding: 0.75rem 1.25rem;
    margin-bottom: 1rem;
    border: 1px solid transparent;
    border-radius: 0.375rem;
}

.alert-success {
    color: #065f46;
    background-color: #d1fae5;
    border-color: #a7f3d0;
}

.alert-danger {
    color: #721c24;
    background-color: #f8d7da;
    border-color: #f5c6cb;
}

.header-filters {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.filter-select {
    padding: 0.5rem 1rem;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    background: white;
    font-size: 0.875rem;
    min-width: 200px;
}

.estado-entregado {
    background-color: #dcfce7;
    color: #166534;
    padding: 0.25rem 0.75rem;
    border-radius: 9999px;
    font-size: 0.75rem;
    font-weight: 600;
}

.estado-no-entregado {
    background-color: #fee2e2;
    color: #991b1b;
    padding: 0.25rem 0.75rem;
    border-radius: 9999px;
    font-size: 0.75rem;
    font-weight: 600;
}

.producto-row-entregado {
    background-color: #f0fdf4;
}

.producto-row-no-entregado {
    background-color: #fef2f2;
}

.btn {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 1rem;
    border: none;
    border-radius: 6px;
    font-size: 0.875rem;
    font-weight: 500;
    cursor: pointer;
    text-decoration: none;
    transition: all 0.2s ease;
}

.btn-primary {
    background-color: #3b82f6;
    color: white;
}

.btn-primary:hover {
    background-color: #2563eb;
}

.btn-outline-primary {
    background-color: transparent;
    color: #3b82f6;
    border: 1px solid #3b82f6;
}

.btn-outline-primary:hover {
    background-color: #3b82f6;
    color: white;
}

.btn-danger {
    background-color: #ef4444;
    color: white;
}
.btn-danger:hover {
    background-color: #dc2626;
}
.btn-icon {
    width: 32px;
    height: 32px;
    padding: 0;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 6px;
}
.btn-danger-outline {
    background-color: transparent;
    color: #ef4444;
    border: 1px solid #ef4444;
}
.btn-danger-outline:hover {
    background-color: #ef4444;
    color: white;
}
</style>

<div class="dashboard-grid">
    <?php require_once __DIR__ . '/includes/sidebar.php'; ?>

    <main class="dashboard-main">
        <div class="dashboard-header">
            <h1 class="dashboard-welcome">
                <?= $es_admin ? 'Gestión de Entregas (Administrador)' : 'Mis Entregas' ?>
            </h1>
        </div>

        <!-- Panel de Estadísticas -->
        <div class="stats-panel">
            <h3>📦 Resumen de Entregas</h3>
            <div class="stats-grid-mini">
                <div class="stat-mini">
                    <div class="stat-mini-value"><?= $total_asignaciones ?></div>
                    <div class="stat-mini-label">Total Asignaciones</div>
                </div>
                <div class="stat-mini">
                    <div class="stat-mini-value"><?= $proyectos_completados ?></div>
                    <div class="stat-mini-label">Completados</div>
                </div>
                <div class="stat-mini">
                    <div class="stat-mini-value"><?= round($porcentaje_completados, 1) ?>%</div>
                    <div class="stat-mini-label">% Completado</div>
                </div>
            </div>
        </div>

        <div class="card-table">
            <div class="card-table-header">
                <h2 class="card-table-title">Productos Entregados</h2>
                <div class="header-filters">
                    <button class="btn btn-outline-primary" onclick="mostrarAsignaciones()" id="btn-vista-asignaciones">
                        <i class="fa-solid fa-list"></i> Vista por Asignaciones
                    </button>
                    <button class="btn btn-primary" onclick="cargarTodosLosProductos()" id="btn-vista-productos">
                        <i class="fa-solid fa-boxes"></i> Vista de Productos
                    </button>
                    <select id="filtro-estado" class="filter-select" onchange="filtrarProductos()" style="display: none;">
                        <option value="todos">Todos los productos</option>
                        <option value="entregados">Solo entregados</option>
                        <option value="no-entregados">Solo no entregados</option>
                    </select>
                </div>
            </div>
            <div class="card-table-body">
                <!-- Tabla de productos individuales -->
                <div id="productos-completos" style="display: none;">
                    <div class="table-wrapper">
                        <table class="data-table" id="tablaProductosCompletos">
                            <thead>
                                <tr>
                                    <th>Producto</th>
                                    <th>Proyecto</th>
                                    <th>Comunidad</th>
                                    <th>Bloque</th>
                                    <th>Cantidad</th>
                                    <th>Estado</th>
                                    <th>Fecha Entrega</th>
                                    <th>Observaciones</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody id="tbody-productos-completos">
                                <!-- Se llenará dinámicamente -->
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- Tabla de asignaciones (vista original) -->
                <div id="asignaciones-resumen">
                <div class="table-wrapper">
                    <?php if (isset($error_msg)): ?>
                        <div class="alert alert-danger">Error: <?= h($error_msg) ?></div>
                    <?php elseif (empty($asignaciones)): ?>
                        <div class="alert alert-info">No se han encontrado asignaciones.</div>
                    <?php else: ?>
                        <table class="data-table" id="tablaEntregas">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Proyecto</th>
                                    <th>Comunidad</th>
                                    <th>Bloque</th>
                                    <th>Progreso</th>
                                    <th>Estado</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($asignaciones as $asig): ?>
                                    <tr>
                                        <td><?= h($asig['id_asignacion']) ?></td>
                                        <td>
                                            <div style="font-weight: 600;"><?= h($asig['proyecto_nombre'] ?? 'N/A') ?></div>
                                            <div style="font-size: 0.875rem; color: #6b7280;">
                                                Por: <?= h($asig['creado_por'] ?? 'N/A') ?>
                                            </div>
                                        </td>
                                        <td><?= h($asig['comunidad_nombre']) ?></td>
                                        <td><?= h($asig['bloque']) ?></td>
                                        <td>
                                            <?php 
                                            $porcentaje = $asig['porcentaje_entrega'] ?? 0;
                                            $productos_entregados = $asig['productos_entregados'] ?? 0;
                                            $total_productos = $asig['total_productos'] ?? 0;
                                            ?>
                                            <div class="progress-bar">
                                                <div class="progress-fill" style="width: <?= $porcentaje ?>%"></div>
                                                <div class="progress-text">
                                                    <?= $productos_entregados ?>/<?= $total_productos ?> (<?= round($porcentaje, 1) ?>%)
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <?php if ($asig['completado']): ?>
                                                <span class="status-badge status-completado">Completado</span>
                                            <?php elseif ($porcentaje > 0): ?>
                                                <span class="status-badge status-en-progreso">En Progreso</span>
                                            <?php else: ?>
                                                <span class="status-badge status-pendiente">Pendiente</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <button class="btn-icon btn-icon-primary" 
                                                    onclick="gestionarEntregas(<?= (int)$asig['id_asignacion'] ?>)"
                                                    title="Gestionar entregas">
                                                <i class="fa-solid fa-boxes-stacked"></i>
                                            </button>
                                            <button class="btn-icon btn-danger"
                                                    onclick="eliminarAsignacion(<?= (int)$asig['id_asignacion'] ?>, this)"
                                                    title="Eliminar asignación completa">
                                                <i class="fa-solid fa-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Modal Gestionar Entregas -->
<div class="modal-overlay" id="modal-entregas" aria-hidden="true">
    <div class="modal-content" role="dialog" aria-modal="true" aria-labelledby="title-entregas">
        <div class="modal-header">
            <h3 id="title-entregas" class="modal-title">
                <i class="fa-solid fa-boxes-stacked" style="margin-right:.5rem;"></i>
                Gestionar Entregas
            </h3>
            <button class="modal-close" onclick="closeModal('modal-entregas')" aria-label="Cerrar">&times;</button>
        </div>
        <div class="modal-body" id="modal-body-entregas">
            Cargando...
        </div>
    </div>
</div>

<script>
function openModal(id) {
    const el = document.getElementById(id);
    if (!el) return;
    el.classList.add('show');
    el.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
}

function closeModal(id) {
    const el = document.getElementById(id);
    if (!el) return;
    el.classList.remove('show');
    el.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
}

// Cerrar modal al hacer clic fuera
document.getElementById('modal-entregas').addEventListener('click', e => {
    if (e.target === e.currentTarget) closeModal('modal-entregas');
});

// Cerrar modal con Escape
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') closeModal('modal-entregas');
});

async function gestionarEntregas(id_asignacion) {
    const modalBody = document.getElementById('modal-body-entregas');
    modalBody.innerHTML = 'Cargando...';
    openModal('modal-entregas');

    try {
        const response = await fetch(`api_get_entregas_asignacion.php?id_asignacion=${id_asignacion}`);
        const data = await response.json();
        
        if (!data.ok) {
            throw new Error(data.msg || 'Error desconocido');
        }

        const asignacion = data.asignacion;
        const productos = data.productos;

        let html = `
            <div style="margin-bottom: 1.5rem; padding: 1rem; background: #f8fafc; border-radius: 8px;">
                <h4 style="margin: 0 0 0.5rem 0; color: #374151;">
                    ${asignacion.proyecto_nombre}
                </h4>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; font-size: 0.875rem; color: #6b7280;">
                <div><strong>Comunidad:</strong> ${asignacion.comunidad_nombre}</div>
                <div><strong>Bloque:</strong> ${asignacion.bloque}</div>
                <div><strong>Beneficiarios:</strong> ${asignacion.beneficiarios}</div>
                <div data-summary-progreso><strong>Progreso:</strong> ${asignacion.productos_entregados || 0}/${asignacion.total_productos || 0}</div>
                </div>
            </div>
            
            <div id="productos-container">
        `;

        if (productos.length === 0) {
            html += '<div class="alert alert-info">No hay productos asignados para esta asignación.</div>';
        } else {
            productos.forEach((producto, index) => {
                const entregado = producto.entregado == 1;
                html += `
                    <div class="producto-item ${entregado ? 'producto-entregado' : ''}" data-producto-id="${producto.id_producto}">
                        <div class="producto-info">
                            <div class="producto-nombre">${producto.producto_nombre || 'Producto sin nombre'}</div>
                            <div class="producto-detalles">
                                Asignado: ${producto.cantidad_asignada || 0} ${producto.unidad || 'unidades'}
                                ${producto.fecha_entrega ? ` | Entregado: ${new Date(producto.fecha_entrega).toLocaleDateString()}` : ''}
                                ${producto.entregado_por ? ` | Por: ${producto.entregado_por}` : ''}
                            </div>
                        </div>
                        <div class="producto-controles">
                            <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                                <input type="checkbox" class="checkbox-entrega" 
                                       ${entregado ? 'checked' : ''}
                                       onchange="toggleEntrega(${id_asignacion}, ${producto.id_producto}, this.checked)">
                                <span style="font-size: 0.875rem;">Entregado</span>
                            </label>
                            <input type="text" class="observaciones-input" 
                                   value="${producto.observaciones || ''}"
                                   placeholder="Observaciones"
                                   onchange="actualizarObservaciones(${id_asignacion}, ${producto.id_producto}, this.value)">
                            <button class="btn btn-sm btn-danger-outline" onclick="eliminarProducto(${id_asignacion}, ${producto.id_producto}, this)" title="Eliminar producto">
                                <i class="fa-solid fa-trash"></i>
                            </button>
                        </div>
                    </div>
                `;
            });
        }

        html += '</div>';
        modalBody.innerHTML = html;

    } catch (error) {
        modalBody.innerHTML = `<div class="alert alert-danger">Error al cargar entregas: ${error.message}</div>`;
    }
}

async function eliminarProducto(id_asignacion, id_producto, btn) {
    const productoNombre = btn.closest('.producto-item').querySelector('.producto-nombre').textContent;
    if (!confirm(`¿Está seguro de que desea eliminar el producto "${productoNombre}" de esta asignación? Esta acción no se puede deshacer.`)) {
        return;
    }

    try {
        const response = await fetch('api_delete_producto_asignacion.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                id_asignacion: id_asignacion,
                id_producto: id_producto
            })
        });

        const data = await response.json();

        if (!data.ok) {
            throw new Error(data.msg || 'Error al eliminar el producto.');
        }

        const productoItem = btn.closest('.producto-item');
        productoItem.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
        productoItem.style.opacity = '0';
        productoItem.style.transform = 'translateX(-20px)';
        setTimeout(() => {
            productoItem.remove();
            const container = document.getElementById('productos-container');
            if (container && container.children.length === 0) {
                container.innerHTML = '<div class="alert alert-info">No hay productos asignados para esta asignación.</div>';
            }
        }, 300);

        actualizarFilaTabla(id_asignacion, data.porcentaje, data.completado, data.productos_entregados, data.total_productos);
        
        const modalSummary = document.querySelector('[data-summary-progreso]');
        if(modalSummary) {
            modalSummary.innerHTML = `<strong>Progreso:</strong> ${data.productos_entregados || 0}/${data.total_productos || 0}`;
        }

        mostrarMensaje('Producto eliminado correctamente.', 'success');

    } catch (error) {
        mostrarMensaje('Error al eliminar: ' + error.message, 'error');
    }
}

async function toggleEntrega(id_asignacion, id_producto, entregado) {
    try {
        const productoItem = document.querySelector(`.producto-item[data-producto-id="${id_producto}"]`);
        const observacionesInput = productoItem.querySelector('.observaciones-input');
        
        const response = await fetch('api_actualizar_entrega.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                id_asignacion: id_asignacion,
                id_producto: id_producto,
                entregado: entregado,
                cantidad_entregada: 0, // Ya no se usa el campo de cantidad
                observaciones: observacionesInput.value || ''
            })
        });

        const data = await response.json();
        
        if (!data.ok) {
            throw new Error(data.msg || 'Error al actualizar');
        }

        if (entregado) {
            productoItem.classList.add('producto-entregado');
        } else {
            productoItem.classList.remove('producto-entregado');
        }

        actualizarFilaTabla(id_asignacion, data.porcentaje, data.completado, data.productos_entregados, data.total_productos);
        
        const modalSummary = document.querySelector('[data-summary-progreso]');
        if(modalSummary) {
            modalSummary.innerHTML = `<strong>Progreso:</strong> ${data.productos_entregados || 0}/${data.total_productos || 0}`;
        }
        
        mostrarMensaje('Entrega actualizada correctamente', 'success');

    } catch (error) {
        mostrarMensaje('Error al actualizar entrega: ' + error.message, 'error');
        const checkbox = document.querySelector(`.producto-item[data-producto-id="${id_producto}"] .checkbox-entrega`);
        if(checkbox) checkbox.checked = !entregado;
    }
}


async function actualizarObservaciones(id_asignacion, id_producto, observaciones) {
    try {
        const productoItem = document.querySelector(`.producto-item[data-producto-id="${id_producto}"]`);
        const checkbox = productoItem.querySelector('.checkbox-entrega');
        
        const response = await fetch('api_actualizar_entrega.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                id_asignacion: id_asignacion,
                id_producto: id_producto,
                entregado: checkbox.checked,
                cantidad_entregada: 0, // Ya no se usa el campo de cantidad
                observaciones: observaciones
            })
        });

        const data = await response.json();
        
        if (!data.ok) {
            throw new Error(data.msg || 'Error al actualizar');
        }

    } catch (error) {
        mostrarMensaje('Error al actualizar observaciones: ' + error.message, 'error');
    }
}

function actualizarFilaTabla(id_asignacion, porcentaje, completado, productos_entregados, total_productos) {
    const filas = document.querySelectorAll('#tablaEntregas tbody tr');
    filas.forEach(fila => {
        const idCell = fila.cells[0];
        if (idCell && idCell.textContent == id_asignacion) {
            const progressFill = fila.querySelector('.progress-fill');
            const progressText = fila.querySelector('.progress-text');
            if (progressFill && progressText) {
                progressFill.style.width = porcentaje + '%';
                progressText.textContent = `${productos_entregados || 0}/${total_productos || 0} (${Math.round(porcentaje)}%)`;
            }
            
            const statusBadge = fila.querySelector('.status-badge');
            if (statusBadge) {
                statusBadge.className = 'status-badge ' + (completado ? 'status-completado' : (porcentaje > 0 ? 'status-en-progreso' : 'status-pendiente'));
                statusBadge.textContent = completado ? 'COMPLETADO' : (porcentaje > 0 ? 'EN PROGRESO' : 'PENDIENTE');
            }
        }
    });
}

function mostrarMensaje(mensaje, tipo) {
    const div = document.createElement('div');
    div.className = `alert alert-${tipo === 'success' ? 'success' : 'danger'}`;
    div.textContent = mensaje;
    div.style.position = 'fixed';
    div.style.top = '20px';
    div.style.right = '20px';
    div.style.zIndex = '2147483647';
    div.style.minWidth = '300px';
    div.style.boxShadow = '0 4px 15px rgba(0,0,0,0.1)';
    
    document.body.appendChild(div);
    
    setTimeout(() => {
        if (div.parentNode) {
            div.parentNode.removeChild(div);
        }
    }, 3000);
}

// Cargar todos los productos
async function cargarTodosLosProductos() {
    try {
        mostrarMensaje('Cargando productos...', 'info');
        
        const response = await fetch('api_get_todos_productos_entregas.php');
        const data = await response.json();
        
        if (!data.ok) {
            throw new Error(data.msg || 'Error al cargar productos');
        }
        
        const productos = data.productos;
        const tbody = document.getElementById('tbody-productos-completos');
        
        if (productos.length === 0) {
            tbody.innerHTML = '<tr><td colspan="9" class="text-center">No hay productos para mostrar</td></tr>';
        } else {
            let html = '';
            productos.forEach(producto => {
                const entregado = producto.entregado == 1;
                const estadoClass = entregado ? 'producto-row-entregado' : 'producto-row-no-entregado';
                const estadoBadge = entregado ? 
                    '<span class="estado-entregado">ENTREGADO</span>' : 
                    '<span class="estado-no-entregado">NO ENTREGADO</span>';
                
                html += `
                    <tr class="${estadoClass}" data-estado="${entregado ? 'entregado' : 'no-entregado'}">
                        <td>
                            <strong>${producto.producto_nombre}</strong><br>
                            <small>${producto.cantidad_asignada} ${producto.unidad || 'unidades'}</small>
                        </td>
                        <td>${producto.proyecto_nombre}</td>
                        <td>${producto.comunidad_nombre}</td>
                        <td>${producto.bloque}</td>
                        <td>${producto.cantidad_asignada} ${producto.unidad || ''}</td>
                        <td>${estadoBadge}</td>
                        <td>${producto.fecha_entrega ? new Date(producto.fecha_entrega).toLocaleDateString() : '-'}</td>
                        <td>
                            <input type="text" class="observaciones-input" 
                                   value="${producto.observaciones || ''}"
                                   placeholder="Observaciones"
                                   onchange="actualizarObservacionesDirecto(${producto.id_asignacion}, ${producto.id_producto}, this.value)"
                                   style="width: 120px; font-size: 0.75rem;">
                        </td>
                        <td>
                            <label style="display: flex; align-items: center; gap: 0.25rem; cursor: pointer;">
                                <input type="checkbox" 
                                       ${entregado ? 'checked' : ''}
                                       onchange="toggleEntregaDirecto(${producto.id_asignacion}, ${producto.id_producto}, this.checked, this)">
                                <span style="font-size: 0.75rem;">${entregado ? 'Sí' : 'No'}</span>
                            </label>
                        </td>
                    </tr>
                `;
            });
            tbody.innerHTML = html;
        }
        
        // Mostrar la tabla de productos y ocultar la de asignaciones
        document.getElementById('productos-completos').style.display = 'block';
        document.getElementById('asignaciones-resumen').style.display = 'none';
        document.getElementById('filtro-estado').style.display = 'block';
        
        // Actualizar botones
        document.getElementById('btn-vista-productos').className = 'btn btn-primary';
        document.getElementById('btn-vista-asignaciones').className = 'btn btn-outline-primary';
        
        mostrarMensaje(`${productos.length} productos cargados correctamente`, 'success');
        
    } catch (error) {
        mostrarMensaje('Error al cargar productos: ' + error.message, 'error');
    }
}

// Filtrar productos por estado
function filtrarProductos() {
    const filtro = document.getElementById('filtro-estado').value;
    const filas = document.querySelectorAll('#tbody-productos-completos tr');
    
    filas.forEach(fila => {
        const estado = fila.getAttribute('data-estado');
        
        if (filtro === 'todos') {
            fila.style.display = '';
        } else if (filtro === 'entregados' && estado === 'entregado') {
            fila.style.display = '';
        } else if (filtro === 'no-entregados' && estado === 'no-entregado') {
            fila.style.display = '';
        } else {
            fila.style.display = 'none';
        }
    });
    
    // Contar productos visibles
    const filasVisibles = document.querySelectorAll('#tbody-productos-completos tr[style=""], #tbody-productos-completos tr:not([style])');
    const totalVisibles = Array.from(filasVisibles).filter(fila => !fila.querySelector('td[colspan]')).length;
    
    mostrarMensaje(`Mostrando ${totalVisibles} productos`, 'info');
}

// Toggle entrega directamente desde la tabla
async function toggleEntregaDirecto(id_asignacion, id_producto, entregado, checkbox) {
    try {
        const fila = checkbox.closest('tr');
        const observacionesInput = fila.querySelector('.observaciones-input');
        
        const response = await fetch('api_actualizar_entrega.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                id_asignacion: id_asignacion,
                id_producto: id_producto,
                entregado: entregado,
                cantidad_entregada: 0,
                observaciones: observacionesInput.value || ''
            })
        });

        const data = await response.json();
        
        if (!data.ok) {
            throw new Error(data.msg || 'Error al actualizar');
        }

        // Actualizar UI de la fila
        const estadoBadge = fila.querySelector('.estado-entregado, .estado-no-entregado');
        const fechaCell = fila.cells[6];
        const labelSpan = checkbox.nextElementSibling;
        
        if (entregado) {
            fila.className = 'producto-row-entregado';
            fila.setAttribute('data-estado', 'entregado');
            estadoBadge.className = 'estado-entregado';
            estadoBadge.textContent = 'ENTREGADO';
            fechaCell.textContent = new Date().toLocaleDateString();
            labelSpan.textContent = 'Sí';
        } else {
            fila.className = 'producto-row-no-entregado';
            fila.setAttribute('data-estado', 'no-entregado');
            estadoBadge.className = 'estado-no-entregado';
            estadoBadge.textContent = 'NO ENTREGADO';
            fechaCell.textContent = '-';
            labelSpan.textContent = 'No';
        }
        
        mostrarMensaje('Estado actualizado correctamente', 'success');

    } catch (error) {
        mostrarMensaje('Error al actualizar: ' + error.message, 'error');
        // Revertir checkbox
        checkbox.checked = !entregado;
    }
}

// Actualizar observaciones directamente
async function actualizarObservacionesDirecto(id_asignacion, id_producto, observaciones) {
    try {
        const response = await fetch('api_actualizar_entrega.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                id_asignacion: id_asignacion,
                id_producto: id_producto,
                entregado: null, // No cambiar el estado
                cantidad_entregada: 0,
                observaciones: observaciones
            })
        });

        const data = await response.json();
        
        if (!data.ok) {
            throw new Error(data.msg || 'Error al actualizar observaciones');
        }

    } catch (error) {
        mostrarMensaje('Error al actualizar observaciones: ' + error.message, 'error');
    }
}

// Mostrar vista de asignaciones
function mostrarAsignaciones() {
    document.getElementById('productos-completos').style.display = 'none';
    document.getElementById('asignaciones-resumen').style.display = 'block';
    document.getElementById('filtro-estado').style.display = 'none';
    
    // Actualizar botones
    document.getElementById('btn-vista-asignaciones').className = 'btn btn-primary';
    document.getElementById('btn-vista-productos').className = 'btn btn-outline-primary';
}

async function eliminarAsignacion(id_asignacion, btn) {
    const fila = btn.closest('tr');
    if (!fila) return;

    if (!confirm(`¿Está seguro de que desea eliminar la asignación #${id_asignacion}? Esta acción es permanente y no se puede deshacer.`)) {
        return;
    }

    try {
        const response = await fetch('api_delete_asignacion.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id_asignacion: id_asignacion })
        });

        const data = await response.json();

        if (!response.ok) {
            throw new Error(data.msg || 'Error desconocido en el servidor');
        }

        fila.style.transition = 'all 0.3s ease';
        fila.style.opacity = '0';
        fila.style.transform = 'translateX(-30px)';
        setTimeout(() => {
            fila.remove();
            // Aquí podrías recalcular las estadísticas si fuera necesario
        }, 300);

        mostrarMensaje('Asignación eliminada correctamente.', 'success');

    } catch (error) {
        mostrarMensaje(`Error al eliminar: ${error.message}`, 'error');
    }
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>