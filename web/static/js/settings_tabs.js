'use strict';

/** Accessible, history-aware tabs for the unified Settings screen.
 * Reuses the .tabs/.tab/.tab-content styles from Device Settings.
 */
document.addEventListener('DOMContentLoaded', () => {
    const root = document.querySelector('.settings-shell');
    if (!root) return;

    const tabs = Array.from(root.querySelectorAll('.settings-tabs [role="tab"]'));
    const panels = new Map(tabs.map(tab => [tab.dataset.tab, document.getElementById(tab.dataset.tab)]));
    const defaultTab = 'settings-general';

    function tabFromUrl() {
        const requested = new URLSearchParams(window.location.search).get('tab');
        const normalized = requested === 'connections' ? 'mcp' : (requested || 'general');
        const id = `settings-${normalized}`;
        return panels.has(id) ? id : defaultTab;
    }

    function activate(id, updateHistory = false, focus = false) {
        if (!panels.has(id)) return;
        for (const tab of tabs) {
            const active = tab.dataset.tab === id;
            tab.classList.toggle('active', active);
            tab.setAttribute('aria-selected', String(active));
            tab.tabIndex = active ? 0 : -1;
            const panel = panels.get(tab.dataset.tab);
            panel.classList.toggle('active', active);
            panel.hidden = !active;
            if (active && focus) tab.focus();
        }
        if (updateHistory) {
            const url = new URL(window.location.href);
            if (id === defaultTab) url.searchParams.delete('tab');
            else url.searchParams.set('tab', id.slice('settings-'.length));
            window.history.pushState(null, '', url);
        }
    }

    tabs.forEach((tab, index) => {
        tab.addEventListener('click', () => activate(tab.dataset.tab, true));
        tab.addEventListener('keydown', event => {
            let next;
            switch (event.key) {
                case 'ArrowRight': case 'ArrowDown': next = (index + 1) % tabs.length; break;
                case 'ArrowLeft': case 'ArrowUp': next = (index + tabs.length - 1) % tabs.length; break;
                case 'Home': next = 0; break;
                case 'End': next = tabs.length - 1; break;
                default: return;
            }
            event.preventDefault();
            activate(tabs[next].dataset.tab, true, true);
        });
    });
    window.addEventListener('popstate', () => activate(tabFromUrl()));
    activate(tabFromUrl());
});
