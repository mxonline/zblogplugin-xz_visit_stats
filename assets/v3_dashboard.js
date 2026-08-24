(function () {
  'use strict';
  var data = window.XZVS_DASHBOARD || {}, echarts = window.echarts;
  function tableRows(title) {
    var sections = document.querySelectorAll('.xz-overview-section'), result = [];
    Array.prototype.forEach.call(sections, function (section) {
      var h = section.querySelector('h2'), table = section.querySelector('table');
      if (!h || !table || h.textContent.indexOf(title) === -1) return;
      Array.prototype.forEach.call(table.querySelectorAll('tbody tr'), function (tr) {
        var cells = tr.querySelectorAll('td'); if (cells.length) result.push(Array.prototype.map.call(cells, function (c) { return c.textContent.trim(); }));
      });
    });
    return result;
  }
  var trendRows = tableRows('真人 / 蜘蛛趋势');
  if (!data.trend && trendRows.length) data.trend = trendRows.map(function (r) { return { label: r[0], human_pv: parseInt(r[1], 10) || 0, bot_pv: parseInt(r[2], 10) || 0, error_4xx: parseInt(r[3], 10) || 0, error_5xx: parseInt(r[4], 10) || 0 }; });
  if (!data.sources) data.sources = tableRows('来源占比').map(function (r) { return { source_type: r[0], total_pv: parseInt(r[1], 10) || 0 }; });
  if (!data.spiders) data.spiders = tableRows('蜘蛛排行').map(function (r) { return { bot_name: r[0], bot_pv: parseInt(r[1], 10) || 0 }; });
  if (!data.errors) data.errors = tableRows('错误摘要').map(function (r) { return { path: r[0], visits: parseInt(r[2], 10) || 0 }; });
  if (!echarts) return;
  function chart(id, option) {
    var node = document.getElementById(id);
    if (!node) return;
    if (!option.series || !option.series.length || option.series.every(function (s) { return !s.data || !s.data.length; })) { node.textContent = '暂无数据'; node.className += ' xzvs-empty'; return; }
    var instance = echarts.init(node); instance.setOption(option); window.addEventListener('resize', function () { instance.resize(); });
  }
  var trend = data.trend || [], labels = trend.map(function (r) { return r.label; });
  chart('xzvs-chart-trend', { tooltip: { trigger: 'axis' }, legend: { data: ['真人 PV', '蜘蛛 PV', '4xx', '5xx'] }, xAxis: { type: 'category', data: labels }, yAxis: { type: 'value' }, series: [{ name: '真人 PV', type: 'line', data: trend.map(function (r) { return r.human_pv || 0; }) }, { name: '蜘蛛 PV', type: 'line', data: trend.map(function (r) { return r.bot_pv || 0; }) }, { name: '4xx', type: 'line', data: trend.map(function (r) { return r.error_4xx || 0; }) }, { name: '5xx', type: 'line', data: trend.map(function (r) { return r.error_5xx || 0; }) }] });
  var hours = data.hours || [];
  chart('xzvs-chart-hours', { tooltip: { trigger: 'axis' }, xAxis: { type: 'category', data: hours.map(function (r) { return r.hour; }) }, yAxis: { type: 'value' }, series: [{ name: '真人 PV', type: 'bar', data: hours.map(function (r) { return r.human_pv || 0; }) }, { name: '蜘蛛 PV', type: 'bar', data: hours.map(function (r) { return r.bot_pv || 0; }) }] });
  function rows(items, name, value) { return (items || []).map(function (r) { return { name: r[name] || r.label || '-', value: Number(r[value] || r.visits || 0) }; }); }
  chart('xzvs-chart-source', { tooltip: { trigger: 'item' }, series: [{ type: 'pie', radius: ['38%', '68%'], data: rows(data.sources, 'source_type', 'total_pv') }] });
  chart('xzvs-chart-browser', { tooltip: { trigger: 'axis' }, xAxis: { type: 'category', data: rows(data.browser, 'name', 'visits').map(function (r) { return r.name; }) }, yAxis: { type: 'value' }, series: [{ type: 'bar', data: rows(data.browser, 'name', 'visits').map(function (r) { return r.value; }) }] });
  chart('xzvs-chart-spider', { tooltip: { trigger: 'axis' }, xAxis: { type: 'category', data: rows(data.spiders, 'bot_name', 'bot_pv').map(function (r) { return r.name; }) }, yAxis: { type: 'value' }, series: [{ type: 'bar', data: rows(data.spiders, 'bot_name', 'bot_pv').map(function (r) { return r.value; }) }] });
  chart('xzvs-chart-errors', { tooltip: { trigger: 'axis' }, xAxis: { type: 'category', data: rows(data.errors, 'path', 'visits').map(function (r) { return r.name; }) }, yAxis: { type: 'value' }, series: [{ type: 'bar', data: rows(data.errors, 'path', 'visits').map(function (r) { return r.value; }) }] });
  chart('xzvs-chart-rum', { tooltip: { trigger: 'axis' }, xAxis: { type: 'category', data: (data.rum || []).map(function (r) { return r.path || '-'; }) }, yAxis: { type: 'value' }, series: [{ name: 'LCP P75', type: 'bar', data: (data.rum || []).map(function (r) { return r.lcp_p75 || 0; }) }, { name: 'FCP P75', type: 'bar', data: (data.rum || []).map(function (r) { return r.fcp_p75 || 0; }) }] });
}());
