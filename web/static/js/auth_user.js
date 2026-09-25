$(document).ready(function(){
    const interval = setInterval(() => {
        if (l10n_loaded) {
            $("#login-form").css({"opacity":"1"});
            clearInterval(interval);
        }
    }, 100);

    $(".form-group").submit((e)=>{
        $("#login-btn").prop('disabled', true);
        $("#login-btn").html('<svg xmlns="http://www.w3.org/2000/svg" width="30" height="30" viewBox="0 0 24 24"><circle cx="18" cy="12" r="0" fill="currentColor"><animate attributeName="r" begin=".67" calcMode="spline" dur="1.5s" keySplines="0.2 0.2 0.4 0.8;0.2 0.2 0.4 0.8;0.2 0.2 0.4 0.8" repeatCount="indefinite" values="0;2;0;0"/></circle><circle cx="12" cy="12" r="0" fill="currentColor"><animate attributeName="r" begin=".33" calcMode="spline" dur="1.5s" keySplines="0.2 0.2 0.4 0.8;0.2 0.2 0.4 0.8;0.2 0.2 0.4 0.8" repeatCount="indefinite" values="0;2;0;0"/></circle><circle cx="6" cy="12" r="0" fill="currentColor"><animate attributeName="r" begin="0" calcMode="spline" dur="1.5s" keySplines="0.2 0.2 0.4 0.8;0.2 0.2 0.4 0.8;0.2 0.2 0.4 0.8" repeatCount="indefinite" values="0;2;0;0"/></circle></svg>');
    });

  const langSwitch = document.getElementById('lang-switch');
  const selectedLang = document.getElementById('selected-lang');
  const langOptions = document.getElementById('lang-options');

  function closeDropdown() {
    langOptions.classList.remove('show');
  }

  selectedLang.addEventListener('click', function(event) {
    event.stopPropagation();
    if (langOptions.classList.contains('show')) {
      closeDropdown();
    } else {
      langOptions.classList.add('show');
    }
  });

  langOptions.querySelectorAll('li').forEach(option => {
    option.addEventListener('click', function() {
      const selectedValue = this.getAttribute('data-value');
      const selectedText = this.textContent;
      closeDropdown();
          fetch(`translations.php?lang=${selectedValue}`)
        .then(() => {
          return localization.setLang(selectedValue);
        })
        .catch(error => {
          console.error('Error:', error);
        });
    });
  });

  document.addEventListener('click', function(event) {
    if (!langSwitch.contains(event.target)) {
      closeDropdown();
    }
  });
});
