/* TraceForms — utilitários do painel */
window.FP = {
  csrf: () => document.querySelector('meta[name="csrf-token"]').content,

  toast(text, type) { window.dispatchEvent(new CustomEvent('toast', { detail: { text, type } })); },

  copy(text, label) {
    const done = () => FP.toast((label || 'Copiado') + ' para a área de transferência.');
    if (navigator.clipboard && window.isSecureContext) return navigator.clipboard.writeText(text).then(done);
    const t = document.createElement('textarea'); t.value = text; document.body.appendChild(t); t.select();
    try { document.execCommand('copy'); done(); } finally { t.remove(); }
  },

  num(n) { return new Intl.NumberFormat('pt-BR').format(n || 0); },

  async api(url, method, body) {
    const res = await fetch(url, {
      method, headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': FP.csrf() },
      body: body ? JSON.stringify(body) : undefined,
    });
    const data = await res.json().catch(() => ({}));
    if (!res.ok) { const e = new Error(data.message || 'Erro ' + res.status); e.data = data; throw e; }
    return data;
  },

  palette: ['#0e7c66', '#e0a526', '#1d5fa8', '#b4472b', '#6d3fc0', '#5aa889', '#c1688d', '#2c4a43', '#4f6fa8', '#7a8a85', '#8a6d1f', '#b7c4bf'],

  chartDefaults() {
    if (!window.Chart) return;
    Chart.defaults.font.family = "'Instrument Sans', system-ui, sans-serif";
    Chart.defaults.font.size = 12;
    Chart.defaults.color = '#5e6e69';
    Chart.defaults.plugins.legend.labels.usePointStyle = true;
    Chart.defaults.plugins.legend.labels.boxWidth = 8;
    Chart.defaults.plugins.tooltip.backgroundColor = '#14231f';
    Chart.defaults.plugins.tooltip.padding = 10;
    Chart.defaults.plugins.tooltip.cornerRadius = 8;
    Chart.defaults.plugins.tooltip.boxPadding = 4;
    Chart.defaults.maintainAspectRatio = false;
  },

  line(el, labels, datasets) {
    FP.chartDefaults();
    return new Chart(el, {
      type: 'line',
      data: {
        labels, datasets: datasets.map((d, i) => Object.assign({
          borderColor: d.color || FP.palette[i], backgroundColor: (d.color || FP.palette[i]) + '14',
          fill: !!d.fill, tension: .3, borderWidth: d.width || 2, pointRadius: 0, pointHoverRadius: 4, borderDash: d.dash || [],
        }, d))
      },
      options: {
        interaction: { mode: 'index', intersect: false },
        scales: {
          x: { grid: { display: false }, ticks: { maxTicksLimit: 12, maxRotation: 0 } },
          y: { beginAtZero: true, grid: { color: '#e8eeeb' }, border: { display: false }, ticks: { precision: 0 } },
        },
        plugins: { legend: { position: 'top', align: 'end' } },
      },
    });
  },

  doughnut(el, labels, values, colors) {
    FP.chartDefaults();
    return new Chart(el, {
      type: 'doughnut',
      data: { labels, datasets: [{ data: values, backgroundColor: colors || FP.palette, borderWidth: 2, borderColor: '#fff', hoverOffset: 6 }] },
      options: { cutout: '72%', plugins: { legend: { position: 'right' } } },
    });
  },

  bars(el, labels, values, opts) {
    FP.chartDefaults();
    opts = opts || {};
    return new Chart(el, {
      type: 'bar',
      data: { labels, datasets: [{ data: values, backgroundColor: opts.colors || '#0e7c66', borderRadius: 4, maxBarThickness: 46, label: opts.label || '' }] },
      options: {
        indexAxis: opts.horizontal ? 'y' : 'x',
        plugins: { legend: { display: false }, tooltip: { callbacks: { label: (c) => ' ' + FP.num(c.raw) + (opts.suffix || '') } } },
        scales: {
          x: { grid: { display: !!opts.horizontal, color: '#e8eeeb' }, border: { display: false }, beginAtZero: true, ticks: { precision: 0 } },
          y: { grid: { display: !opts.horizontal, color: '#e8eeeb' }, border: { display: false }, beginAtZero: true, ticks: { precision: 0 } },
        },
      },
    });
  },
};
