if (!localStorage.getItem("theme")) localStorage.setItem("theme", "default");

if (localStorage.getItem("theme").indexOf("dark") !== -1) {
 document.head.innerHTML += '<link rel="stylesheet" href="/static/css/dark.css">';
}