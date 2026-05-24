<?php
/**
 * Anex Tour — Widget: Search Form [anex_search]
 * Standalone tour search form. On submit → redirects to the catalog page.
 */
defined('ABSPATH') || exit;

$ajax_url   = admin_url('admin-ajax.php');
$nonce      = wp_create_nonce('ittour_lab_public');

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

$catalog_url = function_exists( 'anex_get_catalog_search_page_permalink' )
    ? anex_get_catalog_search_page_permalink( [] )
    : home_url( '/' );
?>
<div class="anex-widget anex-search-widget">
<style>
@import url('https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&display=swap');
.anex-search-widget{font-family:'Montserrat',system-ui,sans-serif;box-sizing:border-box}
.anex-search-widget *{box-sizing:border-box}
:root{--accent:#1a5dc8;--accent-strong:#1348a8;--text:#111827;--muted:#6b7280;--line:#e5e7eb;--radius-md:12px;--star:#f59e0b}
.anex-search-widget .asw-card{padding:28px 28px 24px;border:1px solid rgba(220,228,242,.75);border-radius:20px;background:rgba(255,255,255,.96)}
.anex-search-widget .asw-title{margin:0 0 18px;font-size:16px;font-weight:800;color:var(--accent);letter-spacing:0}
.asw-grid{display:grid;grid-template-columns:repeat(12,minmax(0,1fr));gap:10px 12px;align-items:end}
.ps-field{display:grid;gap:4px;min-width:0}
.ps-field label,.ps-field .ps-label{font-size:11px;font-weight:800;color:#5d6b87;text-transform:uppercase;letter-spacing:.06em;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
    .ps-field input,.ps-field select{min-height:46px;padding:0 12px;border-radius:var(--radius-md);border:1px solid var(--line);background:#fff;font:inherit;font-size:15px;font-weight:600;color:var(--text);width:100%}
    .ps-field input:focus,.ps-field select:focus{outline:2px solid var(--accent);outline-offset:-1px;border-color:var(--accent)}
    .ps-field select[multiple]{min-height:140px;padding:8px 10px}
    .ps-field select[multiple] optgroup{font-style:normal;font-weight:800;color:#003087}
    .ps-field select[multiple] option{padding:6px 8px;color:#50575e}
    .ps-field select[multiple] option:checked{background:#1a5dc8;color:#fff}
    .ps-field .ps-help{margin:2px 0 0;font-size:12px;line-height:1.4;color:#50575e}
.ps-country-wrap{grid-column:span 4;position:relative;min-width:0}
.asw-grid>.ps-field--grow:not(.ps-country-wrap){grid-column:span 4}
.asw-grid>.ps-field--date{grid-column:span 2}
.asw-grid>.ps-field--narrow{grid-column:span 2}
.asw-grid>.ps-submit-wrap{grid-column:span 4}
.ps-country-dd{position:absolute;left:0;right:0;top:calc(100% + 4px);z-index:9200;max-height:260px;overflow-y:auto;margin:0;padding:6px;list-style:none;border-radius:14px;border:1px solid var(--line);background:#fff;box-shadow:0 16px 40px rgba(15,35,72,.14);display:none}
.ps-country-dd.is-open{display:block}
.ps-country-dd button{display:block;width:100%;text-align:left;padding:10px 12px;border:0;border-radius:10px;background:transparent;font:inherit;font-size:14px;font-weight:700;cursor:pointer;color:var(--text)}
.ps-country-dd button:hover{background:rgba(26,93,200,.08)}
.ps-submit{display:flex;align-items:center;justify-content:center;width:100%;min-height:50px;padding:0 22px;border:0;border-radius:14px;background:#f31624;color:#fff;font:inherit;font-size:15px;font-weight:900;cursor:pointer;transition:background .18s,transform .12s}
.ps-submit:hover{background:#d01020;transform:translateY(-1px)}
.ps-submit:active{transform:translateY(0)}
.ps-submit:disabled{opacity:.65;cursor:not-allowed}
@media(max-width:720px){
  .asw-grid{grid-template-columns:repeat(6,minmax(0,1fr))}
  .ps-country-wrap{grid-column:1 / -1}
  .asw-grid>.ps-field--grow:not(.ps-country-wrap){grid-column:1 / -1}
  .asw-grid>.ps-field--date{grid-column:span 3}
  .asw-grid>.ps-field--narrow{grid-column:span 3}
  .asw-grid>.ps-submit-wrap{grid-column:1 / -1}
}
@media(max-width:480px){
  .anex-search-widget .asw-card{padding:18px}
  .asw-grid>.ps-field--date,.asw-grid>.ps-field--narrow{grid-column:1 / -1}
}
</style>

<div class="asw-card">
    <p class="asw-title">Пошук туру в каталозі</p>
    <form class="asw-grid" id="anex-sw-form" autocomplete="off" novalidate>
        <div class="ps-field ps-field--grow ps-country-wrap">
            <span class="ps-label" id="asw-country-lbl">Країна, курорт, готель</span>
            <input type="hidden" id="asw-country-id">
            <input type="text" id="asw-country-q" placeholder="Почніть вводити країну" aria-labelledby="asw-country-lbl" aria-controls="asw-country-dd" autocomplete="off">
            <ul class="ps-country-dd" id="asw-country-dd" role="listbox"></ul>
        </div>
        <div class="ps-field ps-field--grow">
            <label class="ps-label" for="asw-from">Звідки</label>
            <select id="asw-from" multiple size="6" aria-describedby="asw-from-help"><option value="">Завантаження…</option></select>
            <p class="ps-help" id="asw-from-help">Оберіть до 3 міст. Для авіанапрямків міста з України приховано.</p>
        </div>
        <div class="ps-field ps-field--date">
            <label class="ps-label" for="asw-d1">Дата від</label>
            <input id="asw-d1" type="text" inputmode="numeric" placeholder="дд.мм.рр" maxlength="8">
        </div>
        <div class="ps-field ps-field--date">
            <label class="ps-label" for="asw-d2">Дата до</label>
            <input id="asw-d2" type="text" inputmode="numeric" placeholder="дд.мм.рр" maxlength="8">
        </div>
        <div class="ps-field ps-field--narrow">
            <label class="ps-label" for="asw-n1">Ночей від</label>
            <input id="asw-n1" type="text" inputmode="numeric" maxlength="2" value="6" placeholder="6">
        </div>
        <div class="ps-field ps-field--narrow">
            <label class="ps-label" for="asw-n2">Ночей до</label>
            <input id="asw-n2" type="text" inputmode="numeric" maxlength="2" value="8" placeholder="8">
        </div>
        <div class="ps-field ps-field--narrow">
            <label class="ps-label" for="asw-adults">Дорослих</label>
            <select id="asw-adults">
                <option value="1">1</option>
                <option value="2" selected>2</option>
                <option value="3">3</option>
                <option value="4">4</option>
            </select>
        </div>
        <div class="ps-field ps-field--narrow">
            <label class="ps-label" for="asw-children">Дітей</label>
            <select id="asw-children">
                <option value="0" selected>0</option>
                <option value="1">1</option>
                <option value="2">2</option>
                <option value="3">3</option>
            </select>
        </div>
        <div class="ps-submit-wrap">
            <button type="submit" class="ps-submit" id="asw-submit">Шукати тури</button>
        </div>
    </form>
</div>
<script>
(function(){
    var ajaxUrl  = <?php echo wp_json_encode($ajax_url); ?>;
    var nonce    = <?php echo wp_json_encode($nonce); ?>;
    var catalogUrl = <?php echo wp_json_encode($catalog_url); ?>;
    var IMG_BASE = 'https://www.ittour.com.ua/';

    var apiCache   = new Map();
    var apiPending = new Map();
    var departureCache = new Map();
    var allCountries   = [];
    var MAX_FROM_SELECT = 3;
    var departureCountryGroups = {
        'Україна': ['Київ', 'Львів', 'Одеса', 'Запоріжжя', 'Харків', 'Дніпро', 'Біла Церква', 'Вінниця', 'Долина', 'Дубно', 'Жашків', 'Житомир', 'Івано-Франківськ', 'Ізмаїл', "Кам'янець-Подільський", 'Калуш', 'Коломия', 'Кривий Ріг', 'Кропивницький', 'Кременець', 'Луцьк', 'Миколаїв', 'Полтава', 'Рівне', 'Тернопіль', 'Ужгород', 'Хмельницький', 'Черкаси', 'Чернівці', 'Чернігів', 'Берегово', 'Мукачево'],
        'Молдова': ['Кишинів'],
        'Польща': ['Варшава', 'Вроцлав', 'Гданськ', 'Жешув', 'Катовіце', 'Краків', 'Лодзь', 'Люблін', 'Бидгощ', 'Познань', 'Зелена Гура', 'Ольштин'],
        'Румунія': ['Бакеу', 'Бая-Маре', 'Брашов', 'Бухарест', 'Клуж-Напока', 'Крайова', 'Орада', 'Сібіу', 'Сучава', 'Тімішоара', 'Ясси'],
        'Німеччина': ['Берлін', 'Дюссельдорф', 'Кельн', 'Франкфурт-на-Майні', 'Нюрнберг', 'Дрезден', 'Дортмунд', 'Мюнхен', 'Кассель', 'Падерборн', 'Фрідріхсхафен'],
        'Чехія': ['Прага', 'Острава', 'Брно'],
        'Угорщина': ['Будапешт'],
        'Литва': ['Вільнюс'],
        'Латвія': ['Рига'],
        'Естонія': ['Таллінн'],
        'Австрія': ['Відень'],
        'Італія': ['Мілан', 'Рим', 'Турин', 'Болонья'],
        'Іспанія': ['Мадрид'],
        'Бельгія': ['Брюссель'],
        'Казахстан': ['Алмати', 'Астана', 'Актобе', 'Атирау'],
        'Словаччина': ['Братислава'],
        'Швейцарія': ['Цюрих'],
        'Нідерланди': ['Амстердам']
    };
    var departureCityToCountry = (function(){
        var map = new Map();
        Object.keys(departureCountryGroups).forEach(function(countryName){
            departureCountryGroups[countryName].forEach(function(cityName){
                map.set(normalizeSearchToken(cityName), countryName);
            });
        });
        return map;
    })();
    var busDestinationCountries = new Set([
        normalizeSearchToken('Болгарія'),
        normalizeSearchToken('Греція'),
        normalizeSearchToken('Чорногорія'),
        normalizeSearchToken('Хорватія'),
        normalizeSearchToken('Албанія'),
        normalizeSearchToken('Туреччина'),
        normalizeSearchToken('Італія')
    ]);

    var elCountryId = document.getElementById('asw-country-id');
    var elCountryQ  = document.getElementById('asw-country-q');
    var elCountryDd = document.getElementById('asw-country-dd');
    var elFrom      = document.getElementById('asw-from');
    var elD1        = document.getElementById('asw-d1');
    var elD2        = document.getElementById('asw-d2');
    var elN1        = document.getElementById('asw-n1');
    var elN2        = document.getElementById('asw-n2');
    var elAdults    = document.getElementById('asw-adults');
    var elChildren  = document.getElementById('asw-children');
    var elSubmit    = document.getElementById('asw-submit');
    var elForm      = document.getElementById('anex-sw-form');

    function esc(v){ var d=document.createElement('div'); d.textContent=v==null?'':String(v); return d.innerHTML; }
    function escAttr(v){ return String(v==null?'':v).replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;'); }
    function normalizeSearchToken(value){ return String(value||'').trim().toLowerCase().replace(/ё/g,'е').replace(/є/g,'е').replace(/і/g,'и').replace(/ї/g,'и'); }

    function formatApiDate(date){
        var dd=String(date.getDate()).padStart(2,'0');
        var mm=String(date.getMonth()+1).padStart(2,'0');
        var yy=String(date.getFullYear()).slice(-2);
        return dd+'.'+mm+'.'+yy;
    }

    function defaultDates(){
        var a=new Date(); a.setHours(12,0,0,0); a.setDate(a.getDate()+14);
        var b=new Date(a); b.setDate(b.getDate()+7);
        return {d1:formatApiDate(a), d2:formatApiDate(b)};
    }

    function apiError(data){
        return data && typeof data === 'object' && !Array.isArray(data) && data.error
            ? (data.error_desc || data.error) : '';
    }

    async function api(path, query){
        var key = path+'::'+JSON.stringify(query||{});
        var hit = apiCache.get(key);
        if(hit && Date.now() < hit.expires) return hit.data;
        if(apiPending.has(key)) return apiPending.get(key);
        var body = new URLSearchParams();
        body.set('action','ittour_lab_public'); body.set('nonce',nonce);
        body.set('path',path); body.set('lang','uk');
        body.set('query',JSON.stringify(query||{}));
        var req = fetch(ajaxUrl,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/x-www-form-urlencoded'},body})
            .then(r=>r.json())
            .then(function(p){
                if(!p.success) throw new Error((p.data&&p.data.message)||'Помилка');
                var inner = p.data && p.data.data;
                if(apiError(inner)) throw new Error(apiError(inner));
                var ttl = path.startsWith('dictionary') ? 86400000 : path.startsWith('module/params') ? 86400000 : 3600000;
                apiCache.set(key,{expires:Date.now()+ttl, data:inner});
                apiPending.delete(key);
                return inner;
            }).catch(function(e){ apiPending.delete(key); throw e; });
        apiPending.set(key, req);
        return req;
    }

    function closeDd(){
        if(!elCountryDd) return;
        elCountryDd.classList.remove('is-open');
        elCountryDd.innerHTML='';
        if(elCountryQ) elCountryQ.setAttribute('aria-expanded','false');
    }

    function openDd(matches){
        if(!elCountryDd||!matches.length) return;
        elCountryDd.innerHTML = matches.slice(0,80).map(function(c){
            return '<li role="none"><button type="button" role="option" data-id="'+escAttr(c.id)+'">'+esc(c.name)+'</button></li>';
        }).join('');
        elCountryDd.classList.add('is-open');
        if(elCountryQ) elCountryQ.setAttribute('aria-expanded','true');
        elCountryDd.querySelectorAll('button[data-id]').forEach(function(btn){
            btn.addEventListener('click',function(){ setCountry(btn.getAttribute('data-id')||''); closeDd(); });
        });
    }

    function setCountry(id){
        var meta = allCountries.find(function(c){ return c.id===String(id); });
        if(elCountryId) elCountryId.value = String(id);
        if(elCountryQ)  elCountryQ.value  = meta ? meta.name : '';
        loadFromCities(id);
    }

    function departureCountryLabel(city){
        var key = normalizeSearchToken(city && city.name ? city.name : '');
        return departureCityToCountry.get(key) || 'Інші країни';
    }

    function selectedCountryToken(countryId){
        var meta = allCountries.find(function(country){ return country.id === String(countryId || ''); });
        return normalizeSearchToken(meta && meta.name ? meta.name : '');
    }

    function filterDepartureCitiesForDestination(cities, countryId){
        var destinationToken = selectedCountryToken(countryId);
        var busOnly = busDestinationCountries.has(destinationToken);
        return (cities || []).filter(function(city){
            var departureCountry = departureCountryLabel(city);
            if (departureCountry !== 'Україна') return true;
            return busOnly;
        });
    }

    function selectedFromIds(){
        if(!elFrom) return [];
        return Array.from(elFrom.selectedOptions || []).map(function(option){ return String(option.value || '').trim(); }).filter(Boolean).slice(0, MAX_FROM_SELECT);
    }

    function enforceFromLimit(lastChangedValue){
        if(!elFrom) return;
        var selected = selectedFromIds();
        if(selected.length <= MAX_FROM_SELECT) return;
        Array.from(elFrom.options || []).forEach(function(option){
            if(option && String(option.value || '') === String(lastChangedValue || '')){
                option.selected = false;
            }
        });
    }

    function fillFromSelect(cities, countryId){
        if(!elFrom) return;
        var filtered = filterDepartureCitiesForDestination(cities, countryId);
        if(!filtered.length){
            elFrom.innerHTML = '<option value="" disabled>Немає доступних міст виїзду</option>';
            elFrom.disabled = true;
            return;
        }
        var groups = new Map();
        filtered.forEach(function(city){
            var groupName = departureCountryLabel(city);
            if(!groups.has(groupName)) groups.set(groupName, []);
            groups.get(groupName).push(city);
        });
        elFrom.innerHTML = Array.from(groups.entries()).map(function(entry){
            var label = entry[0];
            var options = entry[1].sort(function(left, right){
                return String(left.name || '').localeCompare(String(right.name || ''), 'uk');
            }).map(function(city){
                return '<option value="'+escAttr(String(city.id || ''))+'">'+esc(city.name || '')+'</option>';
            }).join('');
            return '<optgroup label="'+escAttr(label)+'">'+options+'</optgroup>';
        }).join('');
        elFrom.disabled = false;
    }

    function loadFromCities(countryId){
        if(!elFrom||!countryId) return;
        var key = String(countryId);
        if(departureCache.has(key)){ fillFromSelect(departureCache.get(key), countryId); return; }
        elFrom.innerHTML='<option value="">Завантаження…</option>';
        elFrom.disabled = true;
        api('module/params/'+countryId,{entity:'from_city'}).then(function(data){
            var cities = (data.from_cities||[]).slice().sort(function(a,b){
                var leftCountry = departureCountryLabel(a);
                var rightCountry = departureCountryLabel(b);
                if(leftCountry !== rightCountry){
                    return leftCountry.localeCompare(rightCountry, 'uk');
                }
                return (a.name||'').localeCompare(b.name||'','uk');
            });
            departureCache.set(key, cities);
            fillFromSelect(cities, countryId);
        }).catch(function(){ elFrom.disabled=false; });
    }

    /* ── Init ── */
    async function init(){
        // Set default dates
        var d = defaultDates();
        if(elD1&&!elD1.value) elD1.value = d.d1;
        if(elD2&&!elD2.value) elD2.value = d.d2;

        // Load countries — як у каталозі: module/params → countries (dictionary/country має інший формат)
        try {
            var data = await api('module/params',{});
            var raw = (data && data.countries) ? data.countries : [];
            allCountries = raw.map(function(c){
                return { id:String(c.id||''), name:c.name||'', image:c.image||'' };
            }).filter(function(c){ return c.id; });
        } catch(e) { allCountries=[]; }

        // Country autocomplete
        if(elCountryQ){
            elCountryQ.addEventListener('focus',function(){
                var q=elCountryQ.value.trim().toLowerCase();
                var list=allCountries.filter(function(c){ return (c.name||'').toLowerCase().includes(q); }).slice(0,80);
                openDd(list.length?list:allCountries.slice(0,40));
            });
            elCountryQ.addEventListener('input',function(){
                var q=elCountryQ.value.trim().toLowerCase();
                openDd(allCountries.filter(function(c){ return (c.name||'').toLowerCase().includes(q); }).slice(0,80));
            });
            elCountryQ.addEventListener('keydown',function(ev){ if(ev.key==='Escape') closeDd(); });
        }
        document.addEventListener('click',function(ev){
            if(!elCountryDd||!elCountryQ) return;
            if(!elCountryDd.contains(ev.target)&&ev.target!==elCountryQ) closeDd();
        });

        // Date auto-format dd.mm.yy
        [elD1, elD2].forEach(function(el){
            if(!el) return;
            el.addEventListener('input',function(){
                var v=el.value.replace(/\D/g,'');
                if(v.length>=5) v=v.slice(0,2)+'.'+v.slice(2,4)+'.'+v.slice(4,8);
                else if(v.length>=3) v=v.slice(0,2)+'.'+v.slice(2);
                el.value=v;
            });
        });
        [elN1, elN2].forEach(function(el){
            if(!el) return;
            el.addEventListener('input', function(){
                el.value = el.value.replace(/\D/g,'').slice(0,2);
            });
        });
        if(elFrom){
            elFrom.addEventListener('change', function(event){
                var changed = event.target && event.target.value ? event.target.value : '';
                enforceFromLimit(changed);
            });
        }

        // Form submit → redirect to catalog page
        if(elForm){
            elForm.addEventListener('submit',function(e){
                e.preventDefault();
                var countryId = elCountryId ? elCountryId.value : '';
                if(!countryId){ elCountryQ && elCountryQ.focus(); return; }
                var url = new URL(catalogUrl);
                url.searchParams.set('country_id', countryId);
                if(elFrom&&selectedFromIds().length) url.searchParams.set('from', selectedFromIds().join(','));
                if(elD1&&elD1.value)             url.searchParams.set('d1',       elD1.value);
                if(elD2&&elD2.value)             url.searchParams.set('d2',       elD2.value);
                if(elN1&&elN1.value)             url.searchParams.set('n1',       elN1.value);
                if(elN2&&elN2.value)             url.searchParams.set('n2',       elN2.value);
                if(elAdults&&elAdults.value)     url.searchParams.set('adults',   elAdults.value);
                if(elChildren&&elChildren.value) url.searchParams.set('children', elChildren.value);
                window.location.href = url.toString();
            });
        }

        // Try to load default (Turkey) departure cities
        if(allCountries.length && elFrom){
            var turkey = allCountries.find(function(c){ return c.id==='318'; });
            if(turkey){ setCountry(turkey.id); elCountryQ.value=turkey.name; }
        }
    }

    init().catch(console.error);
})();
</script>
</div>
