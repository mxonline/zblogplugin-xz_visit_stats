(function () {
  'use strict';
  document.addEventListener('click', function (event) {
    var target = event.target;
    if (!target || typeof target.closest !== 'function') { return; }
    var button = target.closest('.xz-detail-toggle');
    if (!button) { return; }
    var row = document.getElementById(button.getAttribute('data-target'));
    if (!row) { return; }
    var willOpen = row.hasAttribute('hidden');
    if (willOpen) { row.removeAttribute('hidden'); } else { row.setAttribute('hidden', 'hidden'); }
    button.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
    button.textContent = willOpen ? '收起' : '详情';
  });
}());
