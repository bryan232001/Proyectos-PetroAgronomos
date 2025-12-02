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
                u_creador.nombre AS creador_nombre
            FROM proyecto_asignacion pa
            LEFT JOIN proyectos p ON p.id = pa.id_proyecto
            LEFT JOIN usuarios u_tecnico ON u_tecnico.id_usuario = pa.id_tecnico
            LEFT JOIN usuarios u_creador ON u_creador.usuario = p.creado_por
            ORDER BY p.nombre ASC, pa.id_asignacion ASC
        ");
    } else {
        // Usuarios normales solo ven sus propias asignaciones
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
                u_creador.nombre AS creador_nombre
            FROM proyecto_asignacion pa
            LEFT JOIN proyectos p ON p.id = pa.id_proyecto
            LEFT JOIN usuarios u_tecnico ON u_tecnico.id_usuario = pa.id_tecnico
            LEFT JOIN usuarios u_creador ON u_creador.usuario = p.creado_por
            WHERE p.creado_por = ?
            ORDER BY p.nombre ASC, pa.id_asignacion ASC
        ");
        $stmt->execute([$usuario_actual]);
    }
    
    $asignaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // START: Bloque para diferenciar proyectos con el mismo nombre
    $name_counts = array_count_values(array_column($asignaciones, 'proyecto_nombre'));
    $name_counters = [];

    foreach ($asignaciones as &$asig) {
        $nombre = $asig['proyecto_nombre'];
        
        // Si el nombre está duplicado, agregar un contador
        if (isset($name_counts[$nombre]) && $name_counts[$nombre] > 1) {
            if (!isset($name_counters[$nombre])) {
                $name_counters[$nombre] = 1;
            }
            
            // Usar una nueva clave para el nombre a mostrar
            $asig['proyecto_nombre_display'] = $nombre . ' (' . $name_counters[$nombre] . ')';
            
            $name_counters[$nombre]++;
        } else {
            // Si no hay duplicados, solo usar el nombre original
            $asig['proyecto_nombre_display'] = $nombre;
        }
    }
    unset($asig); // Romper la referencia
    // END: Bloque para diferenciar proyectos
    
    // Calcular estadísticas
    $total_asignaciones = count($asignaciones);
    $total_beneficiarios = array_sum(array_column($asignaciones, 'beneficiarios'));
    $bloques_unicos = count(array_unique(array_column($asignaciones, 'bloque')));
    
} catch (Exception $e) {
    error_log("Error al cargar asignaciones: " . $e->getMessage());
    $error_msg = $e->getMessage();
    $asignaciones = [];
    $total_asignaciones = 0;
    $total_beneficiarios = 0;
    $bloques_unicos = 0;
}

// Preparar datos para exportar con cabeceras amigables
$export_data = array_map(function($asig) {
    return [
        'Fecha' => date('d/m/Y', strtotime($asig['fecha_asignacion'])),
        'Proyecto' => $asig['proyecto_nombre'] ?? 'N/A',
        'Comunidad' => $asig['comunidad_nombre'],
        'Bloque' => $asig['bloque'],
        'Beneficiarios' => $asig['beneficiarios']
    ];
}, $asignaciones);

$pageTitle = 'Historial de Asignaciones';
$is_dashboard = true;
require_once __DIR__ . '/includes/header.php';
?>

