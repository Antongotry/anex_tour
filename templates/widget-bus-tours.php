<?php
/**
 * Anex Tour — Widget: Hot Bus Tours [anex_bus_tours]
 * Standalone showcase for excursion/bus tours from IT-Tour module-excursion/search.
 */
defined( 'ABSPATH' ) || exit;

$ajax_url = admin_url( 'admin-ajax.php' );
$nonce    = wp_create_nonce( 'ittour_lab_public' );

if ( function_exists( 'ittour_lab_get_token' ) && ittour_lab_get_token() === '' ) {
    $is_admin = current_user_can( 'manage_options' );
    echo '<div style="padding:24px;border:2px dashed #e53535;border-radius:12px;background:#fff5f5;font-family:Montserrat,sans-serif;color:#e53535">';
    echo '<strong>Anex Tour: API Token не налаштовано.</strong><br>';
    if ( $is_admin ) {
        echo 'Перейдіть у <a href="' . esc_url( admin_url( 'admin.php?page=anex-tour' ) ) . '"><strong>Anex Tour → Налаштування</strong></a> і вставте токен IT-Tour.';
    } else {
        echo 'Виджет тимчасово недоступний.';
    }
    echo '</div>';
    return;
}

$catalog_url = function_exists( 'anex_get_excursions_page_permalink' )
    ? anex_get_excursions_page_permalink( [] )
    : home_url( '/ekskursijni-tury/' );

$detail_url = function_exists( 'anex_get_excursion_detail_nav_base_url' )
    ? anex_get_excursion_detail_nav_base_url()
    : $catalog_url;

$widget_id = function_exists( 'wp_unique_id' ) ? wp_unique_id( 'anex-bus-tours-' ) : ( 'anex-bus-tours-' . wp_rand( 1000, 9999 ) );

