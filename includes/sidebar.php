<?php
// includes/sidebar.php
$current_page = basename($_SERVER['PHP_SELF']);
?>
<nav class="dashboard-nav" id="sidebar">
    <!-- Header del Sidebar -->
    <div class="sidebar-header">
        <div class="sidebar-brand">
            <div class="brand-icon">
                <i class="fa-solid fa-leaf"></i>
            </div>
            <div class="brand-text">
                <div class="brand-title">Petro Agrónomos</div>
                <div class="brand-subtitle">Sistema de Gestión</div>
            </div>
        </div>
        <button class="sidebar-toggle-btn" id="sidebarToggle">
            <i class="fa-solid fa-bars"></i>
        </button>
    </div>

    <!-- Menú Principal -->
    <div class="nav-section">
        <div class="nav-section-title">Principal</div>
        <a href="dashboard.php" class="nav-link <?= ($current_page == 'dashboard.php') ? 'active' : '' ?>">
            <i class="fa-solid fa-home"></i>
            <span>Dashboard</span>
        </a>
    </div>

    <!-- Sección Proyectos -->
    <div class="nav-section">
        <div class="nav-section-title">Proyectos</div>
        <a href="proyectos_carrito.php" class="nav-link <?= ($current_page == 'proyectos_carrito.php') ? 'active' : '' ?>">
            <i class="fa-solid fa-plus-circle"></i>
            <span>Crear Proyectos</span>
        </a>
        <!-- <a href="mapa_proyectos.php" class="nav-link <?= ($current_page == 'mapa_proyectos.php') ? 'active' : '' ?>">
            <i class="fa-solid fa-map"></i>
            <span>Mapa de Proyectos</span>
        </a> -->
        
        <!-- Menú desplegable de Asignaciones -->
        <div class="nav-dropdown">
            <button class="nav-dropdown-toggle" data-target="asignaciones-menu">
                <i class="fa-solid fa-map-location-dot"></i>
                <span>Asignaciones</span>
                <i class="fa-solid fa-chevron-down dropdown-arrow"></i>
            </button>
            <div class="nav-dropdown-menu" id="asignaciones-menu">
                <a href="asignar_proyectos.php" class="nav-dropdown-item <?= ($current_page == 'asignar_proyectos.php') ? 'active' : '' ?>">
                    <i class="fa-solid fa-plus"></i>
                    <span>Nueva Asignación</span>
                </a>
                <a href="ver_asignaciones.php" class="nav-dropdown-item <?= ($current_page == 'ver_asignaciones.php') ? 'active' : '' ?>">
                    <i class="fa-solid fa-list"></i>
                    <span>Ver Asignaciones</span>
                </a>
                <a href="gestionar_entregas.php" class="nav-dropdown-item <?= ($current_page == 'gestionar_entregas.php') ? 'active' : '' ?>">
                    <i class="fa-solid fa-boxes-stacked"></i>
                    <span>Gestionar Entregas</span>
                </a>
                <a href="proyectos_completados.php" class="nav-dropdown-item <?= ($current_page == 'proyectos_completados.php') ? 'active' : '' ?>">
                    <i class="fa-solid fa-check-circle"></i>
                    <span>Proyectos Completados</span>
                </a>
            </div>
        </div>
    </div>

    <!-- Sección Catálogos -->
    <div class="nav-section">
        <div class="nav-section-title">Catálogos</div>
        <a href="precios_catalogo.php" class="nav-link <?= ($current_page == 'precios_catalogo.php') ? 'active' : '' ?>">
            <i class="fa-solid fa-tags"></i>
            <span>Precios</span>
        </a>
        
        <!-- Menú desplegable de Administración -->
        <div class="nav-dropdown">
            <button class="nav-dropdown-toggle" data-target="admin-menu">
                <i class="fa-solid fa-cog"></i>
                <span>Administración</span>
                <i class="fa-solid fa-chevron-down dropdown-arrow"></i>
            </button>
            <div class="nav-dropdown-menu" id="admin-menu">
                <a href="usuarios.php" class="nav-dropdown-item <?= ($current_page == 'usuarios.php') ? 'active' : '' ?>">
                    <i class="fa-solid fa-users"></i>
                    <span>Usuarios</span>
                </a>
                <a href="reportes.php" class="nav-dropdown-item <?= ($current_page == 'reportes.php') ? 'active' : '' ?>">
                    <i class="fa-solid fa-chart-bar"></i>
                    <span>Reportes</span>
                </a>
            </div>
        </div>
    </div>

    <!-- Logout -->
    <div class="nav-section nav-section-bottom">
        <a href="logout.php" class="nav-link logout-link">
            <i class="fa-solid fa-right-from-bracket"></i>
            <span>Cerrar Sesión</span>
        </a>
    </div>
