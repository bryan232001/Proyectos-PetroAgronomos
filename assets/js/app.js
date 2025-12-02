// Función global para toggle del sidebar
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const body = document.body;
    const overlay = document.getElementById('mobile-overlay');
    
    if (window.innerWidth <= 768) {
        // Móvil: toggle clase mobile-open
        sidebar.classList.toggle('mobile-open');
        overlay.classList.toggle('show');
        body.style.overflow = sidebar.classList.contains('mobile-open') ? 'hidden' : '';
    } else {
        // Escritorio: toggle clase collapsed
        sidebar.classList.toggle('collapsed');
        body.classList.toggle('sidebar-collapsed');
    }
}

// Efectos y animaciones
document.addEventListener('DOMContentLoaded', function() {
    // Mostrar barra móvil en dispositivos pequeños
    function checkMobileBar() {
        const mobileBar = document.getElementById('mobile-top-bar');
        if (mobileBar) {
            if (window.innerWidth <= 768) {
                mobileBar.style.display = 'flex';
            } else {
                mobileBar.style.display = 'none';
            }
        }
    }
    
    // Ejecutar al cargar y al redimensionar
    checkMobileBar();
    window.addEventListener('resize', checkMobileBar);

    // Header scroll effect
    window.addEventListener('scroll', function() {
        const header = document.querySelector('header');
        if (header && window.scrollY > 100) {
            header.style.background = 'rgba(255, 255, 255, 0.98)';
            header.style.boxShadow = '0 4px 25px rgba(0, 0, 0, 0.1)';
        } else if (header) {
            header.style.background = 'rgba(255, 255, 255, 0.95)';
            header.style.boxShadow = '0 2px 20px rgba(0, 0, 0, 0.05)';
        }
    });

    // Ripple effect para botones
    document.querySelectorAll('.btn-ripple').forEach(button => {
        button.addEventListener('click', function(e) {
            const ripple = document.createElement('span');
            const rect = this.getBoundingClientRect();
            const size = Math.max(rect.width, rect.height);
            const x = e.clientX - rect.left - size / 2;
            const y = e.clientY - rect.top - size / 2;
            
            ripple.style.cssText = `
                position: absolute;
                width: ${size}px;
                height: ${size}px;
                left: ${x}px;
                top: ${y}px;
                background: rgba(255, 255, 255, 0.4);
                border-radius: 50%;
                transform: scale(0);
                animation: ripple 0.6s linear;
                pointer-events: none;
            `;
            
            this.appendChild(ripple);
            
            setTimeout(() => {
                ripple.remove();
            }, 600);
        });
    });

    // CSS para la animación ripple
    const style = document.createElement('style');
    style.textContent = `
        @keyframes ripple {
            to {
                transform: scale(4);
                opacity: 0;
            }
        }
    `;
    document.head.appendChild(style);

    // Toggle filters section
    const toggleFiltersBtn = document.getElementById('toggle-filters');
    const filtersCollapse = document.getElementById('filters-collapse');
    const filtersToggleIcon = document.getElementById('filters-toggle-icon');

    if (toggleFiltersBtn && filtersCollapse && filtersToggleIcon) {
        toggleFiltersBtn.addEventListener('click', () => {
            filtersCollapse.classList.toggle('show');
            filtersToggleIcon.classList.toggle('fa-chevron-down');
            filtersToggleIcon.classList.toggle('fa-chevron-up');
        });
    }

    // Cerrar menú móvil al hacer clic en overlay
    const overlay = document.getElementById('mobile-overlay');
    if (overlay) {
        overlay.addEventListener('click', function() {
            const sidebar = document.getElementById('sidebar');
            if (sidebar) {
                sidebar.classList.remove('mobile-open');
                overlay.classList.remove('show');
                document.body.style.overflow = '';
            }
        });
    }

    // Cerrar menú móvil con tecla Escape
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('mobile-overlay');
            if (sidebar && sidebar.classList.contains('mobile-open')) {
                sidebar.classList.remove('mobile-open');
                overlay.classList.remove('show');
                document.body.style.overflow = '';
            }
        }
    });

    // Sidebar toggle functionality para escritorio
    const sidebarToggle = document.querySelector('.sidebar-toggle');
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', toggleSidebar);
    }

    // Ajustar layout al redimensionar ventana
    window.addEventListener('resize', function() {
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('mobile-overlay');
        
        if (window.innerWidth > 768) {
            // Escritorio: limpiar clases móviles
            if (sidebar) {
                sidebar.classList.remove('mobile-open');
            }
            if (overlay) {
                overlay.classList.remove('show');
            }
            document.body.style.overflow = '';
        }
        
        checkMobileBar();
    });
});