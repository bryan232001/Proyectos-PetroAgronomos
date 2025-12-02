<?php
session_start();
if (!isset($_SESSION['usuario'])) { header('Location: login.php'); exit; }
require_once __DIR__ . '/includes/db.php';

$proyecto_id = (int)($_POST['proyecto_id'] ?? 0);

if ($proyecto_id > 0) {
    $stmt = $pdo->prepare("SELECT creado_por FROM proyectos WHERE id = ?");
    $stmt->execute([$proyecto_id]);
    $creador = $stmt->fetchColumn();

    if ($creador !== $_SESSION['usuario']) {
        $_SESSION['flash_error'] = 'No tiene permisos para modificar este proyecto.';
        header('Location: proyectos_carrito.php?proyecto_id='.$proyecto_id);
        exit;
    }
}

$cartKey = "cart_$proyecto_id";
$items = $_SESSION[$cartKey] ?? [];

if ($proyecto_id<=0 || empty($items)) {
  header('Location: proyectos_carrito.php?proyecto_id='.$proyecto_id); exit;
}

try {
  $pdo->beginTransaction();

  // Primero, eliminar los items anteriores de este proyecto para reemplazarlos con el carrito actual.
  $del = $pdo->prepare("DELETE FROM proyecto_items WHERE proyecto_id = ?");
  $del->execute([$proyecto_id]);

  // Releer datos frescos desde el catálogo
  $ids = implode(',', array_map('intval', array_column($items, 'id')));
  $precios = [];

  $stmt = $pdo->query("
    SELECT id_producto, descripcion, unidad, precio_unit
    FROM catalogo_productos
    WHERE id_producto IN ($ids)
  ");
  while($r = $stmt->fetch(PDO::FETCH_ASSOC)){
    $precios[(int)$r['id_producto']] = [
      'descripcion' => $r['descripcion'],
      'unidad'      => $r['unidad'],
      'precio'      => (float)$r['precio_unit']
    ];
  }

  $ins = $pdo->prepare("
    INSERT INTO proyecto_items
      (proyecto_id, producto_id, descripcion, unidad, cantidad, precio_unit, subtotal)
    VALUES (?,?,?,?,?,?,?)
  ");

  foreach($items as $it){
    $pid  = (int)$it['id'];
    $cant = (float)$it['cantidad'];
    $meta = $precios[$pid] ?? ['descripcion'=>$it['nombre'], 'unidad'=>$it['unidad'], 'precio'=>$it['precio']];
    $prec = (float)$meta['precio'];
    $subt = $prec * $cant;

    $ins->execute([$proyecto_id, $pid, $meta['descripcion'], $meta['unidad'], $cant, $prec, $subt]);
  }

  $pdo->commit();
  unset($_SESSION[$cartKey]);
  $_SESSION['flash_success'] = 'Proyecto guardado correctamente.';
  header('Location: proyectos_carrito.php?proyecto_id='.$proyecto_id);
} catch(Throwable $e){
  if ($pdo->inTransaction()) {
    $pdo->rollBack();
  }
  error_log("Error en proyectos_cart_save.php: " . $e->getMessage()); // Log para el desarrollador
  $_SESSION['flash_error'] = 'Error al guardar el proyecto. Por favor, inténtelo de nuevo.'; // Mensaje para el usuario
  header('Location: proyectos_carrito.php?proyecto_id='.$proyecto_id);
}