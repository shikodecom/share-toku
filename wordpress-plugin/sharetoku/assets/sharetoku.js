(function () {
  'use strict';
  var seen = new Set();
  function eventId() {
    var bytes = new Uint8Array(16);
    crypto.getRandomValues(bytes);
    var alphabet = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';
    var value = BigInt('0x' + Array.from(bytes, function (b) { return b.toString(16).padStart(2, '0'); }).join(''));
    var result = '';
    for (var i = 0; i < 26; i++) { result = alphabet[Number(value % 32n)] + result; value = value / 32n; }
    return result;
  }
  function send(card, type) {
    var container = card.closest('.sharetoku-placement');
    if (!container || !container.dataset.sharetokuEventToken || !container.dataset.sharetokuEndpoint) return;
    var payload = JSON.stringify({event_token:container.dataset.sharetokuEventToken,events:[{
      event_id:eventId(),type:type,slot:card.dataset.sharetokuSlot,occurred_at:new Date().toISOString(),
      page_path:location.pathname,content_key:'post:' + (document.body.dataset.postId || 'unknown')
    }]});
    if (navigator.sendBeacon) {
      try { if (navigator.sendBeacon(container.dataset.sharetokuEndpoint, new Blob([payload], {type:'application/json'}))) return; } catch (_) {}
    }
    fetch(container.dataset.sharetokuEndpoint, {method:'POST',headers:{'Content-Type':'application/json'},body:payload,keepalive:true,credentials:'omit'}).catch(function () {});
  }
  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.sharetoku-card').forEach(function (card) {
      var key = card.dataset.sharetokuPlacement + ':' + card.dataset.sharetokuSlot;
      if (!seen.has(key)) { seen.add(key); send(card, 'impression'); }
    });
  });
  document.addEventListener('click', function (event) {
    var link = event.target.closest('.sharetoku-link');
    if (link) send(link.closest('.sharetoku-card'), 'click');
    var button = event.target.closest('.sharetoku-copy');
    if (button && navigator.clipboard) navigator.clipboard.writeText(button.dataset.code || '').then(function () {
      var live = button.closest('.sharetoku-card').querySelector('.sharetoku-live'); if (live) live.textContent = 'コピーしました';
    }).catch(function () {});
  });
})();
