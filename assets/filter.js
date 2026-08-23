(function () {
  'use strict';

  function setExpanded(panel, button, expanded) {
    panel.classList.toggle('is-open', expanded);
    button.setAttribute('aria-expanded', expanded ? 'true' : 'false');
    button.textContent = expanded ? '收起高级筛选' : '高级筛选';
  }

  function initPanel(panel) {
    var button = panel.querySelector('.xz-filter-toggle');
    if (!button) { return; }

    var advanced = panel.querySelector('.xz-advanced-filter');
    if (!panel.classList.contains('is-open') && advanced) {
      var fields = advanced.querySelectorAll('input, select, textarea');
      for (var fieldIndex = 0; fieldIndex < fields.length; fieldIndex++) {
        var field = fields[fieldIndex];
        var defaultValue = field.getAttribute('data-default-value');
        var hasValue = field.tagName.toLowerCase() === 'select'
          ? (defaultValue !== null ? field.value !== defaultValue : field.selectedIndex > 0)
          : field.value !== '';
        if (hasValue) {
          panel.classList.add('is-open');
          break;
        }
      }
    }

    setExpanded(panel, button, panel.classList.contains('is-open'));

    button.addEventListener('click', function () {
      setExpanded(panel, button, !panel.classList.contains('is-open'));
    });
  }

  var panels = document.querySelectorAll('.xz-filter-panel');
  for (var index = 0; index < panels.length; index++) {
    initPanel(panels[index]);
  }
}());
