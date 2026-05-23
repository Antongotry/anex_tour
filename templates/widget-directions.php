<?php
/**
 * Anex Tour — Widget: Popular Directions [anex_directions]
 * Standalone "Популярні напрямки" country card grid.
 */
defined('ABSPATH') || exit;

$ajax_url = admin_url('admin-ajax.php');
$nonce    = wp_create_nonce('ittour_lab_public');

// Token check
if (function_exists('ittour_lab_get_token') && ittour_lab_get_token() === '') {
    echo '<div style="padding:18px;border:2px dashed #e53535;border-radius:12px;background:#fff5f5;font-family:Montserrat,sans-serif;color:#e53535">';
    echo '<strong>⚠ Anex Tour: API Token не налаштовано.</strong> ';
    if (current_user_can('manage_options')) {
        echo '<a href="' . esc_url(admin_url('admin.php?page=anex-tour')) . '">Перейти в налаштування →</a>';
    }
    echo '</div>';
    return;
}

$featured_country_fallbacks = [
    '318' => 'Туреччина', '338' => 'Єгипет', '344' => 'Болгарія',
    '343' => 'Греція', '16' => 'ОАЕ', '372' => 'Чорногорія',
];
$featured_countries = [];
foreach ($featured_country_fallbacks as $id => $name) {
    $featured_countries[] = ['id' => $id, 'name' => $name];
}

$catalog_url = function_exists( 'anex_get_catalog_page_permalink' )
    ? anex_get_catalog_page_permalink( [] )
    : home_url( '/' );
