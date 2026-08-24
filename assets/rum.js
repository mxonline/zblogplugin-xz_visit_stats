(function () {
  'use strict';
  if (!window.performance) return;
  var nav = performance.getEntriesByType && performance.getEntriesByType('navigation')[0];
  var data = { path: location.pathname, language: navigator.language || '', screen: screen.width + 'x' + screen.height, viewport: innerWidth + 'x' + innerHeight, lcp: 0, inp: 0, cls: 0, ttfb: nav ? Math.max(0, nav.responseStart - nav.requestStart) : 0, fcp: 0 };
  var sent = false, cls = 0;
  function observe(type, callback) {
    try { if (window.PerformanceObserver) new PerformanceObserver(function (list) { list.getEntries().forEach(callback); }).observe({ type: type, buffered: true }); } catch (e) {}
  }
  observe('paint', function (entry) { if (entry.name === 'first-contentful-paint') data.fcp = Math.round(entry.startTime); });
  observe('largest-contentful-paint', function (entry) { data.lcp = Math.round(entry.startTime); });
  observe('layout-shift', function (entry) { if (!entry.hadRecentInput) cls += entry.value; data.cls = Math.round(cls * 10000) / 10000; });
  observe('event', function (entry) { if (entry.duration) data.inp = Math.max(data.inp, Math.round(entry.duration)); });
  function send() {
    if (sent) return;
    sent = true;
    var body = JSON.stringify(data), url = location.origin + '/zb_users/plugin/xz_visit_stats/rum.php';
    if (navigator.sendBeacon) { navigator.sendBeacon(url, new Blob([body], { type: 'application/json' })); }
    else if (window.fetch) { fetch(url, { method: 'POST', body: body, headers: { 'Content-Type': 'application/json' }, credentials: 'same-origin', keepalive: true }); }
  }
  addEventListener('pagehide', send, { once: true });
  document.addEventListener('visibilitychange', function () { if (document.visibilityState === 'hidden') send(); });
}());
