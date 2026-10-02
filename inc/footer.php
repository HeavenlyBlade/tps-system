<?php
if (!isset($pdo)) {
    require_once __DIR__ . '/../config.php';
}
$ab = defined('ASSET_BASE') ? ASSET_BASE : '';
?>
    </main>

    <footer>
        🌟 TPS System &copy; <?php echo date('Y'); ?> &nbsp;·&nbsp; Benjamine Panganiban - BSIT-III
    </footer>

    <script src="<?php echo $ab; ?>/bootstrap/js/bootstrap.bundle.min.js"></script>

    <script>
    function confirmLogout(event) {
        event.preventDefault();
        if (confirm('Are you sure you want to logout?')) {
            window.location.href = event.target.closest('a').href;
        }
        return false;
    }
    </script>

    <!-- PWA Service Worker -->
    <script>
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register('<?php echo $ab; ?>/sw.js')
            .then(() => console.log('SW registered'))
            .catch(() => console.log('SW registration failed'));
    }

    let installPrompt;
    window.addEventListener('beforeinstallprompt', (e) => {
        e.preventDefault();
        installPrompt = e;
        const btn = document.createElement('button');
        btn.className = 'btn btn-warning btn-sm position-fixed shadow';
        btn.style.cssText = 'bottom: 20px; right: 20px; z-index: 9999; border-radius: 50px; padding: 10px 20px;';
        btn.innerHTML = '📲 Install App';
        btn.onclick = () => {
            installPrompt.prompt();
            installPrompt.userChoice.then(() => btn.remove());
        };
        document.body.appendChild(btn);
    });
    </script>
</body>
</html>
