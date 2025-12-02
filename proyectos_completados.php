<?php
session_start();
if (!isset($_SESSION['usuario'])) { header('Location: login.php'); exit; }

require_once __DIR__ . '/includes/db.php';

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$usuario_actual = $_SESSION['usuario'] ?? '';
$rol_actual = $_SESSION['id_rol'] ?? 2;
$es_admin = ($rol_actual == 1);

$pageTitle = 'Proyectos Completados';
require_once __DIR__ . '/includes/header.php';
?>

<style>
.completado-card {
    background: white;
    border-radius: 12px;
    padding: 1.5rem;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    border-left: 4px solid #10b981;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
    margin-bottom: 1rem;
}

.completado-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 16px rgba(0,0,0,0.15);
}

.completado-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 1rem;
}

.completado-titulo {
    margin: 0;
    font-size: 1.25rem;
    font-weight: 600;
    color: #1f2937;
    flex: 1;
    margin-right: 1rem;
}

.completado-fecha {
    font-size: 0.875rem;
    color: #6b7280;
    background: #f3f4f6;
    padding: 0.5rem 1rem;
    border-radius: 9999px;
    white-space: nowrap;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.completado-info {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-bottom: 1rem;
}

.completado-detalle {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.875rem;
    color: #6b7280;
}

.completado-stats {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding-top: 1rem;
    border-top: 1px solid #e5e7eb;
}

.completado-progreso {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: #10b981;
    font-weight: 600;
    font-size: 0.875rem;
}

.completado-acciones {
    display: flex;
    gap: 0.5rem;
}

.stats-panel {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
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

.filtros-panel {
    background: white;
    padding: 1rem;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    margin-bottom: 1.5rem;
}

.filtros-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    align-items: end;
}

.form-group {
    display: flex;
    flex-direction: column;
}

.form-group label {
    font-size: 0.875rem;
    font-weight: 600;
    color: #374151;
    margin-bottom: 0.25rem;
}

.nice-input {
    padding: 0.5rem;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    font-size: 0.875rem;
}

.btn-filtrar {
    background: #3b82f6;
    color: white;
    border: none;
    padding: 0.5rem 1rem;
    border-radius: 6px;
    cursor: pointer;
    font-weight: 600;
}

.btn-filtrar:hover {
    background: #2563eb;
}

.empty-state {
    text-align: center;
    padding: 3rem;
    color: #6b7280;
}

.empty-state i {
    font-size: 3rem;
    margin-bottom: 1rem;
    color: #d1d5db;
}

.tabs-nav {
    display: flex;
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    overflow: hidden;
}

.tab-btn {
    flex: 1;
    padding: 1rem 1.5rem;
    border: none;
    background: transparent;
    color: #6b7280;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
}

.tab-btn:hover {
    background: #f3f4f6;
    color: #374151;
}

.tab-btn.active {
    background: #3b82f6;
    color: white;
}

.tab-content {
    display: none;
}

.tab-content.active {
    display: block;
}

.btn-export {
    background: #059669;
    color: white;
    border: none;
    padding: 0.5rem 1rem;
    border-radius: 6px;
    cursor: pointer;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    transition: background 0.2s ease;
}

.btn-export:hover {
    background: #047857;
}

.producto-card {
    background: white;
    border-radius: 8px;
    padding: 1rem;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    margin-bottom: 0.5rem;
    border-left: 4px solid #d1d5db;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.producto-card:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.15);
}

.producto-card.entregado {
    border-left-color: #10b981;
    background: #f0fdf4;
}

.producto-card.no-entregado {
    border-left-color: #ef4444;
    background: #fef2f2;
}

.producto-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 0.5rem;
}

.producto-nombre {
    font-weight: 600;
    color: #1f2937;
    margin: 0;
}

.producto-estado {
    padding: 0.25rem 0.75rem;
    border-radius: 9999px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
}

.estado-entregado {
    background: #dcfce7;
    color: #166534;
}

.estado-no-entregado {
    background: #fee2e2;
    color: #991b1b;
}

.producto-detalles {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 0.5rem;
    font-size: 0.875rem;
    color: #6b7280;
}

