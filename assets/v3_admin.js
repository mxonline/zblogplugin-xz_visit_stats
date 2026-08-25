(function () {
  'use strict';
  document.addEventListener('alpine:init', function () {
    Alpine.data('xzvsApp', function () {
      return { navOpen: false };
    });
  });
  function text(value) { return String(value || '').replace(/[<>]/g, ''); }
  var table = document.querySelector('.xz-visit-table');
  if (table) {
    var head = table.querySelector('thead tr');
    if (head) { var th = document.createElement('th'); th.textContent = '详情'; head.appendChild(th); }
    table.querySelectorAll('tbody tr').forEach(function (row) {
      if (!row.querySelector('td') || row.children.length < 3) return;
      var cells = row.querySelectorAll('td'), buttonCell = document.createElement('td'), button = document.createElement('button');
      button.type = 'button'; button.className = 'button xzvs-drawer-open'; button.textContent = '详情';
      var snapshot = Array.prototype.map.call(cells, function (cell) { return cell.textContent.trim(); });
      button.addEventListener('click', function () { openDrawer(snapshot); });
      buttonCell.appendChild(button); row.appendChild(buttonCell);
    });
  }
  function openDrawer(values) {
    var drawer = document.getElementById('xzvs-record-drawer');
    if (!drawer) {
      drawer = document.createElement('aside'); drawer.id = 'xzvs-record-drawer'; drawer.className = 'xzvs-drawer'; drawer.setAttribute('aria-hidden', 'true');
      drawer.innerHTML = '<div class="xzvs-drawer-head"><strong>访问详情</strong><button type="button" class="button xzvs-drawer-close">关闭</button></div><div class="xzvs-drawer-body"></div>';
      document.body.appendChild(drawer); drawer.querySelector('.xzvs-drawer-close').addEventListener('click', closeDrawer);
    }
    var body = drawer.querySelector('.xzvs-drawer-body'); body.textContent = '';
    values.forEach(function (value, index) { var row = document.createElement('p'); row.innerHTML = '<strong>字段 ' + (index + 1) + '</strong> '; var span = document.createElement('span'); span.textContent = text(value); row.appendChild(span); body.appendChild(row); });
    drawer.setAttribute('aria-hidden', 'false'); drawer.classList.add('is-open');
  }
  function closeDrawer() { var drawer = document.getElementById('xzvs-record-drawer'); if (drawer) { drawer.classList.remove('is-open'); drawer.setAttribute('aria-hidden', 'true'); } }
  document.addEventListener('keydown', function (event) { if (event.key === 'Escape') closeDrawer(); });
}());
