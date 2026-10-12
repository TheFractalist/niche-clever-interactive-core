(function () {
  'use strict';
  document.addEventListener('submit', function (event) {
    var form = event.target;
    if (!form || !form.matches('[data-ncli-interactive]')) {
      return;
    }
    event.preventDefault();
    var status = form.parentElement.querySelector('.ncli-panel__status');
    if (status) {
      status.textContent = 'Question handling is not available in this development stage.';
    }
  });
}());
