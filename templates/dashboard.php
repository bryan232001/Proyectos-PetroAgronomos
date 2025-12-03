<?php
// templates/dashboard.php
?>
<div class="mobile-overlay" id="mobile-overlay"></div>

<!-- Barra superior móvil -->
<div id="mobile-top-bar" style="display: none;">
    <button class="mobile-menu-btn" onclick="toggleSidebar()">
        <i class="fas fa-bars"></i>
    </button>
    <h1 class="dashboard-welcome">Panel <span>Comunitario</span></h1>
</div>

<div class="dashboard-grid">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="dashboard-main">
        <h2 class="section-title">Estadísticas Generales</h2>
        <div class="stats-grid">
            <!-- Proyectos Creados -->
            <div class="stat-card">
                <div class="stat-icon-wrapper" style="background-color: #e0f2fe;">
                    <i class="fa-solid fa-folder-open" style="color: #0ea5e9;"></i>
                </div>
                <div class="stat-info">
                    <h3 class="stat-title"><?= $es_admin ? 'Total Proyectos' : 'Mis Proyectos' ?></h3>
                    <p class="stat-value"><?= number_format($totalProyectos, 0, ',', '.') ?></p>
                    <p class="stat-subtitle"><?= $es_admin ? 'Todos los bloques' : 'Creados por mí' ?></p>
                </div>
            </div>

            <!-- Beneficiarios de Proyectos Completados -->
            <div class="stat-card">
                <div class="stat-icon-wrapper" style="background-color: #dcfce7;">
                    <i class="fa-solid fa-users" style="color: #22c55e;"></i>
                </div>
                <div class="stat-info">
                    <h3 class="stat-title">Beneficiarios Atendidos</h3>
                    <p class="stat-value"><?= number_format($totalBeneficiariosCompletados, 0, ',', '.') ?></p>
                    <p class="stat-subtitle">Proyectos completados</p>
                </div>
            </div>

            <!-- Inversión Completada -->
            <div class="stat-card">
                <div class="stat-icon-wrapper" style="background-color: #dcfce7;">
                    <i class="fa-solid fa-sack-dollar" style="color: #22c55e;"></i>
                </div>
                <div class="stat-info">
                    <h3 class="stat-title">Inversión Entregada</h3>
                    <p class="stat-value">$ <?= number_format($inversionCompletados, 2, ',', '.') ?></p>
                    <p class="stat-subtitle">Proyectos completados</p>
                </div>
            </div>

            <!-- Inversión Pendiente -->
            <div class="stat-card">
                <div class="stat-icon-wrapper" style="background-color: #fef3c7;">
                    <i class="fa-solid fa-clock" style="color: #f59e0b;"></i>
                </div>
                <div class="stat-info">
                    <h3 class="stat-title">Inversión Pendiente</h3>
                    <p class="stat-value">$ <?= number_format($inversionPendiente, 2, ',', '.') ?></p>
                    <p class="stat-subtitle">Por entregar</p>
                </div>
            </div>

            <!-- Inversión Total Esperada -->
            <div class="stat-card">
                <div class="stat-icon-wrapper" style="background-color: #e0f2fe;">
                    <i class="fa-solid fa-chart-line" style="color: #0ea5e9;"></i>
                </div>
                <div class="stat-info">
                    <h3 class="stat-title">Inversión Total</h3>
                    <p class="stat-value">$ <?= number_format($inversionEsperada, 2, ',', '.') ?></p>
                    <p class="stat-subtitle">Esperada total</p>
                </div>
            </div>

            <!-- Porcentaje de Proyectos Entregados -->
            <div class="stat-card">
                <div class="stat-icon-wrapper" style="background-color: #dcfce7;">
                    <i class="fa-solid fa-percentage" style="color: #22c55e;"></i>
                </div>
                <div class="stat-info">
                    <h3 class="stat-title">% Proyectos Entregados</h3>
                    <p class="stat-value"><?= number_format($porcentajeEntregados, 1, ',', '.') ?>%</p>
                    <p class="stat-subtitle"><?= $proyectosCompletados ?> de <?= $totalAsignaciones ?> completados</p>
                </div>
            </div>

            <!-- Proyectos Completados -->
            <div class="stat-card">
                <div class="stat-icon-wrapper" style="background-color: #dcfce7;">
                    <i class="fa-solid fa-check-circle" style="color: #22c55e;"></i>
                </div>
                <div class="stat-info">
                    <h3 class="stat-title">Proyectos Completados</h3>
                    <p class="stat-value"><?= number_format($proyectosCompletados, 0, ',', '.') ?></p>
                    <p class="stat-subtitle">Totalmente entregados</p>
                </div>
            </div>

            <!-- Proyectos en Progreso -->
            <div class="stat-card">
                <div class="stat-icon-wrapper" style="background-color: #fef3c7;">
                    <i class="fa-solid fa-spinner" style="color: #f59e0b;"></i>
                </div>
                <div class="stat-info">
                    <h3 class="stat-title">Proyectos en Progreso</h3>
                    <p class="stat-value"><?= number_format($proyectosEnProgreso, 0, ',', '.') ?></p>
                    <p class="stat-subtitle">En ejecución</p>
                </div>
            </div>
        </div>

        <!-- Resumen de Inversiones -->
        <h2 class="section-title">Resumen de Inversiones</h2>
        <div class="inversion-summary">
            <div class="inversion-card completada">
                <div class="inversion-header">
                    <h3>Inversión Entregada</h3>
                    <div class="inversion-icon">
                        <i class="fa-solid fa-check-circle"></i>
                    </div>
                </div>
                <div class="inversion-amount">$ <?= number_format($inversionCompletados, 2, ',', '.') ?></div>
                <div class="inversion-progress">
                    <div class="progress-bar">
                        <div class="progress-fill completada" style="width: <?= $inversionEsperada > 0 ? ($inversionCompletados / $inversionEsperada) * 100 : 0 ?>%"></div>
                    </div>
                    <span class="progress-text"><?= $inversionEsperada > 0 ? number_format(($inversionCompletados / $inversionEsperada) * 100, 1) : 0 ?>% del total</span>
                </div>
            </div>

            <div class="inversion-card pendiente">
                <div class="inversion-header">
                    <h3>Inversión Pendiente</h3>
                    <div class="inversion-icon">
                        <i class="fa-solid fa-clock"></i>
                    </div>
                </div>
                <div class="inversion-amount">$ <?= number_format($inversionPendiente, 2, ',', '.') ?></div>
                <div class="inversion-progress">
                    <div class="progress-bar">
                        <div class="progress-fill pendiente" style="width: <?= $inversionEsperada > 0 ? ($inversionPendiente / $inversionEsperada) * 100 : 0 ?>%"></div>
                    </div>
                    <span class="progress-text"><?= $inversionEsperada > 0 ? number_format(($inversionPendiente / $inversionEsperada) * 100, 1) : 0 ?>% del total</span>
                </div>
            </div>

            <div class="inversion-card total">
                <div class="inversion-header">
                    <h3>Inversión Total Esperada</h3>
                    <div class="inversion-icon">
                        <i class="fa-solid fa-chart-line"></i>
                    </div>
                </div>
                <div class="inversion-amount">$ <?= number_format($inversionEsperada, 2, ',', '.') ?></div>
                <div class="inversion-details">
                    <div class="detail-item">
                        <span class="detail-label">Proyectos completados:</span>
                        <span class="detail-value"><?= $proyectosCompletados ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Proyectos en progreso:</span>
                        <span class="detail-value"><?= $proyectosEnProgreso ?></span>
                    </div>
                </div>
            </div>
        </div>

                <h2 class="section-title">Análisis Gráfico</h2>
        <div class="charts-grid">
            <div class="chart-container">
                <h3 class="chart-title">Distribución de Proyectos por Estado</h3>
                <canvas id="proyectosPorEstadoChart"></canvas>
            </div>
        </div>

        <?php if (!empty($proyectosCompletadosRecientes)): ?>
        <h2 class="section-title">Proyectos Completados Recientemente</h2>
        <div class="completados-grid">
            <?php foreach ($proyectosCompletadosRecientes as $completado): ?>
            <div class="completado-card">
                <div class="completado-header">
                    <h4 class="completado-titulo"><?= htmlspecialchars($completado['proyecto_nombre']) ?></h4>
                    <span class="completado-fecha"><?= date('d/m/Y', strtotime($completado['fecha_completado'])) ?></span>
                </div>
                <div class="completado-info">
                    <div class="completado-detalle">
                        <i class="fa-solid fa-map-marker-alt"></i>
                        <?= htmlspecialchars($completado['comunidad_nombre']) ?> - <?= htmlspecialchars($completado['bloque']) ?>
                    </div>
                    <div class="completado-progreso">
                        <i class="fa-solid fa-check-circle" style="color: #10b981;"></i>
                        100% Completado
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </main>
</div>