$tabs = [
    [ 'id' => 'all', 'name' => 'Усі автобусні', 'country' => '112:109:53:30:68:420:76:442:318:320' ],
    [ 'id' => 'romania', 'name' => 'Румунія', 'country' => '112' ],
    [ 'id' => 'poland-czech', 'name' => 'Польща / Чехія', 'country' => '109:53:30' ],
    [ 'id' => 'turkey', 'name' => 'Туреччина', 'country' => '318' ],
    [ 'id' => 'italy', 'name' => 'Італія', 'country' => '76:68:30' ],
    [ 'id' => 'croatia', 'name' => 'Хорватія', 'country' => '442' ],
];
?>
<section class="anex-bus-tours-widget" id="<?php echo esc_attr( $widget_id ); ?>">
<style>
@import url('https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800;900&display=swap');
#<?php echo esc_attr( $widget_id ); ?>{--abtw-accent:#1a5dc8;--abtw-accent-strong:#1348a8;--abtw-red:#f31624;--abtw-text:#111827;--abtw-muted:#6b7280;--abtw-line:#e5e7eb;--abtw-card:#fff;--abtw-star:#f59e0b;font-family:'Montserrat',system-ui,-apple-system,'Segoe UI',sans-serif;color:var(--abtw-text);width:100%;max-width:none!important;margin:0!important;padding:0!important;background:transparent!important}
#<?php echo esc_attr( $widget_id ); ?> *{box-sizing:border-box}
#<?php echo esc_attr( $widget_id ); ?> .abtw-frame{width:100%;margin:0!important;padding:34px 34px 36px;border:0!important;background:transparent!important}
#<?php echo esc_attr( $widget_id ); ?> .abtw-head{display:flex;align-items:flex-end;justify-content:space-between;gap:18px;margin-bottom:26px}
#<?php echo esc_attr( $widget_id ); ?> .abtw-copy{display:grid;gap:8px;max-width:780px}
#<?php echo esc_attr( $widget_id ); ?> .abtw-title{margin:0;color:#003087;font-size:clamp(30px,4vw,48px);font-weight:900;line-height:.98;letter-spacing:-.04em}
#<?php echo esc_attr( $widget_id ); ?> .abtw-subtitle{margin:0;color:var(--abtw-muted);font-size:clamp(16px,1.6vw,20px);line-height:1.45}
#<?php echo esc_attr( $widget_id ); ?> .abtw-note{margin:0;color:#6f7b94;font-size:13px;font-weight:700;text-align:right;max-width:360px}
#<?php echo esc_attr( $widget_id ); ?> .abtw-tabs{display:flex;flex-wrap:wrap;gap:10px;margin:0 0 24px}
#<?php echo esc_attr( $widget_id ); ?> .abtw-tab{appearance:none;display:inline-flex;align-items:center;gap:8px;min-height:48px;padding:11px 20px;border:1px solid var(--abtw-line)!important;border-radius:999px;background:#fff!important;background-image:none!important;color:var(--abtw-text)!important;font:inherit;font-size:15px;font-weight:900;line-height:1;cursor:pointer;box-shadow:none!important;text-decoration:none!important;transition:background .18s,color .18s,border-color .18s,box-shadow .18s,transform .18s}
#<?php echo esc_attr( $widget_id ); ?> .abtw-tab:hover{border-color:var(--abtw-accent)!important;color:var(--abtw-accent)!important;transform:translateY(-1px)}
#<?php echo esc_attr( $widget_id ); ?> .abtw-tab.is-active,#<?php echo esc_attr( $widget_id ); ?> .abtw-tab.is-active:hover,#<?php echo esc_attr( $widget_id ); ?> .abtw-tab.is-active:focus{background:var(--abtw-accent)!important;border-color:var(--abtw-accent)!important;color:#fff!important;box-shadow:0 12px 26px rgba(26,93,200,.22)!important;transform:none}
#<?php echo esc_attr( $widget_id ); ?> .abtw-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:18px}
#<?php echo esc_attr( $widget_id ); ?> .abtw-skeleton{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:18px}
#<?php echo esc_attr( $widget_id ); ?> .abtw-skeleton-card{min-height:430px;border-radius:22px;background:linear-gradient(90deg,#eef2f9 25%,#f8fbff 37%,#eef2f9 63%);background-size:400% 100%;animation:abtw-shimmer 1.35s ease infinite}
@keyframes abtw-shimmer{0%{background-position:100% 50%}to{background-position:0 50%}}
#<?php echo esc_attr( $widget_id ); ?> .abtw-card{display:flex;flex-direction:column;overflow:hidden;min-width:0;border:1px solid var(--abtw-line);border-radius:22px;background:var(--abtw-card);box-shadow:0 14px 32px rgba(33,51,98,.07);transition:transform .24s ease,border-color .24s ease,box-shadow .24s ease}
#<?php echo esc_attr( $widget_id ); ?> .abtw-card:hover{transform:translateY(-4px);border-color:rgba(26,93,200,.24);box-shadow:0 22px 42px rgba(21,39,78,.10)}
#<?php echo esc_attr( $widget_id ); ?> .abtw-media{position:relative;aspect-ratio:253/160;overflow:hidden;background:linear-gradient(135deg,rgba(26,93,200,.12),rgba(26,93,200,.03))}
#<?php echo esc_attr( $widget_id ); ?> .abtw-media img{display:block;width:100%;height:100%;object-fit:cover;transition:transform .48s ease,filter .32s ease}
#<?php echo esc_attr( $widget_id ); ?> .abtw-card:hover .abtw-media img{transform:scale(1.045);filter:saturate(1.04)}
#<?php echo esc_attr( $widget_id ); ?> .abtw-media.is-empty::after{content:'Фото оновлюється';position:absolute;inset:0;display:flex;align-items:center;justify-content:center;color:var(--abtw-muted);font-weight:800}
#<?php echo esc_attr( $widget_id ); ?> .abtw-badges{position:absolute;left:14px;right:14px;top:14px;display:flex;flex-wrap:wrap;gap:8px;z-index:2}
#<?php echo esc_attr( $widget_id ); ?> .abtw-badge{display:inline-flex;align-items:center;min-height:30px;padding:7px 11px;border-radius:999px;background:rgba(255,255,255,.95);color:#173b7d;font-size:12px;font-weight:900;box-shadow:0 10px 22px rgba(12,29,62,.14);backdrop-filter:blur(10px)}
#<?php echo esc_attr( $widget_id ); ?> .abtw-badge--red{background:var(--abtw-red);color:#fff}
#<?php echo esc_attr( $widget_id ); ?> .abtw-body{display:flex;flex:1;flex-direction:column;padding:18px 20px 20px}
#<?php echo esc_attr( $widget_id ); ?> .abtw-route{margin:0 0 8px;color:var(--abtw-muted);font-size:14px;font-weight:700;line-height:1.35;display:-webkit-box;-webkit-line-clamp:1;-webkit-box-orient:vertical;overflow:hidden}
#<?php echo esc_attr( $widget_id ); ?> .abtw-card-title{margin:0 0 14px;color:var(--abtw-text);font-size:20px;font-weight:900;line-height:1.14;letter-spacing:-.025em;display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical;overflow:hidden;min-height:3.42em}
#<?php echo esc_attr( $widget_id ); ?> .abtw-meta{display:grid;gap:7px;margin:0 0 16px;color:var(--abtw-muted);font-size:14px;font-weight:700;line-height:1.35}
#<?php echo esc_attr( $widget_id ); ?> .abtw-meta strong{color:#4b5873;font-weight:900}
#<?php echo esc_attr( $widget_id ); ?> .abtw-price{margin:auto 0 16px;color:var(--abtw-muted);font-size:14px;font-weight:700;line-height:1.35}
#<?php echo esc_attr( $widget_id ); ?> .abtw-price strong{display:block;margin-top:4px;color:var(--abtw-text);font-size:28px;font-weight:900;line-height:1;letter-spacing:-.035em}
#<?php echo esc_attr( $widget_id ); ?> .abtw-action,#<?php echo esc_attr( $widget_id ); ?> .abtw-action:visited{display:inline-flex;align-items:center;justify-content:center;width:100%;min-height:56px;padding:14px 18px;border:0;border-radius:14px;background:linear-gradient(135deg,var(--abtw-accent) 0%,#1347ad 100%);color:#fff!important;font-size:16px;font-weight:900;text-align:center;text-decoration:none!important;box-shadow:none;transition:transform .2s ease,background .2s ease,box-shadow .2s ease}
#<?php echo esc_attr( $widget_id ); ?> .abtw-action:hover,#<?php echo esc_attr( $widget_id ); ?> .abtw-action:focus{transform:translateY(-1px);background:linear-gradient(135deg,var(--abtw-accent-strong) 0%,#1a5dc8 100%);color:#fff!important;text-decoration:none!important;box-shadow:0 12px 24px rgba(26,93,200,.20)}
#<?php echo esc_attr( $widget_id ); ?> .abtw-empty{padding:34px 20px;border:1px solid var(--abtw-line);border-radius:18px;background:#fff;color:var(--abtw-muted);font-weight:800;text-align:center}
#<?php echo esc_attr( $widget_id ); ?> .abtw-error{color:#d91420}
@media(max-width:1180px){#<?php echo esc_attr( $widget_id ); ?> .abtw-grid,#<?php echo esc_attr( $widget_id ); ?> .abtw-skeleton{grid-template-columns:repeat(3,minmax(0,1fr))}}
@media(max-width:860px){#<?php echo esc_attr( $widget_id ); ?> .abtw-frame{padding:28px 22px 30px}#<?php echo esc_attr( $widget_id ); ?> .abtw-head{align-items:flex-start;flex-direction:column}#<?php echo esc_attr( $widget_id ); ?> .abtw-note{text-align:left}#<?php echo esc_attr( $widget_id ); ?> .abtw-grid,#<?php echo esc_attr( $widget_id ); ?> .abtw-skeleton{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:560px){#<?php echo esc_attr( $widget_id ); ?> .abtw-frame{padding:24px 16px 26px}#<?php echo esc_attr( $widget_id ); ?> .abtw-tabs{display:grid;grid-template-columns:1fr 1fr}#<?php echo esc_attr( $widget_id ); ?> .abtw-tab{justify-content:center;padding:10px 12px;font-size:13px}#<?php echo esc_attr( $widget_id ); ?> .abtw-grid,#<?php echo esc_attr( $widget_id ); ?> .abtw-skeleton{grid-template-columns:1fr}#<?php echo esc_attr( $widget_id ); ?> .abtw-card-title{min-height:0}}
</style>

<div class="abtw-frame">
    <div class="abtw-head">
        <div class="abtw-copy">
            <h2 class="abtw-title">Гарячі автобусні тури</h2>
            <p class="abtw-subtitle">Актуальні автобусні екскурсійні програми з IT-Tour — ціна показана за 2 дорослих за весь тур.</p>
        </div>
        <p class="abtw-note">Дані оновлюються автоматично. Перед бронюванням менеджер перевіряє актуальність.</p>
    </div>
    <div class="abtw-tabs" data-abtw-tabs></div>
    <div class="abtw-panel" data-abtw-panel>
        <div class="abtw-skeleton" aria-label="Завантаження">
            <div class="abtw-skeleton-card"></div>
            <div class="abtw-skeleton-card"></div>
            <div class="abtw-skeleton-card"></div>
            <div class="abtw-skeleton-card"></div>
        </div>
    </div>
</div>
<script>
(function(){
    var root = document.getElementById(<?php echo wp_json_encode( $widget_id ); ?>);
    if (!root) return;

    var ajaxUrl = <?php echo wp_json_encode( $ajax_url ); ?>;
    var nonce = <?php echo wp_json_encode( $nonce ); ?>;
    var detailBaseUrl = <?php echo wp_json_encode( $detail_url ); ?>;
    var catalogUrl = <?php echo wp_json_encode( $catalog_url ); ?>;
    var IMG_BASE = 'https://www.ittour.com.ua/';
    var TABS = <?php echo wp_json_encode( $tabs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ); ?>;
    var CARDS_PER_TAB = 4;
    var FETCH_TARGET = 10;
    var cache = new Map();
    var pending = new Map();
    var tabCache = new Map();
    var tabsEl = root.querySelector('[data-abtw-tabs]');
    var panelEl = root.querySelector('[data-abtw-panel]');

    function esc(value){ var node=document.createElement('div'); node.textContent=value==null?'':String(value); return node.innerHTML; }
    function escAttr(value){ return String(value==null?'':value).replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;'); }
    function formatMoneyUAH(value){ if(value==null||Number.isNaN(Number(value))) return '—'; return new Intl.NumberFormat('uk-UA').format(Math.round(Number(value))) + ' грн'; }
    function fixMediaUrl(value){ if(!value||typeof value!=='string') return ''; var url=value.trim(); if(url.indexOf('//')===0) url='https:'+url; if(url.indexOf('http://')===0) url='https://'+url.slice(7); if(!/^https?:\/\//i.test(url)) url=IMG_BASE.replace(/\/$/,'')+'/'+url.replace(/^\//,''); return url; }
    function formatApiDate(date){ var dd=String(date.getDate()).padStart(2,'0'); var mm=String(date.getMonth()+1).padStart(2,'0'); var yy=String(date.getFullYear()).slice(-2); return dd+'.'+mm+'.'+yy; }
    function formatHumanDate(value){ if(!value) return '—'; if(/^\d{2}\.\d{2}\.\d{2}$/.test(value)){ var p=value.split('.'); return p[0]+'.'+p[1]+'.20'+p[2]; } if(/^\d{4}-\d{2}-\d{2}$/.test(value)){ var y=value.split('-'); return y[2]+'.'+y[1]+'.'+y[0]; } return String(value); }
    function nightsLabel(value){ var n=parseInt(value,10)||0; if(!n) return ''; var mod10=n%10, mod100=n%100; var word='ночей'; if(mod10===1&&mod100!==11) word='ніч'; else if(mod10>=2&&mod10<=4&&(mod100<12||mod100>14)) word='ночі'; return n+' '+word; }
    function apiError(data){ return data&&typeof data==='object'&&!Array.isArray(data)&&data.error ? (data.error_desc||data.error) : ''; }

    function api(path, query){
        var clean = {};
        Object.keys(query||{}).forEach(function(key){ var value=query[key]; if(value!=null&&String(value).trim()!=='') clean[key]=String(value); });
        var key = path+'::'+JSON.stringify(clean);
        var hit = cache.get(key);
        if(hit && Date.now() < hit.expires) return Promise.resolve(hit.data);
        if(pending.has(key)) return pending.get(key);
        var body = new URLSearchParams();
        body.set('action','ittour_lab_public');
        body.set('nonce',nonce);
        body.set('path',path);
        body.set('lang','uk');
        body.set('query',JSON.stringify(clean));
        var request = fetch(ajaxUrl,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:body})
            .then(function(response){ return response.json(); })
            .then(function(payload){
                if(!payload || !payload.success) throw new Error((payload && payload.data && payload.data.message) || 'Помилка завантаження');
                var inner = payload.data && payload.data.data;
                if(apiError(inner)) throw new Error(apiError(inner));
                cache.set(key,{expires:Date.now()+6*60*60*1000,data:inner});
                pending.delete(key);
                return inner;
            })
            .catch(function(error){ pending.delete(key); throw error; });
        pending.set(key, request);
        return request;
    }

    function isBusOffer(offer){
        if(!offer || typeof offer !== 'object') return false;
        if(Number(offer.transport_type_id) === 2) return true;
        var label = String(offer.transport_type || '').toLowerCase();
        return label.indexOf('автоб') !== -1 || label.indexOf('bus') !== -1;
    }

    function priceUAH(offer){
        var prices = offer && offer.prices && typeof offer.prices === 'object' ? offer.prices : null;
        var value = prices && prices['2'] != null ? Number(prices['2']) : Number((offer && offer.price) || 0);
        return Number.isFinite(value) && value > 0 ? value : 0;
    }

    function dateTs(offer){
        var value = String((offer && offer.date_from) || '').trim();
        if(!value) return Infinity;
        var ts = Date.parse(value);
        return Number.isFinite(ts) ? ts : Infinity;
    }

    function imageFromOffer(offer){
        var pools = [];
        ['country_images','hotel_images','images','photos','gallery'].forEach(function(key){
            if(Array.isArray(offer && offer[key])) pools = pools.concat(offer[key]);
        });
        for(var i=0;i<pools.length;i++){
            var item = pools[i];
            if(typeof item === 'string'){
                var strUrl = fixMediaUrl(item);
                if(strUrl) return strUrl;
            } else if(item && typeof item === 'object'){
                var raw = item.full || item.web || item.url || item.src || item.image || item.photo || item.large || item.thumb || '';
                var objUrl = fixMediaUrl(raw);
                if(objUrl) return objUrl;
            }
        }
        if(offer && typeof offer.image === 'string') return fixMediaUrl(offer.image);
        return '';
    }

    function signature(offer){
        var key = String((offer && offer.key) || '').trim();
        if(key) return 'key:'+key;
        return [offer && offer.name, offer && offer.date_from, offer && offer.duration].map(function(v){ return String(v||'').trim().toLowerCase(); }).join('|');
    }

    function collectUnique(target, seen, offers){
        (offers||[]).forEach(function(offer){
            if(!isBusOffer(offer)) return;
            var sig = signature(offer);
            if(!sig || seen.has(sig)) return;
            seen.add(sig);
            target.push(offer);
        });
    }

    function sortOffers(offers){
        return (offers||[]).slice().sort(function(a,b){
            var pa = priceUAH(a) || Infinity;
            var pb = priceUAH(b) || Infinity;
            if(pa !== pb) return pa - pb;
            return dateTs(a) - dateTs(b);
        });
    }

    function buildWindows(){
        var base = new Date();
        base.setHours(12,0,0,0);
        return [140,28,56,84,112,168,196,0].map(function(offset){
            var start = new Date(base.getTime());
            start.setDate(start.getDate()+offset);
            var end = new Date(start.getTime());
            end.setDate(end.getDate()+27);
            return {date_from:formatApiDate(start), date_till:formatApiDate(end)};
        });
    }

    function queryForTab(tab, win){
        return {
            country: tab.country,
            date_from: win.date_from,
            date_till: win.date_till,
            night_from: '1',
            night_till: '21',
            adult: '2',
            child: '0',
            page: '1',
            items_per_page: '60'
            // transport_type=2 currently returns empty for some valid bus tours, so we filter by transport_type_id/label after the API response.
        };
    }

    async function fetchTabOffers(tab){
        if(tabCache.has(tab.id)) return tabCache.get(tab.id);
        var offers = [];
        var seen = new Set();
        var lastError = '';
        var windows = buildWindows();
        for(var i=0;i<windows.length && offers.length<FETCH_TARGET;i++){
            try{
                var data = await api('module-excursion/search', queryForTab(tab, windows[i]));
                collectUnique(offers, seen, Array.isArray(data && data.offers) ? data.offers : []);
            }catch(error){
                lastError = error && error.message ? error.message : String(error || '');
            }
        }
        var result = {offers: sortOffers(offers), error: offers.length ? '' : lastError};
        tabCache.set(tab.id, result);
        return result;
    }

    function buildDetailUrl(offer){
        var base = detailBaseUrl || catalogUrl || window.location.href;
        var url = new URL(base, window.location.origin);
        var countries = Array.isArray(offer.country_names) ? offer.country_names.join(', ') : String(offer.country || '');
        var cities = Array.isArray(offer.city_names) ? offer.city_names.join(', ') : '';
        var image = imageFromOffer(offer);
        url.searchParams.set('excursion_detail','1');
        url.searchParams.set('exc_key',String(offer.key || ''));
        url.searchParams.set('exc_name',String(offer.name || 'Автобусний тур'));
        url.searchParams.set('exc_country',countries);
        url.searchParams.set('exc_cities',cities);
        url.searchParams.set('exc_from',String(offer.from_city || ''));
        url.searchParams.set('exc_date',String(offer.date_from || ''));
        url.searchParams.set('exc_nights',String(offer.duration || ''));
        url.searchParams.set('exc_price',String(priceUAH(offer) || ''));
        url.searchParams.set('exc_image',image);
        return url.toString();
    }

    function renderLoading(){
        panelEl.innerHTML = '<div class="abtw-skeleton" aria-label="Завантаження"><div class="abtw-skeleton-card"></div><div class="abtw-skeleton-card"></div><div class="abtw-skeleton-card"></div><div class="abtw-skeleton-card"></div></div>';
    }

    function renderCards(offers, error){
        if(error && (!offers || !offers.length)){
            panelEl.innerHTML = '<div class="abtw-empty abtw-error">'+esc(error)+'</div>';
            return;
        }
        if(!offers || !offers.length){
            panelEl.innerHTML = '<div class="abtw-empty">Зараз немає доступних автобусних турів у цьому напрямку. Спробуйте іншу вкладку.</div>';
            return;
        }
        panelEl.innerHTML = '<div class="abtw-grid">' + offers.slice(0,CARDS_PER_TAB).map(function(offer){
            var name = String(offer.name || 'Автобусний тур');
            var countries = Array.isArray(offer.country_names) ? offer.country_names.join(', ') : '';
            var cities = Array.isArray(offer.city_names) ? offer.city_names.slice(0,4).join(', ') : '';
            var route = cities || countries || 'Маршрут уточнюється';
            var date = formatHumanDate(offer.date_from || '');
            var duration = nightsLabel(offer.duration || 0);
            var fromCity = String(offer.from_city || '').trim();
            var meal = String(offer.meal_type_full || offer.meal_type || '').trim();
            var nightMoves = parseInt(offer.night_moves,10) || 0;
            var image = imageFromOffer(offer);
            var price = priceUAH(offer);
            var badges = '<span class="abtw-badge abtw-badge--red">Автобус</span>' + (nightMoves > 0 ? '<span class="abtw-badge">'+esc(nightMoves+' нічні переїзди')+'</span>' : '');
            var media = '<div class="abtw-media'+(image ? '' : ' is-empty')+'">' + (image ? '<img src="'+escAttr(image)+'" alt="'+escAttr(name)+'" loading="lazy">' : '') + '<div class="abtw-badges">'+badges+'</div></div>';
            var meta = [];
            if(date && date !== '—') meta.push('<span><strong>Від</strong> '+esc(date)+'</span>');
            if(duration) meta.push('<span><strong>Тривалість</strong> '+esc(duration)+'</span>');
            if(fromCity && fromCity !== '.') meta.push('<span><strong>Виїзд</strong> '+esc(fromCity)+'</span>');
            if(meal) meta.push('<span><strong>Харчування</strong> '+esc(meal)+'</span>');
            return '<article class="abtw-card">' + media + '<div class="abtw-body">' +
                '<p class="abtw-route">'+esc(countries || route)+'</p>' +
                '<h3 class="abtw-card-title">'+esc(name)+'</h3>' +
                '<div class="abtw-meta">'+meta.join('')+'</div>' +
                '<div class="abtw-price">Ціна за 2 дорослих за весь тур<strong>'+esc(price ? formatMoneyUAH(price) : 'Ціну уточнюємо')+'</strong></div>' +
                '<a class="abtw-action" href="'+escAttr(buildDetailUrl(offer))+'">Переглянути деталі</a>' +
                '</div></article>';
        }).join('') + '</div>';
    }

    function setActive(id){
        tabsEl.querySelectorAll('.abtw-tab').forEach(function(button){
            button.classList.toggle('is-active', button.getAttribute('data-tab') === id);
        });
    }

    async function activateTab(tab){
        setActive(tab.id);
        renderLoading();
        try{
            var result = await fetchTabOffers(tab);
            renderCards(result.offers, result.error);
        }catch(error){
            renderCards([], error && error.message ? error.message : String(error || 'Помилка завантаження'));
        }
    }

    function init(){
        tabsEl.innerHTML = TABS.map(function(tab, index){
            return '<button type="button" class="abtw-tab'+(index===0?' is-active':'')+'" data-tab="'+escAttr(tab.id)+'">'+esc(tab.name)+'</button>';
        }).join('');
        tabsEl.addEventListener('click', function(event){
            var button = event.target && event.target.closest ? event.target.closest('.abtw-tab') : null;
            if(!button) return;
            var id = button.getAttribute('data-tab');
            var tab = TABS.find(function(item){ return item.id === id; });
            if(tab) activateTab(tab);
        });
        if(TABS[0]) activateTab(TABS[0]);
    }

    init();
})();
</script>
</section>
