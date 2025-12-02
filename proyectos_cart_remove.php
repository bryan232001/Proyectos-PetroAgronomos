<?php
session_start();
if (!isset($_SESSION['usuario'])) { header('Location: login.php'); exit; }
require_once __DIR__ . '/includes/db.php';

$proyecto_id = (int)($_GET['proyecto_id'] ?? 0);
$idx         = (int)($_GET['idx'] ?? -1);
$cartKey = "cart_$proyecto_id";

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

if ($proyecto_id>0 && isset($_SESSION[$cartKey][$idx])) {
  array_splice($_SESSION[$cartKey], $idx, 1);
}
header('Location: proyectos_carrito.php?proyecto_id='.$proyecto_id);