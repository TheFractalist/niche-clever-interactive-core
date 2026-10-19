(function () {
  'use strict';

  // At most one help note is open at a time. Hover and keyboard focus show a note briefly; a click
  // "pins" it so it stays until the same button is clicked again, another note opens, the viewer
  // clicks elsewhere or presses Escape.
  function setOpen(button, open) {
    var id = button.getAttribute('aria-controls');
    var help = id ? document.getElementById(id) : null;
    if (!help) {
      return;
    }
    if (open) {
      closeAll(button);
    } else {
      button.removeAttribute('data-ncli-pinned');
    }
    button.setAttribute('aria-expanded', open ? 'true' : 'false');
    help.hidden = !open;
  }

  function closeAll(except) {
    Array.prototype.forEach.call(document.querySelectorAll('.ncli-admin__help-toggle[aria-expanded="true"]'), function (other) {
      if (other !== except) {
        setOpen(other, false);
      }
    });
  }

  document.addEventListener('focusin', function (event) {
    var button = event.target;
    if (button && button.matches && button.matches('.ncli-admin__help-toggle')) {
      setOpen(button, true);
    }
  });

  document.addEventListener('focusout', function (event) {
    var button = event.target;
    if (!button || !button.matches || !button.matches('.ncli-admin__help-toggle')) {
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
    var target = event.target;
    var button = target && target.closest ? target.closest('.ncli-admin__help-toggle') : null;
    if (!button) {
      // A click anywhere outside a help control closes every open note.
      if (!(target && target.closest && target.closest('.ncli-admin__field-help'))) {
        closeAll(null);
      }
      return;
    }
    event.preventDefault();
    if (button.getAttribute('data-ncli-pinned') === 'true') {
      setOpen(button, false);
      return;
    }
    setOpen(button, true);
    button.setAttribute('data-ncli-pinned', 'true');
  });

  // Choosing a colour means the owner no longer wants the panel style's own accent.
  document.addEventListener('input', function (event) {
    var picker = event.target;
    if (!picker || !picker.matches('.ncli-admin__colour')) {
      return;
    }
    var useDefault = document.getElementById('ncli-accent_color_default');
    if (useDefault) {
      useDefault.checked = false;
    }
  });

  var closeTimers = new WeakMap();

  function toggleFor(target) {
    var wrapper = target && target.closest ? target.closest('.ncli-admin__field-label') : null;
    return wrapper ? wrapper.querySelector('.ncli-admin__help-toggle') : null;
  }

  document.addEventListener('mouseover', function (event) {
    var button = toggleFor(event.target);
    if (!button) {
      return;
    }
    window.clearTimeout(closeTimers.get(button));
    setOpen(button, true);
  });

  document.addEventListener('mouseout', function (event) {
    var button = toggleFor(event.target);
    if (!button) {
      return;
    }
    closeTimers.set(button, window.setTimeout(function () {
      var wrapper = button.closest('.ncli-admin__field-label');
      if (wrapper && !wrapper.contains(document.activeElement) && button.getAttribute('data-ncli-pinned') !== 'true') {
        setOpen(button, false);
      }
    }, 200));
  });

  function syncEfforts() {
    var model = document.getElementById('ncli-model_id');
    var effort = document.getElementById('ncli-model_reasoning_effort');
    if (!model || !effort || !model.selectedOptions.length) {
      return;
    }
    var raw = model.selectedOptions[0].getAttribute('data-efforts') || '';
    var allowed = raw === '' ? [] : raw.split(',');
    Array.prototype.forEach.call(effort.options, function (option) {
      var valid = option.value === 'default' || allowed.indexOf(option.value) !== -1;
      option.hidden = !valid;
      option.disabled = !valid;
    });
    if (effort.selectedOptions.length && effort.selectedOptions[0].disabled) {
      effort.value = 'default';
    }
    var row = effort.closest('tr');
    if (row) {
      row.hidden = allowed.length === 0;
    }
  }

  document.addEventListener('change', function (event) {
    if (event.target && event.target.id === 'ncli-model_id') {
      syncEfforts();
    }
  });

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', syncEfforts);
  } else {
    syncEfforts();
  }

  document.addEventListener('keydown', function (event) {
    if (event.key !== 'Escape') {
      return;
    }
    closeAll(null);
  });
}());
