<?php
session_start();
if (!isset($_SESSION['usuario'])) { header('Location: login.php'); exit; }

require_once __DIR__ . '/includes/db.php';

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

if (!isset($pdo)) { die("Error: No hay conexión a la base de datos"); }

try {
    // Obtener información del usuario actual
    $usuario_actual = $_SESSION['usuario'] ?? '';
    $rol_actual = $_SESSION['id_rol'] ?? 2; // Por defecto técnico
    $es_admin = ($rol_actual == 1);
    
    // Construir consulta según permisos
    if ($es_admin) {
        // Administrador ve todo el consolidado
        $stmt = $pdo->query("
            SELECT 
                pa.bloque AS BLOQUE,
                p.nombre AS PROYECTO,
                cp.descripcion AS DESCRIPCION,
                cp.unidad AS UNIDAD,
                cp.precio_unit AS PRECIO_UNITARIO,
                SUM(pad.cantidad) as CANTIDAD,
                cp.cpc AS CPC,
                cp.proceso AS PROCESO,
                p.creado_por AS CREADO_POR
            FROM proyecto_asignacion_detalle pad
            JOIN proyecto_asignacion pa ON pad.id_asignacion = pa.id_asignacion
            JOIN catalogo_productos cp ON pad.id_producto = cp.id_producto
            JOIN proyectos p ON pa.id_proyecto = p.id
            GROUP BY 
                pa.bloque, 
                p.nombre,
                cp.descripcion, 
                cp.unidad, 
                cp.precio_unit, 
                cp.cpc, 
                cp.proceso,
                p.creado_por
            ORDER BY pa.bloque, p.nombre, cp.descripcion
        ");
    } else {
        // Usuarios normales solo ven su propio consolidado
        $stmt = $pdo->prepare("
            SELECT 
                pa.bloque AS BLOQUE,
                p.nombre AS PROYECTO,
                cp.descripcion AS DESCRIPCION,
                cp.unidad AS UNIDAD,
                cp.precio_unit AS PRECIO_UNITARIO,
                SUM(pad.cantidad) as CANTIDAD,
                cp.cpc AS CPC,
                cp.proceso AS PROCESO,
                p.creado_por AS CREADO_POR
            FROM proyecto_asignacion_detalle pad
            JOIN proyecto_asignacion pa ON pad.id_asignacion = pa.id_asignacion
            JOIN catalogo_productos cp ON pad.id_producto = cp.id_producto
            JOIN proyectos p ON pa.id_proyecto = p.id
            WHERE p.creado_por = ?
            GROUP BY 
                pa.bloque, 
                p.nombre,
                cp.descripcion, 
                cp.unidad, 
                cp.precio_unit, 
                cp.cpc, 
                cp.proceso,
                p.creado_por
            ORDER BY pa.bloque, p.nombre, cp.descripcion
        ");
        $stmt->execute([$usuario_actual]);
    }
    
    $consolidado = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calcular totales
    $total_cantidad = array_sum(array_column($consolidado, 'CANTIDAD'));
    $total_valor = array_sum(array_map(function($item) {
        return (float)$item['PRECIO_UNITARIO'] * (float)$item['CANTIDAD'];
    }, $consolidado));
    
} catch (Exception $e) {
    error_log("Error al cargar consolidado de asignaciones: " . $e->getMessage());
    $error_msg = $e->getMessage();
    $consolidado = [];
    $total_cantidad = 0;
    $total_valor = 0;
}

// Preparar datos para exportar, incluyendo el valor total
$export_data = array_map(function($item) {
    $item['V_TOTAL'] = (float)$item['PRECIO_UNITARIO'] * (float)$item['CANTIDAD'];
    return $item;
}, $consolidado);

$pageTitle = 'Consolidado de Asignaciones';
require_once __DIR__ . '/includes/header.php';
?>

<style>
  /* Estilos para el diseño */
  .dashboard-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
    flex-wrap: wrap;
    gap: 1rem;
  }

  .header-info {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
  }

  .badge {
    padding: 0.375rem 0.75rem;
    font-size: 0.75rem;
    border-radius: 0.375rem;
    font-weight: 500;
  }

  .badge-primary { background-color: #dbeafe; color: #1e40af; }
  .badge-success { background-color: #dcfce7; color: #166534; }
  .badge-warning { background-color: #fef3c7; color: #92400e; }

  .stats-panel {
    background: linear-gradient(135deg, #3b82f6 0%, #1e40af 100%);
    color: white;
    padding: 1.5rem;
    border-radius: 12px;
    margin-bottom: 1.5rem;
    box-shadow: 0 4px 15px rgba(59, 130, 246, 0.3);
  }

  .stats-panel h3 {
    margin: 0 0 1rem 0;
    font-size: 1.25rem;
    font-weight: 600;
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

  .alert-info {
    color: #0c5460;
    background-color: #d1ecf1;
    border-color: #bee5eb;
  }

  @media (max-width: 768px) {
    .dashboard-header {
      flex-direction: column;
      align-items: flex-start;
    }
    
    .stats-grid-mini {
      grid-template-columns: repeat(2, 1fr);
    }
  }
</style>

<div class="dashboard-grid">
  <?php require_once __DIR__ . '/includes/sidebar.php'; ?>

  <main class="dashboard-main">
    <div class="dashboard-header">
        <h1 class="dashboard-welcome">
            <?= $es_admin ? 'Consolidado General (Administrador)' : 'Mi Consolidado' ?>
        </h1>
        <div class="header-info">
            <span class="badge badge-primary"><?= count($consolidado) ?> productos</span>
            <span class="badge badge-success">$<?= number_format($total_valor, 2) ?></span>
        </div>
    </div>

    <!-- Panel de Totales para Administrador -->
    <?php if ($es_admin && !empty($consolidado)): ?>
    <div class="stats-panel">
      <h3>💰 Resumen Financiero</h3>
      <div class="stats-grid-mini">
        <div class="stat-mini">
          <div class="stat-mini-value"><?= count($consolidado) ?></div>
          <div class="stat-mini-label">Productos Únicos</div>
        </div>
        <div class="stat-mini">
          <div class="stat-mini-value"><?= number_format($total_cantidad, 2) ?></div>
          <div class="stat-mini-label">Cantidad Total</div>
        </div>
        <div class="stat-mini">
          <div class="stat-mini-value">$<?= number_format($total_valor, 2) ?></div>
          <div class="stat-mini-label">Valor Total</div>
        </div>
        <div class="stat-mini">
          <div class="stat-mini-value"><?= count(array_unique(array_column($consolidado, 'BLOQUE'))) ?></div>
          <div class="stat-mini-label">Bloques Involucrados</div>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <div class="card-table">
      <div class="card-table-header">
        <h2 class="card-table-title">Productos Consolidados</h2>
        <button onclick="exportarExcel()" class="btn btn-success">Exportar a Excel</button>
      </div>
      <div class="card-table-body">
        <div class="table-wrapper">
          <?php if (isset($error_msg)): ?>
            <div class="alert alert-danger">Error: <?= h($error_msg) ?></div>
          <?php elseif (empty($consolidado)): ?>
            <div class="alert alert-info">No se han encontrado datos para el consolidado.</div>
          <?php else: ?>
            <table class="data-table" id="tablaConsolidado">
              <thead>
                <tr>
                  <th>BLOQUE</th>
                  <th>PROYECTO</th>
                  <th>DESCRIPCION</th>
                  <th>UNIDAD</th>
                  <th>PRECIO UNITARIO</th>
                  <th>CANTIDAD</th>
                  <th>V. TOTAL</th>
                  <th>CPC</th>
                  <th>PROCESO</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($consolidado as $item): ?>
                  <tr>
                    <td><?= h($item['BLOQUE']) ?></td>
                    <td><?= h($item['PROYECTO']) ?></td>
                    <td><?= h($item['DESCRIPCION']) ?></td>
                    <td><?= h($item['UNIDAD']) ?></td>
                    <td><?= h(number_format($item['PRECIO_UNITARIO'], 2)) ?></td>
                    <td><?= h(number_format($item['CANTIDAD'], 2)) ?></td>
                    <td><?= h(number_format($item['PRECIO_UNITARIO'] * $item['CANTIDAD'], 2)) ?></td>
                    <td><?= h($item['CPC']) ?></td>
                    <td><?= h($item['PROCESO']) ?></td>
                  </tr>
                <?php endforeach; ?>
                <?php if (!empty($consolidado)): ?>
                  <tr style="background-color: #2C5282; color: white; font-weight: bold; border-top: 3px solid #1A365D;">
                    <td style="text-align: center; font-weight: bold;">TOTAL</td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td style="text-align: right; font-weight: bold;"><?= number_format($total_cantidad, 2) ?></td>
                    <td style="text-align: right; font-weight: bold;"><?= number_format($total_valor, 2) ?></td>
                    <td></td>
                    <td></td>
                  </tr>
                <?php endif; ?>
              </tbody>
            </table>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </main>
</div>

<script>
const dataToExport = <?= json_encode($export_data) ?>;

function exportarExcel() {
    if (dataToExport.length === 0) {
        alert("No hay datos para exportar.");
        return;
    }

    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'exportar_excel.php';
    form.style.display = 'none';

    const reportInput = document.createElement('input');
    reportInput.type = 'hidden';
    reportInput.name = 'report';
    reportInput.value = 'consolidado_bloque';
    form.appendChild(reportInput);

    const filenameInput = document.createElement('input');
    filenameInput.type = 'hidden';
    filenameInput.name = 'filename';
    filenameInput.value = 'consolidado_por_bloque.xls';
    form.appendChild(filenameInput);

    const dataInput = document.createElement('input');
    dataInput.type = 'hidden';
    dataInput.name = 'data';
    dataInput.value = JSON.stringify(dataToExport);
    form.appendChild(dataInput);

    document.body.appendChild(form);
    form.submit();
    document.body.removeChild(form);
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
