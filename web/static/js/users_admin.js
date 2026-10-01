"use strict";
function submitForm(el) {
  const submitBtn = el.querySelector('button[type="submit"]');

  if (submitBtn.disabled) {
    return false;
  }

  submitBtn.disabled = true;

  fetch(el.getAttribute("action"), {
    method: el.method,
    body: new FormData(el),
    redirect: 'manual'
  })
  .then(response => {
    if (response.type === 'opaqueredirect' || (response.status >= 300 && response.status < 400)) {
      location.href = '.?logout=true';
      return null;
    }
    if (response.status === 401 || response.status === 419 || response.status === 403) {
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
  });

  return false;
}
