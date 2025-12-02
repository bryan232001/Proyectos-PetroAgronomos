<?php
// login.php
session_start();
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/db.php';
$err = '';

// Verificar errores de URL
if (isset($_GET['error'])) {
    switch ($_GET['error']) {
        case 'inactive':
            $err = 'Tu usuario está inactivo. Contacta al administrador.';
            break;
        case 'invalid':
            $err = 'Usuario o contraseña incorrectos.';
            break;
        case 'system':
            $err = 'Error del sistema. Intenta nuevamente.';
            break;
        default:
            $err = 'Error de autenticación.';
    }
}

// Procesar envío
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // Acepta usuario O correo en el mismo campo
  $login = trim($_POST['login'] ?? '');
  $pass  = $_POST['password'] ?? '';
  if ($login === '' || $pass === '') {
    $err = 'Ingresa tu usuario/correo y contraseña.';
  } else {
    try {
      // Ajusta el nombre real de tu tabla si difiere
      $TABLE = 'usuarios';

      $st = $pdo->prepare("
        SELECT id_usuario, nombre, usuario, email, pass_hash, id_rol, activo
        FROM {$TABLE}
        WHERE email = :login OR usuario = :login
        LIMIT 1
      ");
      $st->execute([':login' => $login]);
      $u = $st->fetch();

      if ($u && (int)$u['activo'] === 1 && password_verify($pass, $u['pass_hash'])) {
        // Login OK: asegura sesión y guarda datos mínimos
        session_regenerate_id(true);
        $_SESSION['id_usuario'] = (int)$u['id_usuario'];
        $_SESSION['nombre']  = $u['nombre'];
        $_SESSION['usuario'] = $u['usuario'];
        $_SESSION['email']   = $u['email'];
        $_SESSION['id_rol']  = (int)$u['id_rol'];

        // Actualizar último acceso
        $stmt = $pdo->prepare("UPDATE usuarios SET ultimo_acceso = NOW() WHERE id_usuario = ?");
        $stmt->execute([$u['id_usuario']]);

        // Registrar log de login
        try {
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
            $stmt = $pdo->prepare("INSERT INTO user_logs (id_usuario, accion, descripcion, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$u['id_usuario'], 'login', 'Inicio de sesión exitoso', $ip, $user_agent]);
        } catch (Exception $e) {
            // Log error silently
        }

        header('Location: ' . url_to('dashboard.php'));
        exit;
      }

      // Mensajes claros
      if ($u && (int)$u['activo'] !== 1) {
        $err = 'Tu usuario está inactivo. Contacta al administrador.';
      } else {
        $err = 'Credenciales no válidas.';
      }
    } catch (Throwable $e) {
      $err = 'Error de conexión o consulta.';
    }
  }
}

// Vista
$pageTitle = 'Iniciar Sesión - EP Petroecuador';
require_once __DIR__ . '/includes/header.php';
?>

<div class="login-page">
    <div class="login-container">
        <!-- Left Side - Branding -->
        <div class="login-brand">
            <div class="brand-content">
                <div class="brand-logo">
                    <div class="logo-icon">EP</div>
                    <div class="logo-text">
                        <span class="logo-title">EP PETROECUADOR</span>
                        <span class="logo-subtitle">Relaciones Comunitarias</span>
                    </div>
                </div>
                
                <h2 class="brand-title">Sistema de Gestión de Proyectos Productivos</h2>
                <p class="brand-description">
                    Plataforma integral para la gestión y seguimiento de proyectos comunitarios, 
                    fortaleciendo el desarrollo sostenible y la autogestión local.
                </p>
                
                <div class="brand-features">
                    <div class="brand-feature">
                        <i class="fa-solid fa-seedling"></i>
                        <span>Proyectos Productivos</span>
                    </div>
                    <div class="brand-feature">
                        <i class="fa-solid fa-users"></i>
                        <span>Gestión Comunitaria</span>
                    </div>
                    <div class="brand-feature">
                        <i class="fa-solid fa-chart-line"></i>
                        <span>Seguimiento y Control</span>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Right Side - Login Form -->
        <div class="login-form-container">
            <div class="login-form-content">
                <div class="form-header">
                    <h1 class="form-title">Iniciar Sesión</h1>
                    <p class="form-subtitle">Accede para gestionar proyectos y comunidades</p>
                </div>

                <?php if ($err): ?>
                    <div class="alert alert-error">
                        <i class="fa-solid fa-exclamation-triangle"></i>
                        <span><?= e($err) ?></span>
                    </div>
                <?php endif; ?>

                <form method="post" class="login-form" autocomplete="on" novalidate>
                    <div class="form-group">
                        <label for="login" class="form-label">
                            <i class="fa-solid fa-user"></i>
                            Usuario o Correo
                        </label>
                        <input
                            id="login"
                            name="login"
                            type="text"
                            class="form-input"
                            required
                            placeholder="Ingresa tu usuario o correo electrónico"
                            autofocus
                            value="<?= e($_POST['login'] ?? '') ?>"
                        >
                    </div>

                    <div class="form-group">
                        <label for="password" class="form-label">
                            <i class="fa-solid fa-lock"></i>
                            Contraseña
                        </label>
                        <div class="password-input-container">
                            <input
                                id="password"
                                name="password"
                                type="password"
                                class="form-input"
                                required
                                placeholder="Ingresa tu contraseña"
                            >
                            <button type="button" class="password-toggle" onclick="togglePassword()">
                                <i class="fa-solid fa-eye" id="password-icon"></i>
                            </button>
                        </div>
                    </div>

                    <button type="submit" class="btn-login">
                        <i class="fa-solid fa-sign-in-alt"></i>
                        <span>Iniciar Sesión</span>
                    </button>
                </form>

                <div class="form-footer">
                    <p class="footer-text">
                        <i class="fa-solid fa-shield-alt"></i>
                        Acceso seguro y protegido
                    </p>
                </div>
            </div>
            
            <div class="login-footer">
                <p>© <?= date('Y') ?> EP Petroecuador - Todos los derechos reservados</p>
            </div>
        </div>
    </div>
</div>

<script>
function togglePassword() {
    const passwordInput = document.getElementById('password');
    const passwordIcon = document.getElementById('password-icon');
    
    if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        passwordIcon.className = 'fa-solid fa-eye-slash';
    } else {
        passwordInput.type = 'password';
        passwordIcon.className = 'fa-solid fa-eye';
    }
}

// Auto-focus en el primer campo vacío
document.addEventListener('DOMContentLoaded', function() {
    const loginInput = document.getElementById('login');
    const passwordInput = document.getElementById('password');
    
    if (loginInput.value === '') {
        loginInput.focus();
    } else {
        passwordInput.focus();
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