.producto-detalle {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

@media (max-width: 768px) {
    .completado-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.5rem;
    }
    
    .completado-titulo {
        margin-right: 0;
    }
    
    .completado-info {
        grid-template-columns: 1fr;
    }
    
    .completado-stats {
        flex-direction: column;
        align-items: flex-start;
        gap: 1rem;
    }
    
    .filtros-grid {
        grid-template-columns: 1fr;
    }
    
    .tabs-nav {
        flex-direction: column;
    }
    
    .producto-detalles {
        grid-template-columns: 1fr;
    }
}
</style>

<div class="dashboard-grid">
    <?php require_once __DIR__ . '/includes/sidebar.php'; ?>

    <main class="dashboard-main">
        <div class="dashboard-header">
            <h1 class="dashboard-welcome">
                <?= $es_admin ? 'Todos los Proyectos Completados' : 'Mis Proyectos Completados' ?>
            </h1>
        </div>

        <!-- Panel de Estadísticas -->
        <div class="stats-panel" id="stats-panel">
            <h3>🎉 Resumen de Proyectos Completados</h3>
            <div class="stats-grid-mini" id="stats-grid">
                <div class="stat-mini">
                    <div class="stat-mini-value" id="total-completados">-</div>
                    <div class="stat-mini-label">Proyectos Completados</div>
                </div>
                <div class="stat-mini">
                    <div class="stat-mini-value" id="total-beneficiarios">-</div>
                    <div class="stat-mini-label">Beneficiarios Atendidos</div>
                </div>
                <div class="stat-mini">
                    <div class="stat-mini-value" id="bloques-completados">-</div>
                    <div class="stat-mini-label">Bloques con Entregas</div>
                </div>
                <div class="stat-mini">
                    <div class="stat-mini-value">100%</div>
                    <div class="stat-mini-label">Tasa de Entrega</div>
                </div>
            </div>
        </div>

        <!-- Filtros -->
        <div class="filtros-panel">
            <div class="filtros-grid">
                <div class="form-group">
                    <label for="filtro-bloque">Filtrar por Bloque</label>
                    <select id="filtro-bloque" class="nice-input">
                        <option value="">Todos los bloques</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="filtro-fecha">Completados desde</label>
                    <input type="date" id="filtro-fecha" class="nice-input">
                </div>
                <div class="form-group">
                    <label for="filtro-proyecto">Buscar proyecto</label>
                    <input type="text" id="filtro-proyecto" class="nice-input" placeholder="Nombre del proyecto...">
                </div>
                <div class="form-group">
                    <label>&nbsp;</label>
                    <button class="btn-filtrar" onclick="aplicarFiltros()">
                        <i class="fa-solid fa-filter"></i> Filtrar
                    </button>
                </div>
            </div>
        </div>

        <!-- Pestañas de navegación -->
        <div class="tabs-container" style="margin-bottom: 1.5rem;">
            <div class="tabs-nav" style="display: flex; background: white; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); overflow: hidden;">
                <button class="tab-btn active" onclick="switchTab('proyectos')" id="tab-proyectos">
                    <i class="fa-solid fa-list-check"></i> Proyectos Completados
                </button>
                <button class="tab-btn" onclick="switchTab('productos')" id="tab-productos">
                    <i class="fa-solid fa-boxes-stacked"></i> Productos Entregados
                </button>
            </div>
        </div>

        <!-- Contenido de Proyectos Completados -->
        <div id="tab-content-proyectos" class="tab-content active">
            <div id="proyectos-container">
                <div class="text-center" style="padding: 2rem;">
                    <i class="fa-solid fa-spinner fa-spin" style="font-size: 2rem; color: #6b7280;"></i>
                    <p style="margin-top: 1rem; color: #6b7280;">Cargando proyectos completados...</p>
                </div>
            </div>
        </div>

        <!-- Contenido de Productos -->
        <div id="tab-content-productos" class="tab-content" style="display: none;">
            <!-- Filtros para productos -->
            <div class="filtros-panel">
                <div class="filtros-grid">
                    <div class="form-group">
                        <label for="filtro-producto-bloque">Filtrar por Bloque</label>
                        <select id="filtro-producto-bloque" class="nice-input">
                            <option value="">Todos los bloques</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="filtro-producto-comunidad">Filtrar por Comunidad</label>
                        <select id="filtro-producto-comunidad" class="nice-input">
                            <option value="">Todas las comunidades</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="filtro-producto-estado">Estado de Entrega</label>
                        <select id="filtro-producto-estado" class="nice-input">
                            <option value="">Todos</option>
                            <option value="entregado">Entregados</option>
                            <option value="no_entregado">No Entregados</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>&nbsp;</label>
                        <button class="btn-filtrar" onclick="aplicarFiltrosProductos()">
                            <i class="fa-solid fa-filter"></i> Filtrar
                        </button>
                    </div>
                </div>
                <div style="margin-top: 1rem; display: flex; gap: 0.5rem;">
                    <button class="btn-export" onclick="exportarProductos()">
                        <i class="fa-solid fa-file-excel"></i> Exportar a Excel
                    </button>
                </div>
            </div>

            <!-- Estadísticas de productos -->
            <div class="stats-panel" style="background: linear-gradient(135deg, #059669 0%, #047857 100%);">
                <h3>📦 Resumen de Productos</h3>
                <div class="stats-grid-mini" id="productos-stats-grid">
                    <div class="stat-mini">
                        <div class="stat-mini-value" id="total-productos-asignados">-</div>
                        <div class="stat-mini-label">Total Productos</div>
                    </div>
                    <div class="stat-mini">
                        <div class="stat-mini-value" id="total-productos-entregados">-</div>
                        <div class="stat-mini-label">Entregados</div>
                    </div>
                    <div class="stat-mini">
                        <div class="stat-mini-value" id="total-productos-pendientes">-</div>
                        <div class="stat-mini-label">Pendientes</div>
                    </div>
                    <div class="stat-mini">
                        <div class="stat-mini-value" id="porcentaje-entrega-productos">-%</div>
                        <div class="stat-mini-label">% Entregado</div>
                    </div>
                </div>
            </div>

            <!-- Lista de productos -->
            <div id="productos-container">
                <div class="text-center" style="padding: 2rem;">
                    <i class="fa-solid fa-spinner fa-spin" style="font-size: 2rem; color: #6b7280;"></i>
                    <p style="margin-top: 1rem; color: #6b7280;">Cargando productos...</p>
                </div>
            </div>
        </div>

            </main>
