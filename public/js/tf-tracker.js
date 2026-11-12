/*!
 * TraceForms Tracker — coleta de sinais de visita, dispositivo, origem e engajamento.
 * Roda dentro da página pública do formulário (direta ou incorporada via iframe).
 */
(function (window, document) {
  'use strict';

  var LS = {
    get: function (k) { try { return window.localStorage.getItem(k); } catch (e) { return null; } },
    set: function (k, v) { try { window.localStorage.setItem(k, v); } catch (e) {} }
  };

  function cookie(name, value, days) {
    if (value === undefined) {
      var m = document.cookie.match(new RegExp('(?:^|; )' + name + '=([^;]*)'));
      return m ? decodeURIComponent(m[1]) : null;
    }
    var exp = new Date(Date.now() + days * 864e5).toUTCString();
    document.cookie = name + '=' + encodeURIComponent(value) + '; expires=' + exp + '; path=/; SameSite=Lax' + (location.protocol === 'https:' ? '; Secure' : '');
  }

  function uuid() {
    if (window.crypto && crypto.randomUUID) return crypto.randomUUID();
    return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) {
      var r = Math.random() * 16 | 0; return (c === 'x' ? r : (r & 0x3 | 0x8)).toString(16);
    });
  }

  function params(url) {
    var out = {};
    try { new URL(url).searchParams.forEach(function (v, k) { out[k] = v; }); } catch (e) {}
    return out;
  }

  var UTM = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'];
  var CLICK_IDS = ['gclid', 'gbraid', 'wbraid', 'dclid', 'fbclid', 'msclkid', 'ttclid', 'li_fat_id', 'twclid', 'sccid', 'epik', 'yclid', 'irclickid', 'rdt_cid', 'kwai_clid'];

  function TFTracker(opts) {
    this.opts = opts;
    this.sid = null;
    this.loadedAt = performance.now();
    this.started = false;
    this.step = 0;
    this.stats = {};          // { fieldKey: { ms, focus, changes, errors } }
    this.focusField = null;
    this.focusAt = 0;
    this.activeMs = 0;
    this.lastActive = performance.now();
    this.visible = !document.hidden;
    this.tabSwitches = 0;
    this.pasteCount = 0;
    this.partial = null;
    this.queue = [];
  }

  TFTracker.prototype.client = function () {
    var n = navigator, s = window.screen || {}, c = n.connection || n.mozConnection || n.webkitConnection || {};
    var tz = null;
    try { tz = Intl.DateTimeFormat().resolvedOptions().timeZone; } catch (e) {}
    return {
      screen_w: s.width, screen_h: s.height,
      viewport_w: window.innerWidth, viewport_h: window.innerHeight,
      dpr: window.devicePixelRatio || 1,
      touch: ('ontouchstart' in window) || n.maxTouchPoints > 0,
      language: n.language, languages: (n.languages || []).slice(0, 6),
      timezone: tz, tz_offset: -new Date().getTimezoneOffset(),
      connection: c.effectiveType || null, downlink: c.downlink || null,
      dark: window.matchMedia && matchMedia('(prefers-color-scheme: dark)').matches,
      cookies: n.cookieEnabled, dnt: n.doNotTrack === '1' || window.doNotTrack === '1',
      cores: n.hardwareConcurrency || null, memory: n.deviceMemory || null,
      webdriver: !!n.webdriver
    };
  };

  /** Contexto de incorporação: quando dentro de iframe, embed.js repassa os dados da página-mãe. */
  TFTracker.prototype.context = function () {
    var q = params(location.href);
    var inIframe = window.self !== window.top;
    var ctx = {
      url: location.href,
      referrer: document.referrer || null,
      embed_mode: inIframe ? 'iframe' : (q.tf_link ? 'link' : 'direct'),
      parent_url: q.tf_parent || null,
      parent_referrer: q.tf_pref || null,
      link: q.tf_link || null
    };
    if (inIframe && !ctx.parent_url && document.referrer) ctx.parent_url = document.referrer;
    return ctx;
  };

  /** Atribuição de primeiro toque, persistida por 90 dias. */
  TFTracker.prototype.firstTouch = function (ctx) {
    var q = params(location.href);
    if (q.tf_ft) { try { return JSON.parse(q.tf_ft); } catch (e) {} }
    var stored = LS.get('tf_ft');
    if (stored) { try { return JSON.parse(stored); } catch (e) {} }
    var src = Object.assign({}, params(ctx.parent_url || ''), q);
    var ft = { ts: new Date().toISOString(), landing: ctx.parent_url || ctx.url, referrer: ctx.parent_referrer || ctx.referrer };
    UTM.forEach(function (k) { if (src[k]) ft[k] = src[k]; });
    CLICK_IDS.some(function (k) { if (src[k]) { ft.click_id_type = k; return true; } });
    LS.set('tf_ft', JSON.stringify(ft));
    return ft;
  };

  TFTracker.prototype.visitor = function () {
    var id = LS.get('tf_vid') || cookie('tf_vid');
    var returning = !!id;
    if (!id) id = uuid();
    LS.set('tf_vid', id);
    cookie('tf_vid', id, 395);
    return { id: id, returning: returning };
  };

  TFTracker.prototype.post = function (payload, beacon) {
    var url = this.opts.collectUrl;
    var body = JSON.stringify(payload);
    if (beacon && navigator.sendBeacon) {
      try { if (navigator.sendBeacon(url, new Blob([body], { type: 'application/json' }))) return Promise.resolve(null); } catch (e) {}
    }
    return fetch(url, {
      method: 'POST', body: body, keepalive: !!beacon, credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' }
    }).then(function (r) { return r.ok ? r.json() : null; }).catch(function () { return null; });
  };

  TFTracker.prototype.init = function () {
    if (this.opts.preview) return this;
    var self = this;
    var ctx = this.context();
    var v = this.visitor();
    var payload = Object.assign({ type: 'view', visitor_id: v.id, returning: v.returning, client: this.client(), first_touch: this.firstTouch(ctx) }, ctx);

    this.ready = this.post(payload).then(function (res) {
      if (res && res.sid) self.sid = res.sid;
      self.queue.forEach(function (p) { self.send(p); });
      self.queue = [];
      return self.sid;
    });

    document.addEventListener('visibilitychange', function () {
      self.tickActive();
      if (document.hidden) { self.tabSwitches++; self.flush(true); }
      self.visible = !document.hidden;
    });
    window.addEventListener('pagehide', function () { self.flush(true); });
    document.addEventListener('paste', function () { self.pasteCount++; }, true);
    ['pointerdown', 'keydown', 'scroll', 'touchstart'].forEach(function (ev) {
      document.addEventListener(ev, function () { self.tickActive(); }, { passive: true });
    });
    this.heartbeat = setInterval(function () { if (self.started) self.flush(false); }, 15000);
    return this;
  };

  /** Tempo ativo: conta apenas com aba visível e interação nos últimos 30s. */
  TFTracker.prototype.tickActive = function () {
    var now = performance.now();
    if (this.visible && now - this.lastActive < 30000) this.activeMs += now - this.lastActive;
    this.lastActive = now;
  };

  TFTracker.prototype.send = function (p, beacon) {
    if (this.opts.preview) return;
    if (!this.sid) { this.queue.push(p); return; }
    p.sid = this.sid;
    this.post(p, beacon);
  };

  TFTracker.prototype.stat = function (key) {
    return this.stats[key] || (this.stats[key] = { ms: 0, focus: 0, changes: 0, errors: 0 });
  };

  TFTracker.prototype.markStart = function () {
    if (this.started) return;
    this.started = true;
    this.send({ type: 'start', t: Math.round(performance.now() - this.loadedAt) });
  };

  TFTracker.prototype.focus = function (key) {
    this.markStart();
    if (this.focusField === key) return;
    this.blur();
    this.focusField = key;
    this.focusAt = performance.now();
    this.stat(key).focus++;
  };

  TFTracker.prototype.blur = function () {
    if (!this.focusField) return;
    this.stat(this.focusField).ms += Math.round(performance.now() - this.focusAt);
    this.focusField = null;
  };

  TFTracker.prototype.change = function (key, step, partial) {
    this.markStart();
    this.stat(key).changes++;
    this.step = Math.max(this.step, step || 0);
    this.lastField = key;
    this.partial = partial;
  };

  TFTracker.prototype.error = function (key) { this.stat(key).errors++; };

  TFTracker.prototype.reach = function (step, key) {
    this.step = Math.max(this.step, step);
    if (key) this.lastField = key;
  };

  TFTracker.prototype.meta = function () {
    this.tickActive();
    if (this.focusField) { var k = this.focusField; this.blur(); this.focusField = k; this.focusAt = performance.now(); }
    return {
      step: this.step, field: this.lastField || null, stats: this.stats,
      duration_ms: Math.round(performance.now() - this.loadedAt), active_ms: Math.round(this.activeMs),
      tab_switches: this.tabSwitches, paste_count: this.pasteCount
    };
  };

  TFTracker.prototype.flush = function (beacon) {
    if (!this.started) return;
    var p = Object.assign({ type: 'progress' }, this.meta());
    if (this.partial) p.partial = this.partial;
    this.send(p, beacon);
  };

  TFTracker.prototype.stop = function () { clearInterval(this.heartbeat); this.started = false; };

  window.TFTracker = TFTracker;
})(window, document);
