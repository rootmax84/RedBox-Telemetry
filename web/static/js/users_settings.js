"use strict";
/* global $, jQuery, Choices, localization, xhrResponse, lang */

function submitForm(el) {
    const submitBtn = el.querySelector('button[type="submit"]');

    if (submitBtn.disabled) {
        return false;
    }

    submitBtn.disabled = true;

    lang = $("#lang").val();
    fetch(`/translations?lang=${lang}`)
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
                location.href = '/logout';
                return null;
            }
            // 401 (CSRF/login), 419 (Laravel-style), 403 (disabled/denied)
            if (response.status === 401
                || response.status === 419
                || response.status === 403) {
                location.href = '/logout';
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

(function () {
  const SELECTOR = 'label[for="api_gps"]';
  const LINK_HREF = '/api/stream';

  let observer;

  function normalize() {
    const label = document.querySelector(SELECTOR);
    if (!label) return;

    const links = label.querySelectorAll(`a[href="${LINK_HREF}"]`);
    const clone = label.cloneNode(true);
    clone.querySelectorAll('a').forEach(a => a.remove());
    const bare = clone.textContent;

    const hasBareApi = /(^|\s)API(\s|$)/.test(bare);
    const isCorrect = links.length === 1 && !hasBareApi;
    if (isCorrect) return;

    const base = bare.replace(/\s*API\s*$/, '').replace(/\s+$/, '');

    const frag = document.createDocumentFragment();
    if (base) frag.append(document.createTextNode(base + ' '));

    const a = document.createElement('a');
    a.href = LINK_HREF;
    a.target = '_blank';
    a.rel = 'noopener noreferrer';
    a.textContent = 'API';
    frag.append(a);

    observer?.disconnect();
    label.replaceChildren(frag);
    observer?.observe(label, { childList: true, subtree: true, characterData: true });
  }

  document.addEventListener('DOMContentLoaded', () => {
    observer = new MutationObserver(normalize);
    normalize();
    const label = document.querySelector(SELECTOR);
    if (label) {
      observer.observe(label, { childList: true, subtree: true, characterData: true });
    }
  });
})();

$(document).ready(function () {
    $("#lang").val(lang ?? "en");
    createChoices();
});