?>
<div class="anex-widget anex-directions-widget">
<style>
@import url('https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&display=swap');
.anex-directions-widget{font-family:'Montserrat',system-ui,sans-serif}
.anex-directions-widget *{box-sizing:border-box}
:root{--accent:#1a5dc8;--accent-strong:#1348a8;--text:#111827;--muted:#6b7280;--line:#e5e7eb;--card:#fff;--shadow:0 1px 4px rgba(0,0,0,.07)}
@keyframes shimmer{0%{background-position:100% 50%}to{background-position:0 50%}}
.adw-section{display:grid;gap:22px;padding:30px;border:1px solid rgba(220,228,242,.75);border-radius:24px;background:rgba(255,255,255,.88);box-shadow:var(--shadow)}
.adw-head h2{margin:0 0 8px;font-size:clamp(24px,3vw,40px);line-height:.98;letter-spacing:0}
.adw-head p{max-width:720px;margin:0;color:var(--muted);font-size:16px}
.adw-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:18px}
@media(max-width:900px){.adw-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:580px){.adw-grid{grid-template-columns:1fr}.adw-section{padding:18px}}
.direction-card{display:grid;overflow:hidden;border:1px solid rgba(220,228,242,.8);border-radius:18px;background:var(--card);transition:transform .22s,border-color .22s,box-shadow .22s}
.direction-card:hover{transform:translateY(-3px);border-color:rgba(26,93,200,.18);box-shadow:0 22px 38px rgba(17,38,77,.08)}
.direction-media{position:relative;overflow:hidden;aspect-ratio:16/10;background:linear-gradient(135deg,rgba(26,93,200,.14),rgba(26,93,200,.03))}
.direction-media img{width:100%;height:100%;object-fit:cover;transition:transform .45s}
.direction-card:hover .direction-media img{transform:scale(1.04)}
.direction-badge{position:absolute;left:14px;top:14px;display:inline-flex;align-items:center;gap:8px;min-height:34px;padding:8px 12px;border-radius:999px;background:rgba(255,255,255,.94);color:var(--accent);font-size:12px;font-weight:800;letter-spacing:.02em}
.direction-body{display:grid;gap:14px;padding:18px 18px 20px}
.direction-top{display:flex;align-items:flex-start;justify-content:space-between;gap:12px}
.direction-top h3{margin:0;font-size:28px;line-height:1;letter-spacing:0}
.direction-price{flex:0 0 auto;padding:8px 12px;border-radius:12px;background:rgba(26,93,200,.06);color:var(--accent);font-size:13px;font-weight:800;white-space:nowrap}
.direction-copy{margin:0;color:var(--muted);font-size:14px}
.direction-tags{display:flex;flex-wrap:wrap;gap:8px}
.direction-tag{display:inline-flex;align-items:center;min-height:34px;padding:8px 12px;border-radius:999px;background:rgba(26,93,200,.06);color:var(--accent);font-size:13px;font-weight:700}
.direction-footer{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-top:4px}
.direction-note{color:var(--muted);font-size:13px;font-weight:700}
.direction-action{display:inline-flex;align-items:center;justify-content:center;min-height:42px;padding:10px 16px;border:1px solid rgba(26,93,200,.12);border-radius:12px;background:rgba(26,93,200,.04);color:var(--accent);font-size:14px;font-weight:800;cursor:pointer;text-decoration:none;transition:background .22s,transform .22s}
.direction-action:hover{transform:translateY(-1px);background:rgba(26,93,200,.1)}
.direction-skeleton{min-height:390px;border-radius:18px;background:linear-gradient(90deg,#eef2f9 25%,#f8fbff 37%,#eef2f9 63%);background-size:400% 100%;animation:shimmer 1.4s ease infinite}
</style>

<section class="adw-section" aria-labelledby="anex-dir-title">
    <div class="adw-head">
        <h2 id="anex-dir-title">Популярні напрямки</h2>
        <p>Країни та курорти, які зараз найчастіше трапляються в актуальних пропозиціях. Блок оновлюється автоматично.</p>
    </div>
    <div class="adw-grid" id="anex-dir-grid">
        <div class="direction-skeleton"></div>
        <div class="direction-skeleton"></div>
        <div class="direction-skeleton"></div>
        <div class="direction-skeleton"></div>
        <div class="direction-skeleton"></div>
        <div class="direction-skeleton"></div>
    </div>
</section>
<script>
(function(){
    var ajaxUrl  = <?php echo wp_json_encode($ajax_url); ?>;
    var nonce    = <?php echo wp_json_encode($nonce); ?>;
    var catalogUrl = <?php echo wp_json_encode($catalog_url); ?>;
    var IMG_BASE = 'https://www.ittour.com.ua/';
    var FEATURED = <?php echo wp_json_encode(array_values($featured_countries), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>.map(function(c){ return {...c, id:String(c.id)}; });

    var apiCache   = new Map();
    var apiPending = new Map();

    function esc(v){ var d=document.createElement('div'); d.textContent=v==null?'':String(v); return d.innerHTML; }
    function escAttr(v){ return String(v==null?'':v).replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;'); }
    function formatMoneyUAH(v){ if(v==null||isNaN(Number(v))) return '—'; return new Intl.NumberFormat('uk-UA').format(Math.round(Number(v)))+' грн'; }
    function fixMediaUrl(v){ if(!v||typeof v!=='string') return ''; var u=v.trim(); if(u.startsWith('//')) u='https:'+u; if(u.startsWith('http://')) u='https://'+u.slice(7); if(!/^https?:\/\//i.test(u)) u=IMG_BASE.replace(/\/$/,'')+'/'+u.replace(/^\//,''); return u; }

    function apiError(data){
        return data && typeof data === 'object' && !Array.isArray(data) && data.error
            ? (data.error_desc || data.error) : '';
    }

    async function api(path, query){
        var key = path+'::'+JSON.stringify(query||{});
        var hit = apiCache.get(key);
        if(hit&&Date.now()<hit.expires) return hit.data;
        if(apiPending.has(key)) return apiPending.get(key);
        var body = new URLSearchParams();
        body.set('action','ittour_lab_public'); body.set('nonce',nonce);
        body.set('path',path); body.set('lang','uk');
        body.set('query',JSON.stringify(query||{}));
        var req = fetch(ajaxUrl,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/x-www-form-urlencoded'},body})
            .then(function(r){ return r.json(); })
            .then(function(p){
                if(!p.success) throw new Error((p.data&&p.data.message)||'Помилка API');
                var inner = p.data && p.data.data;
                if(apiError(inner)) throw new Error(apiError(inner));
                var ttl = path.startsWith('module/search-list')?7200000:3600000;
                apiCache.set(key,{expires:Date.now()+ttl,data:inner});
                apiPending.delete(key);
                return inner;
            }).catch(function(e){ apiPending.delete(key); throw e; });
        apiPending.set(key, req); return req;
    }

    function formatApiDate(d){ var dd=String(d.getDate()).padStart(2,'0'); var mm=String(d.getMonth()+1).padStart(2,'0'); var yy=String(d.getFullYear()).slice(-2); return dd+'.'+mm+'.'+yy; }

    function minPrice(cards){ var vals=(cards||[]).map(function(c){ return Number(c.priceUAH||0); }).filter(function(p){ return p>0; }); return vals.length?Math.min.apply(null,vals):null; }

    function summarizeRegions(cards){
        var map=new Map();
        (cards||[]).forEach(function(c){ var r=(c.region||'').trim(); if(!r) return; var cur=map.get(r)||{count:0}; cur.count++; map.set(r,cur); });
        return [...map.entries()].sort(function(a,b){ return b[1].count-a[1].count; }).slice(0,4).map(function(e){ return e[0]; });
    }

    function pickImage(offer){ var cands=[].concat((offer&&offer.hotel_images)||[]).concat((offer&&offer.images)||[]); var main=cands.find(function(i){ return String(i.is_main)==='1'; }); var img=main||cands[0]; return img?fixMediaUrl(img.full||img.web||img.thumb):''; }

    function cardFromOffer(offer, win){ return { key:offer.key||'', hotelId:String(offer.hotel_id||''), priceUAH:offer.prices&&offer.prices['2']!=null?offer.prices['2']:offer.price||null, region:offer.region||'', image:pickImage(offer) }; }

    function dirSearchQuery(country, win){
        return {type:'1',kind:'1',country:String(country.id),adult_amount:'2',child_amount:'0',hotel_rating:'3:78',night_from:'5',night_till:'10',date_from:win.date_from,date_till:win.date_till,items_per_page:'12',hotel_info:'0',currency:'2'};
    }

    async function loadCountryCards(country){
        var base=new Date(); base.setHours(12,0,0,0);
        var wins=[21,35].map(function(off){ var s=new Date(base); s.setDate(s.getDate()+off); var e=new Date(s); e.setDate(e.getDate()+7); return {date_from:formatApiDate(s),date_till:formatApiDate(e)}; });
        var lastError=null;
        try{
            var batch=await Promise.all(wins.map(function(win){ return api('module/search-list', dirSearchQuery(country, win)); }));
            for(var i=0;i<batch.length;i++){
                var offers=batch[i].offers||[];
                if(offers.length>0) return {cards: offers.map(function(o){ return cardFromOffer(o,wins[i]); }), error:null};
            }
        } catch(e){ lastError=e.message||String(e); }
        return {cards:[], error:lastError};
    }

    function directionCardHtml(country, cards, idx){
        var imgUrl = '';
        if(cards.length&&cards[0].image) imgUrl = cards[0].image;
        var imageHtml = imgUrl ? '<img src="'+escAttr(imgUrl)+'" alt="'+escAttr(country.name)+'" loading="'+(idx<3?'eager':'lazy')+'" referrerpolicy="no-referrer-when-downgrade">' : '';
        var price = minPrice(cards);
        var priceHtml = price ? '<span class="direction-price">від '+esc(formatMoneyUAH(price))+'</span>' : '';
        var regions = summarizeRegions(cards);
        var tags = regions.length ? regions.map(function(r){ return '<span class="direction-tag">'+esc(r)+'</span>'; }).join('') : '<span class="direction-tag">Курорти уточнюються</span>';
        var note = cards.length ? 'Добірка оновлюється за актуальними пропозиціями' : 'Відкрийте країну, щоб підтягнути доступні курорти та ціни';
        var href = catalogUrl + (catalogUrl.indexOf('?') >= 0 ? '&' : '?') + 'country_id=' + encodeURIComponent(country.id) + '#offers-section';
        return '<article class="direction-card">'+
            '<div class="direction-media">'+imageHtml+'<span class="direction-badge">Актуально зараз</span></div>'+
            '<div class="direction-body">'+
                '<div class="direction-top"><h3>'+esc(country.name)+'</h3>'+priceHtml+'</div>'+
                '<p class="direction-copy">Курорти, які зараз найчастіше трапляються в актуальних пропозиціях.</p>'+
                '<div class="direction-tags">'+tags+'</div>'+
                '<div class="direction-footer">'+
                    '<span class="direction-note">'+esc(note)+'</span>'+
                    '<a class="direction-action" href="'+escAttr(href)+'">Показати готелі</a>'+
                '</div>'+
            '</div></article>';
    }

    async function initDirections(){
        var grid = document.getElementById('anex-dir-grid');
        if(!grid) return;

        var results = [];
        for(var i=0;i<FEATURED.length;i+=2){
            var chunk=FEATURED.slice(i,i+2);
            var chunkResults=await Promise.all(chunk.map(function(country, j){
                var idx=i+j;
                return loadCountryCards(country).then(function(res){
                    return {country, cards: res.cards, error: res.error, idx};
                }).catch(function(e){ return {country, cards:[], error: e.message||String(e), idx}; });
            }));
            results=results.concat(chunkResults);
        }
        // Show error if first country has error (likely token/API issue)
        var firstError = results[0] && results[0].error;
        if(firstError && results.every(function(r){ return r.error; })){
            grid.outerHTML = '<p style="color:#e53535;font-weight:700;padding:20px">⚠ Помилка API: '+esc(firstError)+'</p>';
            return;
        }
        grid.innerHTML = results.map(function(r){ return directionCardHtml(r.country, r.cards, r.idx); }).join('');
    }

    initDirections().catch(console.error);
})();
</script>
</div>
