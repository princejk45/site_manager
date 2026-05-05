/**
 * Dashboard 2.0 - JavaScript Utilities
 * Fullmidia Site Manager
 * 
 * Features:
 * - Dark mode toggle
 * - Widget configuration
 * - Real-time updates
 * - Toast notifications
 */

class DashboardV2 {
    constructor() {
        this.darkModeEnabled = localStorage.getItem('darkMode') === 'true';
        this.init();
    }

    init() {
        this.setupMobileDefaults();
        this.setupDarkMode();
        this.setupHamburger();
        this.setupLanguageSwitcher();
        this.setupToasts();
        this.setupAutoRefresh();
        this.setupWidgetInteraction();
    }

    /**
     * Setup Mobile Defaults
     */
    setupMobileDefaults() {
        // On mobile, start with sidebar collapsed (hidden)
        if (window.innerWidth <= 768) {
            document.body.classList.add('sidebar-collapse');
        }
        
        // Handle window resize to adjust sidebar state
        window.addEventListener('resize', () => {
            if (window.innerWidth > 768) {
                // Desktop: ensure sidebar is NOT collapsed
                document.body.classList.remove('sidebar-collapse');
            }
        });
    }

    /**
     * Hamburger Menu - Mobile & Desktop Responsive
     */
    setupHamburger() {
        const hamburger = document.querySelector('.hamburger-toggle');
        if (!hamburger) return;
        
        hamburger.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();

            // CSS controls layout; JS only toggles state class.
            document.body.classList.toggle('sidebar-collapse');
        });
        
        // Close sidebar when clicking on a sidebar link (mobile only)
        document.querySelectorAll('nav.modern-sidebar a[href*="action="]').forEach(link => {
            link.addEventListener('click', () => {
                if (window.innerWidth <= 768) {
                    document.body.classList.add('sidebar-collapse');
                }
            });
        });
        
        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', (e) => {
            if (window.innerWidth <= 768 && 
                !e.target.closest('nav.modern-sidebar') && 
                !e.target.closest('.hamburger-toggle')) {
                document.body.classList.add('sidebar-collapse');
            }
        });
    }

    /**
     * Language Switcher Dropdown Reliability
     */
    setupLanguageSwitcher() {
        const wrapper = document.querySelector('.language-switcher');
        const toggle = document.getElementById('languageDropdown');
        const menu = wrapper ? wrapper.querySelector('.dropdown-menu') : null;

        if (!wrapper || !toggle || !menu) return;
        if (toggle.dataset.langClickBound === '1') return;
        toggle.dataset.langClickBound = '1';

        const closeMenu = () => {
            wrapper.classList.remove('show');
            toggle.classList.remove('show');
            menu.classList.remove('show');
            toggle.setAttribute('aria-expanded', 'false');
        };

        toggle.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            if (typeof e.stopImmediatePropagation === 'function') {
                e.stopImmediatePropagation();
            }

            const isOpen = menu.classList.contains('show');
            wrapper.classList.toggle('show', !isOpen);
            toggle.classList.toggle('show', !isOpen);
            menu.classList.toggle('show', !isOpen);
            toggle.setAttribute('aria-expanded', String(!isOpen));
        }, true);

        document.addEventListener('click', (e) => {
            if (wrapper.contains(e.target)) return;
            closeMenu();
        });

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                closeMenu();
            }
        });

        menu.querySelectorAll('a').forEach((link) => {
            link.addEventListener('click', () => {
                closeMenu();
            });
        });
    }

    /**
     * Dark Mode Management
     */
    setupDarkMode() {
        if (this.darkModeEnabled) {
            document.body.classList.add('dark-mode');
        }

        const darkModeButton = document.getElementById('darkModeToggle');
        const darkModeDropdownButton = document.getElementById('darkModeDropdownToggle');

        if (darkModeButton) {
            darkModeButton.addEventListener('click', (e) => {
                e.preventDefault();
                this.toggleDarkMode();
            });
        }

        if (darkModeDropdownButton) {
            darkModeDropdownButton.addEventListener('click', (e) => {
                e.preventDefault();
                this.toggleDarkMode();
            });
        }

        this.updateDarkModeButtonUI();
    }

    toggleDarkMode() {
        this.darkModeEnabled = !this.darkModeEnabled;
        document.body.classList.toggle('dark-mode', this.darkModeEnabled);
        localStorage.setItem('darkMode', this.darkModeEnabled);
        this.updateDarkModeButtonUI();
        this.showToast(`Dark mode ${this.darkModeEnabled ? 'enabled' : 'disabled'}`, 'info');
    }

    updateDarkModeButtonUI() {
        const topbarButton = document.getElementById('darkModeToggle');
        const dropdownButton = document.getElementById('darkModeDropdownToggle');

        if (topbarButton) {
            const icon = topbarButton.querySelector('i');
            if (icon) {
                icon.classList.remove('fa-moon', 'fa-sun');
                icon.classList.add(this.darkModeEnabled ? 'fa-sun' : 'fa-moon');
            }
        }

        if (dropdownButton) {
            dropdownButton.innerHTML = this.darkModeEnabled
                ? '<i class="fas fa-sun mr-2"></i>Light Mode'
                : '<i class="fas fa-moon mr-2"></i>Dark Mode';
        }
    }

    /**
     * Toast Notifications
     */
    setupToasts() {
        // Initialize toast container if not exists
        if (!document.getElementById('toast-container')) {
            const container = document.createElement('div');
            container.id = 'toast-container';
            container.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                z-index: 9999;
                max-width: 400px;
            `;
            document.body.appendChild(container);
        }
    }

    showToast(message, type = 'info', duration = 3000) {
        const container = document.getElementById('toast-container');
        const toast = document.createElement('div');
        
        const bgColors = {
            'info': '#4299e1',
            'success': '#48bb78',
            'warning': '#ed8936',
            'error': '#f56565'
        };

        toast.style.cssText = `
            background: ${bgColors[type] || bgColors['info']};
            color: white;
            padding: 16px;
            border-radius: 8px;
            margin-bottom: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.15);
            animation: slideIn 0.3s ease-out;
            font-size: 14px;
        `;

        toast.textContent = message;
        container.appendChild(toast);

        if (duration > 0) {
            setTimeout(() => {
                toast.style.animation = 'slideOut 0.3s ease-out';
                setTimeout(() => toast.remove(), 300);
            }, duration);
        }

        return toast;
    }

    /**
     * Auto-Refresh Data
     */
    setupAutoRefresh() {
        // Auto-refresh disabled - API endpoint not yet implemented
        // TODO: Implement dashboard API endpoint for async data updates
        return;
    }

    async refreshDashboard() {
        try {
            // Fetch updated dashboard data
            const response = await fetch('index.php?action=dashboard&api=true');
            if (!response.ok) return;

            const data = await response.json();
            this.updateKPIs(data);
            this.updateAlerts(data);
        } catch (error) {
            console.error('Dashboard refresh failed:', error);
        }
    }

    updateKPIs(data) {
        // Update KPI cards with new data
        const kpiElements = document.querySelectorAll('.kpi-card-value');
        if (kpiElements.length > 0) {
            kpiElements[0].textContent = data.totalServices || 0;
            kpiElements[1].textContent = data.expiringServices || 0;
            kpiElements[2].textContent = data.servicesWithIssues || 0;
            kpiElements[3].textContent = data.healthScore + '%' || '0%';
        }
    }

    updateAlerts(data) {
        // Update alerts panel
        if (data.alerts && data.alerts.length > 0) {
            const alertList = document.querySelector('.alert-list');
            if (alertList) {
                alertList.innerHTML = data.alerts.map(alert => `
                    <li class="alert-item">
                        <div class="alert-item-icon">${alert.priority}</div>
                        <div class="alert-item-text">${alert.message}</div>
                    </li>
                `).join('');
            }
        }
    }

    /**
     * Widget Interaction
     */
    setupWidgetInteraction() {
        // Make widgets clickable/expandable
        document.querySelectorAll('.service-card').forEach(card => {
            card.addEventListener('click', (e) => {
                if (!e.target.closest('a')) {
                    this.expandServiceCard(card);
                }
            });
        });

        // KPI card click handler
        document.querySelectorAll('.kpi-card').forEach((card, index) => {
            card.style.cursor = 'pointer';
            card.addEventListener('click', () => this.handleKPIClick(index));
        });
    }

    expandServiceCard(card) {
        // Expand service card for detailed view
        card.classList.toggle('expanded');
        if (card.classList.contains('expanded')) {
            card.style.gridColumn = 'span 2';
        } else {
            card.style.gridColumn = '';
        }
    }

    handleKPIClick(index) {
        const actions = [
            () => window.location.href = 'index.php?action=websites',
            () => window.location.href = 'index.php?action=websites&sort=expiry_date',
            () => window.location.href = 'index.php?action=diagnostics',
            () => window.location.href = 'index.php?action=diagnostics',
            () => window.location.href = 'index.php?action=hosting'
        ];

        if (actions[index]) {
            actions[index]();
        }
    }

    /**
     * Configuration Management
     */
    setRefreshInterval(ms) {
        localStorage.setItem('dashboardRefreshInterval', ms);
        this.showToast(`Dashboard refresh interval set to ${ms / 1000}s`, 'success');
    }

    getRefreshInterval() {
        return parseInt(localStorage.getItem('dashboardRefreshInterval') || 60000);
    }
}

// Initialize dashboard on page load
document.addEventListener('DOMContentLoaded', () => {
    window.dashboard = new DashboardV2();
});

// Global delegated fallback for hamburger toggle.
document.addEventListener('click', (e) => {
    const toggle = e.target.closest('.hamburger-toggle');
    if (!toggle) return;

    e.preventDefault();
    document.body.classList.toggle('sidebar-collapse');
});

// Add CSS animation keyframes
const style = document.createElement('style');
style.textContent = `
    @keyframes slideIn {
        from {
            opacity: 0;
            transform: translateX(400px);
        }
        to {
            opacity: 1;
            transform: translateX(0);
        }
    }

    @keyframes slideOut {
        from {
            opacity: 1;
            transform: translateX(0);
        }
        to {
            opacity: 0;
            transform: translateX(400px);
        }
    }

    .service-card.expanded {
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
    }
`;
document.head.appendChild(style);
