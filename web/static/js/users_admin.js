"use strict";
function submitForm(el) {
  const submitBtn = el.querySelector('button[type="submit"]');

  if (submitBtn.disabled) {
    return false;
  }

  submitBtn.disabled = true;

  fetch(el.getAttribute("action"), {
    method: el.method,
    body: new FormData(el)
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
  });

  return false;
}