<!-- ======= (SOLO DE APOYO) si aún no pegaste el CSS global, este bloque mínimo maquilla los modales ======= -->
<style>
  .modal-overlay{ position:fixed; inset:0; background:rgba(15,23,42,.6); backdrop-filter:blur(4px);
    display:flex; align-items:center; justify-content:center; padding:20px; z-index:2147483647;
    opacity:0; visibility:hidden; transition:all .25s ease; }
  .modal-overlay.show{ opacity:1; visibility:visible; }
  .modal-content{ width:100%; max-width:520px; background:#fff; border-radius:16px; overflow:hidden;
    box-shadow:0 25px 60px rgba(0,0,0,.25); transform:translateY(10px) scale(.98); transition:transform .25s ease; }
  .modal-overlay.show .modal-content{ transform:translateY(0) scale(1); }
  .modal-header{ display:flex; align-items:center; justify-content:space-between; padding:16px 20px;
    background:linear-gradient(135deg,#3b82f6,#10b981); color:#fff; }
  .modal-title{ margin:0; font-weight:700; font-size:1.05rem; }
  .modal-close{ border:0; background:transparent; color:#fff; width:32px; height:32px; border-radius:999px;
    display:grid; place-items:center; font-size:20px; cursor:pointer; }
  .modal-close:hover{ background:rgba(255,255,255,.18); }
  .modal-body{ padding:18px 20px; color:#0f172a; max-height: 70vh; overflow-y: auto; }
  .modal-footer{ padding:14px 20px; background:#f8fafc; border-top:1px solid #e2e8f0; display:flex; gap:10px; justify-content:flex-end; }
  .btn-secondary{ background:#fff; color:#0f172a; border:1px solid #cbd5e1; padding:.6rem 1.2rem; border-radius:12px; font-weight:600; cursor:pointer; }
  .btn-secondary:hover{ background:#f1f5f9; }
  .btn-danger{ background:#ef4444; color:#fff; border:1px solid #ef4444; padding:.6rem 1.2rem; border-radius:12px; font-weight:700; cursor:pointer;
    box-shadow:0 4px 14px rgba(239,68,68,.2); }
  .btn-danger:hover{ filter:brightness(.95); }
  @media (max-width:480px){ .modal-content{ max-width:94vw; } }

  /* Estilos para el nuevo diseño */
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
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 1.5rem;
    border-radius: 12px;
    margin-bottom: 1.5rem;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
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
        <?= $es_admin ? 'Todas las Asignaciones (Administrador)' : 'Mis Asignaciones' ?>
      </h1>
      <div class="header-info">
        <span class="badge badge-primary"><?= $total_asignaciones ?> asignaciones</span>
        <span class="badge badge-success"><?= number_format($total_beneficiarios) ?> beneficiarios</span>
        <span class="badge badge-warning"><?= $bloques_unicos ?> bloques</span>
      </div>
    </div>

    <!-- Panel de Estadísticas para Administrador -->
    <?php if ($es_admin && !empty($asignaciones)): ?>
    <div class="stats-panel">
      <h3>📊 Resumen Ejecutivo</h3>
      <div class="stats-grid-mini">
        <div class="stat-mini">
          <div class="stat-mini-value"><?= $total_asignaciones ?></div>
          <div class="stat-mini-label">Total Asignaciones</div>
        </div>
        <div class="stat-mini">
          <div class="stat-mini-value"><?= number_format($total_beneficiarios) ?></div>
          <div class="stat-mini-label">Total Beneficiarios</div>
        </div>
        <div class="stat-mini">
          <div class="stat-mini-value"><?= $bloques_unicos ?></div>
          <div class="stat-mini-label">Bloques Activos</div>
        </div>
        <div class="stat-mini">
          <div class="stat-mini-value"><?= count(array_unique(array_column($asignaciones, 'creado_por'))) ?></div>
          <div class="stat-mini-label">Usuarios Activos</div>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <div class="card-table">
      <div class="card-table-header">
        <h2 class="card-table-title">Asignaciones Registradas</h2>
        <div style="display: flex; gap: 10px;">
          <a href="consolidado_asignaciones.php" class="btn btn-primary">Consolidado por Bloque</a>
          <a href="consolidado_descripcion.php" class="btn btn-secondary">Consolidado por Descripción</a>
          <button onclick="exportarExcel()" class="btn btn-success">Exportar a Excel</button>
        </div>
      </div>
      <div class="card-table-body">
        <div class="table-wrapper">
          <?php if (isset($error_msg)): ?>
            <div class="alert alert-danger">Error: <?= h($error_msg) ?></div>
          <?php elseif (empty($asignaciones)): ?>
            <div class="alert alert-info">No se han encontrado asignaciones.</div>
          <?php else: ?>
            <table class="data-table" id="tablaAsignaciones">
              <thead>
                <tr>
                  <th>ID</th><th>Fecha</th><th>Proyecto</th><th>Creado por</th><th>Comunidad</th><th>Bloque</th><th>Beneficiarios</th><th>Acciones</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($asignaciones as $asig): ?>
                  <tr id="row-<?= h($asig['id_asignacion']) ?>">
                    <td><?= h($asig['id_asignacion']) ?></td>
                    <td><?= h(date('d/m/Y', strtotime($asig['fecha_asignacion']))) ?></td>
                    <td><?= h($asig['proyecto_nombre_display'] ?? 'N/A') ?></td>
                    <td><?= h($asig['creado_por'] ?? 'N/A') ?></td>
                    <td><?= h($asig['comunidad_nombre']) ?></td>
                    <td><?= h($asig['bloque']) ?></td>
                    <td><?= h($asig['beneficiarios']) ?></td>
                    <td>
                      <button class="btn-icon btn-icon-primary" onclick="verDetalles(<?= (int)$asig['id_asignacion'] ?>)"><i class="fa-solid fa-eye"></i></button>
                      <?php 
                      // Asumimos rol 1 es Admin. Solo Admin o el creador pueden borrar.
                      $can_manage = (isset($_SESSION['id_rol']) && $_SESSION['id_rol'] == 1) || (isset($asig['creado_por']) && isset($_SESSION['usuario']) && $asig['creado_por'] == $_SESSION['usuario']);
                      if ($can_manage): 
                      ?>
                      <button class="btn-icon btn-icon-danger" onclick="confirmarBorrado(<?= (int)$asig['id_asignacion'] ?>)"><i class="fa-solid fa-trash"></i></button>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </main>
</div>

<!-- Modal Detalles -->
<div class="modal-overlay" id="modal-detalles" aria-hidden="true">
  <div class="modal-content" role="dialog" aria-modal="true" aria-labelledby="title-detalles">
    <div class="modal-header">
      <h3 id="title-detalles" class="modal-title"><i class="fa-solid fa-list-ul" style="margin-right:.5rem;"></i>Detalles de la Asignación</h3>
      <button class="modal-close" onclick="closeModal('modal-detalles')" aria-label="Cerrar">&times;</button>
    </div>
    <div class="modal-body" id="modal-body-detalles">Cargando...</div>
    <div class="modal-footer">
      <button class="btn-secondary" onclick="closeModal('modal-detalles')">Cerrar</button>
    </div>
  </div>
</div>

<!-- Modal Confirmar Borrado -->
<div class="modal-overlay" id="modal-confirmar-borrado" aria-hidden="true">
  <div class="modal-content" role="dialog" aria-modal="true" aria-labelledby="title-borrar">
    <div class="modal-header">
      <h3 id="title-borrar" class="modal-title"><i class="fa-solid fa-triangle-exclamation" style="margin-right:.5rem;"></i>Confirmar Borrado</h3>
      <button class="modal-close" onclick="closeModal('modal-confirmar-borrado')" aria-label="Cerrar">&times;</button>
    </div>
    <div class="modal-body">
      <p>¿Estás seguro de que deseas eliminar esta asignación? Esta acción no se puede deshacer.</p>
    </div>
    <div class="modal-footer">
      <button class="btn-secondary" onclick="closeModal('modal-confirmar-borrado')">Cancelar</button>
      <button id="btn-borrar" class="btn-danger">Eliminar</button>
    </div>
  </div>
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
    reportInput.value = 'asignaciones';
    form.appendChild(reportInput);

    const filenameInput = document.createElement('input');
    filenameInput.type = 'hidden';
    filenameInput.name = 'filename';
    filenameInput.value = 'historial_asignaciones.xls';
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

function openModal(id){
  const el = document.getElementById(id);
  if(!el) return;
  el.classList.add('show');
  el.setAttribute('aria-hidden','false');
  document.body.style.overflow = 'hidden';
  const focusable = el.querySelector('button, [href], input, select, textarea');
  if(focusable) setTimeout(()=>focusable.focus(), 30);
}
function closeModal(id){
  const el = document.getElementById(id);
  if(!el) return;
  el.classList.remove('show');
  el.setAttribute('aria-hidden','true');
  document.body.style.overflow = '';
}

['modal-detalles','modal-confirmar-borrado'].forEach(mid=>{
  const m = document.getElementById(mid);
  if(m){ m.addEventListener('click', e => { if(e.target === m) closeModal(mid); }); }
});
document.addEventListener('keydown', e=>{
  if(e.key === 'Escape'){
    closeModal('modal-detalles');
    closeModal('modal-confirmar-borrado');
  }
});

async function verDetalles(id_asignacion){
  const modalBody = document.getElementById('modal-body-detalles');
  modalBody.innerHTML = '<div style="text-align: center; padding: 2rem;"><i class="fa-solid fa-spinner fa-spin" style="font-size: 2rem; color: #6b7280;"></i><p style="margin-top: 1rem; color: #6b7280;">Cargando detalles...</p></div>';
  openModal('modal-detalles');

  try{
    const r = await fetch(`api_get_asignacion_detalle.php?id=${id_asignacion}`);
    const data = await r.json();
    if(!data.ok) throw new Error(data.msg || 'Error desconocido');

    if(!data.detalles || data.detalles.length === 0){
      modalBody.innerHTML = '<div style="text-align: center; padding: 2rem;"><i class="fa-solid fa-box-open" style="font-size: 2rem; color: #d1d5db;"></i><p style="margin-top: 1rem; font-weight: 500; color: #6b7280;">No hay productos para esta asignación.</p></div>';
      return;
    }

    let html = '<div style="display: flex; flex-direction: column; gap: 0.75rem;">';
    data.detalles.forEach(it => {
      html += `
        <div style="background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 8px; padding: 1rem; display: flex; justify-content: space-between; align-items: center; gap: 1rem;">
          <div>
            <div style="font-weight: 600; color: #1f2937; margin-bottom: 0.25rem;">${it.descripcion || 'N/A'}</div>
            <div style="font-size: 0.875rem; color: #6b7280;">Unidad: <span style="font-weight: 500; color: #374151;">${it.unidad || 'N/A'}</span></div>
          </div>
          <div style="text-align: right;">
            <div style="font-size: 0.75rem; color: #6b7280;">Cantidad</div>
            <div style="font-size: 1.125rem; font-weight: 700; color: #10b981;">
              ${Number(it.cantidad||0).toFixed(2)}
            </div>
          </div>
        </div>
      `;
    });
    html += '</div>';
    modalBody.innerHTML = html;

  }catch(err){
    modalBody.innerHTML = `<div style="background: #fee2e2; color: #b91c1c; padding: 1rem; border-radius: 8px;"><strong>Error:</strong> ${err.message}</div>`;
  }
}

function confirmarBorrado(id_asignacion){
  openModal('modal-confirmar-borrado');
  const btn = document.getElementById('btn-borrar');
  btn.onclick = ()=> borrarAsignacion(id_asignacion);
}

async function borrarAsignacion(id_asignacion){
  try{
    const r = await fetch('api_delete_asignacion.php', {
      method:'POST',
      headers:{ 'Content-Type':'application/json' },
      body: JSON.stringify({ id_asignacion })
    });
    const data = await r.json();
    if(!data.ok) throw new Error(data.msg || 'Error en el servidor');

    const row = document.getElementById(`row-${id_asignacion}`);
    if(row) row.remove();
    closeModal('modal-confirmar-borrado');
  }catch(err){
    alert(`Error al borrar la asignación: ${err.message}`);
  }
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
