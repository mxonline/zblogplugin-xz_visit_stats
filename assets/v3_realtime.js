(function () {
  'use strict';
  var root = document.getElementById('xzvs-realtime');
  if (!root || !window.fetch) return;
  var state = root.querySelector('.xzvs-realtime-state');
  var busy = false;
  function escape(value) { var span = document.createElement('span'); span.textContent = String(value || ''); return span.innerHTML; }
  function refresh() {
    if (busy || document.hidden) return;
    busy = true;
    fetch('realtime_api.php', { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
      .then(function (response) { if (!response.ok) throw new Error('http'); return response.json(); })
      .then(function (data) {
        ['pv','uv','ip','bot_pv'].forEach(function (key) { var el=root.querySelector('[data-realtime="'+key+'"]'); if (el) el.textContent=Number(data.active[key] || 0).toLocaleString(); });
        var tbody=root.querySelector('tbody'); if (tbody) tbody.innerHTML=(data.rows || []).map(function(r){ return '<tr><td>'+escape(r.visited_at)+'</td><td>'+escape(r.ip)+'</td><td>'+(r.is_bot ? '蜘蛛':'真人')+'</td><td>'+escape(r.bot_name || '-')+'</td><td>'+escape(r.path)+'</td><td>'+escape(r.browser || '-')+'</td><td>'+Number(r.status_code || 0)+'</td></tr>'; }).join('') || '<tr><td colspan="7">暂无实时数据</td></tr>';
        state.textContent = '已更新'; state.className = 'xzvs-realtime-state';
      }).catch(function () { state.textContent = '更新失败，将自动重试。'; state.className = 'xzvs-realtime-state is-error'; })
      .finally(function () { busy = false; });
  }
  document.addEventListener('visibilitychange', function () { if (!document.hidden) refresh(); });
  window.setInterval(refresh, 30000); refresh();
}());