</nav>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Elements
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebar = document.getElementById('sidebar');
    const mobileMenuBtn = document.getElementById('mobile-menu-btn');
    const mobileOverlay = document.getElementById('mobile-overlay');
    
    // Desktop sidebar toggle
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function() {
            sidebar.classList.toggle('collapsed');
            document.body.classList.toggle('sidebar-collapsed');
        });
    }

    // Mobile menu functionality
    if (mobileMenuBtn && sidebar && mobileOverlay) {
        mobileMenuBtn.addEventListener('click', function() {
            sidebar.classList.add('mobile-open');
            mobileOverlay.classList.add('show');
            document.body.style.overflow = 'hidden';
        });

        // Close mobile menu
        function closeMobileMenu() {
            sidebar.classList.remove('mobile-open');
            mobileOverlay.classList.remove('show');
            document.body.style.overflow = '';
        }

        mobileOverlay.addEventListener('click', closeMobileMenu);
        
        // Close on escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeMobileMenu();
            }
        });
    }

    // Dropdown functionality
    const dropdownToggles = document.querySelectorAll('.nav-dropdown-toggle');
    
    dropdownToggles.forEach(toggle => {
        toggle.addEventListener('click', function(e) {
            e.preventDefault();
            const targetId = this.getAttribute('data-target');
            const targetMenu = document.getElementById(targetId);
            const arrow = this.querySelector('.dropdown-arrow');
            
            // Close other dropdowns
            dropdownToggles.forEach(otherToggle => {
                if (otherToggle !== this) {
                    const otherId = otherToggle.getAttribute('data-target');
                    const otherMenu = document.getElementById(otherId);
                    const otherArrow = otherToggle.querySelector('.dropdown-arrow');
                    
                    if (otherMenu) otherMenu.classList.remove('show');
                    otherToggle.classList.remove('active');
                    if (otherArrow) otherArrow.style.transform = 'rotate(0deg)';
                }
            });
            
            // Toggle current dropdown
            if (targetMenu) {
                targetMenu.classList.toggle('show');
                this.classList.toggle('active');
                
                if (targetMenu.classList.contains('show')) {
                    if (arrow) arrow.style.transform = 'rotate(180deg)';
                } else {
                    if (arrow) arrow.style.transform = 'rotate(0deg)';
                }
            }
        });
    });

    // Auto-open dropdown if current page is inside
    const currentDropdownItem = document.querySelector('.nav-dropdown-item.active');
    if (currentDropdownItem) {
        const parentDropdown = currentDropdownItem.closest('.nav-dropdown');
        if (parentDropdown) {
            const toggle = parentDropdown.querySelector('.nav-dropdown-toggle');
            const menu = parentDropdown.querySelector('.nav-dropdown-menu');
            const arrow = toggle ? toggle.querySelector('.dropdown-arrow') : null;
            
            if (menu) menu.classList.add('show');
            if (toggle) toggle.classList.add('active');
            if (arrow) arrow.style.transform = 'rotate(180deg)';
        }
    }
});
</script>
