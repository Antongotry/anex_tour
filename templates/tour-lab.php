<?php
/**
 * ITTour lab: фото (HTTPS + відносні URL), модалка з tour/* та hotel/*.
 */
if (!defined('ABSPATH')) {
    exit;
}

$_anex_embed = defined('ANEX_EMBED_MODE') && ANEX_EMBED_MODE;
if (!$_anex_embed) {
    status_header(200);
    nocache_headers();
}

$ajax_url = admin_url('admin-ajax.php');
$nonce    = wp_create_nonce('ittour_lab_public');
?>
<?php if (!$_anex_embed): ?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo('charset'); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?php echo esc_html(get_bloginfo('name')); ?> — Тури та відпочинок</title>
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<?php endif; ?>
	<style>
		/* ─── Variables ─── */
		:root {
			--bg: #f4f6fb;
			--card: #fff;
			--text: #111827;
			--muted: #6b7280;
			--line: #e5e7eb;
			--blue: #1a5dc8;
			--blue-dark: #1348a8;
			--blue-light: #2870e0;
			--green: #22c55e;
			--green-hover: #16a34a;
			--red: #e53535;
			--star: #f59e0b;
			--radius: 12px;
			--shadow: 0 1px 4px rgba(0,0,0,.07);
			--shadow-md: 0 4px 16px rgba(0,0,0,.1);
		}
		*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
		body {
			background: var(--bg);
			color: var(--text);
			font: 15px/1.6 'Montserrat', system-ui, -apple-system, sans-serif;
		}

		/* ─── Layout ─── */
		.wrap { max-width: 1160px; margin: 0 auto; padding: 0 20px; }
		.page-body { padding-bottom: 60px; }

		/* ─── Header ─── */
		.topbar {
			background: var(--card);
			box-shadow: 0 2px 12px rgba(0,0,0,.08);
			position: sticky;
			top: 0;
			z-index: 1000;
			padding: 0;
		}
		.topbar .wrap {
			display: flex;
			align-items: center;
			justify-content: space-between;
			height: 66px;
			gap: 20px;
			max-width: 1160px;
			margin: 0 auto;
			padding: 0 20px;
		}
		.logo {
			display: flex;
			align-items: center;
			gap: 6px;
			text-decoration: none;
			flex-shrink: 0;
		}
		.logo-wordmark {
			display: flex;
			flex-direction: column;
			line-height: 1.1;
		}
		.logo-wordmark .l-anex { font-size: 17px; font-weight: 800; color: var(--blue); letter-spacing: -.3px; }
		.logo-wordmark .l-tour { font-size: 11px; font-weight: 600; color: var(--blue); letter-spacing: 1.5px; text-transform: uppercase; }
		.logo-heart { color: var(--red); font-size: 16px; }
		.topbar-nav {
			display: flex;
			align-items: center;
			gap: 2px;
			flex: 1;
			justify-content: center;
		}
		.topbar-nav a {
			color: var(--text);
			text-decoration: none;
			font-size: 14px;
			font-weight: 500;
			padding: 7px 13px;
			border-radius: 8px;
			transition: color .15s, background .15s;
			white-space: nowrap;
		}
		.topbar-nav a:hover,
		.topbar-nav a.active { color: var(--blue); background: #eff6ff; }
		.topbar-right {
			display: flex;
			align-items: center;
			gap: 12px;
			flex-shrink: 0;
		}
		.topbar-phone { display: flex; flex-direction: column; text-align: right; }
		.topbar-phone a { font-size: 14px; font-weight: 700; color: var(--text); text-decoration: none; }
		.topbar-phone a:hover { color: var(--blue); }
		.topbar-phone .online {
			font-size: 11px;
			color: #16a34a;
			font-weight: 500;
			display: flex;
			align-items: center;
			gap: 4px;
			justify-content: flex-end;
		}
		.topbar-phone .online::before { content: '●'; font-size: 7px; }
		.topbar-socials { display: flex; gap: 6px; }
		.topbar-socials a {
			width: 32px; height: 32px;
			border-radius: 50%;
			display: flex; align-items: center; justify-content: center;
			font-size: 14px;
			text-decoration: none;
			font-weight: 700;
			transition: opacity .15s;
			color: #fff;
		}
		.topbar-socials a:hover { opacity: .85; }
		.soc-v  { background: #7360f2; }
		.soc-t  { background: #2AABEE; }
		.soc-i  { background: linear-gradient(45deg,#f09433,#e6683c,#dc2743,#cc2366,#bc1888); }
		.badge-dev {
			font-size: 11px;
			background: #fef3c7;
			color: #92400e;
			padding: 3px 9px;
			border-radius: 999px;
			white-space: nowrap;
		}

		/* ─── Sections ─── */
		.section { margin-bottom: 48px; }
		.section:first-child { margin-top: 36px; }
		.section-head {
			display: flex;
			align-items: baseline;
			justify-content: space-between;
			gap: 12px;
			margin-bottom: 20px;
		}
		.section-head h2 { font-size: 1.45rem; font-weight: 800; color: var(--text); }
		.section-head p { color: var(--muted); font-size: .9rem; margin-top: 4px; }
		.section-head-sub { margin-top: 4px; color: var(--muted); font-size: .88rem; }

		/* ─── City tabs ─── */
		.tabs-wrap { position: relative; margin-bottom: 24px; }
		.tabs {
			display: flex;
			gap: 8px;
			overflow-x: auto;
			padding: 4px 48px 4px 0;
			scrollbar-width: none;
		}
		.tabs::-webkit-scrollbar { display: none; }
		.tab {
			flex: 0 0 auto;
			padding: 9px 18px;
			border-radius: 10px;
			border: 1.5px solid var(--line);
			background: var(--card);
			cursor: pointer;
			font-size: 14px;
			font-weight: 500;
			color: var(--text);
			transition: all .15s;
		}
		.tab:hover { border-color: var(--blue); color: var(--blue); }
		.tab.active { background: var(--blue); color: #fff; border-color: var(--blue); }
		.tab-scroll {
			position: absolute;
			right: 0; top: 50%;
			transform: translateY(-50%);
			width: 38px; height: 38px;
			border-radius: 50%;
			border: 1.5px solid var(--line);
			background: var(--card);
			cursor: pointer;
			font-size: 18px;
			display: flex; align-items: center; justify-content: center;
			color: var(--muted);
			transition: all .15s;
		}
		.tab-scroll:hover { border-color: var(--blue); color: var(--blue); }

		/* ─── Image wrappers ─── */
		.img-wrap { position: relative; width: 100%; height: 160px; background: #e5e7eb; overflow: hidden; }
		.img-wrap.tall { height: 150px; }
		.img-wrap img.cover { width: 100%; height: 100%; object-fit: cover; display: block; transition: transform .4s; }
		.img-wrap img.cover:hover { transform: scale(1.04); }
		.img-wrap.no-img::after {
			content: 'Немає фото';
			position: absolute; inset: 0;
			display: flex; align-items: center; justify-content: center;
			color: var(--muted); font-size: .85rem;
		}
		.img-wrap.no-img img { display: none; }

		/* ─── Country cards ─── */
		.grid-countries {
			display: grid;
			grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
			gap: 18px;
		}
		.card-country {
			background: var(--card);
			border-radius: var(--radius);
			overflow: hidden;
			box-shadow: var(--shadow);
			border: 1.5px solid var(--line);
			display: flex;
			flex-direction: column;
			transition: box-shadow .2s, transform .2s;
		}
		.card-country:hover { box-shadow: var(--shadow-md); transform: translateY(-3px); }
		.card-country .body {
			padding: 14px 16px 16px;
			display: flex;
			flex: 1;
			justify-content: space-between;
			gap: 12px;
		}
		.card-country .body h3 { font-size: 15px; font-weight: 700; margin-bottom: 4px; }
		.card-country .price-block { text-align: right; flex-shrink: 0; }
		.card-country .price-label { font-size: .72rem; color: var(--muted); }
		.card-country .price { font-weight: 800; font-size: 1.05rem; color: var(--blue); }

		/* ─── Hotels carousel ─── */
		.hotels-row {
			display: flex;
			gap: 16px;
			overflow-x: auto;
			padding-bottom: 10px;
			scrollbar-width: thin;
			scrollbar-color: var(--line) transparent;
		}
		.hotel-card {
			flex: 0 0 260px;
			background: var(--card);
			border-radius: var(--radius);
			border: 1.5px solid var(--line);
			overflow: hidden;
			box-shadow: var(--shadow);
			display: flex;
			flex-direction: column;
			transition: box-shadow .2s, transform .2s;
		}
		.hotel-card:hover { box-shadow: var(--shadow-md); transform: translateY(-3px); }
		.hotel-card .badge-rate {
			position: absolute; top: 10px; right: 10px;
			background: rgba(255,255,255,.96);
			border-radius: 8px; padding: 6px 9px;
			font-size: .75rem;
			display: flex; align-items: center; gap: 8px;
			box-shadow: var(--shadow); z-index: 1;
		}
		.hotel-card .badge-rate strong {
			background: var(--blue); color: #fff;
			padding: 4px 8px; border-radius: 6px;
			font-size: .82rem;
		}
		.hotel-card .hb { padding: 14px 14px 16px; flex: 1; display: flex; flex-direction: column; gap: 4px; }
		.hotel-card .hb h3 { font-size: .95rem; font-weight: 700; }
		.hotel-card .loc { font-size: .8rem; color: var(--muted); margin-bottom: 8px; }
		.stars { color: var(--star); font-size: .85rem; margin-bottom: 4px; letter-spacing: 1px; }

		/* ─── Buttons ─── */
		.btn-green {
			display: block;
			width: 100%;
			text-align: center;
			padding: 10px 16px;
			border: none;
			border-radius: 10px;
			background: var(--blue);
			color: #fff;
			font-weight: 600;
			font-size: 14px;
			cursor: pointer;
			font-family: inherit;
			transition: background .15s, transform .1s;
			margin-top: auto;
		}
		.btn-green:hover { background: var(--blue-dark); transform: translateY(-1px); }

		/* ─── Search bar ─── */
		.search-bar {
			display: grid;
			grid-template-columns: 1.2fr 1fr 1fr .7fr .7fr auto;
			gap: 12px;
			background: var(--card);
			padding: 18px 20px;
			border-radius: 16px;
			border: 1.5px solid var(--line);
			box-shadow: var(--shadow);
			align-items: end;
		}
		@media (max-width: 960px) { .search-bar { grid-template-columns: 1fr 1fr; } }
		@media (max-width: 560px)  { .search-bar { grid-template-columns: 1fr; } }
		.field label {
			display: block;
			font-size: .7rem;
			font-weight: 600;
			color: var(--muted);
			margin-bottom: 5px;
			text-transform: uppercase;
			letter-spacing: .5px;
		}
		.field select, .field input {
			width: 100%;
			padding: 10px 12px;
			border: 1.5px solid var(--line);
			border-radius: 9px;
			font: inherit;
			font-size: 14px;
			color: var(--text);
			background: #fff;
			transition: border-color .15s;
		}
		.field select:focus, .field input:focus {
			outline: none;
			border-color: var(--blue);
		}
		.btn-search {
			align-self: end;
			padding: 11px 24px;
			border: none;
			border-radius: 10px;
			background: var(--blue);
			color: #fff;
			font-weight: 700;
			font-size: 14px;
			cursor: pointer;
			font-family: inherit;
			white-space: nowrap;
			transition: background .15s, transform .1s;
			height: 44px;
		}
		.btn-search:hover { background: var(--blue-dark); transform: translateY(-1px); }

		/* ─── Results layout ─── */
		.layout-results { display: grid; grid-template-columns: 240px 1fr; gap: 20px; }
		@media (max-width: 900px) { .layout-results { grid-template-columns: 1fr; } }
		.results-title { font-size: .95rem; font-weight: 700; margin-bottom: 14px; color: var(--muted); }

		/* ─── Filters ─── */
		.filters {
			background: var(--card);
			border: 1.5px solid var(--line);
			border-radius: var(--radius);
			padding: 18px 16px;
			font-size: .88rem;
			position: sticky;
			top: 80px;
			align-self: start;
		}
		.filters h4 { font-size: .95rem; font-weight: 700; margin-bottom: 12px; color: var(--text); }
		.filter-label {
			font-weight: 600;
			font-size: .82rem;
			color: var(--muted);
			text-transform: uppercase;
			letter-spacing: .4px;
			margin-bottom: 8px;
		}
		.rating-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 6px; margin-bottom: 16px; }
		.rating-grid label {
			display: flex;
			align-items: center;
			justify-content: center;
			padding: 8px 4px;
			border: 1.5px solid var(--line);
			border-radius: 8px;
			cursor: pointer;
			font-size: .8rem;
			transition: all .15s;
		}
		.rating-grid label:hover { border-color: var(--blue); color: var(--blue); }
		.rating-grid input { display: none; }
		.rating-grid label:has(input:checked) { border-color: var(--blue); background: #eff6ff; color: var(--blue); font-weight: 600; }
		.filter-reset {
			font-size: .8rem;
			color: var(--blue);
			border: none;
			background: none;
			cursor: pointer;
			padding: 0;
			margin-bottom: 14px;
			font-family: inherit;
		}
		.filter-reset:hover { text-decoration: underline; }
		.filter-input {
			width: 100%;
			margin-top: 6px;
			padding: 9px 10px;
			border: 1.5px solid var(--line);
			border-radius: 8px;
			font: inherit;
			font-size: 14px;
			transition: border-color .15s;
		}
		.filter-input:focus { outline: none; border-color: var(--blue); }

		/* ─── Tour rows ─── */
		.tour-row {
			display: grid;
			grid-template-columns: 200px 1fr 220px;
			gap: 16px;
			background: var(--card);
			border: 1.5px solid var(--line);
			border-radius: var(--radius);
			padding: 16px;
			margin-bottom: 12px;
			box-shadow: var(--shadow);
			transition: box-shadow .2s, transform .2s;
		}
		.tour-row:hover { box-shadow: var(--shadow-md); transform: translateY(-2px); }
		@media (max-width: 800px) { .tour-row { grid-template-columns: 1fr; } }
		.tour-row .photo { height: 140px; border-radius: 10px; overflow: hidden; background: #e5e7eb; }
		.tour-row .hotel-name { font-size: 1rem; font-weight: 700; margin-bottom: 5px; }
		.tour-row .hotel-meta { color: var(--muted); font-size: .85rem; margin-bottom: 8px; }
		.tour-row .offers-mini { font-size: .78rem; color: var(--muted); margin-top: 8px; max-height: 4.5em; overflow: hidden; }
		.price-big { font-size: 1.15rem; font-weight: 800; color: var(--blue); }

		/* ─── States ─── */
		.loading { color: var(--muted); padding: 8px 0; font-size: .9rem; }
		.err-banner {
			background: #fef2f2;
			border: 1.5px solid #fecaca;
			color: #991b1b;
			padding: 12px 16px;
			border-radius: 10px;
			margin-bottom: 16px;
			font-size: .9rem;
		}
		.skel {
			animation: pulse 1.2s ease-in-out infinite;
			background: #e5e7eb;
			border-radius: 10px;
			height: 280px;
		}
		@keyframes pulse { 0%,100%{opacity:1} 50%{opacity:.45} }

		/* ─── Modal ─── */
		.detail-backdrop {
			position: fixed; inset: 0;
			background: rgba(0,0,0,.5);
			z-index: 10000;
			display: flex; align-items: flex-start; justify-content: center;
			padding: 24px 12px;
			overflow-y: auto;
			backdrop-filter: blur(2px);
		}
		.detail-backdrop[hidden] { display: none !important; }
		.detail-modal {
			background: var(--card);
			width: min(940px, 100%);
			border-radius: 16px;
			box-shadow: 0 16px 48px rgba(0,0,0,.22);
			position: relative;
			margin-bottom: 40px;
		}
		.detail-close {
			position: absolute; top: 12px; right: 14px;
			width: 36px; height: 36px;
			border: none; border-radius: 50%;
			background: #f3f4f6; font-size: 22px; line-height: 1;
			cursor: pointer; z-index: 2;
			transition: background .15s;
		}
		.detail-close:hover { background: #e5e7eb; }
		.detail-head { padding: 20px 52px 14px 22px; border-bottom: 1px solid var(--line); }
		.detail-head h2 { font-size: 1.1rem; font-weight: 700; }
		.detail-tabs {
			display: flex; flex-wrap: wrap; gap: 6px;
			padding: 10px 16px;
			border-bottom: 1px solid var(--line);
			background: #fafafa;
		}
		.dtab {
			padding: 7px 14px;
			border-radius: 8px;
			border: 1.5px solid transparent;
			background: transparent;
			cursor: pointer;
			font-size: .83rem;
			font-family: inherit;
			color: var(--muted);
			transition: all .15s;
		}
		.dtab:hover { color: var(--blue); border-color: var(--line); background: #fff; }
		.dtab.active { background: #fff; border-color: var(--blue); color: var(--blue); font-weight: 600; }
		.dpane { padding: 16px 20px 24px; max-height: min(70vh, 640px); overflow: auto; font-size: .88rem; }
		.dpane pre {
			white-space: pre-wrap; word-break: break-word;
			font-size: 11px; background: #f9fafb;
			padding: 12px; border-radius: 8px;
			border: 1px solid var(--line);
			max-height: 420px; overflow: auto;
		}
		.gal { display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 10px; }
		.gal a { display: block; border-radius: 8px; overflow: hidden; border: 1px solid var(--line); }
		.gal img { width: 100%; height: 100px; object-fit: cover; display: block; }
		.hotel-desc { line-height: 1.6; color: #374151; }
		.if-html { width: 100%; min-height: 320px; border: 1px solid var(--line); border-radius: 8px; background: #fff; }

		/* ─── Responsive tweaks ─── */
		@media (max-width: 768px) {
			.topbar-nav { display: none; }
			.grid-countries { grid-template-columns: 1fr 1fr; }
		}
		@media (max-width: 480px) {
			.grid-countries { grid-template-columns: 1fr; }
			.topbar-right .topbar-phone { display: none; }
		}
	</style>
<?php if (!$_anex_embed): ?>
</head>
<body>
<?php endif; ?>
	<!-- ═══ HEADER ═══ -->
	<header class="topbar">
		<div class="wrap">
			<a href="#" class="logo">
				<div class="logo-wordmark">
					<span class="l-anex">anex</span>
					<span class="l-tour">Tour</span>
				</div>
				<span class="logo-heart">♥</span>
			</a>

			<nav class="topbar-nav">
				<a href="#" class="active">Головна</a>
				<a href="#">Тури</a>
				<a href="#">Послуги</a>
				<a href="#">Країни</a>
				<a href="#">Блог</a>
				<a href="#">Про нас</a>
			</nav>

			<div class="topbar-right">
				<div class="topbar-phone">
					<a href="tel:+380979451781">+380979451781</a>
					<span class="online">Ми завжди онлайн</span>
				</div>
				<div class="topbar-socials">
					<a href="#" class="soc-v"  title="Viber">V</a>
					<a href="#" class="soc-t"  title="Telegram">T</a>
					<a href="#" class="soc-i"  title="Instagram">I</a>
				</div>
				<span class="badge-dev">Lab</span>
			</div>
		</div>
	</header>

	<div class="wrap page-body">
		<div id="global-error"></div>

		<!-- ═══ Тури з вашого міста ═══ -->
		<section class="section">
			<div class="section-head">
				<div>
					<h2>Тури з вашого міста</h2>
					<div class="section-head-sub">Оберіть місто вильоту — покажемо актуальні пропозиції</div>
				</div>
			</div>
			<div class="tabs-wrap">
				<div class="tabs" id="city-tabs"></div>
				<button type="button" class="tab-scroll" id="tabs-next" aria-label="Далі">›</button>
			</div>
			<div id="countries-loading" class="loading" style="margin-top:12px">Завантаження…</div>
			<div class="grid-countries" id="countries-grid" style="margin-top:4px"></div>
		</section>

		<!-- ═══ Популярні готелі ═══ -->
		<section class="section">
			<div class="section-head">
				<div>
					<h2>Популярні готелі</h2>
					<div class="section-head-sub">Топ-готелі за відгуками наших клієнтів</div>
				</div>
			</div>
			<div class="hotels-row" id="hotels-carousel"></div>
			<div id="hotels-loading" class="loading">Завантаження…</div>
		</section>

		<!-- ═══ Пошук турів ═══ -->
		<section class="section">
			<div class="section-head">
				<div>
					<h2>Пошук турів</h2>
					<div class="section-head-sub">Знайдіть ідеальний тур за параметрами</div>
				</div>
			</div>
			<form class="search-bar" id="search-form">
				<div class="field"><label>Країна</label><select id="sf-country"></select></div>
				<div class="field"><label>Місто вильоту</label><select id="sf-from"></select></div>
				<div class="field"><label>Дата від</label><input type="text" id="sf-d1" placeholder="дд.мм.рр"></div>
				<div class="field"><label>Дата до</label><input type="text" id="sf-d2" placeholder="дд.мм.рр"></div>
				<div class="field">
					<label>Ночі (від — до)</label>
					<div style="display:flex;gap:6px">
						<input type="number" id="sf-n1" min="1" max="30" value="6" style="width:50%">
						<input type="number" id="sf-n2" min="1" max="30" value="8" style="width:50%">
					</div>
				</div>
				<button type="submit" class="btn-search">Знайти тури</button>
			</form>
			<div class="layout-results" style="margin-top:24px">
				<aside class="filters">
					<h4>Фільтри</h4>
					<button type="button" class="filter-reset" id="filter-reset">Скинути всі</button>
					<div class="filter-label">Зірки готелю</div>
					<div class="rating-grid" id="rating-filters"></div>
					<div class="filter-label" style="margin-top:4px">Ціна до (грн)</div>
					<input type="number" id="sf-price-max" min="0" step="500" value="200000" class="filter-input">
				</aside>
				<div>
					<div class="results-title" id="results-count">Знайдено 0 готелів</div>
					<div id="results-list"></div>
					<div class="loading" id="results-loading" style="display:none">Пошук…</div>
				</div>
			</div>
		</section>
	</div>

	<div id="detail-backdrop" class="detail-backdrop" hidden>
		<div class="detail-modal" role="dialog" aria-modal="true" aria-labelledby="dh-title">
			<button type="button" class="detail-close" id="detail-close" aria-label="Закрити">&times;</button>
			<div class="detail-head">
				<h2 id="dh-title">Деталі</h2>
				<p id="dh-sub" style="margin:6px 0 0;color:var(--muted);font-size:.85rem"></p>
			</div>
			<div class="detail-tabs" id="detail-tabs"></div>
			<div id="detail-pane" class="dpane"></div>
		</div>
	</div>

	<!-- ═══ FOOTER ═══ -->
	<footer style="background:#0f2460;color:rgba(255,255,255,.75);padding:36px 0 20px;margin-top:60px">
		<div class="wrap" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:20px">
			<div>
				<div style="display:flex;align-items:center;gap:6px;margin-bottom:8px">
					<div style="display:flex;flex-direction:column;line-height:1.1">
						<span style="font-size:16px;font-weight:800;color:#fff">anex</span>
						<span style="font-size:10px;font-weight:600;color:rgba(255,255,255,.6);letter-spacing:1.5px">TOUR</span>
					</div>
					<span style="color:#e53535;font-size:15px">♥</span>
				</div>
				<div style="font-size:12px;color:rgba(255,255,255,.5)">Офіційне франчайзингове агентство Anex Tour</div>
			</div>
			<div style="display:flex;flex-direction:column;gap:4px;font-size:13px">
				<a href="tel:+380979451781" style="color:rgba(255,255,255,.8);text-decoration:none">+380979451781</a>
				<span style="font-size:11px;color:#4ade80">● Ми завжди онлайн</span>
			</div>
			<div style="font-size:11px;color:rgba(255,255,255,.4);text-align:right">
				Львів, вул. Героїв УПА, 6<br>
				© <?php echo date('Y'); ?> Anex Tour Львів
			</div>
		</div>
	</footer>

	<script>
	(function(){
		const ajaxUrl = <?php echo wp_json_encode($ajax_url); ?>;
		const nonce = <?php echo wp_json_encode($nonce); ?>;
		const IMG_BASE = 'https://www.ittour.com.ua/';
		const COUNTRY_IDS = [338,318,16,320,372,376];
		const REF_COUNTRY_FOR_CITIES = 318;
		const HOTEL_COUNTRY_POPULAR = 318;

		function showErr(msg) {
			document.getElementById('global-error').innerHTML = '<div class="err-banner">' + esc(msg) + '</div>';
		}
		function esc(s) {
			const d = document.createElement('div');
			d.textContent = s == null ? '' : String(s);
			return d.innerHTML;
		}
		function escAttr(s) {
			return String(s == null ? '' : s).replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;');
		}

		/** Чому не було фото: http на https-сайті, або відносний шлях без домену */
		function fixMediaUrl(u) {
			if (!u || typeof u !== 'string') return '';
			let x = u.trim();
			if (x.startsWith('//')) x = 'https:' + x;
			if (x.startsWith('http://')) x = 'https://' + x.slice(7);
			if (!/^https?:\/\//i.test(x)) x = IMG_BASE.replace(/\/$/, '') + '/' + x.replace(/^\//, '');
			return x;
		}

		function pickImageFromOffer(o) {
			if (!o) return '';
			const list = o.hotel_images || o.images || [];
			const main = list.find(i => String(i.is_main) === '1' || i.is_main === 1);
			const im = main || list[0];
			return im && im.full ? fixMediaUrl(im.full) : (im && im.web ? fixMediaUrl(im.web) : '');
		}

		function imgHtml(url, wrapClass) {
			const wc = (wrapClass || '').trim();
			const cls = 'img-wrap' + (wc ? ' ' + wc : '');
			const u = fixMediaUrl(url);
			if (!u) return '<div class="' + cls + ' no-img"><span></span></div>';
			return '<div class="' + cls + '"><img class="cover" src="' + escAttr(u) + '" alt="" loading="lazy" referrerpolicy="no-referrer-when-downgrade" onerror="this.closest(\'.img-wrap\').classList.add(\'no-img\')"></div>';
		}

		async function api(path, query) {
			const body = new URLSearchParams();
			body.set('action', 'ittour_lab_public');
			body.set('nonce', nonce);
			body.set('path', path);
			body.set('lang', 'uk');
			body.set('query', JSON.stringify(query || {}));
			const r = await fetch(ajaxUrl, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body });
			const j = await r.json();
			if (!j.success) throw new Error((j.data && j.data.message) || 'Помилка AJAX');
			const pack = j.data;
			if (pack.data && pack.data.error) throw new Error(pack.data.error_desc || pack.data.error || 'Помилка API');
			return pack.data;
		}

		function pad(n) { return n < 10 ? '0' + n : '' + n; }
		function fmtDMY(d) { return pad(d.getDate()) + '.' + pad(d.getMonth() + 1) + '.' + String(d.getFullYear()).slice(-2); }
		function defaultDates() {
			const a = new Date();
			a.setDate(a.getDate() + 3);
			const b = new Date(a);
			b.setDate(b.getDate() + 10);
			return { d1: fmtDMY(a), d2: fmtDMY(b) };
		}
		function moneyUAH(n) {
			if (n == null || isNaN(n)) return '—';
			return new Intl.NumberFormat('uk-UA').format(Math.round(n)) + ' грн';
		}

		let selectedCityId = null;
		let allCountries = [];
		let hotelRatings = [];

		function mergeFromCities(a, b) {
			const m = new Map();
			[...(a || []), ...(b || [])].forEach(c => { if (c && c.id) m.set(String(c.id), c); });
			const list = Array.from(m.values());
			const pri = ['2014', '143', '1745', '449', '1212'];
			list.sort((x, y) => {
				const ix = pri.indexOf(String(x.id)), iy = pri.indexOf(String(y.id));
				if (ix >= 0 || iy >= 0) return (ix < 0 ? 999 : ix) - (iy < 0 ? 999 : iy);
				return (x.name || '').localeCompare(y.name || '', 'uk');
			});
			return list;
		}

		async function initCities() {
			document.getElementById('countries-loading').style.display = 'block';
			try {
				const [p318, p338] = await Promise.all([
					api('module/params/' + REF_COUNTRY_FOR_CITIES, { entity: 'from_city' }),
					api('module/params/338', { entity: 'from_city' })
				]);
				const cities = mergeFromCities(p318.from_cities || [], p338.from_cities || []);
				if (!cities.length) throw new Error('Порожній список міст');
				selectedCityId = String(cities[0].id);
				const el = document.getElementById('city-tabs');
				el.innerHTML = '';
				cities.forEach(c => {
					const b = document.createElement('button');
					b.type = 'button';
					b.className = 'tab' + (String(c.id) === selectedCityId ? ' active' : '');
					b.textContent = c.name;
					b.dataset.id = String(c.id);
					b.addEventListener('click', async () => {
						selectedCityId = String(c.id);
						el.querySelectorAll('.tab').forEach(t => t.classList.toggle('active', t.dataset.id === selectedCityId));
						document.getElementById('sf-from').value = selectedCityId;
						await refreshCountriesBlock();
						await refreshHotelsCarousel();
					});
					el.appendChild(b);
				});
				document.getElementById('tabs-next').onclick = () => el.scrollBy({ left: 280, behavior: 'smooth' });
				document.getElementById('countries-loading').style.display = 'none';
				await refreshCountriesBlock();
				await refreshHotelsCarousel();
			} catch (e) {
				document.getElementById('countries-loading').textContent = '';
				showErr(String(e.message || e));
			}
		}

		async function oneCountryCard(countryId, fromCity, d1, d2) {
			const data = await api('module/search-list', {
				type: '1', kind: '1', country: String(countryId), from_city: fromCity,
				adult_amount: '2', child_amount: '0', hotel_rating: '3:78',
				night_from: '6', night_till: '8', date_from: d1, date_till: d2,
				items_per_page: '1', hotel_info: '1', currency: '2'
			});
			const off = (data.offers && data.offers[0]) ? data.offers[0] : null;
			if (!off) return null;
			const img = pickImageFromOffer(off);
			const price = off.prices && off.prices['2'] != null ? off.prices['2'] : null;
			const depShort = off.date_from ? (() => { const p = off.date_from.split('-'); return pad(p[2]) + '.' + pad(p[1]); })() : '';
			return {
				name: off.country || '', img, price,
				sub: 'Виліт з ' + (off.from_city || '') + (depShort ? ' ' + depShort : ''),
				key: off.key, hotel_id: off.hotel_id, hotel: off.hotel
			};
		}

		async function refreshCountriesBlock() {
			const grid = document.getElementById('countries-grid');
			grid.innerHTML = '<div class="skel" style="height:280px"></div>'.repeat(6);
			const { d1, d2 } = defaultDates();
			try {
				const cards = await Promise.all(COUNTRY_IDS.map(id => oneCountryCard(id, selectedCityId, d1, d2)));
				grid.innerHTML = '';
				cards.filter(Boolean).forEach(c => {
					const art = document.createElement('article');
					art.className = 'card-country';
					art.innerHTML = imgHtml(c.img, '') +
						'<div class="body"><div><h3 style="margin:0 0 6px">' + esc(c.name) + '</h3><p style="margin:0;font-size:.8rem;color:var(--muted)">' + esc(c.sub) + '</p></div>' +
						'<div class="price-block"><div class="price-label">Ціна за двох від</div><div class="price">' + moneyUAH(c.price) + '</div>' +
						'<button type="button" class="btn-green" style="margin-top:10px">Деталі туру</button></div></div>';
					const imgEl = art.querySelector('.img-wrap');
					if (imgEl) { imgEl.style.height = '160px'; }
					art.querySelector('.btn-green').addEventListener('click', () => openDetailModal({ key: c.key, hotel_id: c.hotel_id, title: c.hotel || c.name }));
					grid.appendChild(art);
				});
				if (!grid.children.length) grid.innerHTML = '<p class="loading">Немає пропозицій.</p>';
			} catch (e) {
				grid.innerHTML = '';
				showErr(String(e.message || e));
			}
		}

		async function refreshHotelsCarousel() {
			const el = document.getElementById('hotels-carousel');
			const ld = document.getElementById('hotels-loading');
			ld.style.display = 'block';
			el.innerHTML = '';
			const { d1, d2 } = defaultDates();
			try {
				const data = await api('module/search-list', {
					type: '1', kind: '1', country: String(HOTEL_COUNTRY_POPULAR), from_city: selectedCityId,
					adult_amount: '2', child_amount: '0', hotel_rating: '3:78',
					night_from: '6', night_till: '8', date_from: d1, date_till: d2,
					items_per_page: '24', hotel_info: '1', currency: '2'
				});
				const seen = new Set();
				const uniq = [];
				for (const o of (data.offers || [])) {
					if (!o.hotel_id || seen.has(o.hotel_id)) continue;
					seen.add(o.hotel_id);
					uniq.push(o);
					if (uniq.length >= 8) break;
				}
				uniq.forEach(o => {
					const stars = '★'.repeat(Math.min(5, parseInt(o.hotel_rating, 10) || 0)) || '★★★';
					const rr = o.hotel_review_rate != null ? String(o.hotel_review_rate) : '—';
					const rc = (o.hotel_review_count != null ? o.hotel_review_count : '') + (o.hotel_review_count != null ? ' відгуків' : '');
					const card = document.createElement('div');
					card.className = 'hotel-card';
					card.innerHTML = '<div style="position:relative">' + imgHtml(pickImageFromOffer(o), 'tall') +
						'<div class="badge-rate"><span>' + (parseFloat(rr) >= 8.5 ? 'Блискуче' : 'Дуже добре') + '<br><small style="color:#6b7280">' + esc(rc) + '</small></span><strong>' + esc(rr) + '</strong></div></div>' +
						'<div class="hb"><div class="stars">' + stars + '</div><h3 style="margin:0 0 4px;font-size:.95rem">' + esc(o.hotel || '') + '</h3>' +
						'<div class="loc" style="font-size:.8rem;color:var(--muted);margin-bottom:12px">' + esc(o.country || '') + ', ' + esc(o.region || '') + '</div>' +
						'<button type="button" class="btn-green btn-det">Деталі туру</button></div>';
					card.querySelector('.btn-det').addEventListener('click', () => openDetailModal({ key: o.key, hotel_id: o.hotel_id, title: o.hotel }));
					el.appendChild(card);
				});
			} catch (e) {
				el.innerHTML = '<p class="loading">' + esc(String(e.message || e)) + '</p>';
			}
			ld.style.display = 'none';
		}

		async function loadModuleParams() {
			const p = await api('module/params', {});
			allCountries = (p.countries || []).slice().sort((a, b) => (a.name || '').localeCompare(b.name || '', 'uk'));
			hotelRatings = p.hotel_ratings || [];
			const sc = document.getElementById('sf-country');
			sc.innerHTML = '';
			allCountries.forEach(c => {
				const o = document.createElement('option');
				o.value = String(c.id);
				o.textContent = c.name;
				if (String(c.id) === '338') o.selected = true;
				sc.appendChild(o);
			});
			await refillFromCities(sc.value);
			sc.addEventListener('change', () => refillFromCities(sc.value));
			const rg = document.getElementById('rating-filters');
			rg.innerHTML = '';
			hotelRatings.forEach(r => {
				const lab = document.createElement('label');
				lab.innerHTML = '<input type="checkbox" name="hr" value="' + esc(String(r.id)) + '"><span>' + esc(String(r.name)) + '*</span>';
				rg.appendChild(lab);
			});
			const d = defaultDates();
			document.getElementById('sf-d1').value = d.d1;
			document.getElementById('sf-d2').value = d.d2;
		}

		async function refillFromCities(countryId) {
			const sel = document.getElementById('sf-from');
			sel.innerHTML = '';
			try {
				const d = await api('module/params/' + countryId, { entity: 'from_city' });
				(d.from_cities || []).forEach(c => {
					const o = document.createElement('option');
					o.value = String(c.id);
					o.textContent = c.name;
					sel.appendChild(o);
				});
				if (selectedCityId && Array.from(sel.options).some(o => o.value === selectedCityId)) sel.value = selectedCityId;
			} catch (e) {
				sel.innerHTML = '<option>Помилка</option>';
			}
		}

		function selectedRatings() {
			const ch = Array.from(document.querySelectorAll('#rating-filters input:checked')).map(i => i.value);
			if (!ch.length) return '3:78';
			return ch.length > 2 ? ch.slice(0, 2).join(':') : ch.join(':');
		}

		async function runSearch() {
			const ld = document.getElementById('results-loading');
			const list = document.getElementById('results-list');
			const cnt = document.getElementById('results-count');
			ld.style.display = 'block';
			list.innerHTML = '';
			const country = document.getElementById('sf-country').value;
			const from_city = document.getElementById('sf-from').value;
			const d1 = document.getElementById('sf-d1').value.trim();
			const d2 = document.getElementById('sf-d2').value.trim();
			const n1 = document.getElementById('sf-n1').value;
			const n2 = document.getElementById('sf-n2').value;
			const priceMax = parseInt(document.getElementById('sf-price-max').value, 10) || 200000;
			try {
				const data = await api('module/search', {
					type: '1', kind: '1', country, from_city, adult_amount: '2', child_amount: '0',
					hotel_rating: selectedRatings(), night_from: n1, night_till: n2,
					date_from: d1, date_till: d2, currency: '2', items_per_page: '12', prices_in_group: '5'
				});
				const hotels = data.hotels || [];
				const filtered = hotels.filter(h => h.min_price == null || h.min_price <= priceMax);
				cnt.textContent = 'Знайдено ' + filtered.length + ' готелів';
				filtered.forEach(h => {
					const offers = h.offers || [];
					const first = offers[0] || {};
					const img = pickImageFromOffer({ hotel_images: h.images, images: h.images }) || pickImageFromOffer(first);
					const stars = '★'.repeat(Math.min(5, parseInt(h.hotel_rating, 10) || 0));
					const price = first.prices && first.prices['2'] != null ? first.prices['2'] : h.min_price;
					const mini = offers.slice(0, 3).map(o => (o.date_from || '') + ' · ' + moneyUAH(o.prices && o.prices['2'])).join(' · ');
					const row = document.createElement('div');
					row.className = 'tour-row';
					row.innerHTML = '<div class="photo">' + imgHtml(img, '') + '</div>' +
						'<div><div class="stars">' + stars + '</div><div class="hotel-name">' + esc(h.hotel) + '</div>' +
						'<div class="hotel-meta">' + esc(h.region || '') + (first.meal_type_full ? ' · ' + esc(first.meal_type_full) : '') + '</div>' +
						'<div class="offers-mini">' + esc(mini) + (offers.length > 3 ? '…' : '') + '</div></div>' +
						'<div style="text-align:right;display:flex;flex-direction:column;justify-content:center;gap:8px">' +
						'<div style="font-size:.75rem;color:var(--muted)">Виліт: ' + esc(first.from_city || '') + '</div>' +
						'<div class="price-big">' + moneyUAH(price) + '</div>' +
						'<button type="button" class="btn-green btn-first">Деталі туру</button>' +
						(offers.length > 1 ? '<button type="button" class="btn-green" style="background:#64748b;margin-top:4px">Ще ' + (offers.length - 1) + ' варіантів</button>' : '') +
						'</div>';
					row.querySelector('.btn-first').addEventListener('click', () => openDetailModal({ key: first.key, hotel_id: h.hotel_id, title: h.hotel }));
					if (offers.length > 1) {
						row.querySelectorAll('.btn-green')[1].addEventListener('click', () => {
							const keys = offers.map(o => o.key).filter(Boolean);
							alert('Ключі пропозицій цього готелю (для tour/info):\n' + keys.join('\n'));
						});
					}
					list.appendChild(row);
				});
				if (!filtered.length) list.innerHTML = '<p class="loading">Нічого не знайдено.</p>';
			} catch (e) {
				list.innerHTML = '<div class="err-banner">' + esc(String(e.message || e)) + '</div>';
				cnt.textContent = 'Помилка';
			}
			ld.style.display = 'none';
		}

		/* ——— Модалка: усі основні read-only методи по туру/готелю ——— */
		const backdrop = document.getElementById('detail-backdrop');
		const pane = document.getElementById('detail-pane');
		const dhTitle = document.getElementById('dh-title');
		const dhSub = document.getElementById('dh-sub');
		const dtabs = document.getElementById('detail-tabs');

		let modalCtx = { key: '', hotel_id: '', title: '' };
		let tabData = {};

		function closeDetailModal() {
			backdrop.hidden = true;
			pane.innerHTML = '';
			dtabs.innerHTML = '';
		}
		document.getElementById('detail-close').addEventListener('click', closeDetailModal);
		backdrop.addEventListener('click', (e) => { if (e.target === backdrop) closeDetailModal(); });

		async function openDetailModal(ctx) {
			modalCtx = ctx;
			dhTitle.textContent = ctx.title || 'Тур';
			dhSub.textContent = 'key: ' + ctx.key + ' · hotel_id: ' + ctx.hotel_id;
			tabData = {};
			backdrop.hidden = false;
			pane.innerHTML = '<p class="loading">Завантаження даних API…</p>';
			const hid = String(ctx.hotel_id || '');
			try {
				const [info, flights, val, himg, hinfo, rev] = await Promise.all([
					api('tour/info/' + ctx.key, { limit_images: '20' }).catch(e => ({ _error: String(e.message || e) })),
					api('tour/flights/' + ctx.key, {}).catch(e => ({ _error: String(e.message || e) })),
					api('tour/validate/' + ctx.key, {}).catch(e => ({ _error: String(e.message || e) })),
					hid ? api('hotel/' + hid + '/hotel-images', { limit_images: '20' }).catch(e => ({ _error: String(e.message || e) })) : Promise.resolve(null),
					hid ? api('hotel/' + hid + '/info', {}).catch(e => ({ _error: String(e.message || e) })) : Promise.resolve(null),
					hid ? api('hotel/' + hid + '/reviews', {}).catch(e => ({ _error: String(e.message || e) })) : Promise.resolve(null)
				]);
				tabData = { info, flights, val, himg, hinfo, rev };
			} catch (e) {
				pane.innerHTML = '<div class="err-banner">' + esc(String(e.message || e)) + '</div>';
				return;
			}
			const tabs = [
				{ id: 'info', label: 'Тур (tour/info)' },
				{ id: 'flights', label: 'Рейси (tour/flights)' },
				{ id: 'val', label: 'Валідація (tour/validate)' },
				{ id: 'himg', label: 'Фото готелю (hotel/…/hotel-images)' },
				{ id: 'hinfo', label: 'Картка готелю (hotel/…/info)' },
				{ id: 'rev', label: 'Відгуки (hotel/…/reviews)' }
			];
			dtabs.innerHTML = '';
			let active = 'info';
			tabs.forEach(t => {
				const b = document.createElement('button');
				b.type = 'button';
				b.className = 'dtab' + (t.id === active ? ' active' : '');
				b.textContent = t.label;
				b.dataset.tab = t.id;
				b.addEventListener('click', () => {
					dtabs.querySelectorAll('.dtab').forEach(x => x.classList.toggle('active', x.dataset.tab === t.id));
					active = t.id;
					renderPane(t.id);
				});
				dtabs.appendChild(b);
			});
			renderPane('info');
		}

		function renderPane(id) {
			const d = tabData[id];
			if (id === 'info') return renderTourInfo(tabData.info);
			if (id === 'flights') return renderJsonOrErr(tabData.flights, 'Рейси');
			if (id === 'val') return renderJsonOrErr(tabData.val, 'Відповідь валідації');
			if (id === 'himg') return renderHotelGallery(tabData.himg);
			if (id === 'hinfo') return renderHotelInfo(tabData.hinfo);
			if (id === 'rev') return renderJsonOrErr(tabData.rev, 'Відгуки');
		}

		function renderJsonOrErr(obj, title) {
			if (!obj) { pane.innerHTML = '<p class="loading">Немає hotel_id</p>'; return; }
			if (obj._error) { pane.innerHTML = '<div class="err-banner">' + esc(obj._error) + '</div>'; return; }
			pane.innerHTML = '<p style="margin-top:0;color:var(--muted)">' + esc(title) + '</p><pre>' + esc(JSON.stringify(obj, null, 2)) + '</pre>';
		}

		function renderTourInfo(info) {
			if (!info || info._error) {
				pane.innerHTML = '<div class="err-banner">' + esc((info && info._error) || 'Немає даних') + '</div>';
				return;
			}
			const hi = info.hotel_info || {};
			const imgs = [];
			(info.hotel_images || []).forEach(im => { if (im.full) imgs.push(fixMediaUrl(im.full)); });
			let gal = '';
			if (imgs.length) {
				gal = '<div class="gal">' + imgs.map(u => '<a href="' + escAttr(u) + '" target="_blank" rel="noopener"><img src="' + escAttr(u) + '" alt="" loading="lazy"></a>').join('') + '</div>';
			}
			const desc = hi.description ? '<div class="hotel-desc">' + esc(hi.description) + '</div>' : '';
			const html = hi.description_html
				? '<details style="margin-top:12px"><summary style="cursor:pointer;color:var(--blue)">Повний HTML-опис з API</summary><pre style="max-height:280px;overflow:auto">' + esc(hi.description_html.slice(0, 12000)) + (hi.description_html.length > 12000 ? '\n…' : '') + '</pre></details>'
				: '';
			const flightsShort = info.flights ? '<pre style="margin-top:12px">' + esc(JSON.stringify(info.flights, null, 2).slice(0, 2500)) + (JSON.stringify(info.flights).length > 2500 ? '\n…' : '') + '</pre>' : '';
			pane.innerHTML = '<div style="display:grid;gap:12px">' +
				'<div><strong>Країна:</strong> ' + esc(info.country) + ' · <strong>Регіон:</strong> ' + esc(info.region) + ' · <strong>Харчування:</strong> ' + esc(info.meal_type_full || info.meal_type) + '</div>' +
				'<div><strong>Ціни:</strong> ' + esc(JSON.stringify(info.prices || {})) + '</div>' +
				gal + desc + html + '<p style="font-size:.8rem;color:var(--muted)">Фрагмент flights з tour/info:</p>' + flightsShort +
				'<p style="font-size:.8rem;color:var(--muted)">Повний JSON:</p><pre>' + esc(JSON.stringify(info, null, 2).slice(0, 12000)) + '</pre></div>';
		}

		function renderHotelGallery(himg) {
			if (!himg || himg._error) {
				pane.innerHTML = '<div class="err-banner">' + esc((himg && himg._error) || 'Немає даних') + '</div>';
				return;
			}
			const hid = String(modalCtx.hotel_id);
			const arr = himg[hid] || himg[modalCtx.hotel_id] || Object.values(himg)[0];
			if (!Array.isArray(arr) || !arr.length) {
				pane.innerHTML = '<p>Галерея порожня або формат відповіді інший.</p><pre>' + esc(JSON.stringify(himg, null, 2)) + '</pre>';
				return;
			}
			pane.innerHTML = '<div class="gal">' + arr.map(im => {
				const u = fixMediaUrl(im.full || im.web || im.thumb);
				return '<a href="' + escAttr(u) + '" target="_blank" rel="noopener"><img src="' + escAttr(u) + '" alt="" loading="lazy"></a>';
			}).join('') + '</div>';
		}

		function renderHotelInfo(hinfo) {
			if (!hinfo || hinfo._error) {
				pane.innerHTML = '<div class="err-banner">' + esc((hinfo && hinfo._error) || 'Немає даних') + '</div>';
				return;
			}
			pane.innerHTML = '<pre>' + esc(JSON.stringify(hinfo, null, 2)) + '</pre>';
		}

		document.getElementById('search-form').addEventListener('submit', function(ev) { ev.preventDefault(); runSearch(); });
		document.getElementById('filter-reset').addEventListener('click', function() {
			document.querySelectorAll('#rating-filters input').forEach(i => { i.checked = false; });
			document.getElementById('sf-price-max').value = '200000';
			runSearch();
		});
		document.getElementById('rating-filters').addEventListener('change', () => runSearch());

		(async function boot() {
			await loadModuleParams();
			await initCities();
			if (selectedCityId) document.getElementById('sf-from').value = selectedCityId;
			await runSearch();
		})();
	})();
	</script>
<?php if (!$_anex_embed): ?>
</body>
</html>
<?php exit;
endif; ?>
