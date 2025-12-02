<?php
// precios_catalogo.php — CRUD catálogo corregido
session_start();
if (!isset($_SESSION['usuario'])) { header('Location: login.php'); exit; }

// ==== DEPURACIÓN OPCIONAL ====
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

// ==== DB ====
require_once __DIR__ . '/includes/db.php';
if (!isset($pdo) || !($pdo instanceof PDO)) {
  die('Error: No hay conexión PDO disponible ($pdo no definido). Revisa includes/db.php');
}
try { $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); } catch (Throwable $e) {}

// ==== Helpers ====
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function redirect_here(){
  $url = strtok($_SERVER['REQUEST_URI'],'#');
  header('Location: '.$url);
  exit;
}

// Normaliza precios
function normPrecio(string $raw): ?float {
  $s = trim($raw);
  if ($s === '') return null;
  $s = str_replace(',', '.', $s);
  $s = preg_replace('/[^\\d.]/', '', $s);
  if (!is_numeric($s)) return null;
  $v = (float)$s; 
  if ($v < 0) return null;
  return $v;
}

// ===== CSRF =====
if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(16)); }
$csrf = $_SESSION['csrf'];

// La tabla catalogo_productos ya existe con los campos correctos:
// id_producto, grupo, descripcion, unidad, precio_unit, cpc, proceso, garantia

/* =========================
   ACCIONES POST
   ========================= */
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
  $csrf_enviado = $_POST['csrf'] ?? '';
  $csrf_session = $_SESSION['csrf'] ?? '';
  if (empty($csrf_enviado) || empty($csrf_session) || !hash_equals($csrf_session, $csrf_enviado)) {
    $_SESSION['mensaje_error'] = ['Error de seguridad (CSRF). Por favor, recarga la página.'];
    redirect_here();
  }

  $accion = $_POST['action'] ?? '';
  $errores_post = [];
  $mensaje_post = '';

  try {
    if ($accion === 'update') {
      $ids = $_POST['id'] ?? [];
      $precios = $_POST['precio'] ?? [];
      $procesados = 0;
      $afectados_proyectos = 0;

      if (is_array($ids) && is_array($precios) && count($ids) === count($precios)) {
        $pdo->beginTransaction();
        
        $stmt_catalogo = $pdo->prepare("UPDATE catalogo_productos SET precio_unit = ? WHERE id_producto = ?");
        $stmt_proyectos = $pdo->prepare("UPDATE proyecto_items SET precio_unit = ?, subtotal = cantidad * ? WHERE producto_id = ?");

        foreach ($ids as $k => $idVal) {
          $id = (int)$idVal;
          if ($id <= 0) continue;
          
          $precio_raw = $precios[$k] ?? '';
          $precio_final = normPrecio((string)$precio_raw);
          if ($precio_final === null) continue;
          
          // 1. Actualizar catálogo principal
          $stmt_catalogo->execute([$precio_final, $id]);
          $procesados++;
          
          // 2. Actualizar precios en todos los `proyecto_items` existentes
          $stmt_proyectos->execute([$precio_final, $precio_final, $id]);
          $afectados_proyectos += $stmt_proyectos->rowCount();
        }
        
        $pdo->commit();
        
        $mensaje_post = "Se actualizaron $procesados precio(s) en el catálogo.";
        if ($afectados_proyectos > 0) {
            $mensaje_post .= " Adicionalmente, se actualizaron $afectados_proyectos productos en proyectos existentes.";
        }
      }
    }

    elseif ($accion === 'add') {
      $grupo = trim($_POST['grupo'] ?? '');
      $descripcion = trim($_POST['descripcion'] ?? '');
      $unidad = trim($_POST['unidad'] ?? '');
      $precio_raw = trim($_POST['precio_unit'] ?? '');
      $cpc = trim($_POST['cpc'] ?? '');
      $proceso = trim($_POST['proceso'] ?? '');
      $garantia = trim($_POST['garantia'] ?? '');

      $precio = normPrecio($precio_raw);

      if ($grupo === '') $errores_post[] = 'El grupo es obligatorio.';
      if ($descripcion === '') $errores_post[] = 'La descripción es obligatoria.';
      if ($unidad === '') $errores_post[] = 'La unidad es obligatoria.';
      if ($precio === null) $errores_post[] = 'El precio es inválido.';

      if (empty($errores_post)) {
        $stmt = $pdo->prepare("INSERT INTO catalogo_productos (grupo, descripcion, unidad, precio_unit, cpc, proceso, garantia) VALUES(?,?,?,?,?,?,?)");
        $stmt->execute([$grupo, $descripcion, $unidad, $precio, $cpc, $proceso, $garantia]);
        $nuevoId = (int)$pdo->lastInsertId();
        $mensaje_post = "Producto agregado correctamente (#{\$nuevoId}).";
      }
    }

    elseif ($accion === 'delete') {
      $del_id = (int)($_POST['del_id'] ?? 0);
      if ($del_id > 0) {
        $stmt = $pdo->prepare("DELETE FROM catalogo_productos WHERE id_producto=?");
        $stmt->execute([$del_id]);
        if ($stmt->rowCount() > 0) {
          $mensaje_post = "Producto eliminado correctamente.";
        } else {
          $errores_post[] = "No se pudo eliminar el producto.";
        }
      }
    }

    elseif ($accion === 'bulk_delete') {
      $sel = $_POST['sel'] ?? [];
      $ids = array_map('intval', array_filter($sel, fn($x) => (int)$x > 0));
      
      if (!empty($ids)) {
        $in = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $pdo->prepare("DELETE FROM catalogo_productos WHERE id_producto IN ($in)");
        $stmt->execute($ids);
        $deleted = $stmt->rowCount();
        if ($deleted > 0) $mensaje_post = "Eliminados $deleted producto(s).";
      } else {
        $errores_post[] = "No se seleccionaron productos.";
      }
    }
  } catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    $errores_post[] = "Error: " . $e->getMessage();
  }

  if (!empty($errores_post)) $_SESSION['mensaje_error'] = $errores_post;
  if (!empty($mensaje_post)) $_SESSION['mensaje_exito'] = $mensaje_post;
  redirect_here();
}

