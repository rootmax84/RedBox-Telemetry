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
    // The normal POST/Redirect/GET flow renders the result in the page header.
    redDialog.make({
        title: mcpTranslate('mcp.dialog.title'),
        message: mcpTranslate(key),
        btnClassSuccessText: mcpTranslate('mcp.dialog.yes'),
        btnClassFailText: mcpTranslate('mcp.dialog.no'),
        onResolve: () => {
            // Native submit bypasses onsubmit; otherwise the confirmation reopens.
            HTMLFormElement.prototype.submit.call(form);
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

/** Show the check mark only if the clipboard API confirms copying succeeded. */
async function copyMcpToken() {
    const el = document.getElementById('mcp-new-token');
    const button = document.querySelector('.mcp-secret .mcp-copy-token');
    if (!el || !button) return false;

    button.classList.remove('is-copied');
    const copyLabel = button.dataset.copyLabel;
    if (copyLabel) {
        button.setAttribute('aria-label', copyLabel);
        button.setAttribute('title', copyLabel);
    }

    let copied = false;
    try {
        if (navigator.clipboard && window.isSecureContext) {
            await navigator.clipboard.writeText(el.value);
            copied = true;
        } else {
            // Legacy browsers: execCommand explicitly reports whether copying succeeded.
            el.focus();
            el.select();
            copied = document.execCommand('copy') === true;
        }
    } catch (error) {
        // Do not show a success icon for a rejected clipboard write.
        copied = false;
    }

    if (copied) {
        button.classList.add('is-copied');
        const successLabel = button.dataset.copiedLabel;
        if (successLabel) {
            button.setAttribute('aria-label', successLabel);
            button.setAttribute('title', successLabel);
        }
    } else {
        // Allow manual copying if the clipboard API is unavailable or denied.
        el.focus();
        el.select();
    }
    return copied;
}

/** MCP status is transient; the one-time secret card must remain visible. */
function initializeMcpNoticeTimeout() {
    document.querySelectorAll('.settings-page .mcp-alert.settings-notice').forEach(notice => {
        window.setTimeout(() => {
            notice.hidden = true;
        }, 5000);
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeMcpNoticeTimeout, { once: true });
} else {
    initializeMcpNoticeTimeout();
}
