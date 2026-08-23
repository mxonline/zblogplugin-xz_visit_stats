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

    button.addEventListener('click', function () {
      setExpanded(panel, button, !panel.classList.contains('is-open'));
    });
  }

  var panels = document.querySelectorAll('.xz-filter-panel');
  for (var index = 0; index < panels.length; index++) {
    initPanel(panels[index]);
  }
}());
