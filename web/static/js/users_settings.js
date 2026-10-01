"use strict";
/* global $, jQuery, Choices, localization, xhrResponse, lang */

function submitForm(el) {
    const submitBtn = el.querySelector('button[type="submit"]');

    if (submitBtn.disabled) {
        return false;
    }

    submitBtn.disabled = true;

    lang = $("#lang").val();
    fetch(`translations.php?lang=${lang}`)
        .then(() => localization.setLang(lang))
        .then(() => {
            return fetch(el.getAttribute("action"), {
                method: el.method,
                body: new FormData(el),
                redirect: 'manual'
            });
        })
        .then(response => {
            // 302/303/307 на catch.php — сессия/CSRF протухли
            if (response.type === 'opaqueredirect'
                || (response.status >= 300 && response.status < 400)) {
                location.href = '.?logout=true';
                return null;
            }
            // 401 (CSRF/login), 419 (Laravel-style), 403 (disabled/denied)
            if (response.status === 401
                || response.status === 419
                || response.status === 403) {
                location.href = '.?logout=true';
                return null;
            }
            return response.text();
        })
        .then(responseText => {
            if (responseText === null) return;
            xhrResponse(responseText);
            setTimeout(() => {
                submitBtn.disabled = false;
            }, 1000);
        })
        .catch(error => {
            console.error('Error:', error);
            setTimeout(() => {
                submitBtn.disabled = false;
            }, 1000);
        })
        .finally(() => {
            createChoices();
        });

    return false;
}

function createChoices() {
    document.querySelectorAll('select').forEach(select => {
        if (select._choices) {
            select._choices.destroy();
            select._choices = null;
        }
    });

    document.querySelectorAll('select').forEach(select => {
        select._choices = new Choices(select, {
            itemSelectText: null,
            shouldSort: false,
            searchEnabled: false,
            classNames: {
                containerInner: ['choices__inner', 'choices__settings__text'],
            },
        });
    });
}

$(document).ready(function () {
    $("#lang").val(lang ?? "en");
    createChoices();
});
