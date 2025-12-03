<?php
session_start();
if (!isset($_SESSION['usuario'])) { header('Location: login.php'); exit; }

require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/db.php';

// Verificar token CSRF
if (!csrf_token_verify($_POST['csrf_token'] ?? '')) {
    $_SESSION['flash_error'] = 'Error de validación (CSRF). Inténtalo de nuevo.';
    header('Location: proyectos_carrito.php');
    exit;
}

if (!empty($_POST['proyecto_id'])) {
    $proyecto_id = (int)$_POST['proyecto_id'];

    // Verificar permisos: Admin puede eliminar cualquier proyecto, usuarios normales solo los suyos
    $rol_actual = $_SESSION['id_rol'] ?? 2;
    $es_admin = ($rol_actual == 1);
    
    if (!$es_admin) {
        // Si no es admin, verificar si es el creador del proyecto
        $stmt = $pdo->prepare("SELECT creado_por FROM proyectos WHERE id = ?");
        $stmt->execute([$proyecto_id]);
        $creador = $stmt->fetchColumn();

        if ($creador !== $_SESSION['usuario']) {
            $_SESSION['flash_error'] = 'No tiene permisos para eliminar este proyecto.';
            header('Location: proyectos_carrito.php');
            exit;
        }
    }

    // Iniciar una transacción para asegurar la integridad de los datos
    $pdo->beginTransaction();
    try {
        // 1. Eliminar los productos relacionados en la tabla `proyecto_items`
        $stmt_items = $pdo->prepare("DELETE FROM proyecto_items WHERE proyecto_id = ?");
        $stmt_items->execute([$proyecto_id]);

        // 2. Eliminar el proyecto de la tabla `proyectos`
        $stmt_proyecto = $pdo->prepare("DELETE FROM proyectos WHERE id = ?");
        $stmt_proyecto->execute([$proyecto_id]);

        $pdo->commit();
        $_SESSION['flash_success'] = 'Proyecto eliminado exitosamente.';
    } catch (PDOException $e) {
        $pdo->rollBack();
        if ($e->getCode() == '23000') {
            $_SESSION['flash_error'] = 'No se puede eliminar el proyecto porque ya tiene asignaciones. Primero elimine las asignaciones asociadas.';
        } else {
            $_SESSION['flash_error'] = 'Error al eliminar el proyecto: ' . $e->getMessage();
        }
    }
}

header('Location: proyectos_carrito.php');
exit;