(function () {
  'use strict';
  var data = window.XZVisitStatsSpider;
  if (!data) { return; }

  function svg(tag, attrs) {
    var node = document.createElementNS('http://www.w3.org/2000/svg', tag);
    Object.keys(attrs || {}).forEach(function (key) { node.setAttribute(key, attrs[key]); });
    return node;
  }
  function empty(container) {
    container.textContent = '该时间范围暂无蜘蛛抓取数据';
    container.classList.add('xz-chart-empty');
  }
  function lineChart(id, rows) {
    var container = document.getElementById(id); if (!container) { return; }
    var max = 0; rows.forEach(function (row) { max = Math.max(max, row.visits); });
    if (!max) { empty(container); return; }
    var width = 760, height = 240, left = 40, right = 16, top = 18, bottom = 36, plotW = width - left - right, plotH = height - top - bottom;
    var chart = svg('svg', { viewBox: '0 0 ' + width + ' ' + height, role: 'img', 'aria-label': '蜘蛛抓取趋势' });
    [0, 0.5, 1].forEach(function (ratio) { var y = top + plotH - plotH * ratio; chart.appendChild(svg('line', { x1: left, y1: y, x2: width - right, y2: y, class: 'xz-chart-grid' })); var text = svg('text', { x: left - 6, y: y + 4, 'text-anchor': 'end', class: 'xz-chart-axis' }); text.textContent = Math.round(max * ratio); chart.appendChild(text); });
    var points = rows.map(function (row, index) { var x = left + (rows.length === 1 ? plotW / 2 : plotW * index / (rows.length - 1)); var y = top + plotH - row.visits / max * plotH; return x.toFixed(1) + ',' + y.toFixed(1); }).join(' ');
    chart.appendChild(svg('polyline', { points: points, fill: 'none', stroke: '#9a6ac5', 'stroke-width': '2.5', class: 'xz-chart-line' }));
    var step = Math.max(1, Math.ceil(rows.length / 8));
    rows.forEach(function (row, index) { if (index % step !== 0 && index !== rows.length - 1) { return; } var x = left + (rows.length === 1 ? plotW / 2 : plotW * index / (rows.length - 1)); var text = svg('text', { x: x, y: height - 12, 'text-anchor': 'middle', class: 'xz-chart-axis' }); text.textContent = row.label; chart.appendChild(text); });
    container.appendChild(chart);
  }
  function verticalBars(id, rows, label) {
    var container = document.getElementById(id); if (!container) { return; }
    var max = 0; rows.forEach(function (row) { max = Math.max(max, row.visits); });
    if (!max) { empty(container); return; }
    var width = 760, height = 220, left = 26, right = 14, top = 12, bottom = 30, plotW = width - left - right, plotH = height - top - bottom, group = plotW / rows.length;
    var chart = svg('svg', { viewBox: '0 0 ' + width + ' ' + height, role: 'img', 'aria-label': label });
    rows.forEach(function (row, index) { var h = row.visits / max * plotH, w = Math.max(2, group * .62), x = left + index * group + (group - w) / 2; chart.appendChild(svg('rect', { x: x, y: top + plotH - h, width: w, height: h, fill: '#3a6ea5', rx: 1 })); if (index % 3 === 0) { var text = svg('text', { x: x + w / 2, y: height - 10, 'text-anchor': 'middle', class: 'xz-chart-axis' }); text.textContent = row.label; chart.appendChild(text); } });
    container.appendChild(chart);
  }
  function distribution(id, rows) {
    var container = document.getElementById(id); if (!container) { return; }
    var max = 0; rows.forEach(function (row) { max = Math.max(max, row.visits); });
    if (!max) { empty(container); return; }
    var height = Math.max(150, rows.length * 30 + 24), width = 500, left = 130, right = 50, top = 12, usable = width - left - right;
    var chart = svg('svg', { viewBox: '0 0 ' + width + ' ' + height, role: 'img', 'aria-label': '蜘蛛类型分布' });
    rows.forEach(function (row, index) { var y = top + index * 30, bar = row.visits / max * usable; var label = svg('text', { x: left - 8, y: y + 15, 'text-anchor': 'end', class: 'xz-chart-axis' }); label.textContent = row.name; chart.appendChild(label); chart.appendChild(svg('rect', { x: left, y: y + 3, width: bar, height: 16, fill: '#9a6ac5', rx: 2 })); var value = svg('text', { x: left + bar + 6, y: y + 15, class: 'xz-chart-axis' }); value.textContent = row.visits; chart.appendChild(value); });
    container.appendChild(chart);
  }
  lineChart('xz-spider-trend-chart', data.trend.items || []);
  verticalBars('xz-spider-hour-chart', data.hours || [], '24 小时蜘蛛抓取量');
  distribution('xz-spider-distribution-chart', data.distribution || []);
}());