</div>

<script>
let proyectosData = [];
let proyectosFiltrados = [];
let productosData = [];
let productosFiltrados = [];

document.addEventListener('DOMContentLoaded', function() {
    cargarProyectosCompletados();
});

// Funciones para manejo de pestañas
function switchTab(tabName) {
    // Ocultar todos los contenidos
    document.querySelectorAll('.tab-content').forEach(content => {
        content.classList.remove('active');
        content.style.display = 'none';
    });
    
    // Desactivar todos los botones
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    
    // Mostrar contenido activo
    const activeContent = document.getElementById(`tab-content-${tabName}`);
    const activeBtn = document.getElementById(`tab-${tabName}`);
    
    if (activeContent && activeBtn) {
        activeContent.classList.add('active');
        activeContent.style.display = 'block';
        activeBtn.classList.add('active');
    }
    
    // Cargar datos específicos según la pestaña
    if (tabName === 'productos' && productosData.length === 0) {
        cargarProductosEntregas();
    }
}

async function cargarProyectosCompletados() {
    try {
        const response = await fetch('api_proyectos_completados.php');
        const data = await response.json();
        
        if (!data.ok) {
            throw new Error(data.msg || 'Error al cargar proyectos');
        }
        
        proyectosData = data.proyectos;
        proyectosFiltrados = [...proyectosData];
        
        // Actualizar estadísticas
        actualizarEstadisticas(data.estadisticas);
        
        // Poblar filtros
        poblarFiltros();
        
        // Mostrar proyectos
        mostrarProyectos();
        
    } catch (error) {
        console.error('Error:', error);
        document.getElementById('proyectos-container').innerHTML = `
            <div class="empty-state">
                <i class="fa-solid fa-exclamation-triangle"></i>
                <h3>Error al cargar proyectos</h3>
                <p>${error.message}</p>
            </div>
        `;
    }
}

