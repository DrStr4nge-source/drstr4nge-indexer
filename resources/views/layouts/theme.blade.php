@verbatim
<style>
/* ===== DrStr4nge Indexer – dunkles Theme (eigenständig, braucht keine Tailwind-Konfiguration) ===== */
:root {
    --tm-bg: #0b0e13;
    --tm-bg-2: #0f131a;
    --tm-panel: #131a24;
    --tm-panel-2: #18212e;
    --tm-line: rgba(255, 255, 255, .07);
    --tm-line-2: rgba(255, 255, 255, .12);
    --tm-text: #e6e9ef;
    --tm-muted: #8b95a5;
    --tm-dim: #5f6b7c;
    --tm-accent: #f59e0b;
    --tm-accent-2: #fbbf24;
    --tm-accent-ink: #1a1203;
    --tm-ok: #34d399;
    --tm-warn: #fbbf24;
    --tm-bad: #f87171;
    --tm-radius: 12px;
    --tm-font: "Figtree", ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
}
*, *::before, *::after { box-sizing: border-box; }
html, body { margin: 0; }
body.tm-body { background: var(--tm-bg); color: var(--tm-text); font-family: var(--tm-font); font-size: 15px; line-height: 1.5; -webkit-font-smoothing: antialiased; }
.tm-body a { color: inherit; text-decoration: none; }
.tm-body img { display: block; max-width: 100%; }
.tm-body code { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: .9em; background: var(--tm-bg); border: 1px solid var(--tm-line); border-radius: 6px; padding: 1px 6px; }
.tm-body h1, .tm-body h2, .tm-body h3 { margin: 0; font-weight: 600; letter-spacing: -.01em; }
.tm-body p { margin: 0; }
.tm-link { color: var(--tm-accent) !important; }
.tm-link:hover { color: var(--tm-accent-2) !important; text-decoration: underline; }
.tm-muted { color: var(--tm-muted); }
.tm-small { font-size: 13px; }
.tm-container { max-width: 1680px; margin: 0 auto; padding: 0 24px; }
.tm-main { padding: 24px 0 48px; }
.tm-stack > * + * { margin-top: 20px; }
.tm-sr { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0 0 0 0); }

