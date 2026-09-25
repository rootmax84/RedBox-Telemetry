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
            });
        })
        .then(response => response.text())
        .then(responseText => {
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