function actualizarEstadisticas(stats) {
    document.getElementById('total-completados').textContent = stats.total_completados;
    document.getElementById('total-beneficiarios').textContent = stats.total_beneficiarios.toLocaleString();
    document.getElementById('bloques-completados').textContent = stats.bloques_completados;
}

function poblarFiltros() {
    // Poblar filtro de bloques
    const bloques = [...new Set(proyectosData.map(p => p.bloque))].sort();
    const selectBloque = document.getElementById('filtro-bloque');
    
    bloques.forEach(bloque => {
        const option = document.createElement('option');
        option.value = bloque;
        option.textContent = bloque;
        selectBloque.appendChild(option);
    });
}

function aplicarFiltros() {
    const filtroBloque = document.getElementById('filtro-bloque').value;
    const filtroFecha = document.getElementById('filtro-fecha').value;
    const filtroProyecto = document.getElementById('filtro-proyecto').value.toLowerCase();
    
    proyectosFiltrados = proyectosData.filter(proyecto => {
        // Filtro por bloque
        if (filtroBloque && proyecto.bloque !== filtroBloque) {
            return false;
        }
        
        // Filtro por fecha
        if (filtroFecha) {
            const fechaCompletado = new Date(proyecto.fecha_completado);
            const fechaFiltro = new Date(filtroFecha);
            if (fechaCompletado < fechaFiltro) {
                return false;
            }
        }
        
        // Filtro por nombre de proyecto
        if (filtroProyecto && !proyecto.proyecto_nombre.toLowerCase().includes(filtroProyecto)) {
            return false;
        }
        
        return true;
    });
    
    mostrarProyectos();
}

function mostrarProyectos() {
    const container = document.getElementById('proyectos-container');
    
    if (proyectosFiltrados.length === 0) {
        container.innerHTML = `
            <div class="empty-state">
                <i class="fa-solid fa-search"></i>
                <h3>No se encontraron proyectos</h3>
                <p>No hay proyectos completados que coincidan con los filtros aplicados.</p>
            </div>
        `;
        return;
    }
    
    let html = '';
    
    proyectosFiltrados.forEach(proyecto => {
        const fechaCompletado = new Date(proyecto.fecha_completado);
        const fechaFormateada = fechaCompletado.toLocaleDateString('es-ES', {
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        });
        
        html += `
            <div class="completado-card">
                <div class="completado-header">
                    <h3 class="completado-titulo">${proyecto.proyecto_nombre}</h3>
                    <div class="completado-fecha">
                        <i class="fa-solid fa-calendar-check"></i>
                        ${fechaFormateada}
                    </div>
                </div>
                
                <div class="completado-info">
                    <div class="completado-detalle">
                        <i class="fa-solid fa-map-marker-alt"></i>
                        <strong>Comunidad:</strong> ${proyecto.comunidad_nombre}
                    </div>
                    <div class="completado-detalle">
                        <i class="fa-solid fa-industry"></i>
                        <strong>Bloque:</strong> ${proyecto.bloque}
                    </div>
                    <div class="completado-detalle">
                        <i class="fa-solid fa-users"></i>
                        <strong>Beneficiarios:</strong> ${proyecto.beneficiarios}
                    </div>
                    <div class="completado-detalle">
                        <i class="fa-solid fa-user-tie"></i>
                        <strong>Creado por:</strong> ${proyecto.creador_nombre || proyecto.creado_por}
                    </div>
                    ${proyecto.tecnico_nombre ? `
                    <div class="completado-detalle">
                        <i class="fa-solid fa-hard-hat"></i>
                        <strong>Técnico:</strong> ${proyecto.tecnico_nombre}
                    </div>
                    ` : ''}
                </div>
                
                <div class="completado-stats">
                    <div class="completado-progreso">
                        <i class="fa-solid fa-check-circle"></i>
                        ${proyecto.productos_entregados}/${proyecto.total_productos} productos entregados (100%)
                    </div>
                    <div class="completado-acciones">
                        <button class="btn-icon btn-icon-primary" 
                                onclick="verDetallesEntrega(${proyecto.id_asignacion})"
                                title="Ver detalles de entrega">
                            <i class="fa-solid fa-eye"></i>
                        </button>
                    </div>
                </div>
            </div>
        `;
    });
    
    container.innerHTML = html;
}