/* =========================
   FILTROS Y CONSULTA
   ========================= */
$mensaje = $_SESSION['mensaje_exito'] ?? '';
$errores = $_SESSION['mensaje_error'] ?? [];
unset($_SESSION['mensaje_exito'], $_SESSION['mensaje_error']);

$grupo = trim($_GET['grupo'] ?? '');
$q = trim($_GET['q'] ?? '');
$orden = $_GET['orden'] ?? 'grupo';
$dir = strtoupper($_GET['dir'] ?? 'ASC');
$dir = $dir === 'DESC' ? 'DESC' : 'ASC';

$validOrden = [
  'descripcion'=>'descripcion',
  'precio'=>'precio_unit',
  'grupo'=>'grupo',
  'unidad'=>'unidad',
  'id'=>'id_producto'
];
$colOrden = $validOrden[$orden] ?? 'grupo';

$limite = max(10, min(200, (int)($_GET['limite'] ?? 50)));
$pagina = max(1, (int)($_GET['page'] ?? 1));
$offset = ($pagina - 1) * $limite;

// Obtener grupos y unidades para filtros
$grupos = [];
$unidades = [];
try {
  $grupos = $pdo->query("SELECT DISTINCT grupo FROM catalogo_productos WHERE grupo IS NOT NULL AND grupo != '' ORDER BY grupo")->fetchAll(PDO::FETCH_COLUMN);
  $unidades = $pdo->query("SELECT DISTINCT unidad FROM catalogo_productos WHERE unidad IS NOT NULL AND unidad != '' ORDER BY unidad")->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
  error_log("Error obteniendo filtros: " . $e->getMessage());
}

// WHERE dinámico
$where = [];
$params = [];
if ($grupo !== '') { $where[] = 'grupo = ?'; $params[] = $grupo; }
if ($q !== '') {
  $where[] = '(descripcion LIKE ? OR unidad LIKE ? OR grupo LIKE ?)';
  $like = '%'.$q.'%';
  array_push($params, $like, $like, $like);
}
$whereSql = $where ? ('WHERE '.implode(' AND ', $where)) : '';

