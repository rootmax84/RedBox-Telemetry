function waitForUsername(callback) {
  if (typeof username !== 'undefined') {
    callback();
  } else {
    requestAnimationFrame(() => waitForUsername(callback));
  }
}

waitForUsername(() => {
  if (!localStorage.getItem(`${username}-theme`)) {
    localStorage.setItem(`${username}-theme`, "default");
  }

  if (localStorage.getItem(`${username}-theme`).indexOf("dark") !== -1) {
    const link = document.createElement('link');
    link.rel = 'stylesheet';
    link.href = darkCssUrl;
    document.head.appendChild(link);
  }
});
