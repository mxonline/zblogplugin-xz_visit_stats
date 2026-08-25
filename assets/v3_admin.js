(function () {
  'use strict';
  var labels = {
    'Campaign / AI 来源': '推广活动与 AI 来源', 'direct': '直接访问', 'external': '外部网站', 'internal': '站内访问', 'search': '搜索引擎', 'social': '社交媒体', 'other': '其他来源',
    'desktop': '桌面设备', 'mobile': '手机', 'tablet': '平板', 'bot': '蜘蛛访问',
    'Path': '页面路径', 'Referer': '来源地址', 'Referer 排行': '来源地址排行', 'Browser': '浏览器', 'Device': '设备类型', 'AI crawler': 'AI 爬虫',
    '页面分析（规范化 Path）': '页面访问分析', '高频错误 Path': '常见错误页面', 'UTM Campaign': 'UTM 推广活动', 'Campaign': '推广活动', 'RUM 性能': '用户体验性能',
    'DurationMs 分位数与慢请求': '服务器处理耗时分位数与慢请求', '真实用户体验 RUM': '真实用户体验（RUM）',
    '本地 GeoIP 数据源不可用或暂无地域数据': '本地地域库不可用，暂不显示地域数据。', 'complete': '正常', 'PathKey': '页面路径索引',
    '关键词仅在 Referer 实际携带且可解析时可见；不承诺完整关键词排名。': '关键词仅在来源地址实际携带且可解析时显示。',
    '暂无 AI crawler 数据': '当前没有识别到 AI 爬虫访问', '错误关联（来源 / 蜘蛛 / AI crawler）': '错误关联（来源、蜘蛛与 AI 爬虫）',
    'DurationMs 仅表示服务端处理耗时，不代表前端页面加载性能。': '服务器响应耗时仅反映服务器处理，不代表访客的页面加载体验。',
    '仅展示 Beacon 启用后产生的数据；LCP / INP / CLS / TTFB / FCP 与服务器 DurationMs 分开统计。': '页面加载体验仅统计开启体验采集后的新访问，并与服务器响应耗时分开查看。',
    '保存 Referer': '保存来源地址', '保存 UA / Browser / OS / Device': '保存浏览器、操作系统与设备信息',
    'RUM 性能': '页面加载体验', '真实用户体验 RUM': '页面加载体验', 'Beacon 访客环境聚合': '访客环境',
    'LCP P75': '主要内容显示速度', 'INP P75': '操作响应速度', 'CLS P75': '页面稳定性', 'TTFB P75': '首字节响应时间', 'FCP P75': '首屏内容出现时间',
    '服务端响应变化': '服务器响应速度趋势',
    'Path': '页面路径', 'PathKey': '页面路径标识', 'Campaign': '推广活动', 'UTM Campaign': 'UTM 推广活动'
  };
  function localize(node) {
    if (node.nodeType !== 3) return;
    var value = node.nodeValue;
    if (labels[value.trim()]) value = value.replace(value.trim(), labels[value.trim()]);
    [['DurationMs', '服务器响应耗时'], ['PathKey', '页面数据状态'], ['Campaign', '推广活动'], ['Beacon', '页面加载体验采集'], ['RUM', '页面加载体验'], ['CIDR', '地址范围'], ['Header', '转发字段'], ['REMOTE_ADDR', '访问来源地址'], ['complete', '正常']].forEach(function (pair) { value = value.split(pair[0]).join(pair[1]); });
    node.nodeValue = value;
  }
  document.querySelectorAll('.xzvs-app *').forEach(function (element) { Array.prototype.forEach.call(element.childNodes, localize); });
  var testRows = Array.prototype.filter.call(document.querySelectorAll('.xzvs-app tbody tr'), function (row) { return /\/(?:_+xz|xz_t5|v3-)/i.test(row.textContent); });
  if (testRows.length) {
    testRows.forEach(function (row) { row.classList.add('xzvs-test-row'); });
  }
  Array.prototype.forEach.call(document.querySelectorAll('.xzvs-v3-extension ul li'), function (item) {
    if (/\b(?:t5|xz_t5|v3[-_]?test)\b/i.test(item.textContent)) item.classList.add('xzvs-test-row');
  });
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
    table.querySelectorAll('tbody tr').forEach(function (row, index) {
      if (!row.querySelector('td') || row.children.length < 3) return;
      var buttonCell = document.createElement('td'), button = document.createElement('button');
      button.type = 'button'; button.className = 'button xzvs-drawer-open'; button.textContent = '详情';
      button.addEventListener('click', function () { openDrawer((window.XZVS_RECORDS || [])[index] || {}); });
      buttonCell.appendChild(button); row.appendChild(buttonCell);
    });
  }
  function display(value, fallback) { return value === null || value === undefined || String(value).trim() === '' ? fallback : String(value); }
  function device(value) { return ({desktop:'桌面设备',mobile:'手机',tablet:'平板'})[value] || display(value, '未识别设备'); }
  function status(value) { var code = Number(value || 0), note = ({200:'正常',201:'正常',204:'正常',301:'跳转',302:'跳转',403:'禁止访问',404:'页面不存在',500:'服务器错误',502:'服务器错误',503:'服务暂不可用'})[code] || '其他状态'; return (code ? code + ' · ' : '') + note; }
  function source(value, referer) { var map={direct:'直接访问',search:'搜索引擎',social:'社交媒体',external:'外部网站',internal:'站内访问',campaign:'推广活动',ai:'AI 助手来源',other:'其他来源'}; return map[value] || (referer ? '其他来源' : '直接访问'); }
  function addGroup(body, title, fields) {
    var group = document.createElement('section'), heading = document.createElement('h3'); heading.textContent = title; group.appendChild(heading);
    fields.forEach(function (field) { if (field.value === false) return; var row=document.createElement('p'), strong=document.createElement('strong'), span=document.createElement('span'); strong.textContent=field.label; span.textContent=field.value; row.appendChild(strong); row.appendChild(span); group.appendChild(row); }); body.appendChild(group);
  }
  function openDrawer(record) {
    var drawer = document.getElementById('xzvs-record-drawer');
    if (!drawer) {
      drawer = document.createElement('aside'); drawer.id = 'xzvs-record-drawer'; drawer.className = 'xzvs-drawer'; drawer.setAttribute('aria-hidden', 'true');
      drawer.innerHTML = '<div class="xzvs-drawer-head"><strong>访问详情</strong><button type="button" class="button xzvs-drawer-close">关闭</button></div><div class="xzvs-drawer-body"></div>';
      document.body.appendChild(drawer); drawer.querySelector('.xzvs-drawer-close').addEventListener('click', closeDrawer);
    }
    var body = drawer.querySelector('.xzvs-drawer-body'); body.textContent = '';
    addGroup(body, '访问信息', [
      {label:'访问时间', value:display(record.vs_VisitedAtText, '暂无数据')}, {label:'访问类型', value:Number(record.vs_IsBot) === 1 ? '蜘蛛访问' : '真人访问'},
      {label:'HTTP 状态', value:status(record.vs_StatusCode)}, {label:'服务器响应耗时', value:display(record.vs_DurationMs, '暂无数据') + (record.vs_DurationMs === null || record.vs_DurationMs === undefined ? '' : ' ms')}
    ]);
    addGroup(body, '页面信息', [
      {label:'页面标题', value:display(record.vs_PageTitle, '升级前无标题数据')}, {label:'页面路径', value:display(record.vs_Path, '暂无数据')}, {label:'内容 ID', value:Number(record.vs_PostID) > 0 ? String(record.vs_PostID) : '暂无关联内容'}
    ]);
    addGroup(body, '来源与访客', [
      {label:'IP 地址', value:display(record.vs_IP, '暂无数据')}, {label:'蜘蛛名称', value:Number(record.vs_IsBot) === 1 ? display(record.vs_BotName, '未识别蜘蛛') : '非蜘蛛'},
      {label:'来源类型', value:source(record.vs_SourceType, record.vs_Referer)}, {label:'来源地址', value:display(record.vs_Referer, '直接访问 / 无来源')},
      {label:'浏览器', value:display(record.vs_Browser, '未识别浏览器')}, {label:'操作系统', value:display(record.vs_Os, '未识别操作系统')}, {label:'设备类型', value:device(record.vs_Device)}
    ]);
    drawer.setAttribute('aria-hidden', 'false'); drawer.classList.add('is-open');
  }
  function closeDrawer() { var drawer = document.getElementById('xzvs-record-drawer'); if (drawer) { drawer.classList.remove('is-open'); drawer.setAttribute('aria-hidden', 'true'); } }
  document.addEventListener('keydown', function (event) { if (event.key === 'Escape') closeDrawer(); });
}());
