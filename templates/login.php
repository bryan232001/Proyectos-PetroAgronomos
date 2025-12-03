<?php
// templates/login.php
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
                    <?= csrf_input() ?>
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
                            value="<?= e($post_login) ?>"
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
