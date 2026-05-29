<?php
/**
 * Anex Tour — Widget: Hot Tours [anex_hot_tours]
 * Standalone "Гарячі тури" country showcase carousel.
 */
defined('ABSPATH') || exit;

$ajax_url = admin_url('admin-ajax.php');
$nonce    = wp_create_nonce('ittour_lab_public');

// Token check — show helpful message if not configured
if (function_exists('ittour_lab_get_token') && ittour_lab_get_token() === '') {
    $is_admin = current_user_can('manage_options');
    echo '<div style="padding:24px;border:2px dashed #e53535;border-radius:12px;background:#fff5f5;font-family:Montserrat,sans-serif;color:#e53535">';
    echo '<strong>⚠ Anex Tour: API Token не налаштовано.</strong><br>';
    if ($is_admin) {
        echo 'Перейдіть у <a href="' . esc_url(admin_url('admin.php?page=anex-tour')) . '"><strong>Anex Tour → Налаштування</strong></a> і вставте токен IT-Tour.';
    } else {
        echo 'Виджет тимчасово недоступний.';
    }
    echo '</div>';
    return;
}

// Featured countries for the showcase tabs.
$featured_country_fallbacks = [
    '318' => 'Туреччина', '338' => 'Єгипет', '39' => 'Болгарія',
    '372' => 'Греція', '16' => 'ОАЕ', '434' => 'Чорногорія',
];
$featured_countries = [];
if (empty($featured_countries)) {
    foreach ($featured_country_fallbacks as $id => $name) {
        $featured_countries[] = ['id' => $id, 'name' => $name];
    }
}

$catalog_url = function_exists('anex_get_catalog_page_permalink')
    ? anex_get_catalog_page_permalink([])
    : home_url('/');

// Куди вести клік по картці готелю (окрема Elementor-сторінка або каталог)
$hotel_detail_nav_url = function_exists('anex_get_hotel_detail_nav_base_url')
    ? anex_get_hotel_detail_nav_base_url()
    : $catalog_url;
