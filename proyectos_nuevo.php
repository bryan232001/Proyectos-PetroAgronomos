<?php
session_start();
if (!isset($_SESSION['usuario'])) { header('Location: login.php'); exit; }
require_once __DIR__ . '/includes/db.php';

$nombre  = trim($_POST['nombre'] ?? '');
$tipo_id = (int)($_POST['tipo_id'] ?? 0); // puede venir vacío

if ($nombre === '') { header('Location: proyectos_carrito.php'); exit; }

/* Si no viene tipo_id, forzar a MIXTO */
if ($tipo_id <= 0) {
  $tipo_id = $pdo->query("SELECT id FROM tipos_proyecto WHERE nombre='MIXTO' LIMIT 1")->fetchColumn();
  if (!$tipo_id) {
    $pdo->prepare("INSERT INTO tipos_proyecto(nombre) VALUES('MIXTO')")->execute();
    $tipo_id = (int)$pdo->lastInsertId();
  }
}

$stmt = $pdo->prepare("INSERT INTO proyectos(nombre, tipo_id, creado_por) VALUES(?,?,?)");
$stmt->execute([$nombre, $tipo_id, $_SESSION['usuario']]);

$newId = (int)$pdo->lastInsertId();
header('Location: proyectos_carrito.php?proyecto_id='.$newId);
