/* global $, jQuery, localization, checkCfg, l10n_loaded,
   copyToClipboard, redDialog, serverError, REMOTE_CONFIG */

// --------------------------------------------------------------
// MCU data → localStorage (was: top of inline <script>)
// --------------------------------------------------------------
let data = REMOTE_CONFIG.dataStr
    ? JSON.parse('[' + REMOTE_CONFIG.dataStr + ']')
    : [];
let stor_data = JSON.parse(localStorage.getItem("data") || "[]");

if (JSON.stringify(data) !== JSON.stringify(stor_data)) {
    localStorage.setItem("data", JSON.stringify(data));
    stor_data = JSON.parse(JSON.stringify(data));
}

const token = REMOTE_CONFIG.token;

// --------------------------------------------------------------
// Tabs, lang-switch, drag-n-drop (was: DOMContentLoaded block)
// --------------------------------------------------------------
document.addEventListener('DOMContentLoaded', function () {
    const interval = setInterval(() => {
        if (l10n_loaded) {
            $('.container-remote').css('display', 'block');
            $('.fetch-data').css('display', 'none');
            clearInterval(interval);
        }
    }, 100);

    const tabs = document.querySelectorAll('.tab');
    const tabContents = document.querySelectorAll('.tab-content');

    tabs.forEach(tab => {
        tab.addEventListener('click', () => {
            const tabId = tab.getAttribute('data-tab');

            tabs.forEach(t => t.classList.remove('active'));
            tabContents.forEach(tc => tc.classList.remove('active'));

            tab.classList.add('active');
            document.getElementById(tabId).classList.add('active');
        });
    });

    if (REMOTE_CONFIG.hasUid) {
        const langSwitch   = document.getElementById('lang-switch');
        const selectedLang = document.getElementById('selected-lang');
        const langOptions  = document.getElementById('lang-options');

        function closeDropdown() {
            langOptions.classList.remove('show');
        }

        selectedLang.addEventListener('click', function (event) {
            event.stopPropagation();
            if (langOptions.classList.contains('show')) {
                closeDropdown();
            } else {
                langOptions.classList.add('show');
            }
        });

        langOptions.querySelectorAll('li').forEach(option => {
            option.addEventListener('click', function () {
                const selectedValue = this.getAttribute('data-value');
                closeDropdown();

                fetch(`translations.php?lang=${selectedValue}`)
                    .then(() => {
                        localization.setLang(selectedValue);
                    })
                    .catch(error => {
                        console.error('Error:', error);
                    });
            });
        });

        document.addEventListener('click', function (event) {
            if (!langSwitch.contains(event.target)) {
                closeDropdown();
            }
        });
    }

    let dropArea = document.getElementById('log');
    let fl = document.getElementById('cfgFile');

    dropArea.addEventListener('drop', drop);
    dropArea.addEventListener('dragover', dragover);
    dropArea.addEventListener('dragleave', dragleave);

    function drop(event) {
        event.preventDefault();
        dropArea.style.border = '';
        fl.files = event.dataTransfer.files;
        checkCfg();
    }

    function dragover(event) {
        event.preventDefault();
        dropArea.style.borderColor = '#0eff00';
    }

    function dragleave(event) {
        event.preventDefault();
        dropArea.style.borderColor = '';
    }
});

// --------------------------------------------------------------
// shareRemote() — defined only when NOT viewing a shared remote
// (was: <?php if (!isset($uid)) { ?> block)
// --------------------------------------------------------------
if (!REMOTE_CONFIG.hasUid) {
    window.shareRemote = function () {
        const uid = REMOTE_CONFIG.shareUid;
        $(".fetch-data").css("display", "block");
        $(".share-img").css("pointer-events", "none");

        fetch('sign.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({uid})
        })
        .then(response => response.json())
        .then(result => {
            if (result.signature) {
                const sig = result.signature;
                const url = `${window.location.origin}/share_remote.php?uid=${encodeURIComponent(uid)}&sig=${sig}`;
                if (navigator.share) {
                    return navigator.share({
                        text: '',
                        url: url,
                    }).catch((err) => {
                        if (err.name !== 'AbortError') throw err;
                    }).finally(() => {
                        $(".fetch-data").css("display", "none");
                        $(".share-img").css("pointer-events", "auto");
                    });
                } else {
                    $(".fetch-data").css("display", "none");
                    $(".share-img").css("pointer-events", "auto");
                    let dialogOpt = {
                        title: localization.key['dialog.confirm'],
                        message: localization.key['share.dialog.text'],
                        btnClassSuccessText: localization.key['btn.yes'],
                        btnClassFailText: localization.key['btn.no'],
                        btnClassFail: "btn btn-info btn-sm",
                        onResolve: function () {
                            copyToClipboard(url);
                        }
                    };
                    redDialog.make(dialogOpt);
                }
            } else {
                $(".fetch-data").css("display", "none");
                serverError(result.error);
            }
        })
        .catch(err => {
            $(".fetch-data").css("display", "none");
            serverError(err);
        });
    };
}
