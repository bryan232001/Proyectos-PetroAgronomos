<?php
session_start();
require_once 'config.php';
require_once 'db.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

function registrarLog($id_usuario, $accion, $descripcion = '') {
    global $pdo;
    try {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        
        $stmt = $pdo->prepare("INSERT INTO user_logs (id_usuario, accion, descripcion, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$id_usuario, $accion, $descripcion, $ip, $user_agent]);
    } catch (Exception $e) {
        // Log error silently
        error_log("Error registrando log: " . $e->getMessage());
    }
}

if ($_POST['usuario'] && $_POST['password']) {
    try {
        $usuario = trim($_POST['usuario']);
        $password = $_POST['password'];
        
        // Buscar usuario en la base de datos
        $stmt = $pdo->prepare("SELECT id_usuario, nombre, usuario, pass_hash, id_rol, activo FROM usuarios WHERE usuario = ? AND activo = 1");
        $stmt->execute([$usuario]);
        $user_data = $stmt->fetch();
        
        if ($user_data && password_verify($password, $user_data['pass_hash'])) {
            // Login exitoso
            $_SESSION['usuario'] = $user_data['usuario'];
            $_SESSION['id_usuario'] = $user_data['id_usuario'];
            $_SESSION['nombre'] = $user_data['nombre'];
            $_SESSION['id_rol'] = $user_data['id_rol'];
            
            // Actualizar último acceso
            $stmt = $pdo->prepare("UPDATE usuarios SET ultimo_acceso = NOW() WHERE id_usuario = ?");
            $stmt->execute([$user_data['id_usuario']]);
            
            // Registrar log de login
            registrarLog($user_data['id_usuario'], 'login', 'Inicio de sesión exitoso');
            
            header('Location: dashboard.php');
        } else {
            // Login fallido
            if ($user_data && !$user_data['activo']) {
                header('Location: login.php?error=inactive');
            } else {
                header('Location: login.php?error=invalid');
            }
        }
    } catch (Exception $e) {
        error_log("Error en autenticación: " . $e->getMessage());
        header('Location: login.php?error=system');
    }
    exit;
}
?>