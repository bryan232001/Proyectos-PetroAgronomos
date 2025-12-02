<?php
$pageTitle = 'Proyectos Productivos y de Emprendimiento - EP Petroecuador';
require_once __DIR__ . '/includes/header.php';
?>

<div class="landing-page">
    <!-- Hero Section -->
    <section class="hero-section">
        <div class="hero-background">
            <div class="hero-overlay"></div>
        </div>
        
        <div class="hero-content">
            <div class="hero-badge">
                <i class="fa-solid fa-leaf"></i>
                <span>EP Petroecuador</span>
            </div>
            
            <h1 class="hero-title">
                Proyectos Productivos y de 
                <span class="text-gradient">Relaciones Comunitarias</span>
            </h1>
            
            <p class="hero-description">
                Los programas de relaciones comunitarias de <strong>EP PETROECUADOR</strong> implementan ejes de 
                <strong>salud, educación, cultura</strong> y <strong>proyectos productivos</strong>; fortaleciendo 
                comunidades mediante la <strong>autogestión</strong> y <strong>negocios inclusivos</strong>.
            </p>
            
            <div class="hero-actions">
                <a href="<?= url_to('login.php') ?>" class="btn-hero-primary">
                    <i class="fa-solid fa-sign-in-alt"></i>
                    Iniciar Sesión
                </a>
                <a href="#features" class="btn-hero-secondary">
                    <i class="fa-solid fa-info-circle"></i>
                    Conocer Más
                </a>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section id="features" class="features-section">
        <div class="container">
            <div class="section-header">
                <h2 class="section-title">Nuestros Programas</h2>
                <p class="section-subtitle">
                    Desarrollamos proyectos sostenibles que dinamizan la economía local y generan empleo
                </p>
            </div>
            
            <div class="features-grid">
                <div class="feature-card">
                    <div class="feature-icon health">
                        <i class="fa-solid fa-heart-pulse"></i>
                    </div>
                    <h3 class="feature-title">Salud</h3>
                    <p class="feature-description">
                        Programas de atención médica y prevención para mejorar la calidad de vida de las comunidades.
                    </p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon education">
                        <i class="fa-solid fa-graduation-cap"></i>
                    </div>
                    <h3 class="feature-title">Educación</h3>
                    <p class="feature-description">
                        Iniciativas educativas que fortalecen las capacidades y conocimientos locales.
                    </p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon culture">
                        <i class="fa-solid fa-masks-theater"></i>
                    </div>
                    <h3 class="feature-title">Cultura</h3>
                    <p class="feature-description">
                        Preservación y promoción de las tradiciones y patrimonio cultural comunitario.
                    </p>
                </div>
                
                <div class="feature-card featured">
                    <div class="feature-icon productive">
                        <i class="fa-solid fa-seedling"></i>
                    </div>
                    <h3 class="feature-title">Proyectos Productivos</h3>
                    <p class="feature-description">
                        Desarrollo de negocios inclusivos y proyectos de autogestión para el crecimiento económico sostenible.
                    </p>
                    <div class="feature-badge">Principal</div>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="cta-section">
        <div class="container">
            <div class="cta-content">
                <h2 class="cta-title">¿Listo para comenzar?</h2>
                <p class="cta-description">
                    Accede al sistema para gestionar proyectos, asignaciones y hacer seguimiento a las iniciativas comunitarias.
                </p>
                <a href="<?= url_to('login.php') ?>" class="btn-cta">
                    <i class="fa-solid fa-rocket"></i>
                    Acceder al Sistema
                </a>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="landing-footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-brand">
                    <div class="footer-logo">
                        <div class="logo-icon">EP</div>
                        <div class="logo-text">
                            <span class="logo-title">EP PETROECUADOR</span>
                            <span class="logo-subtitle">Programa de Relaciones Comunitarias</span>
                        </div>
                    </div>
                    <p class="footer-description">
                        Fortaleciendo comunidades a través de proyectos sostenibles y negocios inclusivos.
                    </p>
                </div>
                
                <div class="footer-info">
                    <h4>Contacto</h4>
                    <p>Sistema de Gestión de Proyectos</p>
                    <p>Relaciones Comunitarias</p>
                    <p>© <?= date('Y') ?> EP Petroecuador</p>
                </div>
            </div>
        </div>
    </footer>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
