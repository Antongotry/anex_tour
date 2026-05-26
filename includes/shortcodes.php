<?php
/**
 * Anex Tour — Shortcode registration.
 *
 * Шорткоди для Elementor та будь-якого редактора WordPress.
 *
 * Використання:
 *   [anex_search]                    — форма пошуку з дизайну каталогу
 *   [anex_tour_results]              — фільтри + результати пошуку
 *   [anex_hot_tours]                 — гарячі тури
 *   [anex_bus_tours]                 — гарячі автобусні екскурсійні тури
 */

defined( 'ABSPATH' ) || exit;

/**
 * Рендерить шаблон у режимі вбудовування (embed mode).
 * Повертає HTML для вставки шорткодом.
 */
function anex_render_template( string $template_path ): string {
    if ( ! is_readable( $template_path ) ) {
        return '<p style="color:red">Anex Tour: шаблон не знайдено (' . esc_html( basename( $template_path ) ) . ').</p>';
    }

    // Prevent double-loading
    static $loaded = [];
    $key = md5( $template_path );

    if ( ! defined( 'ANEX_EMBED_MODE' ) ) {
        define( 'ANEX_EMBED_MODE', true );
    }

    ob_start();
    include $template_path;
    $html = ob_get_clean();

    // Extract content between first <style> and end (skip <!DOCTYPE html>...<body> wrapper)
    // The template outputs: optionally full HTML, or embed content when ANEX_EMBED_MODE=true
    return (string) $html;
}

function anex_catalog_widgets_source(): array {
    static $source = null;
    if ( is_array( $source ) ) {
        return $source;
    }

    $html = anex_render_template( ANEX_PLUGIN_DIR . 'templates/hotel-catalog.php' );

    $extract_between = static function ( string $start, string $end ) use ( $html ): string {
        $start_pos = strpos( $html, $start );
        if ( false === $start_pos ) {
            return '';
        }
        $end_pos = strpos( $html, $end, $start_pos );
        if ( false === $end_pos ) {
            return '';
        }
        return substr( $html, $start_pos, $end_pos - $start_pos );
    };

    preg_match( '#<style\b[^>]*>.*?</style>#is', $html, $style_match );
    preg_match( '#<script\b[^>]*>.*?</script>#is', $html, $script_match );

    $search_card = $extract_between(
        '<div class="hero-search-card hero-search-card--catalog">',
        "\n\n                <div class=\"hero-benefits hero-benefits--in-hero\""
    );

    $results_section = $extract_between(
        '<section class="search-results-page" id="search-results-page" hidden>',
        "\n\n        <section class=\"widget-frame\" id=\"offers-section\">"
    );

    $filters = $extract_between(
        '<aside class="search-filters" id="search-filters-aside"',
        "\n                <div class=\"search-results-main\">"
    );

    $results_main = $extract_between(
        '<div class="search-results-main">',
        "\n            </div>\n        </section>"
    );

    $support = $extract_between(
        '<!-- Mobile filter drawer -->',
        "\n    <script>"
    );

    $source = [
        'style'           => $style_match[0] ?? '',
        'script'          => $script_match[0] ?? '',
        'search_card'     => $search_card,
        'results_section' => $results_section,
        'filters'         => $filters,
        'results_main'    => $results_main,
        'support'         => $support,
    ];

    return $source;
}


/**
 * П.1: на /katalog/ не запускати важкий пошук (лише JS), шорткоди рендеряться як є.
 */
function anex_katalog_lite_footer_flag(): void {
	if ( ! function_exists( 'anex_is_katalog_landing_page' ) || ! anex_is_katalog_landing_page() ) {
		return;
	}
	echo '<script>window.ANEX_CATALOG_LITE=true;</script>';
}