// Total y datos
$totalFilas = 0;
$rows = [];
try {
  $stmt = $pdo->prepare("SELECT COUNT(*) FROM catalogo_productos $whereSql");
  $stmt->execute($params);
  $totalFilas = (int)$stmt->fetchColumn();

  if ($totalFilas > 0) {
    $sql = "SELECT id_producto, grupo, descripcion, unidad, precio_unit, cpc, proceso, garantia
            FROM catalogo_productos
            $whereSql
            ORDER BY $colOrden $dir, id_producto ASC
            LIMIT $limite OFFSET $offset";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
  }
} catch (Exception $e) {
  error_log("Error consultando: " . $e->getMessage());
}

$totalPag = max(1, (int)ceil($totalFilas / $limite));

function build_url($extra = []){
  $base = $_GET;
  foreach ($extra as $k=>$v) { $base[$k] = $v; }
  return '?'.http_build_query($base);
}

$pageTitle = 'Catálogo de Productos';
$is_dashboard = true;
require_once __DIR__ . '/includes/header.php';
?>

<div class="dashboard-grid">
    <?php require_once __DIR__ . '/includes/sidebar.php'; ?>

    <main class="dashboard-main">
      <!-- Header -->
      <div class="page-header">
        <div>
          <i class="fas fa-box-open"></i>
          <span>Catálogo de Productos</span>
        </div>
        <div>
          <button class="btn-primary" type="button" onclick="openModal('modal-add')">
            <i class="fas fa-plus"></i> Agregar Producto
          </button>
        </div>
      </div>

      <!-- Mensajes -->
      <?php if (!empty($errores)): ?>
        <div style="background: #fee2e2; color: #991b1b; padding: 1rem; border-radius: 8px; margin-bottom: 1rem; border-left: 4px solid #dc2626;">
          <strong>Error(es):</strong><br>
          <?= implode('<br>', array_map('h', $errores)) ?>
        </div>
      <?php endif; ?>
      
      <?php if ($mensaje): ?>
        <div style="background: #dcfce7; color: #166534; padding: 1rem; border-radius: 8px; margin-bottom: 1rem; border-left: 4px solid #16a34a;">
          <strong>Éxito:</strong> <?= h($mensaje) ?>
        </div>
      <?php endif; ?>

      <!-- Filtros -->
      <div style="background: white; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); margin-bottom: 1.5rem; overflow: hidden;">
        <div style="padding: 1rem 1.5rem; background: #f8fafc; border-bottom: 1px solid #e2e8f0; font-weight: 600; color: #374151;">
          <i class="fas fa-filter"></i> Filtros de Búsqueda
        </div>
        <div style="padding: 1.5rem;">
          <form method="get" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; align-items: end;">
            <div>
              <label style="display: block; font-weight: 600; color: #374151; margin-bottom: 0.5rem;">
                <i class="fas fa-layer-group"></i> Grupo
              </label>
              <select name="grupo" class="nice-input" style="width: 100%;">
                <option value="">Todos los grupos</option>
                <?php foreach($grupos as $g): ?>
                  <option value="<?= h($g) ?>" <?= $grupo===$g?'selected':'' ?>><?= h($g) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            
            <div>
              <label style="display: block; font-weight: 600; color: #374151; margin-bottom: 0.5rem;">
                <i class="fas fa-search"></i> Buscar
              </label>
              <input type="text" name="q" value="<?= h($q) ?>" class="nice-input" placeholder="Descripción, unidad, grupo..." style="width: 100%;">
            </div>
            
            <div>
              <label style="display: block; font-weight: 600; color: #374151; margin-bottom: 0.5rem;">
                <i class="fas fa-sort"></i> Ordenar
              </label>
              <select name="orden" class="nice-input" style="width: 100%;">
                <option value="grupo" <?= $orden==='grupo'?'selected':'' ?>>Grupo</option>
                <option value="descripcion" <?= $orden==='descripcion'?'selected':'' ?>>Descripción</option>
                <option value="precio" <?= $orden==='precio'?'selected':'' ?>>Precio</option>
                <option value="unidad" <?= $orden==='unidad'?'selected':'' ?>>Unidad</option>
              </select>
            </div>
            
            <div>
              <label style="display: block; font-weight: 600; color: #374151; margin-bottom: 0.5rem;">
                <i class="fas fa-list"></i> Mostrar
              </label>
              <select name="limite" class="nice-input" style="width: 100%;">
                <?php foreach([25,50,100] as $opt): ?>
                  <option value="<?= $opt ?>" <?= $limite===$opt?'selected':'' ?>><?= $opt ?> filas</option>
                <?php endforeach; ?>
              </select>
            </div>
            
            <div style="display: flex; gap: 0.5rem;">
              <button type="submit" class="btn-primary">
                <i class="fas fa-search"></i> Filtrar
              </button>
              <a href="precios_catalogo.php" class="btn-secondary">
                <i class="fas fa-undo"></i> Limpiar
              </a>
            </div>
          </form>
        </div>
      </div>

      <!--Tabla -->
      <form method="post" action="precios_catalogo.php<?= $_SERVER['QUERY_STRING']?('?'.$_SERVER['QUERY_STRING']):'' ?>">
        <input type="hidden" name="csrf" value="<?= h($csrf) ?>">

        <div style="background: white; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); overflow: hidden;">
          <!-- Header de tabla -->
          <div style="padding: 1rem 1.5rem; background: #f8fafc; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
            <div style="display: flex; align-items: center; gap: 1rem;">
              <div style="width: 50px; height: 50px; background: linear-gradient(135deg, #3b82f6, #10b981); border-radius: 12px; display: flex; align-items: center; justify-content: center; color: white; font-size: 1.2rem;">
                <i class="fas fa-cube"></i>
              </div>
              <div>
                <strong><?= number_format($totalFilas) ?></strong> productos encontrados
                <div style="font-size: 0.875rem; color: #6b7280;">
                  Página <?= $pagina ?> de <?= $totalPag ?>
                </div>
              </div>
            </div>
            <div style="display: flex; gap: 0.75rem; flex-wrap: wrap;">
              <button class="btn-primary" type="submit" name="action" value="update">
                <i class="fas fa-save"></i> Guardar Precios
              </button>
              <button class="btn-secondary" type="submit" name="action" value="bulk_delete" onclick="return confirm('¿Eliminar productos seleccionados?');">
                <i class="fas fa-trash"></i> Eliminar Seleccionados
              </button>
            </div>
          </div>

          <!--Tabla responsive -->
          <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse;">
              <thead>
                <tr style="background: #f8fafc;">
                  <th style="padding: 1rem; text-align: left; font-weight: 600; color: #374151; border-bottom: 1px solid #e5e7eb; width: 50px;">
                    <input type="checkbox" onclick="toggleAll(this)" title="Seleccionar todos">
                  </th>
                  <th style="padding: 1rem; text-align: left; font-weight: 600; color: #374151; border-bottom: 1px solid #e5e7eb;">ID</th>
                  <th style="padding: 1rem; text-align: left; font-weight: 600; color: #374151; border-bottom: 1px solid #e5e7eb;">Grupo</th>
                  <th style="padding: 1rem; text-align: left; font-weight: 600; color: #374151; border-bottom: 1px solid #e5e7eb;">Descripción</th>
                  <th style="padding: 1rem; text-align: left; font-weight: 600; color: #374151; border-bottom: 1px solid #e5e7eb;">Unidad</th>
                  <th style="padding: 1rem; text-align: left; font-weight: 600; color: #374151; border-bottom: 1px solid #e5e7eb;">Precio ($)</th>
                  <th style="padding: 1rem; text-align: left; font-weight: 600; color: #374151; border-bottom: 1px solid #e5e7eb;">CPC</th>
                  <th style="padding: 1rem; text-align: left; font-weight: 600; color: #374151; border-bottom: 1px solid #e5e7eb;">Acciones</th>
                </tr>
              </thead>
              <tbody>
              <?php if(empty($rows)): ?>
                <tr>
                  <td colspan="8" style="padding: 3rem; text-align: center; color: #6b7280;">
                    <div style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.3;">
                      <i class="fas fa-inbox"></i>
                    </div>
                    <div style="font-size: 1.25rem; font-weight: 600; margin-bottom: 0.5rem;">No se encontraron productos</div>
                    <div style="margin-bottom: 1.5rem;">
                      <?php if ($q || $grupo): ?>
                        Intenta ajustar los filtros de búsqueda
                      <?php else: ?>
                        Comienza agregando tu primer producto
                      <?php endif; ?>
                    </div>
                    <?php if (!$q && !$grupo): ?>
                      <button class="btn-primary" type="button" onclick="openModal('modal-add')">
                        <i class="fas fa-plus"></i> Agregar Primer Producto
                      </button>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php else: ?>
                <?php foreach($rows as $r): ?>
                  <tr style="border-bottom: 1px solid #f1f5f9; transition: background-color 0.2s ease;" onmouseover="this.style.backgroundColor='#f8fafc'" onmouseout="this.style.backgroundColor=''">
                    <td style="padding: 1rem;">
                      <input type="checkbox" name="sel[]" value="<?= (int)$r['id_producto'] ?>">
                    </td>
                    <td style="padding: 1rem;">
                      <span style="font-family: monospace; color: #6b7280; font-weight: 600;">#<?= (int)$r['id_producto'] ?></span>
                      <input type="hidden" name="id[]" value="<?= (int)$r['id_producto'] ?>">
                    </td>
                    <td style="padding: 1rem;">
                      <span style="background: linear-gradient(135deg, #3b82f6, #10b981); color: white; padding: 0.25rem 0.75rem; border-radius: 20px; font-size: 0.75rem; font-weight: 600; text-transform: uppercase;">
                        <?= h($r['grupo']) ?>
                      </span>
                    </td>
                    <td style="padding: 1rem;">
                      <div style="font-weight: 600; color: #111827; line-height: 1.4;">
                        <?= h($r['descripcion']) ?>
                      </div>
                    </td>
                    <td style="padding: 1rem;">
                      <span style="background: #f1f5f9; padding: 0.25rem 0.5rem; border-radius: 6px; font-size: 0.8rem; font-weight: 600; color: #374151; text-transform: uppercase;">
                        <?= h($r['unidad']) ?>
                      </span>
                    </td>
                    <td style="padding: 1rem;">
                      <div style="position: relative;">
                        <span style="position: absolute; left: 0.75rem; top: 50%; transform: translateY(-50%); color: #6b7280; font-weight: 600;">$</span>
                        <input name="precio[]" type="text" inputmode="decimal"
                               value="<?= h(number_format((float)$r['precio_unit'], 2, '.', '')) ?>"
                               style="width: 100px; padding: 0.5rem 0.75rem 0.5rem 1.5rem; border: 2px solid #e5e7eb; border-radius: 8px; text-align: right; font-weight: 600; background: white; transition: all 0.2s ease;"
                               onfocus="this.style.borderColor='#3b82f6'; this.style.boxShadow='0 0 0 3px rgba(59, 130, 246, 0.1)'; this.select();"
                               onblur="this.style.borderColor='#e5e7eb'; this.style.boxShadow='none';">
                      </div>
                    </td>
                    <td style="padding: 1rem;">
                      <span style="font-family: monospace; font-size: 0.85rem; color: #6b7280; background: #f8fafc; padding: 0.25rem 0.5rem; border-radius: 4px;">
                        <?= h($r['cpc'] ?: '—') ?>
                      </span>
                    </td>
                    <td style="padding: 1rem;">
                      <button type="button" onclick="deleteProduct(<?= (int)$r['id_producto'] ?>)" 
                              style="background: #fee2e2; color: #dc2626; border: none; padding: 0.5rem; border-radius: 8px; cursor: pointer; transition: all 0.2s ease;"
                              onmouseover="this.style.background='#dc2626'; this.style.color='white';"
                              onmouseout="this.style.background='#fee2e2'; this.style.color='#dc2626';"
                              title="Eliminar producto">
                        <i class="fas fa-trash-alt"></i>
                      </button>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
              </tbody>
            </table>
          </div>

          <!-- Footer de tabla -->
          <div style="padding: 1rem 1.5rem; background: #f8fafc; border-top: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
            <div style="display: flex; align-items: center; gap: 0.75rem; color: #6b7280;">
              <i class="fas fa-info-circle"></i>
              <span>Tip: Puedes usar punto o coma como separador decimal</span>
            </div>
            <div style="display: flex; gap: 0.75rem;">
              <button class="btn-primary" type="submit" name="action" value="update">
                <i class="fas fa-save"></i> Guardar Cambios
              </button>
            </div>
          </div>
        </div>
      </form>

      <!-- Paginación -->
      <?php if ($totalPag > 1): ?>
      <div style="display: flex; justify-content: center; align-items: center; gap: 1rem; margin: 2rem 0; flex-wrap: wrap;">
        <?php if($pagina > 1): ?>
          <a href="<?= h(build_url(['page'=>$pagina-1])) ?>" 
             style="background: white; border: 1px solid #d1d5db; color: #374151; padding: 0.75rem 1.25rem; border-radius: 8px; text-decoration: none; font-weight: 600; transition: all 0.2s ease; display: flex; align-items: center; gap: 0.5rem;"
             onmouseover="this.style.background='#3b82f6'; this.style.color='white'; this.style.borderColor='#3b82f6';"
             onmouseout="this.style.background='white'; this.style.color='#374151'; this.style.borderColor='#d1d5db';">
            <i class="fas fa-chevron-left"></i> Anterior
          </a>
        <?php endif; ?>
        
        <div style="background: white; padding: 1rem 1.5rem; border-radius: 8px; border: 1px solid #d1d5db; font-weight: 600; color: #374151; text-align: center;">
          Página <?= $pagina ?> de <?= $totalPag ?>
          <div style="font-size: 0.875rem; color: #6b7280; margin-top: 0.25rem;">
            <?= number_format($totalFilas) ?> productos total
          </div>
        </div>
        
        <?php if($pagina < $totalPag): ?>
          <a href="<?= h(build_url(['page'=>$pagina+1])) ?>" 
             style="background: white; border: 1px solid #d1d5db; color: #374151; padding: 0.75rem 1.25rem; border-radius: 8px; text-decoration: none; font-weight: 600; transition: all 0.2s ease; display: flex; align-items: center; gap: 0.5rem;"
             onmouseover="this.style.background='#3b82f6'; this.style.color='white'; this.style.borderColor='#3b82f6';"
             onmouseout="this.style.background='white'; this.style.color='#374151'; this.style.borderColor='#d1d5db';">
            Siguiente <i class="fas fa-chevron-right"></i>
          </a>
        <?php endif; ?>
      </div>
      <?php endif; ?>
    </main>
