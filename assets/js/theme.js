/**
 * HR GO Management System - Dark Mode Theme Manager
 * Persistent Light / Dark Mode with smooth transitions
 */
(function() {
    function getPreferredTheme() {
        const savedTheme = localStorage.getItem('hr_theme');
        if (savedTheme) return savedTheme;
        return window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
    }

    function applyTheme(theme) {
        document.documentElement.setAttribute('data-theme', theme);
        localStorage.setItem('hr_theme', theme);
        updateThemeToggleIcons(theme);
    }

    function toggleTheme() {
        const current = document.documentElement.getAttribute('data-theme') || getPreferredTheme();
        const next = current === 'dark' ? 'light' : 'dark';
        applyTheme(next);
    }

    function updateThemeToggleIcons(theme) {
        document.querySelectorAll('.theme-toggle-btn').forEach(btn => {
            if (theme === 'dark') {
                btn.innerHTML = '☀️';
                btn.setAttribute('title', 'เปลี่ยนเป็นโหมดสว่าง (Light Mode)');
                btn.style.background = '#334155';
                btn.style.color = '#FDE047';
            } else {
                btn.innerHTML = '🌙';
                btn.setAttribute('title', 'เปลี่ยนเป็นโหมดมืด (Dark Mode)');
                btn.style.background = '#F1F5F9';
                btn.style.color = '#334155';
            }
        });
    }

    function toggleMobileSidebar() {
        const sidebar = document.querySelector('.sidebar');
        let backdrop = document.querySelector('.sidebar-backdrop');
        if (!backdrop) {
            backdrop = document.createElement('div');
            backdrop.className = 'sidebar-backdrop';
            backdrop.onclick = toggleMobileSidebar;
            document.body.appendChild(backdrop);
        }
        if (sidebar) sidebar.classList.toggle('active');
        backdrop.classList.toggle('active');
    }

    function toggleActionDropdown(btn, event) {
        if (event) event.stopPropagation();
        const dropdown = btn.closest('.action-dropdown');
        document.querySelectorAll('.action-dropdown.active').forEach(d => {
            if (d !== dropdown) d.classList.remove('active');
        });
        if (dropdown) dropdown.classList.toggle('active');
    }

    document.addEventListener('click', () => {
        document.querySelectorAll('.action-dropdown.active').forEach(d => d.classList.remove('active'));
    });

    // เรียกทำงานทันทีก่อน DOM Content Loaded เพื่อป้องกันหน้าจอกระพริบขาว (FOUC Flash)
    const initialTheme = getPreferredTheme();
    document.documentElement.setAttribute('data-theme', initialTheme);

    document.addEventListener('DOMContentLoaded', () => {
        updateThemeToggleIcons(initialTheme);
    });

    window.toggleTheme = toggleTheme;
    window.applyTheme = applyTheme;
    window.toggleMobileSidebar = toggleMobileSidebar;
    window.toggleActionDropdown = toggleActionDropdown;
})();
