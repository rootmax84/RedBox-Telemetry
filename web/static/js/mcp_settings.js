/* MCP settings shares translations and the project redDialog confirmation UX. */
function mcpTranslate(key) {
    if (typeof localization !== 'undefined' && localization && localization.key && localization.key[key]) {
        return localization.key[key];
    }
    return (window.MCP_TEXT_FALLBACK && window.MCP_TEXT_FALLBACK[key]) || key;
}

/** Cancel native submit until redDialog resolves. Never use window.confirm(). */
function confirmMcp(event, key) {
    if (!event || !event.currentTarget) return false;
    event.preventDefault();
    const form = event.currentTarget;
    if (form.dataset.mcpConfirmationPending === '1') return false;
    form.dataset.mcpConfirmationPending = '1';
    if (typeof redDialog === 'undefined' || !redDialog || typeof redDialog.make !== 'function') {
        console.error('RedBox redDialog is not available');
        delete form.dataset.mcpConfirmationPending;
        return false;
    }
    // Resolve the confirmation directly with the existing XHR token handler.
    redDialog.make({
        title: mcpTranslate('mcp.dialog.title'),
        message: mcpTranslate(key),
        btnClassSuccessText: mcpTranslate('mcp.dialog.yes'),
        btnClassFailText: mcpTranslate('mcp.dialog.no'),
        onResolve: () => {
            void submitMcpTokenAction(form);
        },
        onReject: () => {
            delete form.dataset.mcpConfirmationPending;
        }
    }).catch(error => {
        delete form.dataset.mcpConfirmationPending;
        console.error('MCP confirmation failed', error);
    });
    return false;
}

async function copyMcpToken() {
    const el = document.getElementById('mcp-new-token');
    if (!el) return;
    try {
        if (navigator.clipboard && window.isSecureContext) {
            await navigator.clipboard.writeText(el.value);
        } else {
            el.focus(); el.select();
            document.execCommand('copy');
        }
    } catch (error) {
        el.focus(); el.select();
    }
}

/** Revoke active / delete expired tokens over XHR and display the result via xhrResponse(text). */
async function submitMcpTokenAction(form) {
    const button = form.querySelector('button[type="submit"]');
    if (button) button.disabled = true;
    try {
        // A hidden input named 'action' shadows HTMLFormElement.action.
        const endpoint = form.getAttribute('action') || '/settings/mcp/tokens';
        const response = await fetch(endpoint, {
            method: 'POST',
            body: new FormData(form),
            credentials: 'same-origin',
            redirect: 'manual',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            }
        });
        if (response.type === 'opaqueredirect' || response.status === 401 || response.status === 403) {
            location.href = '/logout';
            return;
        }
        if (!response.headers.get('content-type')?.includes('application/json')) {
            throw new Error(mcpTranslate('mcp.error.unavailable'));
        }
        const data = await response.json();
        if (!response.ok || !data.ok) {
            throw new Error(typeof data.message === 'string' ? data.message : mcpTranslate('mcp.error.unavailable'));
        }
        const requestedId = Number(form.querySelector('input[name="token_id"]')?.value);
        if (Number(data.token_id) !== requestedId || !Number.isSafeInteger(requestedId) || requestedId < 1) {
            throw new Error(mcpTranslate('mcp.error.unavailable'));
        }
        form.closest('tr')?.remove();
        if (!document.querySelector('.mcp-token-row')) {
            const tbody = document.querySelector('.mcp-table-wrap tbody');
            if (tbody) {
                const tr = document.createElement('tr');
                const td = document.createElement('td');
                td.colSpan = 7;
                td.setAttribute('l10n', 'mcp.tokens.empty');
                td.textContent = mcpTranslate('mcp.tokens.empty');
                tr.appendChild(td);
                tbody.appendChild(tr);
            }
        }
        xhrResponse(data.message);
    } catch (error) {
        serverError(error instanceof Error ? error.message : mcpTranslate('mcp.error.unavailable'));
    } finally {
        if (button) button.disabled = false;
        delete form.dataset.mcpConfirmationPending;
    }
}

/** Apply the same Choices select styling and behavior as /settings. */
function initMcpChoices() {
    const selects = document.querySelectorAll('.mcp-settings select');

    if (typeof Choices !== 'function') {
        if (typeof choicesReady === 'function') {
            choicesReady();
        }
        return;
    }

    selects.forEach(select => {
        if (select._choices) {
            return;
        }

        select._choices = new Choices(select, {
            itemSelectText: null,
            shouldSort: false,
            searchEnabled: false,
            classNames: {
                containerInner: ['choices__inner', 'choices__settings__text'],
            },
        });
    });

    if (typeof choicesReady === 'function') {
        choicesReady();
    }
}

document.addEventListener('DOMContentLoaded', initMcpChoices);