function anex_catalog_widgets_assets(): string {
    static $printed = false;
    $source = anex_catalog_widgets_source();

    if ( ! has_action( 'wp_footer', 'anex_catalog_widgets_footer_assets' ) ) {
        add_action( 'wp_footer', 'anex_catalog_widgets_footer_assets', 40 );
    }
    if ( ! has_action( 'wp_footer', 'anex_katalog_lite_footer_flag' ) ) {
        add_action( 'wp_footer', 'anex_katalog_lite_footer_flag', 5 );
    }

    if ( $printed ) {
        return '';
    }
    $printed = true;

    return $source['style'] . "\n" . '<style>
.anex-catalog-search-widget{display:block;width:100%;max-width:100%!important;margin:0!important;padding:0!important;background:transparent!important;overflow:visible}
.anex-catalog-search-widget .hero-stage{display:block;width:100%;min-height:0!important;height:auto!important;margin:0!important;padding:0!important;background:transparent!important;overflow:visible}
.anex-catalog-search-widget .hero-stage::before,.anex-catalog-search-widget .hero-stage::after{display:none!important}
.anex-catalog-search-widget .hero-layout{display:block;width:100%;max-width:100%!important;min-height:0!important;height:auto!important;margin:0!important;padding:0!important;align-content:normal!important}
.anex-catalog-search-widget .hero-search-card{position:relative;width:100%!important;max-width:100%!important;margin:0!important;padding:18px!important}
.anex-catalog-search-widget .hero-search-card--catalog{width:100%!important;max-width:100%!important}
.anex-catalog-search-widget .hero-catalog-form{width:100%;margin:0}
.anex-catalog-search-widget .anex-search-mode-switch{display:inline-flex;gap:8px;width:fit-content;max-width:100%;margin:8px 0 14px;padding:4px;border:1px solid #dce4f2;border-radius:12px;background:#fff}
.anex-catalog-search-widget .anex-search-mode-btn{appearance:none;border:0;background:transparent;color:#1f2a44;font-weight:700;font-size:14px;line-height:1.2;padding:10px 14px;border-radius:10px;cursor:pointer;transition:all .2s ease}
.anex-catalog-search-widget .anex-search-mode-btn:hover,.anex-catalog-search-widget .anex-search-mode-btn:focus-visible{background:#1a5dc8;color:#fff;outline:none}
.anex-catalog-search-widget .anex-search-mode-btn.is-active{background:#f31624;color:#fff;box-shadow:0 8px 16px rgba(243,22,36,.2)}
.anex-catalog-search-widget .anex-search-mode-btn.is-active:hover,.anex-catalog-search-widget .anex-search-mode-btn.is-active:focus-visible{background:#de0f1c;color:#fff}
body.popular-search-open .anex-catalog-search-widget .hero-stage{min-height:0!important;height:auto!important;margin:0!important;padding:0!important}
body.popular-search-open .anex-catalog-search-widget .hero-stage .hero-layout{min-height:0!important;height:auto!important;margin:0!important;padding:0!important;align-content:normal!important}
.anex-catalog-search-widget .ps-submit,
.anex-catalog-search-widget .ps-submit:hover,
.anex-catalog-search-widget .ps-submit:focus,
.anex-catalog-search-widget .ps-submit:active{background:#f31624!important;color:#fff!important;text-shadow:none!important;opacity:1!important}
.anex-catalog-search-widget .ps-submit:hover{filter:brightness(1.05)}
.anex-catalog-search-widget .ps-inputlike,
.anex-catalog-search-widget .ps-inputlike:hover,
.anex-catalog-search-widget .ps-inputlike:focus,
.anex-catalog-search-widget .ps-inputlike:active{background:#fff!important;color:#1f2a44!important;-webkit-text-fill-color:#1f2a44!important;border-color:#c5d2e8!important;box-shadow:0 0 0 2px rgba(25,93,198,.10)!important;text-shadow:none!important;opacity:1!important}
.anex-catalog-search-widget .ps-inputlike .ps-inputlike-label{color:inherit!important;-webkit-text-fill-color:currentColor!important}
.anex-catalog-search-widget .ps-inputlike .ps-inputlike-label.is-placeholder{color:#64718d!important;-webkit-text-fill-color:#64718d!important}
.anex-catalog-search-widget .ps-inputlike:hover .ps-inputlike-label.is-placeholder,
.anex-catalog-search-widget .ps-inputlike:focus .ps-inputlike-label.is-placeholder{color:#546381!important;-webkit-text-fill-color:#546381!important}
.anex-catalog-search-widget button#ps-country-picker,
.anex-catalog-search-widget button#ps-country-picker:hover,
.anex-catalog-search-widget button#ps-country-picker:focus,
.anex-catalog-search-widget button#ps-country-picker:focus-visible,
.anex-catalog-search-widget button#ps-country-picker:active,
.anex-catalog-search-widget button#ps-country-picker:visited,
.anex-catalog-search-widget button#ps-from-picker,
.anex-catalog-search-widget button#ps-from-picker:hover,
.anex-catalog-search-widget button#ps-from-picker:focus,
.anex-catalog-search-widget button#ps-from-picker:focus-visible,
.anex-catalog-search-widget button#ps-from-picker:active,
.anex-catalog-search-widget button#ps-from-picker:visited{
background:#fff!important;
background-color:#fff!important;
background-image:none!important;
color:#1f2a44!important;
-webkit-text-fill-color:#1f2a44!important;
text-shadow:none!important;
filter:none!important;
}
.anex-catalog-search-widget button#ps-country-picker:hover,
.anex-catalog-search-widget button#ps-country-picker:focus,
.anex-catalog-search-widget button#ps-country-picker:focus-visible,
.anex-catalog-search-widget button#ps-country-picker:active,
.anex-catalog-search-widget button#ps-from-picker:hover,
.anex-catalog-search-widget button#ps-from-picker:focus,
.anex-catalog-search-widget button#ps-from-picker:focus-visible,
.anex-catalog-search-widget button#ps-from-picker:active{
border-color:#b9c9e6!important;
box-shadow:0 0 0 2px rgba(25,93,198,.10)!important;
}
.anex-catalog-search-widget .ps-picker-apply,
.anex-catalog-search-widget .ps-picker-apply:hover,
.anex-catalog-search-widget .ps-picker-apply:focus,
.anex-catalog-search-widget .ps-picker-apply:active,
.anex-catalog-search-widget .ps-picker-apply:visited{background:#f31624!important;color:#fff!important;-webkit-text-fill-color:#fff!important;text-shadow:none!important;opacity:1!important}
.anex-catalog-search-widget .ps-picker-close{background:#fff!important;color:#1f2a44!important;-webkit-text-fill-color:#1f2a44!important;border-color:#c5d2e8!important}
.anex-catalog-search-widget .ps-picker-close:hover,
.anex-catalog-search-widget .ps-picker-close:focus{background:#f31624!important;color:#fff!important;-webkit-text-fill-color:#fff!important;border-color:#f31624!important}
.anex-catalog-results-widget .search-results-page{display:block}
.anex-catalog-results-widget .search-results-loading{display:inline-flex!important;align-items:center;gap:12px;padding:14px 18px;border:1px solid #d7e2f4;border-radius:12px;background:#fff;font-weight:700;color:#3d4d6f}
.anex-catalog-results-widget .search-results-loading::before{content:"";width:16px;height:16px;border-radius:999px;border:2px solid #c8d8f3;border-top-color:#1a5dc8;animation:anex-search-loader-spin .9s linear infinite}
.anex-catalog-results-widget .search-results-loading[hidden]{display:none!important}
@keyframes anex-search-loader-spin{to{transform:rotate(360deg)}}
.anex-catalog-results-widget > .search-results-inner{width:100%!important;max-width:100%!important;margin:0!important;padding:0!important;display:grid;grid-template-columns:minmax(0,260px) minmax(0,1fr);gap:22px 28px;align-items:start}
.anex-catalog-results-widget .search-filters{width:260px;max-width:260px;padding:18px!important;border-radius:var(--radius-lg,18px);border:1px solid var(--line);background:#fff;box-shadow:0 12px 30px rgba(7,19,42,.06)}
.anex-catalog-results-widget .search-results-main{padding:0!important}
.anex-catalog-filters-widget > .search-results-inner{display:block!important;width:260px!important;max-width:260px!important;margin:0!important;padding:0!important}
.anex-catalog-results-main-widget > .search-results-inner{display:block!important;width:100%!important;max-width:100%!important;margin:0!important;padding:0!important}
.anex-catalog-results-main-widget .search-results-main{width:100%!important;max-width:100%!important;padding:0!important}
.anex-catalog-results-main-widget .search-result-row{grid-template-columns:minmax(0,200px) minmax(0,1fr) minmax(0,220px)!important;width:100%!important;max-width:100%!important}
.anex-catalog-results-main-widget .search-result-photo{min-width:0!important}
.anex-catalog-results-main-widget .search-result-side{min-width:0!important}
.anex-catalog-results-main-widget .search-result-cta{color:#fff!important;text-decoration:none!important}
.anex-catalog-results-main-widget .search-result-cta:hover{color:#fff!important}
.anex-catalog-results-widget .search-filters .filter-label-row,
.anex-catalog-results-widget .search-filters .filter-label-row:hover,
.anex-catalog-results-widget .search-filters .filter-label-row:focus,
.anex-catalog-results-widget .search-filters .filter-label-row:active{display:flex!important;align-items:center!important;gap:8px!important;width:100%!important;margin:0!important;padding:0!important;border:0!important;border-radius:0!important;background:transparent!important;background-image:none!important;box-shadow:none!important;color:var(--text)!important;text-align:left!important;text-decoration:none!important;outline:0!important;transform:none!important}
.anex-catalog-results-widget .search-filters .filter-label-row .filter-label{color:var(--text)!important}
.anex-catalog-results-widget .search-filters .filter-label-row .filter-label-icon{background:rgba(26,93,200,.1)!important;color:var(--accent)!important}
.anex-catalog-results-widget .search-filters .filter-label-row:hover .filter-label-icon{background:rgba(26,93,200,.1)!important;color:var(--accent)!important}
.anex-catalog-results-widget .search-filters .search-filters-reset,
.anex-catalog-results-widget .search-filters .search-filters-reset:hover,
.anex-catalog-results-widget .search-filters .search-filters-reset:focus{background:transparent!important;border:0!important;box-shadow:none!important;color:var(--accent)!important;text-decoration:underline!important}
@media(max-width:720px){.anex-catalog-search-widget .anex-search-mode-switch{display:grid;grid-template-columns:1fr 1fr;width:100%}.anex-catalog-search-widget .anex-search-mode-btn{padding:9px 10px;font-size:13px}}
@media(max-width:820px){.anex-catalog-results-widget > .search-results-inner{display:block}.anex-catalog-results-widget .search-filters{display:none}}
</style>';
}

function anex_catalog_widgets_footer_assets(): void {
    static $printed = false;
    if ( $printed ) {
        return;
    }
    $printed = true;

    $source = anex_catalog_widgets_source();
    echo $source['support']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    echo $source['script']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}

function anex_catalog_search_target_url( array $atts = [] ): string {
    if ( function_exists( 'anex_get_catalog_search_page_permalink' ) ) {
        return anex_get_catalog_search_page_permalink( $atts );
    }
    return anex_get_catalog_page_permalink( $atts );
}

function anex_boolish( $value, bool $default = false ): bool {
    if ( null === $value || '' === $value ) {
        return $default;
    }
    if ( is_bool( $value ) ) {
        return $value;
    }
    $string = strtolower( trim( (string) $value ) );
    if ( in_array( $string, [ '1', 'true', 'yes', 'on' ], true ) ) {
        return true;
    }
    if ( in_array( $string, [ '0', 'false', 'no', 'off' ], true ) ) {
        return false;
    }
    return $default;
}

function anex_catalog_search_mode_switch_markup(): string {
    return '<div class="anex-search-mode-switch" id="anex-search-mode-switch" role="tablist" aria-label="Режим пошуку">' .
        '<button type="button" class="anex-search-mode-btn is-active" data-search-mode="hotel" role="tab" aria-selected="true">Готелі</button>' .
        '<button type="button" class="anex-search-mode-btn" data-search-mode="excursion" role="tab" aria-selected="false">Екскурсійні (автобусом)</button>' .
        '</div>' .
        '<input type="hidden" id="ps-search-mode" value="hotel">';
}

function anex_catalog_search_redirect_script( string $target_url, string $excurs_target_url = '', bool $mode_toggle_enabled = false ): string {
    return '<script>
(function(){
    var targetUrl = ' . wp_json_encode( $target_url ) . ';
    var excursTargetUrl = ' . wp_json_encode( $excurs_target_url ) . ';
    var modeToggleEnabled = ' . ( $mode_toggle_enabled ? 'true' : 'false' ) . ';
    function initModeSwitch(){
        if(!modeToggleEnabled) return;
        var modeInput = document.getElementById("ps-search-mode");
        var switcher = document.getElementById("anex-search-mode-switch");
        if(!modeInput || !switcher) return;
        var urlMode = (new URL(window.location.href)).searchParams.get("mode");
        if(urlMode === "excursion"){ modeInput.value = "excursion"; }
        var setMode = function(mode){
            modeInput.value = mode === "excursion" ? "excursion" : "hotel";
            switcher.querySelectorAll("[data-search-mode]").forEach(function(btn){
                var active = btn.getAttribute("data-search-mode") === modeInput.value;
                btn.classList.toggle("is-active", active);
                btn.setAttribute("aria-selected", active ? "true" : "false");
            });
        };
        switcher.addEventListener("click", function(event){
            var btn = event.target && event.target.closest ? event.target.closest("[data-search-mode]") : null;
            if(!btn) return;
            event.preventDefault();
            setMode(btn.getAttribute("data-search-mode") || "hotel");
        });
        setMode(modeInput.value || "hotel");
    }
    initModeSwitch();
    document.addEventListener("submit", function(event){
        var form = event.target && event.target.closest ? event.target.closest("#popular-search-form") : null;
        if(!form) return;
        var formModeInput = form.querySelector("#ps-search-mode");
        var mode = formModeInput ? String(formModeInput.value || "hotel").trim() : "hotel";
        var hasLocalResults = document.getElementById("search-results-page") || document.getElementById("search-results-list") || document.getElementById("search-filters-aside");
        if(hasLocalResults) return;
        event.preventDefault();
        event.stopImmediatePropagation();
        var q = function(id){ var el = document.getElementById(id); return el ? String(el.value || "").trim() : ""; };
        mode = mode || q("ps-search-mode") || "hotel";
        var base = (mode === "excursion" && excursTargetUrl) ? excursTargetUrl : targetUrl;
        var url = new URL(base, window.location.origin);
        url.searchParams.delete("search");
        var countryId = q("ps-country-id");
        var regionId = q("ps-region-id");
        var fromIdsEl = document.getElementById("ps-from-ids");
        var fromCity = fromIdsEl && fromIdsEl.value ? fromIdsEl.value : q("ps-from");
        var d1 = q("ps-d1");
        var d2 = q("ps-d2");
        var n1 = q("ps-n1") || "6";
        var n2 = q("ps-n2") || "8";
        var adults = q("ps-adults") || "2";
        var children = q("ps-children") || "0";

        if (countryId) url.searchParams.set("country_id", countryId);
        else url.searchParams.delete("country_id");
        if (regionId) url.searchParams.set("region", regionId);
        else url.searchParams.delete("region");
        url.searchParams.set("from", fromCity);
        url.searchParams.set("d1", d1);
        url.searchParams.set("d2", d2);
        url.searchParams.set("n1", n1);
        url.searchParams.set("n2", n2);
        url.searchParams.set("adults", adults);
        url.searchParams.set("children", children);

        if(mode === "excursion"){
            url.searchParams.set("mode", "excursion");
            url.searchParams.set("country", countryId);
            url.searchParams.set("from_city", fromCity);
            url.searchParams.set("date_from", d1);
            url.searchParams.set("date_till", d2);
            url.searchParams.set("night_from", n1);
            url.searchParams.set("night_till", n2);
            url.searchParams.set("adult", adults);
            url.searchParams.set("child", children);
            url.searchParams.set("transport_type", "2");
        } else {
            url.searchParams.delete("mode");
            url.searchParams.delete("transport_type");
        }
        window.location.href = url.toString();
    }, true);
})();
</script>';
}

function anex_render_catalog_search_widget( array $atts = [] ): string {
    $source = anex_catalog_widgets_source();
    if ( '' === $source['search_card'] ) {
        return '<p style="color:red">Anex Tour: фрагмент пошуку не знайдено.</p>';
    }

    $mode_toggle_enabled = anex_boolish( $atts['mode_switch'] ?? null, false );
    if ( $mode_toggle_enabled ) {
        $request_path = trim( (string) wp_parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH ), '/' );
        $catalog_slugs = array_filter(
            array_unique(
                [
                    'katalog',
                    'populyarni-goteli',
                    trim( (string) get_option( 'anex_slug_hotel_catalog', '' ), '/' ),
                ]
            )
        );
        if ( in_array( $request_path, $catalog_slugs, true ) ) {
            $mode_toggle_enabled = false;
        }
    }
    $target_url = anex_catalog_search_target_url( $atts );
    $excurs_target_url = anex_get_excursions_page_permalink(
        [ 'target' => (string) ( $atts['excurs_target'] ?? '' ) ]
    );
    $search_card = $source['search_card'];
    if ( $mode_toggle_enabled ) {
        $search_card = preg_replace(
            '#(<p class="hero-catalog-title">.*?</p>)#is',
            '$1' . anex_catalog_search_mode_switch_markup(),
            (string) $search_card,
            1
        ) ?: $search_card;
    }

    return anex_catalog_widgets_assets() .
        '<main class="page-shell anex-embed-root anex-catalog-search-widget">' .
            '<section class="hero-stage">' .
                '<div class="hero-layout">' .
                    $search_card .
                '</div>' .
            '</section>' .
        '</main>' .
        anex_catalog_search_redirect_script( $target_url, $excurs_target_url, $mode_toggle_enabled );
}

function anex_render_excursion_search_widget( array $atts = [] ): string {
    $atts['mode_switch'] = '0';

    if ( empty( $atts['excurs_target'] ) ) {
        $atts['excurs_target'] = '';
    }
    if ( empty( $atts['target'] ) ) {
        $atts['target'] = get_permalink() ?: home_url( '/ekskursijni-tury/' );
    }

    $html = anex_render_catalog_search_widget( $atts );

    $force_mode_script = '<script>(function(){'
        . 'function forceExcursionMode(){'
        . 'var form=document.getElementById("popular-search-form");'
        . 'var modeInput=document.getElementById("ps-search-mode");'
        . 'if(!modeInput && form){modeInput=document.createElement("input");modeInput.type="hidden";modeInput.id="ps-search-mode";modeInput.value="excursion";form.appendChild(modeInput);}'
        . 'if(modeInput){modeInput.value="excursion";}'
        . 'var switcher=document.getElementById("anex-search-mode-switch");'
        . 'if(switcher){switcher.style.display="none";}'
        . '}'
        . 'if(document.readyState==="loading"){document.addEventListener("DOMContentLoaded",forceExcursionMode);}else{forceExcursionMode();}'
        . '})();</script>';

    return $html . $force_mode_script;
}

function anex_render_catalog_results_widget(): string {
    $source = anex_catalog_widgets_source();
    if ( '' === $source['results_section'] ) {
        return '<p style="color:red">Anex Tour: фрагмент результатів не знайдено.</p>';
    }

    return anex_catalog_widgets_assets() .
        '<main class="page-shell anex-embed-root anex-catalog-results-widget anex-catalog-filters-widget">' .
            $source['results_section'] .
        '</main>';
}

function anex_render_catalog_filters_widget(): string {
    $source = anex_catalog_widgets_source();
    if ( '' === $source['filters'] ) {
        return '<p style="color:red">Anex Tour: фрагмент фільтрів не знайдено.</p>';
    }

    return anex_catalog_widgets_assets() .
        '<main class="page-shell anex-embed-root anex-catalog-results-widget anex-catalog-results-main-widget">' .
            '<div class="search-results-inner">' .
                $source['filters'] .
            '</div>' .
        '</main>';
}

function anex_render_catalog_results_main_widget(): string {
    $source = anex_catalog_widgets_source();
    if ( '' === $source['results_main'] ) {
        return '<p style="color:red">Anex Tour: фрагмент списку результатів не знайдено.</p>';
    }

    return anex_catalog_widgets_assets() .
        '<main class="page-shell anex-embed-root anex-catalog-results-widget">' .
            '<div class="search-results-inner">' .
                $source['results_main'] .
            '</div>' .
        '</main>';
}

function anex_hotel_detail_shortcodes_assets(): string {
    return anex_catalog_widgets_assets() . "\n" . '<style>
.anex-hotel-detail-slot{display:block;width:100%;max-width:100%;margin:0;padding:0}
.anex-hotel-detail-slot:empty{display:none}
.anex-hotel-detail-slot>*{margin:0!important;max-width:100%!important}
.anex-hotel-detail-slot .detail-section,
.anex-hotel-detail-slot .detail-nav,
.anex-hotel-detail-slot .best-offer-card,
.anex-hotel-detail-slot .low-price-calendar-wrap,
.anex-hotel-detail-slot .facility-card,
.anex-hotel-detail-slot .booking-benefit-card,
.anex-hotel-detail-slot .review-card,
.anex-hotel-detail-slot a.similar-card{
margin:0!important;
padding:0!important;
border:0!important;
border-radius:0!important;
background:transparent!important;
box-shadow:none!important
}
</style>';
}

function anex_render_hotel_detail_bootstrap_widget(): string {
    return anex_hotel_detail_shortcodes_assets() .
        '<section class="tour-detail-page anex-hotel-detail-bootstrap" id="tour-detail-page" aria-live="polite" style="display:none!important" aria-hidden="true">' .
            '<div class="detail-loading" id="detail-loading" hidden>Завантаження…</div>' .
            '<div class="detail-content" id="detail-content" hidden></div>' .
        '</section>';
}

function anex_render_hotel_detail_slot_widget( string $slot_id ): string {
    return anex_hotel_detail_shortcodes_assets() .
        '<div class="anex-hotel-detail-slot" id="' . esc_attr( $slot_id ) . '"></div>';
}

function anex_excursion_detail_shortcodes_assets(): string {
    return anex_catalog_widgets_assets() . "\n" . '<style>
.anex-excursion-detail-slot{display:block;width:100%;max-width:100%;margin:0;padding:0}
.anex-excursion-detail-slot:not(.anex-exc-popular-slot):empty{display:none}
.anex-exc-popular-slot:empty{display:block;min-height:0}
.anex-excursion-detail-slot>*{margin:0!important;max-width:100%!important}
</style>';
}

function anex_render_excursion_detail_bootstrap_widget(): string {
    return anex_excursion_detail_shortcodes_assets() .
        '<section class="anex-excursion-detail-bootstrap" id="anex-excursion-detail-bootstrap" aria-live="polite">' .
        '<div class="anex-excursion-detail-layout">' .
        '<div class="anex-excursion-detail-main" id="anex-excursion-detail-main-col"></div>' .
        '<aside class="anex-excursion-detail-sidebar-col" id="anex-excursion-detail-sidebar-col" aria-label="Коротка інформація"></aside>' .
        '</div>' .
        '</section>';
}

function anex_render_excursion_detail_slot_widget( string $slot_id, string $extra_class = '' ): string {
    $classes = 'anex-excursion-detail-slot' . ( $extra_class !== '' ? ' ' . sanitize_html_class( $extra_class ) : '' );
    return anex_excursion_detail_shortcodes_assets() .
        '<div class="' . esc_attr( $classes ) . '" id="' . esc_attr( $slot_id ) . '"></div>';
}

function anex_render_hotel_detail_compact_widget(): string {
    return anex_hotel_detail_shortcodes_assets() .
        '<div class="anex-hotel-detail-compact">' .
            '<div class="anex-hotel-detail-slot" id="anex-detail-head"></div>' .
            '<div class="anex-hotel-detail-slot" id="anex-detail-info"></div>' .
            '<div class="anex-hotel-detail-slot" id="anex-detail-prices"></div>' .
            '<div class="anex-hotel-detail-slot" id="anex-detail-calendar"></div>' .
            '<div class="anex-hotel-detail-slot" id="anex-detail-facilities"></div>' .
            '<div class="anex-hotel-detail-slot" id="anex-detail-reviews"></div>' .
            '<div class="anex-hotel-detail-slot" id="anex-detail-similar-price"></div>' .
            '<div class="anex-hotel-detail-slot" id="anex-detail-similar-beach"></div>' .
        '</div>';
}

function anex_render_excursion_detail_compact_widget(): string {
    return anex_excursion_detail_shortcodes_assets() .
        '<div class="anex-excursion-detail-compact">' .
            '<div class="anex-excursion-detail-slot" id="anex-exc-detail-head"></div>' .
            '<div class="anex-excursion-detail-slot" id="anex-exc-detail-info"></div>' .
            '<div class="anex-excursion-detail-slot" id="anex-exc-detail-gallery"></div>' .
            '<div class="anex-excursion-detail-slot" id="anex-exc-detail-program"></div>' .
            '<div class="anex-excursion-detail-slot" id="anex-exc-detail-hikes"></div>' .
            '<div class="anex-excursion-detail-slot" id="anex-exc-detail-included"></div>' .
            '<div class="anex-excursion-detail-slot" id="anex-exc-detail-dates"></div>' .
            '<div class="anex-excursion-detail-slot" id="anex-exc-detail-docs"></div>' .
            '<div class="anex-excursion-detail-slot anex-exc-popular-slot" id="anex-exc-detail-popular"></div>' .
            '<div class="anex-excursion-detail-slot" id="anex-exc-detail-price"></div>' .
        '</div>';
}

/* ─── [anex_hotel_catalog] ─── */
add_shortcode( 'anex_hotel_catalog', function ( $atts ) {
    if ( get_option( 'anex_module_hotel_catalog', '1' ) !== '1' ) {
        return '<!-- Anex Tour: модуль "Каталог готелів" вимкнено в налаштуваннях -->';
    }
    return anex_render_template( ANEX_PLUGIN_DIR . 'templates/hotel-catalog.php' );
} );

/* ─── [anex_tour_lab] ─── */
add_shortcode( 'anex_tour_lab', function ( $atts ) {
    if ( get_option( 'anex_module_tour_lab', '1' ) !== '1' ) {
        return '<!-- Anex Tour: модуль "Пошук турів" вимкнено в налаштуваннях -->';
    }
    return anex_render_template( ANEX_PLUGIN_DIR . 'templates/tour-lab.php' );
} );

/* ─── Search widgets ─── */
add_shortcode( 'anex_search', function ( $atts ) {
    $atts = shortcode_atts(
        [
            'target'        => '',
            'excurs_target' => '',
            'mode_switch'   => '1',
        ],
        (array) $atts,
        'anex_search'
    );
    return anex_render_catalog_search_widget( $atts );
} );

add_shortcode( 'anex_tour_search', function ( $atts ) {
    $atts = shortcode_atts(
        [
            'target'        => '',
            'excurs_target' => '',
            'mode_switch'   => '0',
        ],
        (array) $atts,
        'anex_tour_search'
    );
    return anex_render_catalog_search_widget( $atts );
} );

add_shortcode( 'anex_excursion_search', function ( $atts ) {
    $atts = shortcode_atts(
        [
            'target'        => '',
            'excurs_target' => '',
        ],
        (array) $atts,
        'anex_excursion_search'
    );
    return anex_render_excursion_search_widget( $atts );
} );

add_shortcode( 'anex_excurs_search', function ( $atts ) {
    $atts = shortcode_atts(
        [
            'target'        => '',
            'excurs_target' => '',
        ],
        (array) $atts,
        'anex_excurs_search'
    );
    return anex_render_excursion_search_widget( $atts );
} );

add_shortcode( 'anex_tour_results', function ( $atts ) {
    return anex_render_catalog_results_widget();
} );

add_shortcode( 'anex_tour_filters', function ( $atts ) {
    return anex_render_catalog_filters_widget();
} );

add_shortcode( 'anex_tour_results_main', function ( $atts ) {
    return anex_render_catalog_results_main_widget();
} );

/* ─── [anex_hot_tours] ─── */
add_shortcode( 'anex_hot_tours', function ( $atts ) {
    if ( ! has_action( 'wp_footer', 'anex_katalog_lite_footer_flag' ) ) {
        add_action( 'wp_footer', 'anex_katalog_lite_footer_flag', 5 );
    }
    return anex_render_template( ANEX_PLUGIN_DIR . 'templates/widget-hot-tours.php' );
} );

/* ─── [anex_bus_tours] ─── */
add_shortcode( 'anex_bus_tours', function ( $atts ) {
    if ( ! has_action( 'wp_footer', 'anex_katalog_lite_footer_flag' ) ) {
        add_action( 'wp_footer', 'anex_katalog_lite_footer_flag', 5 );
    }
    return anex_render_template( ANEX_PLUGIN_DIR . 'templates/widget-bus-tours.php' );
} );

add_shortcode( 'anex_hot_bus_tours', function ( $atts ) {
    return do_shortcode( '[anex_bus_tours]' );
} );

/* ─── [anex_directions] ─── */
add_shortcode( 'anex_directions', function ( $atts ) {
    if ( ! has_action( 'wp_footer', 'anex_katalog_lite_footer_flag' ) ) {
        add_action( 'wp_footer', 'anex_katalog_lite_footer_flag', 5 );
    }
    return anex_render_template( ANEX_PLUGIN_DIR . 'templates/widget-directions.php' );
} );

/* ─── Hotel detail split widgets (for Elementor assembly) ─── */
add_shortcode( 'anex_hotel_detail_bootstrap', function () {
    return anex_render_hotel_detail_bootstrap_widget();
} );
add_shortcode( 'anex_hotel_detail_head', function () {
    return anex_render_hotel_detail_slot_widget( 'anex-detail-head' );
} );
add_shortcode( 'anex_hotel_detail_info', function () {
    return anex_render_hotel_detail_slot_widget( 'anex-detail-info' );
} );
add_shortcode( 'anex_hotel_detail_prices', function () {
    return anex_render_hotel_detail_slot_widget( 'anex-detail-prices' );
} );
add_shortcode( 'anex_hotel_detail_calendar', function () {
    return anex_render_hotel_detail_slot_widget( 'anex-detail-calendar' );
} );
add_shortcode( 'anex_hotel_detail_facilities', function () {
    return anex_render_hotel_detail_slot_widget( 'anex-detail-facilities' );
} );
add_shortcode( 'anex_hotel_detail_reviews', function () {
    return anex_render_hotel_detail_slot_widget( 'anex-detail-reviews' );
} );
add_shortcode( 'anex_hotel_detail_similar_price', function () {
    return anex_render_hotel_detail_slot_widget( 'anex-detail-similar-price' );
} );
add_shortcode( 'anex_hotel_detail_similar_beach', function () {
    return anex_render_hotel_detail_slot_widget( 'anex-detail-similar-beach' );
} );
add_shortcode( 'anex_hotel_detail_compact', function () {
    return anex_render_hotel_detail_compact_widget();
} );

/* ─── Excursion detail split widgets (for Elementor assembly) ─── */
add_shortcode( 'anex_excursion_detail_bootstrap', function () {
    return anex_render_excursion_detail_bootstrap_widget();
} );
add_shortcode( 'anex_excursion_detail_head', function () {
    return anex_render_excursion_detail_slot_widget( 'anex-exc-detail-head' );
} );
add_shortcode( 'anex_excursion_detail_info', function () {
    return anex_render_excursion_detail_slot_widget( 'anex-exc-detail-info' );
} );
add_shortcode( 'anex_excursion_detail_program', function () {
    return anex_render_excursion_detail_slot_widget( 'anex-exc-detail-program' );
} );
add_shortcode( 'anex_excursion_detail_price', function () {
    return anex_render_excursion_detail_slot_widget( 'anex-exc-detail-price' );
} );
add_shortcode( 'anex_excursion_detail_gallery', function () {
    return anex_render_excursion_detail_slot_widget( 'anex-exc-detail-gallery' );
} );
add_shortcode( 'anex_excursion_detail_hikes', function () {
    return anex_render_excursion_detail_slot_widget( 'anex-exc-detail-hikes' );
} );
add_shortcode( 'anex_excursion_detail_included', function () {
    return anex_render_excursion_detail_slot_widget( 'anex-exc-detail-included' );
} );
add_shortcode( 'anex_excursion_detail_dates', function () {
    return anex_render_excursion_detail_slot_widget( 'anex-exc-detail-dates' );
} );
add_shortcode( 'anex_excursion_detail_documents', function () {
    return anex_render_excursion_detail_slot_widget( 'anex-exc-detail-docs' );
} );
add_shortcode( 'anex_excursion_detail_popular', function () {
    return anex_render_excursion_detail_slot_widget( 'anex-exc-detail-popular', 'anex-exc-popular-slot' );
} );
add_shortcode( 'anex_excursion_detail_compact', function () {
    return anex_render_excursion_detail_compact_widget();
} );
