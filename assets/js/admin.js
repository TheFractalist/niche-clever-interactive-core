(function () {
  'use strict';

  function setOpen(button, open) {
    var id = button.getAttribute('aria-controls');
    var help = id ? document.getElementById(id) : null;
    if (!help) {
      return;
    }
    button.setAttribute('aria-expanded', open ? 'true' : 'false');
    help.hidden = !open;
  }

  document.addEventListener('focusin', function (event) {
    var button = event.target;
    if (button && button.matches('.ncli-admin__help-toggle')) {
      setOpen(button, true);
    }
  });

  document.addEventListener('focusout', function (event) {
    var button = event.target;
    if (!button || !button.matches('.ncli-admin__help-toggle')) {
      return;
    }
    window.setTimeout(function () {
      var wrapper = button.closest('.ncli-admin__field-label');
      if (wrapper && !wrapper.contains(document.activeElement)) {
        setOpen(button, false);
      }
    }, 0);
  });

  document.addEventListener('click', function (event) {
    var button = event.target;
    if (!button || !button.matches('.ncli-admin__help-toggle')) {
      return;
    }
    event.preventDefault();
    setOpen(button, true);
  });

  document.addEventListener('keydown', function (event) {
    if (event.key !== 'Escape') {
      return;
    }
    var button = document.activeElement;
    if (button && button.matches('.ncli-admin__help-toggle')) {
      setOpen(button, false);
    }
  });
}());