function verDetallesEntrega(id_asignacion) {
    // Redirigir a la página de gestión de entregas con el proyecto específico
    window.open(`gestionar_entregas.php#asignacion-${id_asignacion}`, '_blank');
}

// Aplicar filtros en tiempo real para el campo de búsqueda
document.getElementById('filtro-proyecto').addEventListener('input', function() {
    clearTimeout(this.searchTimeout);
    this.searchTimeout = setTimeout(() => {
        aplicarFiltros();
    }, 300);
});

// Funciones para productos
async function cargarProductosEntregas() {
    try {
        const response = await fetch('api_productos_entregas.php');
        const data = await response.json();
        
        if (!data.ok) {
            throw new Error(data.msg || 'Error al cargar productos');
        }
        
        productosData = data.productos;
        productosFiltrados = [...productosData];
        
        // Actualizar estadísticas de productos
        actualizarEstadisticasProductos(data.estadisticas);
        
        // Poblar filtros de productos
        poblarFiltrosProductos();
        
        // Mostrar productos
        mostrarProductos();
        
    } catch (error) {
        console.error('Error:', error);
        document.getElementById('productos-container').innerHTML = `
            <div class="empty-state">
                <i class="fa-solid fa-exclamation-triangle"></i>
                <h3>Error al cargar productos</h3>
                <p>${error.message}</p>
            </div>
        `;
    }
}

function actualizarEstadisticasProductos(stats) {
    document.getElementById('total-productos-asignados').textContent = stats.total_productos;
    document.getElementById('total-productos-entregados').textContent = stats.productos_entregados;
    document.getElementById('total-productos-pendientes').textContent = stats.productos_pendientes;
    document.getElementById('porcentaje-entrega-productos').textContent = Math.round(stats.porcentaje_entrega) + '%';
}

function poblarFiltrosProductos() {
    // Poblar filtro de bloques
    const bloques = [...new Set(productosData.map(p => p.bloque))].sort();
    const selectBloque = document.getElementById('filtro-producto-bloque');
    selectBloque.innerHTML = '<option value="">Todos los bloques</option>';
    
    bloques.forEach(bloque => {
        const option = document.createElement('option');
        option.value = bloque;
        option.textContent = bloque;
        selectBloque.appendChild(option);
    });
    
    // Poblar filtro de comunidades
    const comunidades = [...new Set(productosData.map(p => p.comunidad_nombre))].sort();
    const selectComunidad = document.getElementById('filtro-producto-comunidad');
    selectComunidad.innerHTML = '<option value="">Todas las comunidades</option>';
    
    comunidades.forEach(comunidad => {
        const option = document.createElement('option');
        option.value = comunidad;
        option.textContent = comunidad;
        selectComunidad.appendChild(option);
    });
}

function aplicarFiltrosProductos() {
    const filtroBloque = document.getElementById('filtro-producto-bloque').value;
    const filtroComunidad = document.getElementById('filtro-producto-comunidad').value;
    const filtroEstado = document.getElementById('filtro-producto-estado').value;
    
    productosFiltrados = productosData.filter(producto => {
        // Filtro por bloque
        if (filtroBloque && producto.bloque !== filtroBloque) {
            return false;
        }
        
        // Filtro por comunidad
        if (filtroComunidad && producto.comunidad_nombre !== filtroComunidad) {
            return false;
        }
        
        // Filtro por estado - Corregir la lógica de comparación
        if (filtroEstado) {
            const entregado = parseInt(producto.entregado) === 1;
            
            if (filtroEstado === 'entregado' && !entregado) {
                return false;
            }
            if (filtroEstado === 'no_entregado' && entregado) {
                return false;
            }
        }
        
        return true;
    });
    
    mostrarProductos();
}

