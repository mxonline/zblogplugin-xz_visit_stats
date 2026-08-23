(function () {
  'use strict';

  var data = window.XZVisitStatsOverview;
  if (!data) { return; }

  function svg(tag, attrs) {
    var node = document.createElementNS('http://www.w3.org/2000/svg', tag);
    Object.keys(attrs || {}).forEach(function (key) { node.setAttribute(key, attrs[key]); });
    return node;
  }

  function empty(container, text) {
    container.textContent = text || '暂无数据，有访问记录后将在这里展示。';
    container.classList.add('xz-chart-empty');
  }

  function lineChart(id, rows) {
    var container = document.getElementById(id);
    if (!container) { return; }
    var max = 0;
    rows.forEach(function (row) { max = Math.max(max, row.pv, row.uv, row.ip); });
    if (!rows.length || max === 0) { empty(container); return; }

    var width = 760, height = 250, left = 42, right = 18, top = 24, bottom = 36;
    var plotWidth = width - left - right, plotHeight = height - top - bottom;
    var chart = svg('svg', { viewBox: '0 0 ' + width + ' ' + height, role: 'img', 'aria-label': '访问量、访客数、独立访客趋势图' });
    [0, 0.5, 1].forEach(function (ratio) {
      var y = top + plotHeight - plotHeight * ratio;
      chart.appendChild(svg('line', { x1: left, y1: y, x2: width - right, y2: y, class: 'xz-chart-grid' }));
      var label = svg('text', { x: left - 7, y: y + 4, 'text-anchor': 'end', class: 'xz-chart-axis' });
      label.textContent = String(Math.round(max * ratio)); chart.appendChild(label);
    });
    var series = [{ key: 'pv', color: '#3a6ea5', label: '访问量' }, { key: 'uv', color: '#42a36b', label: '访客数' }, { key: 'ip', color: '#9a6ac5', label: '独立访客' }];
    series.forEach(function (item) {
      var points = rows.map(function (row, index) {
        var x = left + (rows.length === 1 ? plotWidth / 2 : (plotWidth * index / (rows.length - 1)));
        var y = top + plotHeight - (row[item.key] / max * plotHeight);
        return x.toFixed(1) + ',' + y.toFixed(1);
      }).join(' ');
      chart.appendChild(svg('polyline', { points: points, fill: 'none', stroke: item.color, 'stroke-width': '2.5', class: 'xz-chart-line' }));
    });
    var step = Math.max(1, Math.ceil(rows.length / 8));
    rows.forEach(function (row, index) {
      if (index % step !== 0 && index !== rows.length - 1) { return; }
      var x = left + (rows.length === 1 ? plotWidth / 2 : (plotWidth * index / (rows.length - 1)));
      var label = svg('text', { x: x, y: height - 12, 'text-anchor': 'middle', class: 'xz-chart-axis' });
      label.textContent = row.label; chart.appendChild(label);
    });
    var legend = document.createElement('div'); legend.className = 'xz-chart-legend';
    series.forEach(function (item) { var span = document.createElement('span'); span.innerHTML = '<i style="background:' + item.color + '"></i>' + item.label; legend.appendChild(span); });
    container.appendChild(chart); container.parentNode.insertBefore(legend, container.nextSibling);
  }

  function barChart(id, rows) {
    var container = document.getElementById(id);
    if (!container) { return; }
    var max = 0; rows.forEach(function (row) { max = Math.max(max, row.pv, row.bot); });
    if (!max) { empty(container); return; }
    var width = 760, height = 230, left = 36, right = 14, top = 14, bottom = 30;
    var plotWidth = width - left - right, plotHeight = height - top - bottom, group = plotWidth / rows.length;
    var chart = svg('svg', { viewBox: '0 0 ' + width + ' ' + height, role: 'img', 'aria-label': '24 小时 PV 与蜘蛛访问量' });
    rows.forEach(function (row, index) {
      var x = left + index * group + group * 0.17, barWidth = Math.max(2, group * 0.27);
      [['pv', '#3a6ea5', 0], ['bot', '#9a6ac5', 1]].forEach(function (item) {
        var h = row[item[0]] / max * plotHeight;
        chart.appendChild(svg('rect', { x: x + item[2] * (barWidth + 2), y: top + plotHeight - h, width: barWidth, height: h, fill: item[1], rx: 1 }));
      });
      if (index % 3 === 0) { var label = svg('text', { x: left + index * group + group / 2, y: height - 10, 'text-anchor': 'middle', class: 'xz-chart-axis' }); label.textContent = row.label; chart.appendChild(label); }
    });
    container.appendChild(chart);
    var legend = document.createElement('div'); legend.className = 'xz-chart-legend'; legend.innerHTML = '<span><i style="background:#3a6ea5"></i>PV</span><span><i style="background:#9a6ac5"></i>蜘蛛</span>';
    container.parentNode.insertBefore(legend, container.nextSibling);
  }

  function donutChart(id, rows) {
    var container = document.getElementById(id);
    if (!container) { return; }
    var total = rows.reduce(function (sum, row) { return sum + row.value; }, 0);
    if (!total) { empty(container); return; }
    var colors = ['#3a6ea5', '#9a6ac5'], size = 190, radius = 66, circumference = 2 * Math.PI * radius, offset = 0;
    var chart = svg('svg', { viewBox: '0 0 ' + size + ' ' + size, role: 'img', 'aria-label': '访问类型占比' });
    chart.appendChild(svg('circle', { cx: 95, cy: 95, r: radius, fill: 'none', stroke: '#edf0f3', 'stroke-width': 22 }));
    rows.forEach(function (row, index) {
      var length = row.value / total * circumference;
      var circle = svg('circle', { cx: 95, cy: 95, r: radius, fill: 'none', stroke: colors[index % colors.length], 'stroke-width': 22, 'stroke-dasharray': length + ' ' + (circumference - length), 'stroke-dashoffset': -offset, transform: 'rotate(-90 95 95)' });
      chart.appendChild(circle); offset += length;
    });
    var center = svg('text', { x: 95, y: 91, 'text-anchor': 'middle', class: 'xz-donut-value' }); center.textContent = total; chart.appendChild(center);
    var caption = svg('text', { x: 95, y: 110, 'text-anchor': 'middle', class: 'xz-chart-axis' }); caption.textContent = '总访问'; chart.appendChild(caption);
    container.appendChild(chart);
    var legend = document.createElement('div'); legend.className = 'xz-chart-legend';
    rows.forEach(function (row, index) { var span = document.createElement('span'); span.innerHTML = '<i style="background:' + colors[index % colors.length] + '"></i>' + row.label + ' ' + row.value; legend.appendChild(span); });
    container.parentNode.insertBefore(legend, container.nextSibling);
  }

  lineChart('xz-trend-chart', data.trend.items || []);
  barChart('xz-hour-chart', data.hours || []);
  donutChart('xz-type-chart', data.types || []);
}());
