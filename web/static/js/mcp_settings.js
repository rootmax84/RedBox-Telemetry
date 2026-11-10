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