/* ---- Navigation ---- */
.tm-nav { position: sticky; top: 0; z-index: 40; background: rgba(15, 19, 26, .92); backdrop-filter: blur(10px); border-bottom: 1px solid var(--tm-line); }
.tm-nav-row { display: flex; align-items: center; gap: 20px; height: 60px; }
.tm-logo { display: flex; align-items: center; gap: 10px; font-weight: 700; font-size: 17px; white-space: nowrap; }
.tm-logo-mark { width: 30px; height: 30px; border-radius: 8px; display: grid; place-items: center; background: linear-gradient(135deg, var(--tm-accent), #ea580c); color: var(--tm-accent-ink); }
.tm-logo-text b { color: var(--tm-accent); font-weight: 700; }
.tm-navlinks { display: flex; gap: 4px; }
.tm-navlinks a { padding: 7px 11px; border-radius: 8px; color: var(--tm-muted); font-size: 14.5px; white-space: nowrap; }
.tm-navlinks a:hover { color: var(--tm-text); background: var(--tm-line); }
.tm-navlinks a.is-active { color: var(--tm-text); }
.tm-navlinks a.is-active::after { content: ""; display: block; height: 2px; margin-top: 3px; border-radius: 2px; background: var(--tm-accent); }
.tm-search { flex: 1; display: flex; align-items: stretch; min-width: 0; max-width: 980px; height: 38px; background: var(--tm-bg); border: 1px solid var(--tm-line-2); border-radius: 10px; overflow: hidden; }
.tm-search:focus-within { border-color: var(--tm-accent); box-shadow: 0 0 0 3px rgba(245, 158, 11, .15); }
.tm-search select { appearance: none; background: var(--tm-panel); color: var(--tm-text); border: 0; border-right: 1px solid var(--tm-line-2); padding: 0 28px 0 12px; font: inherit; font-size: 13.5px; cursor: pointer;
    background-image: linear-gradient(45deg, transparent 50%, var(--tm-muted) 50%), linear-gradient(135deg, var(--tm-muted) 50%, transparent 50%);
    background-position: calc(100% - 15px) 16px, calc(100% - 10px) 16px; background-size: 5px 5px; background-repeat: no-repeat; }
.tm-search input { flex: 1; min-width: 0; background: transparent; border: 0; outline: 0; color: var(--tm-text); padding: 0 12px; font: inherit; font-size: 14px; }
.tm-search input::placeholder { color: var(--tm-dim); }
.tm-kbd { align-self: center; margin-right: 8px; font-size: 11px; color: var(--tm-dim); border: 1px solid var(--tm-line-2); border-radius: 5px; padding: 1px 6px; white-space: nowrap; }
.tm-search button { border: 0; background: var(--tm-accent); color: var(--tm-accent-ink); width: 46px; display: grid; place-items: center; cursor: pointer; }
.tm-search button:hover { background: var(--tm-accent-2); }
.tm-nav-right { display: flex; align-items: center; gap: 10px; margin-left: auto; }
.tm-pill { display: inline-flex; align-items: center; gap: 6px; padding: 5px 11px; border-radius: 999px; font-size: 12.5px; font-weight: 600; border: 1px solid var(--tm-line-2); color: var(--tm-muted); white-space: nowrap; }
.tm-pill.is-vip { color: var(--tm-accent-ink); background: var(--tm-accent); border-color: var(--tm-accent); }
.tm-pill:hover { border-color: var(--tm-accent); color: var(--tm-text); }
.tm-pill.is-vip:hover { color: var(--tm-accent-ink); background: var(--tm-accent-2); }

/* Ausklapp-Menüs ohne JavaScript (details/summary) */
details.tm-menu { position: relative; }
details.tm-menu > summary { list-style: none; cursor: pointer; }
details.tm-menu > summary::-webkit-details-marker { display: none; }
.tm-avatar { width: 34px; height: 34px; border-radius: 50%; display: grid; place-items: center; background: var(--tm-panel-2); border: 1px solid var(--tm-line-2); font-weight: 700; font-size: 13px; text-transform: uppercase; }
details.tm-menu[open] .tm-avatar, .tm-avatar:hover { border-color: var(--tm-accent); }
.tm-dropdown { position: absolute; right: 0; top: calc(100% + 8px); min-width: 220px; background: var(--tm-panel); border: 1px solid var(--tm-line-2); border-radius: 12px; padding: 6px; box-shadow: 0 18px 40px rgba(0, 0, 0, .45); z-index: 50; }
.tm-dropdown .tm-dd-head { padding: 8px 10px 10px; border-bottom: 1px solid var(--tm-line); margin-bottom: 6px; }
.tm-dropdown a, .tm-dropdown button { display: flex; width: 100%; align-items: center; gap: 10px; padding: 8px 10px; border-radius: 8px; color: var(--tm-text); background: none; border: 0; font: inherit; font-size: 14px; text-align: left; cursor: pointer; }
.tm-dropdown a:hover, .tm-dropdown button:hover { background: var(--tm-line); }
.tm-burger { display: none; }
.tm-burger > summary { width: 38px; height: 38px; border-radius: 9px; display: grid; place-items: center; border: 1px solid var(--tm-line-2); }
.tm-burger .tm-dropdown { left: 0; right: auto; }
.tm-search-mobile { display: none; padding: 0 0 12px; }

/* ---- Panels und Überschriften ---- */
.tm-panel { background: var(--tm-panel); border: 1px solid var(--tm-line); border-radius: var(--tm-radius); padding: 22px 26px; }
.tm-panel.tight { padding: 0; overflow: hidden; }
.tm-panel-head { display: flex; align-items: flex-end; justify-content: space-between; gap: 16px; margin-bottom: 18px; }
.tm-eyebrow { display: flex; align-items: center; gap: 7px; color: var(--tm-muted); font-size: 12px; font-weight: 600; letter-spacing: .06em; text-transform: uppercase; margin-bottom: 4px; }
.tm-eyebrow svg { color: var(--tm-accent); }
.tm-h1 { font-size: 26px; }
.tm-h2 { font-size: 20px; font-weight: 500 !important; }
.tm-page-head { display: flex; align-items: center; justify-content: space-between; gap: 16px; flex-wrap: wrap; }

/* ---- Buttons, Eingaben ---- */
.tm-btn { display: inline-flex; align-items: center; justify-content: center; gap: 7px; height: 36px; padding: 0 14px; border-radius: 9px; font: inherit; font-size: 14px; font-weight: 600; cursor: pointer; border: 1px solid var(--tm-line-2); background: transparent; color: var(--tm-text) !important; white-space: nowrap; text-decoration: none !important; }
.tm-btn:hover { border-color: var(--tm-accent); }
.tm-btn-primary { background: var(--tm-accent); border-color: var(--tm-accent); color: var(--tm-accent-ink) !important; }
.tm-btn-primary:hover { background: var(--tm-accent-2); border-color: var(--tm-accent-2); }
.tm-btn-danger { border-color: rgba(248, 113, 113, .45); color: var(--tm-bad) !important; }
.tm-btn-danger:hover { background: rgba(248, 113, 113, .1); border-color: var(--tm-bad); }
.tm-btn-sm { height: 30px; padding: 0 10px; font-size: 13px; border-radius: 8px; }
.tm-btn[disabled] { opacity: .45; cursor: not-allowed; }
.tm-input, .tm-select { height: 38px; background: var(--tm-bg); color: var(--tm-text); border: 1px solid var(--tm-line-2); border-radius: 9px; padding: 0 12px; font: inherit; font-size: 14px; outline: 0; width: 100%; }
.tm-input:focus, .tm-select:focus { border-color: var(--tm-accent); box-shadow: 0 0 0 3px rgba(245, 158, 11, .15); }
.tm-input-sm { height: 30px; font-size: 13px; padding: 0 8px; border-radius: 7px; }
.tm-input[type=file] { padding: 5px 6px; height: auto; color: var(--tm-muted); }
.tm-input[type=file]::file-selector-button { margin-right: 12px; height: 28px; padding: 0 12px; border-radius: 7px; border: 1px solid var(--tm-line-2); background: var(--tm-panel-2); color: var(--tm-text); font: inherit; font-size: 13px; font-weight: 600; cursor: pointer; }
.tm-input[type=file]::file-selector-button:hover { border-color: var(--tm-accent); }
.tm-label { display: block; font-size: 13.5px; font-weight: 600; color: var(--tm-muted); margin-bottom: 6px; }
.tm-error { list-style: none; margin: 6px 0 0; padding: 0; color: var(--tm-bad); font-size: 13px; }
.tm-form-row { display: flex; gap: 10px; flex-wrap: wrap; align-items: center; }
.tm-form-row > .grow { flex: 1; min-width: 200px; }

/* ---- Hinweise ---- */
.tm-alert { display: flex; align-items: center; justify-content: space-between; gap: 14px; padding: 12px 16px; border-radius: 10px; border: 1px solid var(--tm-line); background: var(--tm-panel); font-size: 14px; }
.tm-alert.info { border-left: 3px solid var(--tm-accent); }
.tm-alert.ok { border-color: rgba(52, 211, 153, .35); background: rgba(52, 211, 153, .08); }
.tm-alert.warn { border-color: rgba(251, 191, 36, .35); background: rgba(251, 191, 36, .08); }
.tm-alert.bad { border-color: rgba(248, 113, 113, .35); background: rgba(248, 113, 113, .08); }

/* ---- Cover-Reihen ---- */
.tm-row { position: relative; }
.tm-scroller { display: flex; gap: 14px; overflow-x: auto; scroll-snap-type: x mandatory; scroll-behavior: smooth; padding-bottom: 4px; scrollbar-width: none; }
.tm-scroller::-webkit-scrollbar { display: none; }
.tm-card { flex: 0 0 158px; scroll-snap-align: start; background: var(--tm-bg-2); border: 1px solid var(--tm-line); border-radius: 10px; overflow: hidden; transition: transform .15s ease, border-color .15s ease; }
.tm-card:hover { transform: translateY(-3px); border-color: rgba(245, 158, 11, .55); }
.tm-poster { position: relative; aspect-ratio: 2 / 3; background: linear-gradient(160deg, #1f2a3a, #0f141c); overflow: hidden; }
.tm-poster img { width: 100%; height: 100%; object-fit: cover; }
.tm-poster-fallback { position: absolute; inset: 0; display: grid; place-items: center; padding: 14px; text-align: center; color: var(--tm-muted); font-weight: 600; font-size: 14px; }
.tm-rating { position: absolute; top: 7px; right: 7px; display: inline-flex; align-items: center; gap: 3px; padding: 2px 7px; border-radius: 6px; background: rgba(8, 10, 14, .82); color: #fff; font-size: 11.5px; font-weight: 700; }
.tm-rating svg { color: var(--tm-accent); }
.tm-card-body { padding: 9px 10px 11px; }
.tm-card-title { font-size: 14px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.tm-meta { display: flex; align-items: center; gap: 7px; margin-top: 5px; font-size: 12px; color: var(--tm-muted); }
.tm-chip { display: inline-block; padding: 1px 7px; border-radius: 5px; background: var(--tm-panel-2); border: 1px solid var(--tm-line); color: var(--tm-text); font-size: 11.5px; font-weight: 600; white-space: nowrap; }
.tm-chip.accent { background: rgba(245, 158, 11, .14); border-color: rgba(245, 158, 11, .35); color: var(--tm-accent-2); }
.tm-chip.bad { background: rgba(248, 113, 113, .12); border-color: rgba(248, 113, 113, .35); color: var(--tm-bad); }
.tm-report { display: inline-block; position: relative; vertical-align: middle; }
.tm-report > summary { list-style: none; width: 30px; padding: 0; color: var(--tm-muted) !important; }
.tm-report > summary::-webkit-details-marker { display: none; }
.tm-report[open] > summary { border-color: var(--tm-accent); color: var(--tm-text) !important; }
.tm-report-box { position: absolute; right: 0; top: calc(100% + 6px); z-index: 30; width: 240px; padding: 12px; text-align: left; white-space: normal; background: var(--tm-panel-2); border: 1px solid var(--tm-line-2); border-radius: 10px; box-shadow: 0 12px 30px rgba(0, 0, 0, .45); }
.tm-arrow { position: absolute; top: 38%; z-index: 5; width: 36px; height: 36px; border-radius: 50%; display: grid; place-items: center; border: 1px solid var(--tm-line-2); background: rgba(10, 13, 18, .85); color: var(--tm-text); cursor: pointer; }
.tm-arrow:hover { border-color: var(--tm-accent); color: var(--tm-accent); }
.tm-arrow.left { left: -12px; }
.tm-arrow.right { right: -12px; }
.tm-empty { padding: 28px; text-align: center; color: var(--tm-muted); border: 1px dashed var(--tm-line-2); border-radius: 10px; }

/* ---- Tabellen ---- */
.tm-table-wrap { overflow-x: auto; }
.tm-table { width: 100%; border-collapse: collapse; font-size: 14px; }
.tm-table th { text-align: left; font-size: 12px; font-weight: 600; letter-spacing: .05em; text-transform: uppercase; color: var(--tm-muted); padding: 11px 14px; border-bottom: 1px solid var(--tm-line); white-space: nowrap; }
.tm-table td { padding: 10px 14px; border-bottom: 1px solid var(--tm-line); vertical-align: middle; }
.tm-table tr:last-child td { border-bottom: 0; }
.tm-table tbody tr:hover { background: rgba(255, 255, 255, .025); }
.tm-table .nowrap { white-space: nowrap; }
.tm-table .num { text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; }
.tm-relname { word-break: break-all; font-size: 13.5px; }
.tm-thumb { width: 38px; height: 56px; border-radius: 5px; object-fit: cover; background: var(--tm-panel-2); }
.tm-thumb-empty { width: 38px; height: 56px; border-radius: 5px; background: var(--tm-panel-2); border: 1px solid var(--tm-line); }

/* ---- Titelseite ---- */
.tm-hero { position: relative; overflow: hidden; border-radius: var(--tm-radius); border: 1px solid var(--tm-line); background: var(--tm-panel); }
.tm-hero-bg { position: absolute; inset: -40px; background-size: cover; background-position: center; filter: blur(38px) saturate(1.2); opacity: .35; }
.tm-hero-inner { position: relative; display: flex; gap: 28px; padding: 28px; background: linear-gradient(90deg, rgba(19, 26, 36, .96) 30%, rgba(19, 26, 36, .7)); }
.tm-hero-poster { flex: 0 0 220px; border-radius: 10px; overflow: hidden; box-shadow: 0 20px 40px rgba(0, 0, 0, .45); }
.tm-hero-text { display: flex; flex-direction: column; gap: 12px; max-width: 760px; }
.tm-hero-text .tm-h1 span { color: var(--tm-muted); font-weight: 400; }
.tm-hero-actions { display: flex; gap: 10px; flex-wrap: wrap; margin-top: 4px; }

/* ---- Kacheln (Admin, VIP, Konto) ---- */
.tm-grid { display: grid; gap: 16px; }
.tm-grid.cols-3 { grid-template-columns: repeat(3, minmax(0, 1fr)); }
.tm-grid.cols-6 { grid-template-columns: repeat(6, minmax(0, 1fr)); }
.tm-grid.cols-2 { grid-template-columns: repeat(2, minmax(0, 1fr)); }
.tm-grid.cols-4 { grid-template-columns: repeat(4, minmax(0, 1fr)); }
.tm-stat { background: var(--tm-panel); border: 1px solid var(--tm-line); border-radius: var(--tm-radius); padding: 16px 18px; }
.tm-stat .v { font-size: 26px; font-weight: 700; margin-top: 2px; }
.tm-plan { background: var(--tm-panel); border: 1px solid var(--tm-line); border-radius: var(--tm-radius); padding: 24px; text-align: center; display: flex; flex-direction: column; gap: 8px; align-items: center; }
.tm-plan:hover { border-color: rgba(245, 158, 11, .5); }
.tm-plan .price { font-size: 32px; font-weight: 700; }
.tm-plan .price small { font-size: 15px; color: var(--tm-muted); font-weight: 500; }
.tm-bar { height: 8px; background: var(--tm-bg); border-radius: 99px; overflow: hidden; border: 1px solid var(--tm-line); }
.tm-bar > span { display: block; height: 100%; background: var(--tm-accent); }
.tm-tabs { display: flex; gap: 6px; flex-wrap: wrap; margin-bottom: 18px; }
.tm-tabs a { padding: 7px 13px; border-radius: 8px; color: var(--tm-muted); border: 1px solid var(--tm-line); font-size: 14px; }
.tm-tabs a:hover { color: var(--tm-text); border-color: var(--tm-line-2); }
.tm-tabs a.is-active { color: var(--tm-accent-ink); background: var(--tm-accent); border-color: var(--tm-accent); font-weight: 600; }
.tm-list > * { display: flex; justify-content: space-between; align-items: center; gap: 12px; padding: 10px 0; border-bottom: 1px solid var(--tm-line); }
.tm-list > *:last-child { border-bottom: 0; }

/* ---- Film-/Serien-Karten ---- */
.tm-mgrid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 20px; }
.tm-mcard { background: var(--tm-panel); border: 1px solid var(--tm-line); border-radius: var(--tm-radius); padding: 20px; display: flex; flex-direction: column; gap: 18px; }
.tm-mcard-top { display: flex; gap: 20px; }
.tm-mcard-poster { flex: 0 0 150px; border-radius: 10px; overflow: hidden; align-self: flex-start; box-shadow: 0 12px 28px rgba(0, 0, 0, .35); }
.tm-mcard-info { min-width: 0; display: flex; flex-direction: column; gap: 10px; }
.tm-mcard-title { font-size: 21px; }
.tm-mcard-title a:hover { color: var(--tm-accent); }
.tm-iconline { display: inline-flex; align-items: center; gap: 6px; font-size: 14px; color: var(--tm-muted); }
.tm-star { color: var(--tm-accent); font-weight: 600; }
.tm-clamp { display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; color: var(--tm-text); font-size: 14.5px; }
.tm-facts { margin: 0; display: flex; flex-direction: column; gap: 3px; font-size: 13.5px; color: var(--tm-muted); }
.tm-facts dt { display: inline; font-weight: 700; color: var(--tm-text); }
.tm-facts dd { display: inline; margin: 0; }
.tm-mcard-rels { border-top: 1px solid var(--tm-line); padding-top: 16px; display: flex; flex-direction: column; gap: 10px; }
.tm-relbox { background: var(--tm-bg-2); border: 1px solid var(--tm-line); border-radius: 10px; padding: 12px 14px; }
.tm-relbox:hover { border-color: var(--tm-line-2); }
@media (max-width: 1180px) { .tm-mgrid { grid-template-columns: 1fr; } }
@media (max-width: 760px) { .tm-mcard-top { flex-direction: column; } .tm-mcard-poster { flex-basis: auto; width: 140px; } .tm-mcard { padding: 16px; } }

/* ---- Seitennavigation ---- */
.tm-pager { display: flex; flex-wrap: wrap; align-items: center; gap: 6px; margin-top: 18px; }
.tm-pager a, .tm-pager span { min-width: 34px; height: 34px; padding: 0 10px; display: inline-grid; place-items: center; border-radius: 8px; border: 1px solid var(--tm-line); font-size: 14px; color: var(--tm-muted); }
.tm-pager a:hover { border-color: var(--tm-accent); color: var(--tm-text); }
.tm-pager .active { background: var(--tm-accent); border-color: var(--tm-accent); color: var(--tm-accent-ink); font-weight: 700; }
.tm-pager .off { opacity: .4; }
.tm-pager .info { border: 0; margin-left: auto; font-size: 13px; }

/* ---- Fußzeile ---- */
.tm-footer { border-top: 1px solid var(--tm-line); padding: 22px 0 30px; color: var(--tm-dim); font-size: 13px; }
.tm-footer .tm-container { display: flex; justify-content: space-between; gap: 16px; flex-wrap: wrap; }

/* ---- Gast-Seiten (Login, Registrieren) ---- */
.tm-guest { min-height: 100vh; display: grid; place-items: center; padding: 32px 16px; background: radial-gradient(1200px 600px at 50% -10%, rgba(245, 158, 11, .10), transparent 60%), var(--tm-bg); }
.tm-guest-box { width: 100%; max-width: 420px; }
.tm-guest-box .tm-logo { justify-content: center; margin-bottom: 22px; font-size: 20px; }
.tm-guest-card { background: var(--tm-panel); border: 1px solid var(--tm-line); border-radius: 14px; padding: 28px; box-shadow: 0 30px 60px rgba(0, 0, 0, .35); }

/* ---- Breeze-Reste (Profil, Login-Formulare) ans Theme anpassen ---- */
.tm-body .bg-white { background: var(--tm-panel) !important; }
.tm-body .bg-gray-100, .tm-body .bg-gray-50 { background: transparent !important; }
.tm-body .shadow, .tm-body .sm\:shadow-md, .tm-body .shadow-sm { box-shadow: none !important; }
.tm-body .text-gray-900, .tm-body .text-gray-800 { color: var(--tm-text) !important; }
.tm-body .text-gray-700, .tm-body .text-gray-600, .tm-body .text-gray-500 { color: var(--tm-muted) !important; }
.tm-body .hover\:text-gray-900:hover { color: var(--tm-text) !important; }
.tm-body .border-gray-300, .tm-body .border-gray-200, .tm-body .border-gray-100 { border-color: var(--tm-line-2) !important; }
.tm-body .text-indigo-600, .tm-body .text-green-600 { color: var(--tm-accent) !important; }
.tm-body .underline { text-decoration-color: var(--tm-dim); }
.tm-body .max-w-7xl { max-width: 1100px; }
.tm-body .p-4.sm\:p-8 { border: 1px solid var(--tm-line); border-radius: var(--tm-radius); }
.tm-body input[type=checkbox] { -webkit-appearance: none; appearance: none; flex: 0 0 auto; margin: 0; width: 16px; height: 16px; border: 1px solid var(--tm-line-2); border-radius: 4px; background-color: var(--tm-bg); background-image: none; color: var(--tm-accent); cursor: pointer; vertical-align: middle; }
.tm-body input[type=checkbox]:hover { border-color: var(--tm-accent); }
.tm-body input[type=checkbox]:focus { outline: none; box-shadow: none; }
.tm-body input[type=checkbox]:focus-visible { outline: 2px solid rgba(245, 158, 11, .5); outline-offset: 2px; }
.tm-body input[type=checkbox]:checked { background-color: var(--tm-accent); border-color: var(--tm-accent); background-repeat: no-repeat; background-position: center; background-size: 12px 12px;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3E%3Cpath d='M3.5 8.5l3 3 6-7' fill='none' stroke='%231a1203' stroke-width='2.2' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E"); }
.tm-body input[type=checkbox]:indeterminate { background-color: var(--tm-accent); border-color: var(--tm-accent); background-repeat: no-repeat; background-position: center; background-size: 10px 2px;
    background-image: linear-gradient(var(--tm-accent-ink), var(--tm-accent-ink)); }
.tm-body .fixed.inset-0 .bg-gray-500 { background: rgba(0, 0, 0, .7) !important; }

/* ---- Handy und Tablet ---- */
@media (max-width: 1180px) {
    .tm-navlinks { display: none; }
    .tm-burger { display: block; }
    .tm-grid.cols-6 { grid-template-columns: repeat(3, minmax(0, 1fr)); }
    .tm-grid.cols-4 { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}
@media (max-width: 760px) {
    .tm-container { padding: 0 16px; }
    .tm-nav .tm-search { display: none; }
    .tm-search-mobile { display: block; }
    .tm-search-mobile .tm-search { display: flex; max-width: none; }
    .tm-panel { padding: 18px 16px; }
    .tm-panel-head { flex-direction: column; align-items: flex-start; }
    .tm-card { flex-basis: 128px; }
    .tm-arrow { display: none; }
    .tm-hero-inner { flex-direction: column; padding: 18px; }
    .tm-hero-poster { flex-basis: auto; width: 160px; }
    .tm-grid.cols-3, .tm-grid.cols-2 { grid-template-columns: 1fr; }
    .tm-grid.cols-6 { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .tm-hide-sm { display: none; }
    .tm-nav .tm-logo-text { display: none; }
}
</style>
<script>
(function () {
    // Pfeile an den Cover-Reihen
    document.addEventListener('click', function (e) {
        var b = e.target.closest('[data-scroll]');
        if (b) {
            var s = b.closest('.tm-row').querySelector('.tm-scroller');
            s.scrollBy({ left: (b.dataset.scroll === 'left' ? -1 : 1) * s.clientWidth * 0.85, behavior: 'smooth' });
        }
        // offene Menüs schließen, wenn daneben geklickt wird
        document.querySelectorAll('details.tm-menu[open]').forEach(function (d) {
            if (!d.contains(e.target)) d.removeAttribute('open');
        });
        // In die Zwischenablage kopieren
        var c = e.target.closest('[data-copy]');
        if (c && navigator.clipboard) {
            navigator.clipboard.writeText(c.dataset.copy).then(function () {
                var t = c.textContent; c.textContent = 'Kopiert'; setTimeout(function () { c.textContent = t; }, 1500);
            });
        }
    });
    // "Release melden": Fenster frei über der Seite, damit die scrollbare Tabelle es nicht abschneidet
    function placeReport(d) {
        var box = d.querySelector('.tm-report-box'), r = d.querySelector('summary').getBoundingClientRect();
        box.style.position = 'fixed';
        box.style.left = Math.max(8, Math.min(r.right - 240, window.innerWidth - 248)) + 'px';
        var below = r.bottom + 6, h = box.offsetHeight || 170;
        box.style.top = (below + h > window.innerHeight - 8 ? Math.max(8, r.top - h - 6) : below) + 'px';
        box.style.right = 'auto';
    }
    document.addEventListener('toggle', function (e) {
        var d = e.target;
        if (!d.classList || !d.classList.contains('tm-report') || !d.open) return;
        document.querySelectorAll('.tm-report[open]').forEach(function (o) { if (o !== d) o.open = false; });
        placeReport(d);
    }, true);
    document.addEventListener('click', function (e) {
        if (!e.target.closest('.tm-report')) document.querySelectorAll('.tm-report[open]').forEach(function (o) { o.open = false; });
    });
    window.addEventListener('scroll', function () { document.querySelectorAll('.tm-report[open]').forEach(function (o) { o.open = false; }); }, { passive: true });

    // Strg+K / Cmd+K springt in die Suche
    document.addEventListener('keydown', function (e) {
        if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
            var i = document.querySelector('.tm-search input:not([disabled])');
            var all = document.querySelectorAll('.tm-search input');
            for (var n = 0; n < all.length; n++) { if (all[n].offsetParent !== null) { i = all[n]; break; } }
            if (i) { e.preventDefault(); i.focus(); i.select(); }
        }
    });
})();
</script>
@endverbatim