<style>
/* Actualizar grid de estadísticas para acomodar más tarjetas */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.stat-card {
    background: white;
    border-radius: 12px;
    padding: 1.5rem;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    transition: transform 0.2s ease, box-shadow 0.2s ease;
    border-left: 4px solid transparent;
}

.stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 16px rgba(0,0,0,0.15);
}

.stat-info {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.stat-title {
    font-size: 0.875rem;
    font-weight: 600;
    color: #6b7280;
    margin: 0;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.stat-value {
    font-size: 2rem;
    font-weight: 700;
    color: #1f2937;
    margin: 0;
    line-height: 1;
}

.stat-subtitle {
    font-size: 0.75rem;
    color: #9ca3af;
    margin: 0;
    font-weight: 500;
}

.stat-icon-wrapper {
    width: 50px;
    height: 50px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 1rem;
}

.stat-icon-wrapper i {
    font-size: 1.5rem;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .stats-grid {
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 1rem;
    }
    
    .stat-card {
        padding: 1rem;
    }
    
    .stat-value {
        font-size: 1.5rem;
    }
}

@media (max-width: 480px) {
    .stats-grid {
        grid-template-columns: 1fr;
    }
}

/* Estilos para resumen de inversiones */
.inversion-summary {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.inversion-card {
    background: white;
    border-radius: 16px;
    padding: 2rem;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    transition: transform 0.2s ease, box-shadow 0.2s ease;
    border-left: 6px solid;
}

.inversion-card.completada {
    border-left-color: #10b981;
}

.inversion-card.pendiente {
    border-left-color: #f59e0b;
}

.inversion-card.total {
    border-left-color: #3b82f6;
}

.inversion-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 24px rgba(0,0,0,0.15);
}

.inversion-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
}

.inversion-header h3 {
    margin: 0;
    font-size: 1.1rem;
    font-weight: 600;
    color: #374151;
}

.inversion-icon {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
}

.inversion-card.completada .inversion-icon {
    background-color: #d1fae5;
    color: #10b981;
}

.inversion-card.pendiente .inversion-icon {
    background-color: #fef3c7;
    color: #f59e0b;
}

.inversion-card.total .inversion-icon {
    background-color: #dbeafe;
    color: #3b82f6;
}

.inversion-amount {
    font-size: 2.5rem;
    font-weight: 700;
    color: #1f2937;
    margin-bottom: 1rem;
    line-height: 1;
}

.inversion-progress {
    margin-bottom: 1rem;
}

.progress-bar {
    width: 100%;
    height: 8px;
    background-color: #f3f4f6;
    border-radius: 4px;
    overflow: hidden;
    margin-bottom: 0.5rem;
}

.progress-fill {
    height: 100%;
    border-radius: 4px;
    transition: width 0.3s ease;
}

.progress-fill.completada {
    background-color: #10b981;
}

.progress-fill.pendiente {
    background-color: #f59e0b;
}

.progress-text {
    font-size: 0.875rem;
    color: #6b7280;
    font-weight: 500;
}

.inversion-details {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.detail-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.5rem 0;
    border-bottom: 1px solid #f3f4f6;
}

.detail-item:last-child {
    border-bottom: none;
}

.detail-label {
    font-size: 0.875rem;
    color: #6b7280;
    font-weight: 500;
}

.detail-value {
    font-size: 0.875rem;
    color: #1f2937;
    font-weight: 600;
}

@media (max-width: 768px) {
    .inversion-summary {
        grid-template-columns: 1fr;
        gap: 1rem;
    }
    
    .inversion-card {
        padding: 1.5rem;
    }
    
    .inversion-amount {
        font-size: 2rem;
    }
}

.estados-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-bottom: 2rem;
}