function mostrarProductos() {
    const container = document.getElementById('productos-container');
    
    if (productosFiltrados.length === 0) {
        container.innerHTML = `
            <div class="empty-state">
                <i class="fa-solid fa-search"></i>
                <h3>No se encontraron productos</h3>
                <p>No hay productos que coincidan con los filtros aplicados.</p>
            </div>
        `;
        return;
    }
    
    let html = '';
    
    productosFiltrados.forEach(producto => {
        const entregado = producto.entregado == 1;
        const fechaEntrega = producto.fecha_entrega ? new Date(producto.fecha_entrega).toLocaleDateString('es-ES') : 'No entregado';
        
        html += `
            <div class="producto-card ${entregado ? 'entregado' : 'no-entregado'}">
                <div class="producto-header">
                    <h4 class="producto-nombre">${producto.producto_nombre}</h4>
                    <span class="producto-estado ${entregado ? 'estado-entregado' : 'estado-no-entregado'}">
                        ${entregado ? 'Entregado' : 'Pendiente'}
                    </span>
                </div>
                
                <div class="producto-detalles">
                    <div class="producto-detalle">
                        <i class="fa-solid fa-project-diagram"></i>
                        <strong>Proyecto:</strong> ${producto.proyecto_nombre}
                    </div>
                    <div class="producto-detalle">
                        <i class="fa-solid fa-map-marker-alt"></i>
                        <strong>Comunidad:</strong> ${producto.comunidad_nombre}
                    </div>
                    <div class="producto-detalle">
                        <i class="fa-solid fa-industry"></i>
                        <strong>Bloque:</strong> ${producto.bloque}
                    </div>
                    <div class="producto-detalle">
                        <i class="fa-solid fa-boxes"></i>
                        <strong>Cantidad:</strong> ${producto.cantidad_asignada} ${producto.unidad || ''}
                    </div>
                    ${entregado ? `
                    <div class="producto-detalle">
                        <i class="fa-solid fa-calendar-check"></i>
                        <strong>Fecha entrega:</strong> ${fechaEntrega}
                    </div>
                    <div class="producto-detalle">
                        <i class="fa-solid fa-check-circle"></i>
                        <strong>Cantidad entregada:</strong> ${producto.cantidad_entregada} ${producto.unidad || ''}
                    </div>
                    ` : ''}
                    ${producto.observaciones ? `
                    <div class="producto-detalle" style="grid-column: 1 / -1;">
                        <i class="fa-solid fa-comment"></i>
                        <strong>Observaciones:</strong> ${producto.observaciones}
                    </div>
                    ` : ''}
                    ${producto.entregado_por ? `
                    <div class="producto-detalle">
                        <i class="fa-solid fa-user"></i>
                        <strong>Entregado por:</strong> ${producto.entregado_por}
                    </div>
                    ` : ''}
                </div>
            </div>
        `;
    });
    
    container.innerHTML = html;
}

function exportarProductos() {
    if (productosFiltrados.length === 0) {
        alert('No hay productos para exportar');
        return;
    }
    
    // Preparar datos para exportación
    const datosExport = productosFiltrados.map(producto => {
        const entregado = producto.entregado == 1;
        return {
            'Proyecto': producto.proyecto_nombre,
            'Producto': producto.producto_nombre,
            'Comunidad': producto.comunidad_nombre,
            'Bloque': producto.bloque,
            'Cantidad Asignada': producto.cantidad_asignada + ' ' + (producto.unidad || ''),
            'Estado': entregado ? 'Entregado' : 'Pendiente',
            'Cantidad Entregada': entregado ? (producto.cantidad_entregada + ' ' + (producto.unidad || '')) : 'N/A',
            'Fecha Entrega': producto.fecha_entrega ? new Date(producto.fecha_entrega).toLocaleDateString('es-ES') : 'N/A',
            'Entregado Por': producto.entregado_por || 'N/A',
            'Observaciones': producto.observaciones || 'N/A'
        };
    });
    
    // Crear formulario para enviar datos
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'exportar_excel.php';
    form.style.display = 'none';
    
    // Agregar campos
    const reportField = document.createElement('input');
    reportField.name = 'report';
    reportField.value = 'Reporte de Productos - Proyectos Completados';
    form.appendChild(reportField);
    
    const filenameField = document.createElement('input');
    filenameField.name = 'filename';
    filenameField.value = 'productos_entregas_' + new Date().toISOString().split('T')[0] + '.xls';
    form.appendChild(filenameField);
    
    const dataField = document.createElement('input');
    dataField.name = 'data';
    dataField.value = JSON.stringify(datosExport);
    form.appendChild(dataField);
    
    document.body.appendChild(form);
    form.submit();
    document.body.removeChild(form);
}

</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>