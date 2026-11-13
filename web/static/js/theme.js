function waitForUsername(callback) {
  if (typeof username !== 'undefined') {
    callback();
  } else {
    requestAnimationFrame(() => waitForUsername(callback));
  }
}

waitForUsername(() => {
  // Admin HEAD_CONFIG.username is intentionally blank (page title), so use a
  // separate account key for theme preferences without changing other scripts.
  const themeUser = (typeof HEAD_CONFIG !== 'undefined' && HEAD_CONFIG.themeUsername)
    ? HEAD_CONFIG.themeUsername : username;
  if (typeof themeUser !== 'string' || !themeUser.trim()) return;

  if (!localStorage.getItem(`${themeUser}-theme`)) {
    localStorage.setItem(`${themeUser}-theme`, "default");
  }

  if (localStorage.getItem(`${themeUser}-theme`).indexOf("dark") !== -1) {
    const link = document.createElement('link');
    link.rel = 'stylesheet';
    link.href = darkCssUrl;
    document.head.appendChild(link);
  }
});
