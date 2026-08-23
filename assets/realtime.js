(function () {
  'use strict';
  var config = window.XZVisitStatsRealtime;
  if (!config) { return; }
  var table = document.getElementById('xz-realtime-body');
  var updated = document.getElementById('xz-realtime-updated');
  var button = document.getElementById('xz-realtime-refresh');
  var refreshSeconds = 30;

  function escapeText(value) {
    var node = document.createElement('div');
    node.textContent = value === null || value === undefined ? '' : String(value);
    return node.innerHTML;
  }

  function statusClass(status) {
    var group = Math.floor(Number(status) / 100);
    return group >= 2 && group <= 5 ? 'xz-status-' + group + 'xx' : 'xz-status-other';
  }

  function render(rows, generatedAt) {
    if (!table) { return; }
    if (!rows || rows.length === 0) {
      table.innerHTML = '<tr><td colspan="7" class="tdCenter">暂无实时访问记录</td></tr>';
    } else {
      table.innerHTML = rows.map(function (row) {
        var type = Number(row.is_bot) === 1 ? '<span class="xz-type xz-type-bot">蜘蛛</span>' : '<span class="xz-type xz-type-human">普通访客</span>';
        var bot = Number(row.is_bot) === 1 ? (row.bot_name || '未知蜘蛛') : '-';
        var browser = row.browser || '-';
        var time = row.visited_at ? new Date(Number(row.visited_at) * 1000).toLocaleString('sv-SE').replace('T', ' ') : '-';
        return '<tr><td>' + escapeText(time) + '</td><td>' + escapeText(row.ip || '-') + '</td><td><span class="xz-cell-clip" title="' + escapeText(row.path || '') + '">' + escapeText(row.path || '-') + '</span></td><td>' + type + '</td><td><span class="xz-status ' + statusClass(row.status_code) + '">' + escapeText(row.status_code) + '</span></td><td>' + escapeText(browser) + '</td><td>' + escapeText(bot) + '</td></tr>';
      }).join('');
    }
    if (updated && generatedAt) {
      updated.textContent = '更新于 ' + new Date(Number(generatedAt) * 1000).toLocaleString('sv-SE').replace('T', ' ');
    }
  }

  function refresh() {
    if (button) { button.disabled = true; }
    var url = config.endpoint + (config.endpoint.indexOf('?') === -1 ? '?' : '&') + 'ajax=1&limit=' + encodeURIComponent(config.limit);
    fetch(url, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
      .then(function (response) { if (!response.ok) { throw new Error('request failed'); } return response.json(); })
      .then(function (payload) { render(payload.rows || [], payload.generated_at); })
      .catch(function () { if (updated) { updated.textContent = '刷新失败，请稍后重试。'; } })
      .then(function () { if (button) { button.disabled = false; } });
  }

  if (button) { button.addEventListener('click', refresh); }
  window.setInterval(refresh, refreshSeconds * 1000);
}());
