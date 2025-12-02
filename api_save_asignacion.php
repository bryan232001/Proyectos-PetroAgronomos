<?php
session_start();
if (!isset($_SESSION['usuario'])) { http_response_code(401); echo json_encode(['ok'=>false,'msg'=>'No autorizado']); exit; }
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/includes/db.php'; // $pdo

$data = json_decode(file_get_contents('php://input'), true);

$idUsuario = (int)($_SESSION['id_usuario'] ?? 0);
// Verificación crucial: si no hay ID de usuario en la sesión, la inserción fallará.
if ($idUsuario <= 0) {
  http_response_code(403); // Forbidden
  echo json_encode(['ok' => false, 'msg' => 'Error de Sesión: No se pudo identificar al usuario. Por favor, cierre sesión y vuelva a iniciarla.']);
  exit;
}

$id_proyecto        = (int)($data['id_proyecto'] ?? 0);
$bloque             = trim($data['bloque'] ?? '');
$comunidad_nombre   = trim($data['comunidad_nombre'] ?? '');
$cantidad_proyectos = (int)($data['cantidad_proyectos'] ?? 0);
$beneficiarios      = (int)($data['beneficiarios'] ?? 0);
$observaciones      = trim($data['observaciones'] ?? '');

if($id_proyecto<=0)       { echo json_encode(['ok'=>false,'msg'=>'Proyecto requerido']); exit; }
if($bloque==='')          { echo json_encode(['ok'=>false,'msg'=>'Bloque requerido']); exit; }
if($comunidad_nombre===''){ echo json_encode(['ok'=>false,'msg'=>'Comunidad requerida']); exit; }
if($cantidad_proyectos<1) { echo json_encode(['ok'=>false,'msg'=>'Cantidad de proyectos inválida']); exit; }
if($beneficiarios<0)      { echo json_encode(['ok'=>false,'msg'=>'Beneficiarios inválido']); exit; }

$comunidad_nombre = function_exists('mb_strtoupper')
  ? mb_strtoupper($comunidad_nombre, 'UTF-8')
  : strtoupper($comunidad_nombre);

/* existe proyecto */
$stmt = $pdo->prepare("SELECT creado_por FROM proyectos WHERE id=?");
$stmt->execute([$id_proyecto]);
$creador = $stmt->fetchColumn();
if(!$creador){ echo json_encode(['ok'=>false,'msg'=>'Proyecto inexistente']); exit; }

if ($creador !== $_SESSION['usuario']) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'msg' => 'No tiene permisos para asignar este proyecto.']);
    exit;
}

/* zona/provincia por bloque */
$st = $pdo->prepare("SELECT zona, provincia FROM catalogo_bloques WHERE bloque=? LIMIT 1");
$st->execute([$bloque]);
[$zona, $provincia] = $st->fetch(PDO::FETCH_NUM) ?: [null, null];
if(!$zona || !$provincia){ echo json_encode(['ok'=>false,'msg'=>'Bloque no encontrado en catálogo']); exit; }

$hoy = date('Y-m-d');

try{
  $pdo->beginTransaction();

  // Determinar estado de asignación (si no viene, por defecto PROCESADO)
  $estado_asignacion = $data['estado_asignacion'] ?? 'PROCESADO';
  $estados_validos = ['PROCESADO','EN GESTION','EN EJECUCION','ENTREGADO'];
  if (!in_array($estado_asignacion, $estados_validos, true)) {
    $estado_asignacion = 'PROCESADO';
  }

  $sqlCab = "INSERT INTO proyecto_asignacion
    (id_proyecto, bloque, zona, provincia, comunidad_nombre, cantidad_proyectos, beneficiarios, fecha_asignacion, id_tecnico, observaciones, estado_asignacion, fecha_estado)
    VALUES (?,?,?,?,?,?,?,?,?,?,?,NOW())";
  $stmt = $pdo->prepare($sqlCab);
  $stmt->execute([$id_proyecto, $bloque, $zona, $provincia, $comunidad_nombre, $cantidad_proyectos, $beneficiarios, $hoy, $idUsuario, $observaciones, $estado_asignacion]);
  $id_asignacion = (int)$pdo->lastInsertId();

  /* Detalle (opcional) */
  if (!empty($data['productos']) && is_array($data['productos'])) {
    $sqlDet = "INSERT INTO proyecto_asignacion_detalle (id_asignacion, id_producto, cantidad) VALUES (?,?,?)";
    $stDet = $pdo->prepare($sqlDet);
    
    // También crear entradas de seguimiento de entregas
    $sqlEntrega = "INSERT INTO producto_entregas (id_asignacion, id_producto, cantidad_asignada, cantidad_entregada, entregado) VALUES (?,?,?,0,0)";
    $stEntrega = $pdo->prepare($sqlEntrega);
    
    $productos_insertados = 0;
    $ids_productos_procesados = []; // Para evitar duplicados
    foreach($data['productos'] as $pr){
      $idp = (int)($pr['id_producto'] ?? 0);
      $cant = (float)($pr['cantidad'] ?? 0);
      
      // Ignorar si el producto ya fue procesado para esta asignación
      if (in_array($idp, $ids_productos_procesados, true)) {
        continue;
      }

      if($idp>0 && $cant>0){ 
        $stDet->execute([$id_asignacion, $idp, $cant]);
        $stEntrega->execute([$id_asignacion, $idp, $cant]);
        $ids_productos_procesados[] = $idp; // Marcar como procesado
        $productos_insertados++;
      }
    }
    
    // Crear resumen de entregas
    if ($productos_insertados > 0) {
      $sqlResumen = "INSERT INTO asignacion_entregas_resumen (id_asignacion, total_productos, productos_entregados, porcentaje_entrega, completado) VALUES (?,?,0,0.00,0)";
      $stResumen = $pdo->prepare($sqlResumen);
      $stResumen->execute([$id_asignacion, $productos_insertados]);
    }
  }

  $pdo->commit();
  echo json_encode(['ok'=>true,'id_asignacion'=>$id_asignacion]);

}catch(Throwable $e){
  $pdo->rollBack();
  http_response_code(500);
  echo json_encode(['ok'=>false,'msg'=>'Error: '.$e->getMessage()]);
}