.estado-card {
    display: flex;
    align-items: center;
    padding: 1rem;
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.estado-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 16px rgba(0,0,0,0.15);
}

.estado-icon {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 1rem;
    flex-shrink: 0;
}

.estado-count {
    color: white;
    font-weight: bold;
    font-size: 1.2rem;
}

.estado-info {
    flex: 1;
}

.estado-title {
    margin: 0 0 0.25rem 0;
    font-size: 1rem;
    font-weight: 600;
    color: #1f2937;
}

.estado-subtitle {
    margin: 0;
    font-size: 0.875rem;
    color: #6b7280;
}

.completados-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 1rem;
    margin-bottom: 2rem;
}

.completado-card {
    background: white;
    border-radius: 12px;
    padding: 1.5rem;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    border-left: 4px solid #10b981;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
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
    font-size: 1.1rem;
    font-weight: 600;
    color: #1f2937;
    flex: 1;
    margin-right: 1rem;
}

.completado-fecha {
    font-size: 0.875rem;
    color: #6b7280;
    background: #f3f4f6;
    padding: 0.25rem 0.75rem;
    border-radius: 9999px;
    white-space: nowrap;
}

.completado-info {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.completado-detalle,
.completado-progreso {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.875rem;
}

.completado-detalle {
    color: #6b7280;
}

.completado-progreso {
    color: #10b981;
    font-weight: 600;
}

@media (max-width: 768px) {
    .completados-grid {
        grid-template-columns: 1fr;
    }
    
    .completado-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.5rem;
    }
    
    .completado-titulo {
        margin-right: 0;
    }
}

