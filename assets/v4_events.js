(function (window, document) {
    'use strict';

    document.addEventListener('click', function (event) {
        var button = event.target.closest('[data-xzvs-copy-event-api]');
        if (!button) return;
        var code = document.getElementById('xzvs-event-api-example');
        if (!code) return;

        var text = code.textContent || '';
        var originalLabel = button.textContent;
        function showResult(success) {
            button.textContent = success ? '已复制' : '请手动复制';
            window.setTimeout(function () { button.textContent = originalLabel; }, 1800);
        }

        if (window.navigator.clipboard && window.navigator.clipboard.writeText) {
            window.navigator.clipboard.writeText(text).then(function () {
                showResult(true);
            }).catch(function () {
                showResult(false);
            });
            return;
        }
        showResult(false);
    });
}(window, document));
