/* GA4 payload allowlist: never pass cart/customer objects or custom artwork names. */
(function () {
  'use strict';
  const measurementId = 'G-5YY561Z1J8';
  function itemId(id, custom) {
    if (custom || !id) return 'custom-design';
    let hash = 0x811c9dc5;
    for (const byte of new TextEncoder().encode(String(id))) {
      hash = Math.imul(hash ^ byte, 0x01000193) >>> 0;
    }
    return 'sku-' + hash.toString(16).padStart(8, '0');
  }
  function items(lines) {
    return lines.map(line => {
      const price = Number(line.price), quantity = Number(line.qty);
      if (!Number.isFinite(price) || price < 0 || !Number.isInteger(quantity) || quantity < 1) return null;
      return { item_id: itemId(line.id, line.isCustomDesign === true),
        price: Math.round(price * 100 + 1e-8) / 100, quantity };
    }).filter(Boolean);
  }
  function emit(name, params) {
    try {
      if (typeof window.gtag !== 'function') return;
      const extra = { send_to: measurementId, transport_type: 'beacon',
        page_location: location.origin + location.pathname,
        page_referrer: name === 'purchase' ? '' : document.referrer ? new URL(document.referrer).origin + (new URL(document.referrer).origin === location.origin ? new URL(document.referrer).pathname : '') : '' };
      try { if (sessionStorage.getItem('vp_ga4_debug') === '1') extra.debug_mode = true; } catch (_) {}
      window.gtag('event', name, Object.assign({}, params, extra));
    } catch (_) { /* Analytics must never interrupt checkout. */ }
  }
  function cartEvent(name, lines) {
    try {
      const safeItems = items(lines);
      if (!safeItems.length) return;
      emit(name, { currency: 'USD', value: Math.round(safeItems.reduce((sum, i) => sum + i.price * i.quantity, 0) * 100) / 100,
        items: safeItems });
    } catch (_) {}
  }
  window.vpAnalytics = {
    add: (line, quantity) => cartEvent('add_to_cart', [Object.assign({}, line, { qty: quantity })]),
    begin: lines => cartEvent('begin_checkout', lines),
    // Only stripe-success.php supplies this allowlisted, payment-verified payload.
    purchase: params => emit('purchase', params),
    itemId
  };
})();