</div>

<!-- Modal Agregar Producto -->
<div class="modal-overlay" id="modal-add" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); display: flex; align-items: center; justify-content: center; z-index: 9999; opacity: 0; visibility: hidden; transition: all 0.3s ease;">
  <div style="background: white; border-radius: 16px; width: 100%; max-width: 600px; max-height: 90vh; overflow: hidden; transform: scale(0.9); transition: transform 0.3s ease;">
    <div style="background: linear-gradient(135deg, #3b82f6, #10b981); color: white; padding: 1.5rem; display: flex; justify-content: space-between; align-items: center;">
      <div>
        <span style="background: rgba(255,255,255,0.2); padding: 0.25rem 0.75rem; border-radius: 20px; font-size: 0.75rem; font-weight: 600; margin-right: 1rem;">NUEVO</span>
        <span style="font-size: 1.25rem; font-weight: 600;">Agregar Producto</span>
      </div>
      <button onclick="closeModal('modal-add')" style="background: none; border: none; color: white; font-size: 24px; cursor: pointer; width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; transition: background-color 0.2s ease;" onmouseover="this.style.background='rgba(255,255,255,0.2)'" onmouseout="this.style.background='none'">×</button>
    </div>

    <form method="post" action="precios_catalogo.php<?= $_SERVER['QUERY_STRING']?('?'.$_SERVER['QUERY_STRING']):'' ?>">
      <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
      <input type="hidden" name="action" value="add">

      <div style="padding: 1.5rem; max-height: 60vh; overflow-y: auto;">
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
          <div>
            <label style="display: block; font-weight: 600; color: #374151; margin-bottom: 0.5rem;">
              <i class="fas fa-layer-group"></i> Grupo *
            </label>
            <input list="dl_grupos" name="grupo" class="nice-input" placeholder="HERRAMIENTAS" required style="width: 100%;">
            <datalist id="dl_grupos">
              <?php foreach($grupos as $g): ?><option value="<?= h($g) ?>"></option><?php endforeach; ?>
            </datalist>
          </div>
          <div>
            <label style="display: block; font-weight: 600; color: #374151; margin-bottom: 0.5rem;">
              <i class="fas fa-balance-scale"></i> Unidad *
            </label>
            <input list="dl_unidades" name="unidad" class="nice-input" placeholder="UNIDAD" required style="width: 100%;">
            <datalist id="dl_unidades">
              <?php foreach($unidades as $u): ?><option value="<?= h($u) ?>"></option><?php endforeach; ?>
            </datalist>
          </div>
        </div>

        <div style="margin-bottom: 1rem;">
          <label style="display: block; font-weight: 600; color: #374151; margin-bottom: 0.5rem;">
            <i class="fas fa-tag"></i> Descripción *
          </label>
          <input name="descripcion" class="nice-input" placeholder="Nombre detallado del producto" required style="width: 100%;">
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
          <div>
            <label style="display: block; font-weight: 600; color: #374151; margin-bottom: 0.5rem;">
              <i class="fas fa-dollar-sign"></i> Precio *
            </label>
            <input name="precio_unit" class="nice-input" inputmode="decimal" placeholder="0.00" required style="width: 100%;">
          </div>
          <div>
            <label style="display: block; font-weight: 600; color: #374151; margin-bottom: 0.5rem;">
              <i class="fas fa-code"></i> CPC
            </label>
            <input name="cpc" class="nice-input" placeholder="Código" style="width: 100%;">
          </div>
          <div>
            <label style="display: block; font-weight: 600; color: #374151; margin-bottom: 0.5rem;">
              <i class="fas fa-shield-alt"></i> Garantía
            </label>
            <input name="garantia" class="nice-input" placeholder="12 meses" style="width: 100%;">
          </div>
        </div>

        <div>
          <label style="display: block; font-weight: 600; color: #374151; margin-bottom: 0.5rem;">
            <i class="fas fa-cogs"></i> Proceso
          </label>
          <input name="proceso" class="nice-input" placeholder="Proceso de adquisición" style="width: 100%;">
        </div>
      </div>

      <div style="padding: 1rem 1.5rem; background: #f8fafc; border-top: 1px solid #e2e8f0; display: flex; justify-content: flex-end; gap: 1rem;">
        <button type="button" onclick="closeModal('modal-add')" class="btn-secondary">
          <i class="fas fa-times"></i> Cancelar
        </button>
        <button type="submit" class="btn-primary">
          <i class="fas fa-plus"></i> Agregar Producto
        </button>
      </div>
    </form>
  </div>
