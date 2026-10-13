(function () {
  'use strict';

  document.addEventListener('keydown', function (event) {
    if (event.key !== 'Escape') { return; }
    var close = document.querySelector('.xzvs-session-detail-close');
    if (close) { close.click(); }
  });
}());
