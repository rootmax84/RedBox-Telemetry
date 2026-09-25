(function() {
    const CHECK_INTERVAL = 10000;
    function checkMaintenance() {
        fetch('maintenance.php', { method: 'HEAD' })
            .then(response => {
                if (response.status === 200) {
                    window.location.href = '/';
                } else {
                    setTimeout(checkMaintenance, CHECK_INTERVAL);
                }
            })
            .catch(() => {
                setTimeout(checkMaintenance, CHECK_INTERVAL);
            });
    }
    checkMaintenance();
})();