</div>

<script>
function deleteProduct(id) {
  if (!confirm(`¿Eliminar el producto #${id}?

Esta acción no se puede deshacer.`)) return;
  
  const form = document.createElement('form');
  form.method = 'post';
  form.action = 'precios_catalogo.php<?= $_SERVER['QUERY_STRING'] ? '?' . $_SERVER['QUERY_STRING'] : '' ?>';
  
  const fields = { csrf: '<?= h($csrf) ?>', action: 'delete', del_id: id };
  for (const k in fields) {
    const input = document.createElement('input');
    input.type = 'hidden'; 
    input.name = k; 
    input.value = fields[k];
    form.appendChild(input);
  }
  
  document.body.appendChild(form);
  form.submit();
}

function toggleAll(master) {
  const checkboxes = document.querySelectorAll('input[name="sel[]"]');
  checkboxes.forEach(ch => ch.checked = master.checked);
}

function openModal(id) {
  const modal = document.getElementById(id);
  if (!modal) return;
  
  modal.style.opacity = '1';
  modal.style.visibility = 'visible';
  modal.querySelector('.modal > div').style.transform = 'scale(1)';
  
  const firstInput = modal.querySelector('input:not([type="hidden"])');
  if (firstInput) setTimeout(() => firstInput.focus(), 100);
}

function closeModal(id) {
  const modal = document.getElementById(id);
  if (!modal) return;
  
  modal.style.opacity = '0';
  modal.style.visibility = 'hidden';
  modal.querySelector('.modal > div').style.transform = 'scale(0.9)';
  
  // Limpiar formulario
  const form = modal.querySelector('form');
  if (form) form.reset();
}

// Cerrar modal con Escape
document.addEventListener('keydown', e => {
  if (e.key === 'Escape') closeModal('modal-add');
});

// Cerrar modal clickeando fuera
document.getElementById('modal-add').addEventListener('click', e => {
  if (e.target.id === 'modal-add') closeModal('modal-add');
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>