    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script src="<?= BASE_PATH ?>/assets/js/scripts.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/plugins/jquery/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>
    <!-- Session Timeout Warning -->
    <script>
document.addEventListener('DOMContentLoaded', function() {
    const timeoutMinutes = <?php echo (SESSION_TIMEOUT / 60) - 1; ?>;
    const warningTime = timeoutMinutes * 60 * 1000;
    const logoutTime = <?php echo SESSION_TIMEOUT * 1000; ?>;

    let warningTimer;
    let logoutTimer;
    let isWarningShown = false;
    let lastActivity = Date.now();

    function resetTimers() {
        lastActivity = Date.now();
        clearTimeout(warningTimer);
        clearTimeout(logoutTimer);
        isWarningShown = false;

        // Set new timers based on current time
        warningTimer = setTimeout(showTimeoutWarning, warningTime);
        logoutTimer = setTimeout(logout, logoutTime);
    }

    function showTimeoutWarning() {
        if (isWarningShown) return;
        isWarningShown = true;

        const modal = document.createElement('div');
        modal.style.cssText =
            'position:fixed;top:20px;right:20px;background:#fff3cd;padding:15px;border:1px solid #ffeeba;border-radius:4px;z-index:9999;max-width:300px;box-shadow:0 2px 10px rgba(0,0,0,0.1);';
        modal.id = 'sessionTimeoutModal';
        modal.innerHTML = `
            <h4 style="margin-top:0;color:#856404">Sessione in scadenza</h4>
            <p>La tua sessione scadrà tra 1 minuto a causa di inattività.</p>
            <button id="extendSession" class="btn btn-primary btn-sm">Rimani connesso</button>
        `;
        document.body.appendChild(modal);
        document.getElementById('extendSession').addEventListener('click', extendSession);
    }

    function logout() {
        window.location.href = 'index.php?action=login&timeout=1';
    }

    function extendSession() {
        fetch('keepalive.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const modal = document.getElementById('sessionTimeoutModal');
                    if (modal) modal.remove();
                    resetTimers();
                }
            });
    }

    // Reset timers on any activity
    ['click', 'mousemove', 'keypress', 'scroll', 'touchstart'].forEach(event => {
        window.addEventListener(event, resetTimers, {
            passive: true
        });
    });

    // Initial setup
    resetTimers();

    // Check every minute if browser tab is active
    setInterval(() => {
        if (document.hidden) return; // Skip if tab is not active
        if (Date.now() - lastActivity > warningTime && !isWarningShown) {
            showTimeoutWarning();
        }
    }, 60000);
});
    </script>
    </body>

    </html>