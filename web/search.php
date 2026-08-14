<?php
require_once 'db.php';
require_once 'db_limits.php';
include_once 'translations.php';

$pids = [];
$pidQuery = $db->query("SELECT id, description FROM $db_pids_table ORDER BY description ASC");
if ($pidQuery) {
    while ($row = $pidQuery->fetch_assoc()) {
        $pids[] = $row;
    }
}
$db->close();

include 'head.php';
?>
<body>
    <div class="navbar navbar-default navbar-fixed-top navbar-inverse">
        <div class="fetch-data"></div>
        <?php if (!isset($_SESSION['admin']) && $limit > 0) { ?>
            <div class="new-session"><a href='.' l10n='sess.new'></a></div>
            <div class="storage-usage-img"></div>
        <?php } ?>
        <div class="container">
            <div id="theme-switch"></div>
            <div class="navbar-header">
                <a class="navbar-brand" href=".">
                    <div id="redhead">RedB<img src="static/img/logo.svg" alt style="height:11px;">x</div> Telemetry
                </a>
            </div>
        </div>
    </div>

    <div class="menu-container">
        <input type="checkbox" id="menu-toggle" class="menu-toggle"/>
        <label for="menu-toggle" class="menu-button">
            <span class="hamburger">
                <span></span>
                <span></span>
                <span></span>
            </span>
        </label>
        <label for="menu-toggle" class="menu-overlay"></label>
        <ul class="menu-list" role="menu">
          <li role="none">
            <button class="menu-item" role="menuitem" tabindex="-1"  style="color:#961911">
              <span class="icon" id="search-img"></span>
              <span l10n="search.find"></span>
            </button>
          </li>
            <li role="none">
                <button class="menu-item" role="menuitem" tabindex="-1" onclick="favoriteSessions()">
                    <span class="icon" id="fav-img"></span>
                    <span l10n="fav.btn"></span>
                </button>
            </li>
            <li role="none">
                <button class="menu-item" role="menuitem" tabindex="-1" onclick="window.location.href='del_sessions.php'">
                    <span class="icon" id="delMass-img"></span>
                    <span l10n="func.multi.del"></span>
                </button>
            </li>
            <li role="none">
                <button class="menu-item" role="menuitem" tabindex="-1" onclick="pidEdit()">
                    <span class="icon" id="editPid-img"></span>
                    <span l10n="func.pid"></span>
                </button>
            </li>
            <li role="none">
                <button class="menu-item" role="menuitem" tabindex="-1" onclick="showToken()">
                    <span class="icon" id="token-img"></span>
                    <span l10n="func.token"></span>
                </button>
            </li>
            <li role="none">
                <button class="menu-item" role="menuitem" tabindex="-1" onclick="usersSettings()">
                    <span class="icon" id="settings-img"></span>
                    <span l10n="func.settings"></span>
                </button>
            </li>
            <li role="none">
                <button class="menu-item" role="menuitem" tabindex="-1" onclick="remoteRa()">
                    <span class="icon" id="remote-ra-rbx-img"></span>
                    <span l10n="func.remote"></span>
                </button>
            </li>
            <li role="none">
                <button class="menu-item" role="menuitem" tabindex="-1" onclick="showHints()">
                    <span class="icon" id="hint-img"></span>
                    <span l10n="hint.button"></span>
                </button>
            </li>
            <li role="none">
                <button class="menu-item" role="menuitem" tabindex="-1" onclick="logout()">
                    <span class="icon" id="logout-img"></span>
                    <span l10n="logout"></span>
                </button>
            </li>
        </ul>
    </div>

    <div class="col-md-auto col-xs-12" id="right-container">

        <div class="row center-block" style="max-width:720px;">
            <form id="searchForm" class="form-horizontal">
                <table style="width:100%">
                    <tr>
                        <td style="width:10%">
                            <select id="pidSelect" name="pid" class="form-control choices-select" required>
                                <option value="" disabled selected><?= $translations[$lang]['search.select_pid'] ?></option>
                                <?php foreach ($pids as $pid): ?>
                                    <option value="<?= htmlspecialchars($pid['id']) ?>"><?= htmlspecialchars($pid['description']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td style="width:.5%"></td>
                        <td style="width:1%">
                            <select id="operatorSelect" name="operator" class="form-control choices-select">
                                <option value="=" selected>=</option>
                                <option value=">">></option>
                                <option value="<"><</option>
                                <option value=">=">>=</option>
                                <option value="<="><=</option>
                            </select>
                        </td>
                        <td style="width:.5%"></td>
                        <td style="width:5%">
                            <input type="number" step="any" class="form-control" id="valueInput" name="value" l10n-placeholder="stream.val" required>
                        </td>
                        <td style="width:.5%"></td>
                        <td style="width:0%">
                            <button type="submit" class="btn btn-info btn-sm"><?= $translations[$lang]['search.find'] ?></button>
                        </td>
                    </tr>
                </table>
            </form>
        </div>

        <div id="results-container" style="margin-top:20px">
            <table class="table table-del-merge-pid" id="results-table" style="display:none;">
                <thead>
                    <tr>
                        <th><?= $translations[$lang]['s.table.start'] ?></th>
                        <th><?= $translations[$lang]['s.table.end'] ?></th>
                        <th><?= $translations[$lang]['s.table.datapoints'] ?></th>
                        <th><?= $translations[$lang]['sel.profile'] ?></th>
                        <th><?= $translations[$lang]['fav.url'] ?></th>
                    </tr>
                </thead>
                <tbody id="results-body"></tbody>
            </table>
            <div id="loading-indicator" style="text-align:center; padding:20px; display:none;">
                <span class="spinner-border spinner-border-sm" role="status"></span>
            </div>
            <div id="no-more" style="text-align:center; padding:20px; display:none;">
                <span class="label label-default"><?= $translations[$lang]['search.no_more'] ?></span>
            </div>
            <div id="no-results" style="text-align:center; padding:20px; display:none;">
                <span class="label label-default"><?= $translations[$lang]['search.no_results'] ?></span>
            </div>
        </div>
    </div>

    <script>
        const form = document.getElementById('searchForm');
        const pidSelect = document.getElementById('pidSelect');
        const operatorSelect = document.getElementById('operatorSelect');
        const valueInput = document.getElementById('valueInput');
        const resultsBody = document.getElementById('results-body');
        const resultsTable = document.getElementById('results-table');
        const loadingIndicator = document.getElementById('loading-indicator');
        const noMore = document.getElementById('no-more');
        const noResults = document.getElementById('no-results');

        let currentPage = 1;
        let totalResults = 0;
        let hasMore = false;
        let isLoading = false;
        let currentParams = null;

        function formatSessionDate(timestampMs, lang) {
            const ts = Math.floor(timestampMs / 1000);
            const date = new Date(ts * 1000);
            const months = {
                'Jan': localization.key['month.jan'] || 'Jan',
                'Feb': localization.key['month.feb'] || 'Feb',
                'Mar': localization.key['month.mar'] || 'Mar',
                'Apr': localization.key['month.apr'] || 'Apr',
                'May': localization.key['month.may'] || 'May',
                'Jun': localization.key['month.jun'] || 'Jun',
                'Jul': localization.key['month.jul'] || 'Jul',
                'Aug': localization.key['month.aug'] || 'Aug',
                'Sep': localization.key['month.sep'] || 'Sep',
                'Oct': localization.key['month.oct'] || 'Oct',
                'Nov': localization.key['month.nov'] || 'Nov',
                'Dec': localization.key['month.dec'] || 'Dec'
            };
            const monthKey = date.toLocaleString('en', { month: 'short' });
            const month = months[monthKey] || monthKey;
            const timeFormat = document.cookie.replace(/(?:(?:^|.*;\s*)timeformat\s*\=\s*([^;]*).*$)|^.*$/, "$1") || '24';
            const fmt = timeFormat === '12' ? 'd, Y h:ia' : 'd, Y H:i';
            const day = String(date.getDate()).padStart(2, '0');
            const year = date.getFullYear();
            let hours = date.getHours();
            let minutes = String(date.getMinutes()).padStart(2, '0');
            let ampm = '';
            if (timeFormat === '12') {
                ampm = hours >= 12 ? 'pm' : 'am';
                hours = hours % 12 || 12;
            }
            const timeStr = timeFormat === '12' ? `${hours}:${minutes}${ampm}` : `${String(hours).padStart(2, '0')}:${minutes}`;
            return `${month} ${day}, ${year} ${timeStr}`;
        }

        function renderRows(data) {
            data.forEach(session => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td>${formatSessionDate(session.time, '${lang}')}</td>
                    <td>${session.timeend ? formatSessionDate(session.timeend, '${lang}') : '-'}</td>
                    <td>${session.sessionsize}</td>
                    <td>${escapeHtml(session.profileName || '-')}</td>
                    <td><a href="index.php?id=${encodeURIComponent(session.session)}"><?= $translations[$lang]['fav.open'] ?></a></td>
                `;
                resultsBody.appendChild(tr);
            });
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function clearResults() {
            resultsBody.innerHTML = '';
            resultsTable.style.display = 'none';
            noMore.style.display = 'none';
            noResults.style.display = 'none';
            currentPage = 1;
            hasMore = false;
            totalResults = 0;
        }

        function showError(msg) {
            xhrResponse(escapeHtml(msg));
        }

        function isPageScrollable() {
            return document.documentElement.scrollHeight > window.innerHeight;
        }

        function loadPage(page) {
            if (isLoading) return;
            if (page > 1 && !hasMore) return;

            isLoading = true;
            loadingIndicator.style.display = 'block';
            $('.fetch-data').css('display', 'block');

            const params = new FormData();
            params.append('pid', currentParams.pid);
            params.append('operator', currentParams.operator);
            params.append('value', currentParams.value);
            params.append('page', page);

            fetch('search_processor.php', {
                method: 'POST',
                body: params
            })
            .then(response => response.json())
            .then(data => {
                isLoading = false;
                loadingIndicator.style.display = 'none';
                $('.fetch-data').css('display', 'none');

                if (data.error) {
                    showError(data.error);
                    resultsTable.style.display = 'none';
                    return;
                }

                if (page === 1 && data.total === 0) {
                    noResults.style.display = 'block';
                    resultsTable.style.display = 'none';
                    return;
                }

                if (page === 1) {
                    resultsTable.style.display = 'table';
                }

                if (data.data && data.data.length) {
                    renderRows(data.data);
                }

                totalResults = data.total;
                hasMore = data.hasMore;

                if (!hasMore && totalResults > 0) {
                    noMore.style.display = 'block';
                } else {
                    noMore.style.display = 'none';
                }

                currentPage = page;

                if (hasMore && !isPageScrollable()) {
                    loadPage(currentPage + 1);
                }
            })
            .catch(err => {
                isLoading = false;
                loadingIndicator.style.display = 'none';
                $('.fetch-data').css('display', 'none');
                showError(err.message);
            });
        }

        form.addEventListener('submit', function(e) {
            e.preventDefault();

            const pid = pidSelect.value;
            const operator = operatorSelect.value;
            const value = valueInput.value.trim();

            if (!pid) {
                showError('<?= $translations[$lang]['search.error_no_pid'] ?>');
                return;
            }
            if (!value || isNaN(value)) {
                showError('<?= $translations[$lang]['search.error_invalid_value'] ?>');
                return;
            }

            currentParams = { pid, operator, value };

            clearResults();

            loadPage(1);
        });

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting && !isLoading && hasMore) {
                    loadPage(currentPage + 1);
                }
            });
        }, { rootMargin: '0px 0px 100px 0px' });

        const target = document.createElement('div');
        target.id = 'scroll-trigger';
        target.style.height = '1px';
        document.getElementById('results-container').appendChild(target);
        observer.observe(target);

        document.addEventListener('DOMContentLoaded', function() {
            if (typeof Choices !== 'undefined') {
                document.querySelectorAll('.choices-select').forEach(el => new Choices(el, {
                    searchEnabled: true,
                    searchFloor: 2,
                    itemSelectText: ''
                }));
            }
        });
    </script>
</body>
</html>
