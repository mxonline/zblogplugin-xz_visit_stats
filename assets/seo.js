(function () {
  'use strict';

  var data = window.XZVisitStatsSeo;
  if (!data) { return; }

  function svg(tag, attrs) {
    var node = document.createElementNS('http://www.w3.org/2000/svg', tag);
    Object.keys(attrs).forEach(function (key) { node.setAttribute(key, attrs[key]); });
    return node;
  }

  function setEmpty(container, message) {
    container.textContent = message;
    container.classList.add('xz-chart-empty');
  }

  function number(value) { return Number(value) || 0; }

  function lineChart(id, rows) {
    var container = document.getElementById(id);
    if (!container) { return; }
    var max = 0;
    rows.forEach(function (row) { max = Math.max(max, number(row.visits)); });
    if (!max) { setEmpty(container, '该时间范围暂无蜘蛛抓取数据'); return; }

    var width = 760, height = 240, left = 40, right = 16, top = 18, bottom = 36;
    var plotW = width - left - right, plotH = height - top - bottom;
    var chart = svg('svg', { viewBox: '0 0 ' + width + ' ' + height, role: 'img', 'aria-label': '蜘蛛访问趋势' });
    [0, 0.5, 1].forEach(function (ratio) {
      var y = top + plotH - plotH * ratio;
      chart.appendChild(svg('line', { x1: left, y1: y, x2: width - right, y2: y, class: 'xz-chart-grid' }));
      var label = svg('text', { x: left - 6, y: y + 4, 'text-anchor': 'end', class: 'xz-chart-axis' });
      label.textContent = Math.round(max * ratio);
      chart.appendChild(label);
    });
    var points = rows.map(function (row, index) {
      var x = left + (rows.length === 1 ? plotW / 2 : plotW * index / (rows.length - 1));
      var y = top + plotH - number(row.visits) / max * plotH;
      return x.toFixed(1) + ',' + y.toFixed(1);
    }).join(' ');
    chart.appendChild(svg('polyline', { points: points, fill: 'none', stroke: '#2f7a63', 'stroke-width': '2.5', class: 'xz-chart-line' }));
    var step = Math.max(1, Math.ceil(rows.length / 8));
    rows.forEach(function (row, index) {
      if (index % step !== 0 && index !== rows.length - 1) { return; }
      var x = left + (rows.length === 1 ? plotW / 2 : plotW * index / (rows.length - 1));
      var label = svg('text', { x: x, y: height - 12, 'text-anchor': 'middle', class: 'xz-chart-axis' });
      label.textContent = row.label;
      chart.appendChild(label);
    });
    container.appendChild(chart);
  }

  function distributionChart(id, rows) {
    var container = document.getElementById(id);
    if (!container) { return; }
    var active = rows.filter(function (row) { return number(row.visits) > 0; });
    if (!active.length) { setEmpty(container, '该时间范围暂无蜘蛛分类数据'); return; }

    var width = 760, rowHeight = 30, left = 130, right = 96, top = 12;
    var height = Math.max(100, top * 2 + active.length * rowHeight);
    var plotW = width - left - right;
    var chart = svg('svg', { viewBox: '0 0 ' + width + ' ' + height, role: 'img', 'aria-label': '各蜘蛛占比' });
    active.forEach(function (row, index) {
      var y = top + index * rowHeight;
      var percent = Math.min(100, Math.max(0, number(row.percent)));
      var label = svg('text', { x: left - 10, y: y + 17, 'text-anchor': 'end', class: 'xz-chart-axis' });
      label.textContent = row.name;
      chart.appendChild(label);
      chart.appendChild(svg('rect', { x: left, y: y + 5, width: plotW, height: 14, rx: 2, fill: '#edf1f4' }));
      chart.appendChild(svg('rect', { x: left, y: y + 5, width: (plotW * percent / 100).toFixed(1), height: 14, rx: 2, fill: '#3a6ea5' }));
      var value = svg('text', { x: width - right + 8, y: y + 17, class: 'xz-chart-axis' });
      value.textContent = number(row.visits) + '（' + percent.toFixed(1) + '%）';
      chart.appendChild(value);
    });
    container.appendChild(chart);
  }

  function hourChart(id, rows) {
    var container = document.getElementById(id);
    if (!container) { return; }
    var max = 0;
    rows.forEach(function (row) { max = Math.max(max, number(row.visits)); });
    if (!max) { setEmpty(container, '该时间范围暂无 24 小时抓取数据'); return; }

    var width = 760, height = 240, left = 40, right = 16, top = 18, bottom = 36;
    var plotW = width - left - right, plotH = height - top - bottom, barW = plotW / rows.length;
    var chart = svg('svg', { viewBox: '0 0 ' + width + ' ' + height, role: 'img', 'aria-label': '24 小时抓取分布' });
    [0, 0.5, 1].forEach(function (ratio) {
      var y = top + plotH - plotH * ratio;
      chart.appendChild(svg('line', { x1: left, y1: y, x2: width - right, y2: y, class: 'xz-chart-grid' }));
      var scale = svg('text', { x: left - 6, y: y + 4, 'text-anchor': 'end', class: 'xz-chart-axis' });
      scale.textContent = Math.round(max * ratio);
      chart.appendChild(scale);
    });
    rows.forEach(function (row, index) {
      var visits = number(row.visits), barHeight = visits / max * plotH, x = left + index * barW + 1;
      chart.appendChild(svg('rect', { x: x.toFixed(1), y: (top + plotH - barHeight).toFixed(1), width: Math.max(1, barW - 2).toFixed(1), height: barHeight.toFixed(1), fill: '#3a6ea5' }));
      if (index % 3 === 0 || index === rows.length - 1) {
        var label = svg('text', { x: (left + index * barW + barW / 2).toFixed(1), y: height - 12, 'text-anchor': 'middle', class: 'xz-chart-axis' });
        label.textContent = row.label;
        chart.appendChild(label);
      }
    });
    container.appendChild(chart);
  }

  lineChart('xz-seo-trend-chart', data.trend && data.trend.items ? data.trend.items : []);
  distributionChart('xz-seo-distribution-chart', data.engines || []);
  hourChart('xz-seo-hour-chart', data.hours || []);
}());