/* Estilos para la sección de gráficos */
.charts-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.chart-container {
    background: white;
    border-radius: 12px;
    padding: 1.5rem;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    height: 450px; /* Altura fija para el contenedor */
    display: flex;
    flex-direction: column;
}

.chart-title {
    margin-top: 0;
    margin-bottom: 1.5rem;
    font-size: 1.1rem;
    font-weight: 600;
    color: #374151;
    text-align: center;
}
</style>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Helper para formatear moneda
    const formatCurrency = (value) => new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', minimumFractionDigits: 0 }).format(value);

    // Fetch data for charts
    fetch('api_get_estadisticas_dashboard.php')
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok ' + response.statusText);
            }
            return response.json();
        })
        .then(data => {
            // --- Gráfico: Proyectos por Estado ---
            if (data.proyectosPorEstado && data.proyectosPorEstado.length > 0) {
                const proyectosPorEstado = data.proyectosPorEstado;
                const labels = proyectosPorEstado.map(item => item.estado);
                const chartData = proyectosPorEstado.map(item => item.cantidad);
                const backgroundColors = proyectosPorEstado.map(item => item.color);

                const ctx1 = document.getElementById('proyectosPorEstadoChart').getContext('2d');
                new Chart(ctx1, {
                    type: 'doughnut',
                    data: {
                        labels: labels,
                        datasets: [{
                            label: 'Proyectos',
                            data: chartData,
                            backgroundColor: backgroundColors,
                            borderColor: '#fff',
                            borderWidth: 2,
                            hoverOffset: 8
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: {
                                    padding: 20,
                                    font: {
                                        size: 14
                                    }
                                }
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        let label = context.label || '';
                                        if (label) {
                                            label += ': ';
                                        }
                                        if (context.parsed !== null) {
                                            label += context.parsed;
                                        }
                                        return label + ' proyecto(s)';
                                    }
                                }
                            }
                        }
                    }
                });
            }

        })
        .catch(error => {
            console.error('Error al cargar datos para los gráficos:', error);
            document.getElementById('proyectosPorEstadoChart').parentElement.innerHTML = '<p style="text-align:center; color: #ef4444;">No se pudieron cargar los datos del gráfico.</p>';
        });
});
</script>
