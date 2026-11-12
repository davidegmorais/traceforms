/*!
 * TraceForms Embed — incorpora formulários em qualquer site repassando
 * URL da página, referrer, UTMs, click IDs e atribuição de primeiro toque.
 *
 * Uso:
 *   <div data-traceforms="ID_DO_FORM"></div>
 *   <script src="https://SEU_DOMINIO/embed.js" async></script>
 *
 * Opções (atributos): data-height="600" data-auto-resize="true"
 *   data-popup="true" data-button="Fale conosco" (abre em modal ao clicar)
 */
(function () {
  'use strict';
  var script = document.currentScript || document.querySelector('script[src*="embed.js"]');
  var base = script ? new URL(script.src).origin : location.origin;
  var UTM = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'utm_id'];
  var CLICK = ['gclid', 'gbraid', 'wbraid', 'dclid', 'fbclid', 'msclkid', 'ttclid', 'li_fat_id', 'twclid', 'sccid', 'epik', 'yclid', 'irclickid', 'rdt_cid', 'kwai_clid'];

  function store(k, v) { try { if (v === undefined) return localStorage.getItem(k); localStorage.setItem(k, v); } catch (e) { return null; } }

  // Atribuição de primeiro toque no domínio do cliente (90 dias).
  function firstTouch() {
    var q = new URLSearchParams(location.search), raw = store('tf_ft'), ft = null;
    if (raw) { try { ft = JSON.parse(raw); } catch (e) {} }
    if (ft && ft.ts && (Date.now() - new Date(ft.ts).getTime()) < 90 * 864e5) return ft;
    ft = { ts: new Date().toISOString(), landing: location.href, referrer: document.referrer || null };
    UTM.forEach(function (k) { if (q.get(k)) ft[k] = q.get(k); });
    CLICK.some(function (k) { if (q.get(k)) { ft.click_id_type = k; return true; } });
    store('tf_ft', JSON.stringify(ft));
    return ft;
  }

  // Última visita com UTM (last non-direct touch) persiste entre páginas do site.
  function lastTouchParams() {
    var q = new URLSearchParams(location.search), found = {}, has = false;
    UTM.concat(CLICK).forEach(function (k) { if (q.get(k)) { found[k] = q.get(k); has = true; } });
    if (has) { store('tf_lt', JSON.stringify(found)); return found; }
    try { return JSON.parse(store('tf_lt') || '{}'); } catch (e) { return {}; }
  }

  function buildSrc(id, el) {
    var u = new URL(base + '/f/' + encodeURIComponent(id));
    var lt = lastTouchParams();
    Object.keys(lt).forEach(function (k) { u.searchParams.set(k, lt[k]); });
    // Repassa parâmetros extras da página (para campos ocultos).
    new URLSearchParams(location.search).forEach(function (v, k) { if (!u.searchParams.has(k)) u.searchParams.set(k, v); });
    u.searchParams.set('tf_parent', location.href);
    if (document.referrer) u.searchParams.set('tf_pref', document.referrer);
    u.searchParams.set('tf_ft', JSON.stringify(firstTouch()));
    u.searchParams.set('embed', '1');
    if (el.getAttribute('data-transparent') === 'true') u.searchParams.set('transparent', '1');
    return u.toString();
  }

  function makeIframe(id, el) {
    var f = document.createElement('iframe');
    f.src = buildSrc(id, el);
    f.title = el.getAttribute('data-title') || 'Formulário';
    f.loading = 'lazy';
    f.allow = 'clipboard-write';
    f.style.cssText = 'width:100%;border:0;display:block;min-height:200px;height:' + (el.getAttribute('data-height') || '640') + 'px;transition:height .2s';
    f.setAttribute('data-tf-id', id);
    return f;
  }

  function popup(id, el) {
    var btn = document.createElement('button');
    btn.type = 'button';
    btn.textContent = el.getAttribute('data-button') || 'Abrir formulário';
    btn.style.cssText = 'background:' + (el.getAttribute('data-color') || '#5b4cf0') + ';color:#fff;border:0;border-radius:10px;padding:12px 22px;font:600 15px system-ui;cursor:pointer';
    btn.onclick = function () {
      var ov = document.createElement('div');
      ov.style.cssText = 'position:fixed;inset:0;background:rgba(15,14,35,.6);z-index:2147483646;display:flex;align-items:center;justify-content:center;padding:16px';
      var box = document.createElement('div');
      box.style.cssText = 'background:#fff;border-radius:16px;width:100%;max-width:720px;height:min(86vh,760px);overflow:hidden;position:relative;box-shadow:0 30px 80px rgba(0,0,0,.35)';
      var close = document.createElement('button');
      close.innerHTML = '&times;'; close.setAttribute('aria-label', 'Fechar');
      close.style.cssText = 'position:absolute;top:8px;right:12px;border:0;background:none;font-size:28px;cursor:pointer;z-index:2;color:#555';
      close.onclick = function () { ov.remove(); };
      ov.onclick = function (e) { if (e.target === ov) ov.remove(); };
      var f = makeIframe(id, el); f.style.height = '100%'; f.setAttribute('data-no-resize', '1');
      box.appendChild(close); box.appendChild(f); ov.appendChild(box); document.body.appendChild(ov);
    };
    el.appendChild(btn);
  }

  function mount(el) {
    if (el.getAttribute('data-tf-mounted')) return;
    el.setAttribute('data-tf-mounted', '1');
    var id = el.getAttribute('data-traceforms');
    if (el.getAttribute('data-popup') === 'true') return popup(id, el);
    el.appendChild(makeIframe(id, el));
  }

  // Auto-resize e eventos de conversão vindos do iframe.
  window.addEventListener('message', function (e) {
    if (e.origin !== base || !e.data || typeof e.data !== 'object') return;
    var frames = document.querySelectorAll('iframe[data-tf-id]');
    for (var i = 0; i < frames.length; i++) {
      if (frames[i].contentWindow !== e.source) continue;
      if (e.data.type === 'tf:height' && !frames[i].hasAttribute('data-no-resize')) frames[i].style.height = Math.max(200, e.data.height) + 'px';
      if (e.data.type === 'tf:submitted') {
        window.dataLayer = window.dataLayer || [];
        window.dataLayer.push({ event: 'traceforms_submit', traceforms_form: e.data.form, traceforms_response: e.data.response });
        frames[i].dispatchEvent(new CustomEvent('traceforms:submit', { bubbles: true, detail: e.data }));
      }
      if (e.data.type === 'tf:redirect' && e.data.url) window.location.href = e.data.url;
    }
  });

  function boot() { Array.prototype.forEach.call(document.querySelectorAll('[data-traceforms]'), mount); }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot); else boot();
  window.TraceForms = { mount: boot };
})();