?>
<main class="page-shell anex-embed-root anex-hot-tours-widget">
<style>
@import url('https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&display=swap');
.anex-hot-tours-widget{font-family:'Montserrat',system-ui,sans-serif;color:var(--text);width:100%;max-width:none!important;margin:0!important;padding:0!important;background:transparent!important}
.anex-hot-tours-widget *{box-sizing:border-box}
:root{--accent:#1a5dc8;--accent-strong:#1348a8;--accent-soft:rgba(26,93,200,.08);--text:#111827;--muted:#6b7280;--line:#e5e7eb;--card:#fff;--star:#f59e0b;--shadow:0 1px 4px rgba(0,0,0,.07);--radius-md:12px}
@keyframes shimmer{0%{background-position:100% 50%}to{background-position:0 50%}}
.anex-hot-tours-widget .widget-frame{position:relative;overflow:visible;width:100%;margin:0!important;padding:0!important;border:0!important;border-radius:0!important;background:transparent!important;box-shadow:none!important;backdrop-filter:none!important}
.anex-hot-tours-widget .widget-frame::before{display:none!important}
.widget-inner{padding:34px 34px 36px;position:relative}
.toolbar{display:flex;align-items:flex-start;justify-content:space-between;gap:18px;margin-bottom:26px}
.toolbar-copy{display:grid;gap:8px}
.toolbar-copy h2{margin:0;font-size:clamp(28px,4vw,42px);line-height:1;letter-spacing:0;color:#003087;font-weight:800}
.toolbar-copy p{margin:0;color:var(--muted)}
.country-showcase{display:flex;flex-direction:column}
.showcase-tabs{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:24px}
.showcase-tab{display:inline-flex;align-items:center;gap:8px;min-height:44px;padding:10px 18px;border-radius:999px;border:1px solid var(--line);background:#fff;font:inherit;font-size:14px;font-weight:700;color:var(--text);cursor:pointer;transition:background .18s,color .18s,border-color .18s,box-shadow .18s}
.showcase-tab:hover{border-color:var(--accent);color:var(--accent)}
.showcase-tab.is-active{background:var(--accent);border-color:var(--accent);color:#fff;box-shadow:0 8px 20px rgba(26,93,200,.2)}
.showcase-tab .tab-price{font-size:12px;font-weight:700;opacity:.75}
.showcase-tab.is-active .tab-price{opacity:.88;color:rgba(255,255,255,.9)}
#offers-section #showcase-tabs-row .showcase-tab,
#offers-section #showcase-tabs-row .showcase-tab:focus,
#offers-section #showcase-tabs-row .showcase-tab:visited{display:inline-flex;align-items:center;gap:8px;min-height:44px;padding:10px 18px;border-radius:999px;border:1px solid var(--line)!important;background:#fff!important;background-image:none!important;box-shadow:none;color:var(--text)!important;font:inherit;font-size:14px;font-weight:700;text-decoration:none!important;outline:0;cursor:pointer;transition:background .18s,color .18s,border-color .18s,box-shadow .18s}
#offers-section #showcase-tabs-row .showcase-tab:hover{border-color:var(--accent)!important;background:#fff!important;background-image:none!important;color:var(--accent)!important;box-shadow:none!important;transform:none!important}
#offers-section #showcase-tabs-row .showcase-tab.is-active,
#offers-section #showcase-tabs-row .showcase-tab.is-active:hover,
#offers-section #showcase-tabs-row .showcase-tab.is-active:focus{border-color:var(--accent)!important;background:var(--accent)!important;background-image:none!important;color:#fff!important;box-shadow:0 8px 20px rgba(26,93,200,.2)!important;transform:none!important}
#offers-section #showcase-tabs-row .showcase-tab .tab-price{font-size:12px;font-weight:700;opacity:.75;color:inherit!important}
#offers-section #showcase-tabs-row .showcase-tab.is-active .tab-price{opacity:.88;color:rgba(255,255,255,.9)!important}
.showcase-panel{display:none}
.showcase-panel.is-active{display:block}
.showcase-cards{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:18px}
@media(max-width:1180px){.showcase-cards{grid-template-columns:repeat(3,minmax(0,1fr))}}
@media(max-width:860px){.showcase-cards{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:560px){.showcase-cards{grid-template-columns:1fr}}
.showcase-skeleton{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:18px}
@media(max-width:860px){.showcase-skeleton{grid-template-columns:repeat(2,minmax(0,1fr))}}
.skeleton-card{min-height:416px;border-radius:22px;background:linear-gradient(90deg,#eef2f9 25%,#f8fbff 37%,#eef2f9 63%);background-size:400% 100%;animation:shimmer 1.4s ease infinite}
.hotel-card{display:flex;flex-direction:column;overflow:hidden;border:1px solid var(--line);border-radius:16px;background:var(--card);box-shadow:0 14px 32px rgba(33,51,98,.06);transition:transform .28s,border-color .28s,box-shadow .28s}
.hotel-card:hover{transform:translateY(-3px);border-color:rgba(26,93,200,.18);box-shadow:0 18px 36px rgba(21,39,78,.08)}
.hotel-media{position:relative;aspect-ratio:253/160;background:linear-gradient(135deg,rgba(26,93,200,.12),rgba(26,93,200,.03));overflow:hidden}
.hotel-media img{width:100%;height:100%;object-fit:cover;display:block;transition:transform .5s,filter .35s}
.hotel-card:hover .hotel-media img{transform:scale(1.045);filter:saturate(1.04)}
.hotel-media.no-image::after{content:"Немає фото";position:absolute;inset:0;display:flex;align-items:center;justify-content:center;color:var(--muted);font-weight:700}
.review-chip{position:absolute;top:14px;right:14px;display:inline-flex;align-items:stretch;gap:10px;max-width:calc(100% - 28px);padding:8px 8px 8px 12px;border-radius:12px;background:rgba(255,255,255,.96);box-shadow:0 12px 24px rgba(12,29,62,.14);backdrop-filter:blur(10px)}
.review-copy strong{display:block;font-size:12px;line-height:1.1}
.review-copy span{display:block;margin-top:2px;color:var(--muted);font-size:11px;line-height:1.1}
.review-score{display:inline-flex;align-items:center;justify-content:center;min-width:62px;padding:0 10px;border-radius:12px;background:linear-gradient(135deg,#2c6bff 0%,var(--accent) 100%);color:#fff;font-size:18px;font-weight:800}
.hotel-body{display:flex;flex:1;flex-direction:column;padding:16px 18px 18px}
.stars{margin-bottom:8px;color:var(--star);font-size:18px;letter-spacing:.12em}
.hotel-title{margin:0 0 8px;font-size:17px;line-height:1.18;display:-webkit-box;-webkit-box-orient:vertical;-webkit-line-clamp:2;overflow:hidden;min-height:2.4em}
.hotel-location{margin:0 0 12px;color:var(--muted);font-size:14px}
.hotel-meta{display:grid;gap:6px;margin-bottom:16px;color:var(--muted);font-size:13px}
.hotel-price{margin-bottom:16px;font-size:14px;color:var(--muted)}
.hotel-price strong{display:block;margin-top:3px;color:var(--text);font-size:22px}
.card-action,.card-action:visited{margin-top:auto;display:inline-flex;align-items:center;justify-content:center;width:100%;min-height:54px;padding:14px 18px;border:0;border-radius:12px;background:linear-gradient(135deg,var(--accent) 0%,#1347ad 100%);color:#fff!important;font-weight:800;cursor:pointer;text-decoration:none!important;box-shadow:none;transition:transform .22s,background .22s,opacity .22s}
.card-action:hover,.card-action:focus{transform:translateY(-1px);background:linear-gradient(135deg,var(--accent-strong) 0%,#1a5dc8 100%);color:#fff!important;text-decoration:none!important;box-shadow:none}
.card-action:active{transform:translateY(0);color:#fff!important}
.empty-state{padding:40px;text-align:center;color:var(--muted)}
@media(max-width:768px){.widget-inner{padding:26px 22px 28px}.toolbar{flex-direction:column}}
@media(max-width:560px){.widget-frame{border-radius:20px}.widget-inner{padding:22px 16px 22px}}
</style>

<section class="widget-frame" id="offers-section">
    <div class="widget-inner">
        <div class="toolbar">
            <div class="toolbar-copy">
                <h2>Гарячі тури</h2>
            </div>
        </div>
        <div class="country-showcase" id="country-showcase">
            <div class="showcase-skeleton" id="showcase-skeleton">
                <div class="skeleton-card"></div>
                <div class="skeleton-card"></div>
                <div class="skeleton-card"></div>
                <div class="skeleton-card"></div>
            </div>
        </div>
    </div>
</section>
<script>
(function(){
    var ajaxUrl  = <?php echo wp_json_encode($ajax_url); ?>;
    var nonce    = <?php echo wp_json_encode($nonce); ?>;
    var catalogUrl = <?php echo wp_json_encode($catalog_url); ?>;
    var hotelDetailNavUrl = <?php echo wp_json_encode($hotel_detail_nav_url); ?>;
    var IMG_BASE = 'https://www.ittour.com.ua/';
    var FEATURED_COUNTRIES = <?php echo wp_json_encode(array_values($featured_countries), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>.map(function(c){ return {...c, id:String(c.id)}; });

    var apiCache   = new Map();
    var apiPending = new Map();

    function esc(v){ var d=document.createElement('div'); d.textContent=v==null?'':String(v); return d.innerHTML; }
    function escAttr(v){ return String(v==null?'':v).replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;'); }
    function formatMoneyUAH(v){ if(v==null||isNaN(Number(v))) return '—'; return new Intl.NumberFormat('uk-UA').format(Math.round(Number(v)))+' грн'; }
    function starsMarkup(r){ var n=Math.min(5,Math.max(0,parseInt(r,10)||0)); return n?'★'.repeat(n):'★★★'; }
    function reviewLabel(r){ var v=Number(r); if(!v) return 'Є відгуки'; if(v>=9) return 'Чудово'; if(v>=8.5) return 'Блискуче'; if(v>=8) return 'Дуже добре'; if(v>=7) return 'Добре'; if(v>=6) return 'Непогано'; return 'Є відгуки'; }
    function fixMediaUrl(v){ if(!v||typeof v!=='string') return ''; var u=v.trim(); if(u.startsWith('//')) u='https:'+u; if(u.startsWith('http://')) u='https://'+u.slice(7); if(!/^https?:\/\//i.test(u)) u=IMG_BASE.replace(/\/$/,'')+'/'+u.replace(/^\//,''); return u; }
    function pickImage(offer){ var cands=[].concat((offer&&offer.hotel_images)||[]).concat((offer&&offer.images)||[]); var main=cands.find(function(i){ return String(i.is_main)==='1'||i.is_main===1; }); var img=main||cands[0]; return img?fixMediaUrl(img.full||img.web||img.thumb):''; }
    function formatApiDate(d){ var dd=String(d.getDate()).padStart(2,'0'); var mm=String(d.getMonth()+1).padStart(2,'0'); var yy=String(d.getFullYear()).slice(-2); return dd+'.'+mm+'.'+yy; }
    function formatHumanDate(v){ if(!v) return '—'; if(/^\d{2}\.\d{2}\.\d{2}$/.test(v)){ var p=v.split('.'); return p[0]+'.'+p[1]+'.20'+p[2]; } return v; }

    function apiError(data){
        return data && typeof data === 'object' && !Array.isArray(data) && data.error
            ? (data.error_desc || data.error) : '';
    }

    async function api(path, query){
        var key = path+'::'+JSON.stringify(query||{});
        var hit = apiCache.get(key);
        if(hit && Date.now()<hit.expires) return hit.data;
        if(apiPending.has(key)) return apiPending.get(key);
        var body = new URLSearchParams();
        body.set('action','ittour_lab_public'); body.set('nonce',nonce);
        body.set('path',path); body.set('lang','uk');
        body.set('query',JSON.stringify(query||{}));
        var req = fetch(ajaxUrl,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/x-www-form-urlencoded'},body})
            .then(function(r){ return r.json(); })
            .then(function(p){
                if(!p.success) throw new Error((p.data&&p.data.message)||'Помилка проксі');
                var inner = p.data && p.data.data;
                if(apiError(inner)) throw new Error(apiError(inner));
                var ttl = path.startsWith('module/search-list')?7200000:3600000;
                apiCache.set(key,{expires:Date.now()+ttl, data:inner});
                apiPending.delete(key);
                return inner;
            }).catch(function(e){ apiPending.delete(key); throw e; });
        apiPending.set(key, req);
        return req;
    }

    function dedupeHotels(offers){ var seen=new Set(),out=[]; (offers||[]).forEach(function(o){ var id=String(o.hotel_id||o.hotel||Math.random()); if(!seen.has(id)){ seen.add(id); out.push(o); } }); return out; }
    function sortHotels(offers){ return [...offers].sort(function(a,b){ var rc=Number(b.hotel_review_count||0)-Number(a.hotel_review_count||0); if(rc) return rc; var rr=Number(b.hotel_review_rate||0)-Number(a.hotel_review_rate||0); if(rr) return rr; return Number((a.prices&&a.prices['2'])||a.price||0)-Number((b.prices&&b.prices['2'])||b.price||0); }); }

    function offerHasTransport(offer){
        if(!offer) return false;
        // type 2 = hotel-only (no transport)
        var ot = Number(offer.type);
        if(ot===2) return false;
        // explicit transport_type string
        var t=String(offer.transport_type||'').toLowerCase();
        if(t==='flight'||t==='bus') return true;
        // Ukrainian/alternate transport labels
        if(t==='авіа'||t==='авіаперліт'||t==='авіапереліт'||t.indexOf('avia')!==-1||t.indexOf('flight')!==-1) return true;
        // numeric transport_type_id: 1 = flight, 2 = bus
        var ttid = Number(offer.transport_type_id||offer.transport_id||0);
        if(ttid===1||ttid===2) return true;
        // flights arrays with actual content (not just empty arrays or null entries)
        var fl=offer.flights;
        if(fl){
            var hasFrom = Array.isArray(fl.from) && fl.from.some(function(f){ return f&&typeof f==='object'&&Object.keys(f).length>0; });
            var hasTo   = Array.isArray(fl.to)   && fl.to.some(function(f){ return f&&typeof f==='object'&&Object.keys(f).length>0; });
            if(hasFrom||hasTo) return true;
        }
        // type 1 explicitly means package tour in ittour API
        if(ot===1) return true;
        return false;
    }

    function offerMinNights(offer){ return Number(offer.duration||offer.hnight||0); }

    function cardFromOffer(offer, win){
        return {
            key: offer.key||'', hotelId: String(offer.hotel_id||''),
            name: offer.hotel||'Готель', country: offer.country||'', region: offer.region||'',
            rating: offer.hotel_rating||'', reviewRate: offer.hotel_review_rate||null,
            reviewCount: offer.hotel_review_count||null, image: pickImage(offer),
            priceUAH: offer.prices&&offer.prices['2']!=null?offer.prices['2']:offer.price||null,
            dateFrom: offer.date_from||win.date_from, duration: offer.duration||offer.hnight||null,
            mealType: offer.meal_type_full||offer.meal_type||'',
            departureName: offer.from_city_name||offer.from_city||'',
            hasTransport: offerHasTransport(offer)
        };
    }

    function detailUrl(card){
        var url = new URL(hotelDetailNavUrl || catalogUrl);
        url.searchParams.set('tour_key', card.key||'');
        if(card.hotelId) url.searchParams.set('hotel_id', card.hotelId);
        return url.toString();
    }

    var cardsCache    = new Map();
    var errorCache    = new Map();
    var fetchPromises = new Map();
    var CARDS_PER  = 4;
    var MIN_NIGHTS = 5;

    function addDays(d, n){ var r=new Date(d); r.setDate(r.getDate()+n); return r; }

    // Builds rolling date windows starting from today+14 days, each 14 days wide, 4 windows
    function buildSearchWindows(){
        var base = new Date(); base.setHours(12,0,0,0);
        var windows = [];
        for(var offset=14; offset<=70; offset+=14){
            var from = addDays(base, offset);
            var till = addDays(from, 13);
            windows.push({ date_from: formatApiDate(from), date_till: formatApiDate(till) });
        }
        return windows;
    }

    function buildSearchQuery(countryId, win, wider){
        return {
            type: '1',           // package tours with transport only
            country: String(countryId),
            date_from: win.date_from,
            date_till: win.date_till,
            night_from: String(MIN_NIGHTS),
            night_till: wider ? '21' : '14',
            adult_amount: '2',
            child_amount: '0',
            hotel_rating: wider ? '1:78:79' : '3:78',
            hotel_image: '1',
            items_per_page: '18',
            page: '1',
        };
    }

    function fetchCards(country){
        if(cardsCache.has(country.id)) return Promise.resolve({cards: cardsCache.get(country.id), error: errorCache.get(country.id)||null});
        if(fetchPromises.has(country.id)) return fetchPromises.get(country.id);
        var p = (async function(){
            var lastError = null;
            try{
                var windows = buildSearchWindows();
                // First pass: strict (3+ stars, 5–14 nights)
                for(var i=0;i<windows.length;i++){
                    var data = await api('module/search-list', buildSearchQuery(country.id, windows[i], false));
                    var offers = dedupeHotels(sortHotels(data.offers||[])).filter(function(offer){
                        return offerHasTransport(offer) && offerMinNights(offer) >= MIN_NIGHTS;
                    });
                    if(offers.length>=CARDS_PER){
                        var cards=offers.slice(0,CARDS_PER).map(function(o){ return cardFromOffer(o,windows[i]); });
                        cardsCache.set(country.id,cards); errorCache.set(country.id,null);
                        return {cards:cards, error:null};
                    }
                }
                // Second pass: wider (any stars, 5–21 nights)
                for(var j=0;j<windows.length;j++){
                    var data2 = await api('module/search-list', buildSearchQuery(country.id, windows[j], true));
                    var offers2 = dedupeHotels(sortHotels(data2.offers||[])).filter(function(offer){
                        return offerHasTransport(offer) && offerMinNights(offer) >= MIN_NIGHTS;
                    });
                    if(offers2.length>0){
                        var cards2=offers2.slice(0,CARDS_PER).map(function(o){ return cardFromOffer(o,windows[j]); });
                        cardsCache.set(country.id,cards2); errorCache.set(country.id,null);
                        return {cards:cards2, error:null};
                    }
                }
            } catch(e){ lastError = e.message || String(e); }
            cardsCache.set(country.id,[]); errorCache.set(country.id, lastError);
            return {cards:[], error:lastError};
        })();
        fetchPromises.set(country.id, p); return p;
    }

    function renderTabCards(panelEl, cards, errorMsg){
        if(errorMsg){ panelEl.innerHTML='<p class="empty-state" style="color:#e53535">⚠ '+esc(errorMsg)+'</p>'; return; }
        if(!cards.length){ panelEl.innerHTML='<p class="empty-state">Немає доступних пропозицій для цієї країни.</p>'; return; }
        panelEl.innerHTML = '<div class="showcase-cards">' + cards.map(function(card){
            var review = card.reviewRate ? '<div class="review-chip"><div class="review-copy"><strong>'+esc(reviewLabel(card.reviewRate))+'</strong><span>'+esc((card.reviewCount||0)+' відгуків')+'</span></div><div class="review-score">'+esc(Number(card.reviewRate).toFixed(1))+'</div></div>' : '';
            var img = card.image ? '<img src="'+escAttr(card.image)+'" alt="'+escAttr(card.name)+'" loading="lazy">' : '';
            var dur = card.duration ? card.duration+' ночей' : '';
            var departure = card.departureName ? '<span>'+esc(card.departureName)+'</span>' : '';
            return '<article class="hotel-card">'+
                '<div class="'+(card.image?'hotel-media':'hotel-media no-image')+'">'+img+review+'</div>'+
                '<div class="hotel-body">'+
                    '<div class="stars">'+esc(starsMarkup(card.rating))+'</div>'+
                    '<h3 class="hotel-title">'+esc(card.name)+'</h3>'+
                    '<p class="hotel-location">'+esc(card.region||card.country)+'</p>'+
                    '<div class="hotel-meta">'+departure+'<span>'+esc('Від '+formatHumanDate(card.dateFrom))+'</span>'+(dur?'<span>'+esc(dur+(card.mealType?' · '+card.mealType:''))+'</span>':'')+'</div>'+
                    '<div class="hotel-price">Ціна за 2 дорослих за весь тур<strong>'+esc(formatMoneyUAH(card.priceUAH))+'</strong></div>'+
                    '<a class="card-action" href="'+escAttr(detailUrl(card))+'" data-key="'+escAttr(card.key)+'">Переглянути деталі</a>'+
                '</div></article>';
        }).join('') + '</div>';
        panelEl.querySelectorAll('.card-action').forEach(function(btn){
            var key = btn.getAttribute('data-key') || '';
            btn.addEventListener('click', function(){
                var card = cards.find(function(c){ return c.key === key; });
                if(card){ try { sessionStorage.setItem('ittour:last-card:'+key, JSON.stringify(card)); } catch(e) {} }
            });
        });
    }

    async function initShowcase(){
        var showcase = document.getElementById('country-showcase');
        if(!showcase) return;
        showcase.innerHTML = '<div class="showcase-tabs" id="showcase-tabs-row"></div><div class="showcase-panels" id="showcase-panels"></div>';
        var tabsRow = showcase.querySelector('#showcase-tabs-row');
        var panelsEl = showcase.querySelector('#showcase-panels');

        function activateTab(id){
            tabsRow.querySelectorAll('.showcase-tab').forEach(function(b){ b.classList.toggle('is-active', b.getAttribute('data-country')===String(id)); });
            panelsEl.querySelectorAll('.showcase-panel').forEach(function(p){ p.classList.toggle('is-active', p.getAttribute('data-country')===String(id)); });
        }
        function updateTabPrice(id, cards){
            var el = document.getElementById('tab-price-'+escAttr(id));
            if(!el) return;
            var prices = cards.filter(function(c){ return c.hasTransport===true; }).map(function(c){ return Number(c.priceUAH||0); }).filter(function(p){ return p>0; });
            el.textContent = prices.length ? 'від '+formatMoneyUAH(Math.min.apply(null,prices)) : '';
        }

        var panels = [];
        FEATURED_COUNTRIES.forEach(function(country, idx){
            var tab = document.createElement('button');
            tab.type='button'; tab.className='showcase-tab'+(idx===0?' is-active':'');
            tab.setAttribute('data-country', country.id);
            tab.innerHTML = esc(country.name)+'<span class="tab-price" id="tab-price-'+escAttr(country.id)+'"></span>';
            tabsRow.appendChild(tab);

            var panel = document.createElement('div');
            panel.className='showcase-panel'+(idx===0?' is-active':'');
            panel.setAttribute('data-country', country.id);
            panel.innerHTML='<div class="showcase-skeleton"><div class="skeleton-card"></div><div class="skeleton-card"></div><div class="skeleton-card"></div><div class="skeleton-card"></div></div>';
            panelsEl.appendChild(panel);
            panels.push(panel);

            tab.addEventListener('click', function(){
                activateTab(country.id);
                if(cardsCache.has(country.id)){
                    if(!panel.querySelector('.showcase-cards')&&!panel.querySelector('.empty-state')) renderTabCards(panel, cardsCache.get(country.id), errorCache.get(country.id)||null);
                    return;
                }
                fetchCards(country).then(function(res){ renderTabCards(panel, res.cards, res.error); updateTabPrice(country.id, res.cards); });
            });
        });

        if(FEATURED_COUNTRIES[0]){
            fetchCards(FEATURED_COUNTRIES[0]).then(function(res){
                updateTabPrice(FEATURED_COUNTRIES[0].id, res.cards);
                if(panels[0] && !panels[0].querySelector('.showcase-cards')) renderTabCards(panels[0], res.cards, res.error);
            });
        }
    }

    initShowcase().catch(console.error);
})();
</script>
</main>
