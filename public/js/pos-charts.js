/**
 * Royal Hotel POS — 100% Offline-Capable Interactive SVG Charting Library
 * Zero external CDN dependencies so charts work both on local LAN and Cloud Investor Portals.
 */
window.PosCharts = (function () {
  function fmtINR(n) {
    const val = Number(n || 0);
    const neg = val < 0;
    const abs = Math.round(Math.abs(val));
    const str = String(abs);
    let formatted = str;
    if (str.length > 3) {
      const last3 = str.slice(-3);
      const rest = str.slice(0, -3).replace(/\B(?=(\d{2})+(?!\d))/g, ',');
      formatted = rest + ',' + last3;
    }
    return (neg ? '-₹' : '₹') + formatted;
  }

  function fmtShortINR(n) {
    const val = Number(n || 0);
    if (val >= 10000000) return '₹' + (val / 10000000).toFixed(1) + 'Cr';
    if (val >= 100000) return '₹' + (val / 100000).toFixed(1) + 'L';
    if (val >= 1000) return '₹' + (val / 1000).toFixed(1) + 'k';
    return '₹' + Math.round(val);
  }

  function escapeHtml(s) {
    return String(s ?? '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function renderEmpty(el, msg) {
    el.innerHTML = `<div class="chart-empty">${escapeHtml(msg || 'No data available for this period.')}</div>`;
  }

  /**
   * 1. Revenue Area + Bar Trend Chart
   */
  function renderRevenueTrend(containerId, data) {
    const el = document.getElementById(containerId);
    if (!el) return;
    if (!Array.isArray(data) || data.length === 0) {
      return renderEmpty(el, 'No daily sales data yet.');
    }

    const totalRev = data.reduce((s, d) => s + Number(d.revenue || 0), 0);
    if (totalRev <= 0) {
      return renderEmpty(el, 'Settle orders at Billing Counter to populate revenue trend graphs.');
    }

    const W = 680;
    const H = 250;
    const padL = 56;
    const padR = 18;
    const padT = 18;
    const padB = 34;
    const plotW = W - padL - padR;
    const plotH = H - padT - padB;
    const maxVal = Math.max(...data.map((d) => Number(d.revenue || 0)), 100);
    const niceMax = Math.ceil(maxVal / 100) * 100;

    let grid = '';
    for (let i = 0; i <= 4; i++) {
      const y = padT + (plotH * i) / 4;
      const v = niceMax * (1 - i / 4);
      grid += `<line x1="${padL}" y1="${y}" x2="${W - padR}" y2="${y}" stroke="#e2e8f0" stroke-dasharray="${i === 4 ? '' : '3,3'}" />`;
      grid += `<text x="${padL - 8}" y="${y + 4}" text-anchor="end" font-size="10.5" fill="#64748b" font-family="ui-monospace,monospace">${fmtShortINR(v)}</text>`;
    }

    const stepX = data.length > 1 ? plotW / (data.length - 1) : plotW;
    const points = data.map((d, idx) => {
      const x = data.length > 1 ? padL + idx * stepX : padL + plotW / 2;
      const y = padT + plotH - (Number(d.revenue || 0) / niceMax) * plotH;
      return { x, y, ...d };
    });

    const barW = Math.max(6, Math.min(26, (plotW / Math.max(data.length, 1)) * 0.52));
    const bars = points
      .map((p) => {
        const h = Math.max(0, padT + plotH - p.y);
        return `<rect x="${p.x - barW / 2}" y="${p.y}" width="${barW}" height="${h}" rx="4" fill="rgba(234, 88, 12, 0.22)">
          <title>${escapeHtml(p.label)}: ${fmtINR(p.revenue)} (${p.orders} bills, AOV ${fmtINR(p.avg_bill)})</title>
        </rect>`;
      })
      .join('');

    const lineD = points.map((p, i) => `${i === 0 ? 'M' : 'L'} ${p.x.toFixed(1)} ${p.y.toFixed(1)}`).join(' ');
    const areaD =
      points.length > 1
        ? `${lineD} L ${points[points.length - 1].x.toFixed(1)} ${(padT + plotH).toFixed(1)} L ${points[0].x.toFixed(1)} ${(padT + plotH).toFixed(1)} Z`
        : '';

    const dots = points
      .map((p) => {
        if (Number(p.revenue || 0) <= 0 && data.length > 14) return '';
        return `<circle cx="${p.x.toFixed(1)}" cy="${p.y.toFixed(1)}" r="4" fill="#ea580c" stroke="#ffffff" stroke-width="2">
          <title>${escapeHtml(p.label)}: ${fmtINR(p.revenue)} • ${p.orders} bills • Avg ${fmtINR(p.avg_bill)}</title>
        </circle>`;
      })
      .join('');

    const labelStep = Math.max(1, Math.ceil(data.length / 8));
    const xLabels = points
      .map((p, i) => {
        if (i !== 0 && i !== points.length - 1 && i % labelStep !== 0) return '';
        return `<text x="${p.x.toFixed(1)}" y="${H - 10}" text-anchor="middle" font-size="10" fill="#64748b" font-weight="600">${escapeHtml(p.label)}</text>`;
      })
      .join('');

    el.innerHTML = `
      <svg viewBox="0 0 ${W} ${H}" class="pos-svg-chart" role="img" aria-label="Daily Revenue Trend">
        <defs>
          <linearGradient id="revAreaGrad_${containerId}" x1="0" y1="0" x2="0" y2="1">
            <stop offset="0%" stop-color="#ea580c" stop-opacity="0.28"/>
            <stop offset="100%" stop-color="#ea580c" stop-opacity="0.02"/>
          </linearGradient>
        </defs>
        ${grid}
        ${areaD ? `<path d="${areaD}" fill="url(#revAreaGrad_${containerId})" />` : ''}
        ${bars}
        ${points.length > 1 ? `<path d="${lineD}" fill="none" stroke="#ea580c" stroke-width="2.5" stroke-linejoin="round" stroke-linecap="round"/>` : ''}
        ${dots}
        ${xLabels}
      </svg>`;
  }

  /**
   * 2. Interactive SVG Donut / Pie Chart with Legend
   */
  function renderDonut(containerId, slices, centerTitle) {
    const el = document.getElementById(containerId);
    if (!el) return;

    const valid = (Array.isArray(slices) ? slices : []).filter((s) => Number(s.value || 0) > 0);
    const total = valid.reduce((sum, s) => sum + Number(s.value || 0), 0);
    if (valid.length === 0 || total <= 0) {
      return renderEmpty(el, 'No breakdown data in this range.');
    }

    const r = 68;
    const cx = 90;
    const cy = 90;
    const circumference = 2 * Math.PI * r;
    let offset = 0;

    const rings = valid
      .map((s) => {
        const frac = Number(s.value) / total;
        const dash = frac * circumference;
        const gap = Math.max(0, circumference - dash);
        const pct = (frac * 100).toFixed(1);
        const seg = `<circle cx="${cx}" cy="${cy}" r="${r}" fill="none" stroke="${escapeHtml(s.color)}" stroke-width="26"
          stroke-dasharray="${dash.toFixed(2)} ${gap.toFixed(2)}"
          stroke-dashoffset="${(-offset).toFixed(2)}"
          transform="rotate(-90 ${cx} ${cy})">
          <title>${escapeHtml(s.label)}: ${fmtINR(s.value)} (${pct}%)</title>
        </circle>`;
        offset += dash;
        return seg;
      })
      .join('');

    const legend = valid
      .map((s) => {
        const pct = ((Number(s.value) / total) * 100).toFixed(1);
        return `
          <div class="donut-leg-row">
            <span class="donut-dot" style="background:${escapeHtml(s.color)}"></span>
            <span class="donut-leg-lbl">${escapeHtml(s.label)}</span>
            <span class="donut-leg-val mono">${fmtINR(s.value)} <em>(${pct}%)</em></span>
          </div>`;
      })
      .join('');

    el.innerHTML = `
      <div class="donut-wrap">
        <svg viewBox="0 0 180 180" class="donut-svg" role="img" aria-label="${escapeHtml(centerTitle || 'Distribution Chart')}">
          <circle cx="${cx}" cy="${cy}" r="${r}" fill="none" stroke="#f1f5f9" stroke-width="26"/>
          ${rings}
          <text x="${cx}" y="${cy - 4}" text-anchor="middle" font-size="10" font-weight="800" fill="#64748b">${escapeHtml(centerTitle || 'TOTAL')}</text>
          <text x="${cx}" y="${cy + 14}" text-anchor="middle" font-size="14" font-weight="900" fill="#0f172a" font-family="ui-monospace,monospace">${fmtShortINR(total)}</text>
        </svg>
        <div class="donut-legend">${legend}</div>
      </div>`;
  }

  /**
   * 3. Peak Hours Vertical Bar Chart
   */
  function renderHourlyBars(containerId, hours) {
    const el = document.getElementById(containerId);
    if (!el) return;
    if (!Array.isArray(hours) || hours.length === 0) {
      return renderEmpty(el, 'No hourly order data yet.');
    }
    const maxRev = Math.max(...hours.map((h) => Number(h.revenue || 0)), 0);
    if (maxRev <= 0) {
      return renderEmpty(el, 'Hourly rush-hour distribution appears once bills settle.');
    }

    const W = 540;
    const H = 210;
    const padL = 48;
    const padR = 12;
    const padT = 16;
    const padB = 30;
    const plotW = W - padL - padR;
    const plotH = H - padT - padB;
    const slotW = plotW / hours.length;
    const barW = Math.max(8, slotW * 0.62);

    let grid = '';
    for (let i = 0; i <= 3; i++) {
      const y = padT + (plotH * i) / 3;
      const v = maxRev * (1 - i / 3);
      grid += `<line x1="${padL}" y1="${y}" x2="${W - padR}" y2="${y}" stroke="#e2e8f0" stroke-dasharray="${i === 3 ? '' : '3,3'}"/>`;
      grid += `<text x="${padL - 6}" y="${y + 4}" text-anchor="end" font-size="10" fill="#64748b" font-family="ui-monospace,monospace">${fmtShortINR(v)}</text>`;
    }

    const bars = hours
      .map((h, i) => {
        const val = Number(h.revenue || 0);
        const bh = maxRev > 0 ? (val / maxRev) * plotH : 0;
        const x = padL + i * slotW + (slotW - barW) / 2;
        const y = padT + plotH - bh;
        const isPeak = val === maxRev && val > 0;
        const fill = isPeak ? '#ea580c' : '#2563eb';
        return `
          <rect x="${x.toFixed(1)}" y="${y.toFixed(1)}" width="${barW.toFixed(1)}" height="${bh.toFixed(1)}" rx="4" fill="${fill}" opacity="${val > 0 ? '0.9' : '0.15'}">
            <title>${escapeHtml(h.label)}: ${fmtINR(val)} (${h.orders} bills)</title>
          </rect>
          <text x="${(x + barW / 2).toFixed(1)}" y="${H - 9}" text-anchor="middle" font-size="9.5" fill="${isPeak ? '#ea580c' : '#64748b'}" font-weight="${isPeak ? '800' : '600'}">${escapeHtml(h.label)}</text>`;
      })
      .join('');

    el.innerHTML = `<svg viewBox="0 0 ${W} ${H}" class="pos-svg-chart" role="img" aria-label="Hourly Peak Sales">${grid}${bars}</svg>`;
  }

  /**
   * 4. Horizontal Bar Chart (Multi-Outlet Comparison / Top Dishes)
   */
  function renderHorizontalBars(containerId, rows, color) {
    const el = document.getElementById(containerId);
    if (!el) return;
    if (!Array.isArray(rows) || rows.length === 0) {
      return renderEmpty(el, 'No comparison records yet.');
    }
    const maxVal = Math.max(...rows.map((r) => Number(r.value || 0)), 1);
    const barColor = color || '#ea580c';

    el.innerHTML = `
      <div class="hbar-list">
        ${rows
          .map((r) => {
            const val = Number(r.value || 0);
            const pct = Math.max(2, Math.round((val / maxVal) * 100));
            return `
              <div class="hbar-item">
                <div class="hbar-meta">
                  <span class="hbar-name">${escapeHtml(r.label)} ${r.badge ? `<span class="badge b-slate">${escapeHtml(r.badge)}</span>` : ''}</span>
                  <span class="hbar-val mono">${fmtINR(val)} ${r.sub ? `<small>${escapeHtml(r.sub)}</small>` : ''}</span>
                </div>
                <div class="hbar-track"><div class="hbar-fill" style="width:${pct}%;background:${escapeHtml(r.color || barColor)}"></div></div>
              </div>`;
          })
          .join('')}
      </div>`;
  }

  return {
    renderRevenueTrend,
    renderDonut,
    renderHourlyBars,
    renderHorizontalBars,
    fmtINR,
  };
})();
