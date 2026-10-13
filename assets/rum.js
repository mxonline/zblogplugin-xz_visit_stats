(function () {
  'use strict';
  if (!window.performance) return;
  var nav = performance.getEntriesByType && performance.getEntriesByType('navigation')[0];
  function cookie(name) { var m = document.cookie.match(new RegExp('(?:^|; )' + name + '=([^;]*)')); return m ? decodeURIComponent(m[1]) : ''; }
  var sessionKey = cookie('xzvs_sk');
  if (!/^[a-f0-9]{64}$/.test(sessionKey)) { sessionKey = Array.from(crypto.getRandomValues(new Uint8Array(32))).map(function (b) { return ('0' + b.toString(16)).slice(-2); }).join(''); document.cookie = 'xzvs_sk=' + sessionKey + '; Path=/; SameSite=Lax'; }
  var enteredAt = Date.now(), nav = performance.getEntriesByType && performance.getEntriesByType('navigation')[0];
  var data = { path: location.pathname, language: navigator.language || '', screen: screen.width + 'x' + screen.height, viewport: innerWidth + 'x' + innerHeight, lcp: null, inp: null, cls: null, ttfb: nav ? Math.max(0, nav.responseStart - nav.requestStart) : null, fcp: null };
  var sent = false, cls = 0;
  function observe(type, callback) {
    try { if (window.PerformanceObserver) new PerformanceObserver(function (list) { list.getEntries().forEach(callback); }).observe({ type: type, buffered: true }); } catch (e) {}
  }
  observe('paint', function (entry) { if (entry.name === 'first-contentful-paint') data.fcp = Math.round(entry.startTime); });
  observe('largest-contentful-paint', function (entry) { data.lcp = Math.round(entry.startTime); });
  observe('layout-shift', function (entry) { if (!entry.hadRecentInput) cls += entry.value; data.cls = Math.round(cls * 10000) / 10000; });
  observe('event', function (entry) { if (entry.duration) data.inp = data.inp === null ? Math.round(entry.duration) : Math.max(data.inp, Math.round(entry.duration)); });
  function send() {
    if (sent) return;
    sent = true;
    data.lifecycle = { session_key: sessionKey, path: location.pathname, entered_at: enteredAt, left_at: Date.now(), exit_reason: 'pagehide' };
    var body = JSON.stringify(data), url = location.origin + '/zb_users/plugin/xz_visit_stats/rum.php';
    var accepted = false;
    try { if (navigator.sendBeacon) accepted = navigator.sendBeacon(url, new Blob([body], { type: 'application/json' })); } catch (e) {}
    if (!accepted && window.fetch) { try { fetch(url, { method: 'POST', body: body, headers: { 'Content-Type': 'application/json' }, credentials: 'same-origin', keepalive: true }).catch(function () {}); } catch (e) {} }
  }
  addEventListener('pagehide', send, { once: true });
  document.addEventListener('visibilitychange', function () { if (document.visibilityState === 'hidden') send(); });
  window.xzVisitStatsEvent = function (name, params) {
    var payload = JSON.stringify({ name: name, params: params || {}, path: location.pathname, triggered_at: Date.now(), session_key: sessionKey });
    var url = location.origin + '/zb_users/plugin/xz_visit_stats/event.php';
    try { if (navigator.sendBeacon) { navigator.sendBeacon(url, new Blob([payload], { type: 'application/json' })); return; } } catch (e) {}
    try { if (window.fetch) fetch(url, { method: 'POST', body: payload, headers: { 'Content-Type': 'application/json' }, credentials: 'same-origin', keepalive: true }).catch(function () {}); } catch (e) {}
  };
}());
