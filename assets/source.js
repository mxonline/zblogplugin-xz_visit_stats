(function () {
  'use strict';
  var data = window.XZVisitStatsSource;
  if (!data) { return; }
  function svg(tag, attrs) { var node = document.createElementNS('http://www.w3.org/2000/svg', tag); Object.keys(attrs || {}).forEach(function (key) { node.setAttribute(key, attrs[key]); }); return node; }
  function empty(container) { container.textContent = '该时间范围暂无来源数据'; container.classList.add('xz-chart-empty'); }
  function bars(id, rows, labelKey, valueKey, aria) {
    var container = document.getElementById(id); if (!container) { return; }
    var max = 0; rows.forEach(function (row) { max = Math.max(max, row[valueKey]); }); if (!max) { empty(container); return; }
    var width = 600, height = Math.max(150, rows.length * 32 + 18), left = 150, right = 55, top = 10, usable = width - left - right;
    var chart = svg('svg', { viewBox: '0 0 ' + width + ' ' + height, role: 'img', 'aria-label': aria });
    rows.forEach(function (row, index) { var y = top + index * 32, value = row[valueKey], bar = value / max * usable; var label = svg('text', { x: left - 8, y: y + 16, 'text-anchor': 'end', class: 'xz-chart-axis' }); label.textContent = row[labelKey]; chart.appendChild(label); chart.appendChild(svg('rect', { x: left, y: y + 4, width: bar, height: 17, fill: '#3a6ea5', rx: 2 })); var count = svg('text', { x: left + bar + 6, y: y + 17, class: 'xz-chart-axis' }); count.textContent = value; chart.appendChild(count); });
    container.appendChild(chart);
  }
  function trend(id, rows) {
    var container = document.getElementById(id); if (!container) { return; }
    var max = 0; rows.forEach(function (row) { max = Math.max(max, row.visits, row.direct, row.search, row.external); }); if (!max) { empty(container); return; }
    var width = 760, height = 245, left = 42, right = 18, top = 18, bottom = 35, plotW = width - left - right, plotH = height - top - bottom;
    var chart = svg('svg', { viewBox: '0 0 ' + width + ' ' + height, role: 'img', 'aria-label': '来源访问趋势' });
    [0, .5, 1].forEach(function (ratio) { var y = top + plotH - plotH * ratio; chart.appendChild(svg('line', { x1: left, y1: y, x2: width - right, y2: y, class: 'xz-chart-grid' })); var text = svg('text', { x: left - 6, y: y + 4, 'text-anchor': 'end', class: 'xz-chart-axis' }); text.textContent = Math.round(max * ratio); chart.appendChild(text); });
    var series = [{key:'visits',color:'#3a6ea5',label:'全部'}, {key:'direct',color:'#888',label:'直接'}, {key:'search',color:'#42a36b',label:'搜索'}, {key:'external',color:'#9a6ac5',label:'外链'}];
    series.forEach(function (item) { var points = rows.map(function (row, index) { var x = left + (rows.length === 1 ? plotW / 2 : plotW * index / (rows.length - 1)); var y = top + plotH - row[item.key] / max * plotH; return x.toFixed(1)+','+y.toFixed(1); }).join(' '); chart.appendChild(svg('polyline', { points:points, fill:'none', stroke:item.color, 'stroke-width':'2.3', class:'xz-chart-line' })); });
    var step = Math.max(1, Math.ceil(rows.length / 8)); rows.forEach(function (row,index) { if(index%step!==0 && index!==rows.length-1){return;} var x=left+(rows.length===1?plotW/2:plotW*index/(rows.length-1)); var text=svg('text',{x:x,y:height-11,'text-anchor':'middle',class:'xz-chart-axis'});text.textContent=row.label;chart.appendChild(text); }); container.appendChild(chart);
    var legend=document.createElement('div');legend.className='xz-chart-legend';series.forEach(function(item){var span=document.createElement('span');span.innerHTML='<i style="background:'+item.color+'"></i>'+item.label;legend.appendChild(span);});container.parentNode.insertBefore(legend,container.nextSibling);
  }
  bars('xz-source-type-chart', data.types || [], 'type', 'visits', '来源类型分布');
  bars('xz-source-search-chart', data.searches || [], 'name', 'visits', '搜索来源分布');
  trend('xz-source-trend-chart', data.trend.items || []);
}());
