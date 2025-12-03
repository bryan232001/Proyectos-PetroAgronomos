<?php
// src/Controllers/LoginController.php
namespace App\Controllers;

class LoginController {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function handleRequest() {
        $err = '';

        if (isset($_GET['error'])) {
            $err = $this->handleGetErrors($_GET['error']);
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $err = $this->handlePostRequest();
            if ($err === '') {
                // If login is successful, handlePostRequest will redirect.
                // If it returns, it means there was an error.
            }
        }
        
        $this->renderLoginView($err);
    }

    private function handleGetErrors($error) {
        switch ($error) {
            case 'session_expired':
                return 'Tu sesión ha expirado por inactividad. Por favor, inicia sesión de nuevo.';
            case 'inactive':
                return 'Tu usuario está inactivo. Contacta al administrador.';
            case 'invalid':
                return 'Usuario o contraseña incorrectos.';
            case 'system':
                return 'Error del sistema. Intenta nuevamente.';
            default:
                return 'Error de autenticación.';
        }
    }

    private function handlePostRequest() {
        if (!csrf_token_verify($_POST['csrf_token'] ?? '')) {
            return 'Error de validación. Intenta de nuevo.';
        }

        $login = trim($_POST['login'] ?? '');
        $pass  = $_POST['password'] ?? '';
        if ($login === '' || $pass === '') {
          return 'Ingresa tu usuario/correo y contraseña.';
        }

        try {
            $TABLE = 'usuarios';

            $st = $this->pdo->prepare("
              SELECT id_usuario, nombre, usuario, email, pass_hash, id_rol, activo
              FROM {$TABLE}
              WHERE email = :login OR BINARY usuario = :login
              LIMIT 1
            ");
            $st->execute([':login' => $login]);
            $u = $st->fetch();

            if ($u && (int)$u['activo'] === 1 && password_verify($pass, $u['pass_hash'])) {
                session_regenerate_id(true);
                $_SESSION['id_usuario'] = (int)$u['id_usuario'];
                $_SESSION['nombre']  = $u['nombre'];
                $_SESSION['usuario'] = $u['usuario'];
                $_SESSION['email']   = $u['email'];
                $_SESSION['id_rol']  = (int)$u['id_rol'];
                $_SESSION['last_activity'] = time();
                csrf_token_generate();

                $stmt = $this->pdo->prepare("UPDATE usuarios SET ultimo_acceso = NOW() WHERE id_usuario = ?");
                $stmt->execute([$u['id_usuario']]);

                try {
                    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
                    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
                    $stmt = $this->pdo->prepare("INSERT INTO user_logs (id_usuario, accion, descripcion, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([$u['id_usuario'], 'login', 'Inicio de sesión exitoso', $ip, $user_agent]);
                } catch (\Exception $e) {
                    // Log error silently
                }

                header('Location: ' . url_to('dashboard.php'));
                exit;
            }

            if ($u && (int)$u['activo'] !== 1) {
              return 'Tu usuario está inactivo. Contacta al administrador.';
            } else {
              return 'Credenciales no válidas.';
            }
        } catch (\Throwable $e) {
            return 'Error de conexión o consulta.';
        }
    }
    
    private function renderLoginView($err) {
        $pageTitle = 'Iniciar Sesión - EP Petroecuador';
        $is_login_page = true;

        // Make variables available to the view
        $data = [
            'err' => $err,
            'pageTitle' => $pageTitle,
            'is_login_page' => $is_login_page,
            'post_login' => $_POST['login'] ?? ''
        ];
        
        extract($data);

        require_once __DIR__ . '/../../includes/header.php';
        require_once __DIR__ . '/../../templates/login.php';
        require_once __DIR__ . '/../../includes/footer.php';
    }
}
