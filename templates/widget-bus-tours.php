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
#<?php echo esc_attr( $widget_id ); ?> .abtw-modal[hidden]{display:none!important}
#<?php echo esc_attr( $widget_id ); ?> .abtw-modal{position:fixed;inset:0;z-index:999999;display:none;align-items:center;justify-content:center;padding:24px;background:rgba(11,20,38,.62);backdrop-filter:blur(8px)}
#<?php echo esc_attr( $widget_id ); ?> .abtw-modal.is-open{display:flex}
#<?php echo esc_attr( $widget_id ); ?> .abtw-modal-dialog{position:relative;width:min(1120px,calc(100vw - 28px));max-height:min(88vh,920px);overflow:auto;border-radius:30px;background:linear-gradient(180deg,#f7f9ff 0%,#fff 42%);box-shadow:0 28px 90px rgba(7,18,42,.34);border:1px solid rgba(220,228,242,.9)}
#<?php echo esc_attr( $widget_id ); ?> .abtw-modal-close{position:sticky;top:18px;float:right;z-index:4;display:inline-flex;align-items:center;justify-content:center;width:58px;height:58px;margin:18px 18px 0 0;border:3px solid rgba(255,255,255,.88);border-radius:999px;background:var(--abtw-red)!important;color:#fff!important;font:inherit;font-size:34px;font-weight:900;line-height:1;cursor:pointer;box-shadow:0 18px 38px rgba(243,22,36,.28)}
#<?php echo esc_attr( $widget_id ); ?> .abtw-modal-close:hover{transform:translateY(-1px);filter:brightness(.98)}
#<?php echo esc_attr( $widget_id ); ?> .abtw-modal-body{clear:both;padding:22px 34px 34px}
#<?php echo esc_attr( $widget_id ); ?> .abtw-modal-loading{display:grid;gap:14px;padding:52px 24px;text-align:center;color:var(--abtw-muted);font-weight:900}
#<?php echo esc_attr( $widget_id ); ?> .abtw-modal-loading::before{content:"";justify-self:center;width:34px;height:34px;border-radius:999px;border:4px solid #dce6f7;border-top-color:var(--abtw-accent);animation:abtw-spin .8s linear infinite}
@keyframes abtw-spin{to{transform:rotate(360deg)}}
#<?php echo esc_attr( $widget_id ); ?> .abtw-modal-grid{display:grid;grid-template-columns:minmax(0,1.35fr) minmax(320px,.65fr);gap:26px;align-items:start}
#<?php echo esc_attr( $widget_id ); ?> .abtw-modal-main{display:grid;gap:18px;min-width:0}
#<?php echo esc_attr( $widget_id ); ?> .abtw-modal-side{position:sticky;top:22px;display:grid;gap:16px;min-width:0}
#<?php echo esc_attr( $widget_id ); ?> .abtw-modal-hero{display:grid;gap:14px;min-height:0}
#<?php echo esc_attr( $widget_id ); ?> .abtw-modal-photo{position:relative;overflow:hidden;border-radius:24px;background:#eaf0fa;box-shadow:0 18px 42px rgba(33,51,98,.10)}
#<?php echo esc_attr( $widget_id ); ?> .abtw-modal-photo img{width:100%;height:100%;object-fit:cover;display:block}
#<?php echo esc_attr( $widget_id ); ?> .abtw-modal-photo--main{min-height:430px}
#<?php echo esc_attr( $widget_id ); ?> .abtw-modal-thumbs{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:12px}
#<?php echo esc_attr( $widget_id ); ?> .abtw-modal-thumb{appearance:none;display:block;width:100%;height:86px;padding:0;overflow:hidden;border:2px solid transparent!important;border-radius:16px;background:#eaf0fa!important;cursor:pointer;box-shadow:0 10px 24px rgba(33,51,98,.10);transition:border-color .18s,box-shadow .18s,transform .18s}
#<?php echo esc_attr( $widget_id ); ?> .abtw-modal-thumb img{display:block;width:100%;height:100%;object-fit:cover}
#<?php echo esc_attr( $widget_id ); ?> .abtw-modal-thumb:hover{transform:translateY(-1px)}
#<?php echo esc_attr( $widget_id ); ?> .abtw-modal-thumb.is-active{border-color:var(--abtw-accent)!important;box-shadow:0 0 0 4px rgba(26,93,200,.12),0 12px 26px rgba(33,51,98,.14)}
#<?php echo esc_attr( $widget_id ); ?> .abtw-gallery-nav{position:absolute;top:50%;z-index:3;display:inline-flex;align-items:center;justify-content:center;width:52px;height:52px;border:0!important;border-radius:999px;background:rgba(255,255,255,.92)!important;color:var(--abtw-red)!important;font:inherit;font-size:40px;font-weight:900;line-height:1;cursor:pointer;box-shadow:0 14px 32px rgba(12,29,62,.18);transform:translateY(-50%);transition:background .18s,transform .18s}
#<?php echo esc_attr( $widget_id ); ?> .abtw-gallery-nav:hover{background:#fff!important;transform:translateY(-50%) scale(1.04)}
#<?php echo esc_attr( $widget_id ); ?> .abtw-gallery-prev{left:18px}
#<?php echo esc_attr( $widget_id ); ?> .abtw-gallery-next{right:18px;background:var(--abtw-red)!important;color:#fff!important;border:3px solid rgba(255,255,255,.9)!important}
#<?php echo esc_attr( $widget_id ); ?> .abtw-open-photo,#<?php echo esc_attr( $widget_id ); ?> .abtw-open-photo:visited{position:absolute;right:18px;bottom:18px;z-index:3;display:inline-flex;align-items:center;justify-content:center;min-height:38px;padding:9px 14px;border-radius:999px;background:rgba(11,20,38,.72);color:#fff!important;font-size:13px;font-weight:900;text-decoration:none!important;backdrop-filter:blur(8px)}
#<?php echo esc_attr( $widget_id ); ?> .abtw-open-photo:hover{background:rgba(11,20,38,.86);color:#fff!important;text-decoration:none!important}
#<?php echo esc_attr( $widget_id ); ?> .abtw-modal-card{border:1px solid #d9e4f7;border-radius:22px;background:#fff;padding:24px;box-shadow:0 16px 40px rgba(33,51,98,.06)}
#<?php echo esc_attr( $widget_id ); ?> .abtw-modal-title{margin:0;color:var(--abtw-text);font-size:clamp(30px,3.4vw,48px);font-weight:900;line-height:1.05;letter-spacing:-.05em}
#<?php echo esc_attr( $widget_id ); ?> .abtw-modal-route{margin:10px 0 0;color:#65718d;font-size:18px;font-weight:800;line-height:1.4}
#<?php echo esc_attr( $widget_id ); ?> .abtw-modal-tags{display:flex;flex-wrap:wrap;gap:10px;margin-top:18px}
#<?php echo esc_attr( $widget_id ); ?> .abtw-modal-tag{display:inline-flex;align-items:center;min-height:38px;padding:9px 14px;border-radius:999px;background:#edf3ff;color:#173b7d;font-size:14px;font-weight:900}
#<?php echo esc_attr( $widget_id ); ?> .abtw-modal-card h3,#<?php echo esc_attr( $widget_id ); ?> .abtw-modal-side h3{margin:0 0 14px;color:var(--abtw-text);font-size:22px;font-weight:900;line-height:1.12}
#<?php echo esc_attr( $widget_id ); ?> .abtw-modal-copy{display:grid;gap:12px;color:#3d4a68;font-size:16px;font-weight:600;line-height:1.62}
#<?php echo esc_attr( $widget_id ); ?> .abtw-modal-copy p{margin:0}
#<?php echo esc_attr( $widget_id ); ?> .abtw-program-html{display:grid;gap:14px;color:#3d4a68;font-size:16px;font-weight:600;line-height:1.68;word-break:normal;overflow-wrap:anywhere}
#<?php echo esc_attr( $widget_id ); ?> .abtw-program-html p{margin:0 0 12px}
#<?php echo esc_attr( $widget_id ); ?> .abtw-program-html p:last-child{margin-bottom:0}
#<?php echo esc_attr( $widget_id ); ?> .abtw-program-html h1,#<?php echo esc_attr( $widget_id ); ?> .abtw-program-html h2,#<?php echo esc_attr( $widget_id ); ?> .abtw-program-html h3,#<?php echo esc_attr( $widget_id ); ?> .abtw-program-html h4{margin:16px 0 8px;color:var(--abtw-text);font-weight:900;line-height:1.16;letter-spacing:-.02em}
#<?php echo esc_attr( $widget_id ); ?> .abtw-program-html ul,#<?php echo esc_attr( $widget_id ); ?> .abtw-program-html ol{margin:0 0 12px 22px;padding:0}
#<?php echo esc_attr( $widget_id ); ?> .abtw-program-html li{margin:0 0 8px}
#<?php echo esc_attr( $widget_id ); ?> .abtw-program-day{display:grid;gap:8px;padding:14px 0;border-bottom:1px solid #e7eef9}
#<?php echo esc_attr( $widget_id ); ?> .abtw-program-day:first-child{padding-top:0}
#<?php echo esc_attr( $widget_id ); ?> .abtw-program-day:last-child{padding-bottom:0;border-bottom:0}
#<?php echo esc_attr( $widget_id ); ?> .abtw-program-day-title{display:block;color:var(--abtw-text);font-size:18px;font-weight:900;line-height:1.25}
#<?php echo esc_attr( $widget_id ); ?> .abtw-route-list{display:flex;flex-wrap:wrap;gap:10px}
#<?php echo esc_attr( $widget_id ); ?> .abtw-route-step{display:inline-flex;align-items:center;gap:8px;padding:9px 12px;border-radius:999px;background:#edf3ff;color:#173b7d;font-weight:900}
#<?php echo esc_attr( $widget_id ); ?> .abtw-route-step span:first-child{display:inline-flex;align-items:center;justify-content:center;width:26px;height:26px;border-radius:999px;background:#dbe9ff;color:var(--abtw-accent)}
#<?php echo esc_attr( $widget_id ); ?> .abtw-side-price{border:1px solid #d9e4f7;border-radius:24px;background:#fff;padding:24px;box-shadow:0 16px 44px rgba(33,51,98,.08);text-align:center}
#<?php echo esc_attr( $widget_id ); ?> .abtw-price-hint{margin:0 0 8px;color:#6f7b94;font-size:14px;font-weight:900;text-transform:uppercase;letter-spacing:.04em}
#<?php echo esc_attr( $widget_id ); ?> .abtw-price-big{margin:0;color:var(--abtw-accent);font-size:38px;font-weight:900;line-height:1}
#<?php echo esc_attr( $widget_id ); ?> .abtw-price-caption{margin:10px 0 0;color:#65718d;font-size:15px;font-weight:800}
#<?php echo esc_attr( $widget_id ); ?> .abtw-modal-primary,#<?php echo esc_attr( $widget_id ); ?> .abtw-modal-primary:visited{display:inline-flex;align-items:center;justify-content:center;width:100%;min-height:58px;margin-top:18px;padding:14px 18px;border-radius:16px;background:var(--abtw-red);color:#fff!important;font-size:18px;font-weight:900;text-decoration:none!important;box-shadow:0 16px 34px rgba(243,22,36,.18)}
#<?php echo esc_attr( $widget_id ); ?> .abtw-modal-primary:hover{background:#d91420;color:#fff!important;text-decoration:none!important}
#<?php echo esc_attr( $widget_id ); ?> .abtw-facts{display:grid;gap:12px}
#<?php echo esc_attr( $widget_id ); ?> .abtw-fact{padding:16px;border:1px solid #d9e4f7;border-radius:18px;background:#f5f8ff}
#<?php echo esc_attr( $widget_id ); ?> .abtw-fact small{display:block;margin-bottom:6px;color:#6f7b94;font-size:12px;font-weight:900;text-transform:uppercase;letter-spacing:.04em}
#<?php echo esc_attr( $widget_id ); ?> .abtw-fact strong{display:block;color:var(--abtw-text);font-size:17px;font-weight:900;line-height:1.25}
#<?php echo esc_attr( $widget_id ); ?> .abtw-dates-wrap{overflow:auto;border:1px solid #d9e4f7;border-radius:18px}
#<?php echo esc_attr( $widget_id ); ?> .abtw-dates{width:100%;border-collapse:collapse;background:#fff;min-width:560px}
#<?php echo esc_attr( $widget_id ); ?> .abtw-dates th,#<?php echo esc_attr( $widget_id ); ?> .abtw-dates td{padding:13px 14px;border-bottom:1px solid #e7eef9;text-align:left;color:#3d4a68;font-size:14px;font-weight:800}
#<?php echo esc_attr( $widget_id ); ?> .abtw-dates th{background:#f0f5ff;color:#65718d;text-transform:uppercase;font-size:12px;letter-spacing:.04em}
#<?php echo esc_attr( $widget_id ); ?> .abtw-dates tr:last-child td{border-bottom:0}
#<?php echo esc_attr( $widget_id ); ?> .abtw-modal-note{margin:12px 0 0;color:#6f7b94;font-size:14px;font-weight:700;line-height:1.45}
@media(max-width:1180px){#<?php echo esc_attr( $widget_id ); ?> .abtw-grid,#<?php echo esc_attr( $widget_id ); ?> .abtw-skeleton{grid-template-columns:repeat(3,minmax(0,1fr))}}
@media(max-width:980px){#<?php echo esc_attr( $widget_id ); ?> .abtw-modal-grid{grid-template-columns:1fr}#<?php echo esc_attr( $widget_id ); ?> .abtw-modal-side{position:static}#<?php echo esc_attr( $widget_id ); ?> .abtw-modal-hero{grid-template-columns:1fr;min-height:0}#<?php echo esc_attr( $widget_id ); ?> .abtw-modal-photo--main{min-height:320px}#<?php echo esc_attr( $widget_id ); ?> .abtw-modal-thumbs{grid-template-columns:repeat(4,minmax(0,1fr))}}
@media(max-width:860px){#<?php echo esc_attr( $widget_id ); ?> .abtw-frame{padding:28px 22px 30px}#<?php echo esc_attr( $widget_id ); ?> .abtw-head{align-items:flex-start;flex-direction:column}#<?php echo esc_attr( $widget_id ); ?> .abtw-note{text-align:left}#<?php echo esc_attr( $widget_id ); ?> .abtw-grid,#<?php echo esc_attr( $widget_id ); ?> .abtw-skeleton{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:560px){#<?php echo esc_attr( $widget_id ); ?> .abtw-frame{padding:24px 16px 26px}#<?php echo esc_attr( $widget_id ); ?> .abtw-tabs{display:grid;grid-template-columns:1fr 1fr}#<?php echo esc_attr( $widget_id ); ?> .abtw-tab{justify-content:center;padding:10px 12px;font-size:13px}#<?php echo esc_attr( $widget_id ); ?> .abtw-grid,#<?php echo esc_attr( $widget_id ); ?> .abtw-skeleton{grid-template-columns:1fr}#<?php echo esc_attr( $widget_id ); ?> .abtw-card-title{min-height:0}#<?php echo esc_attr( $widget_id ); ?> .abtw-modal{padding:10px}#<?php echo esc_attr( $widget_id ); ?> .abtw-modal-dialog{width:calc(100vw - 20px);max-height:92vh;border-radius:22px}#<?php echo esc_attr( $widget_id ); ?> .abtw-modal-close{width:50px;height:50px;margin:12px 12px 0 0;font-size:28px}#<?php echo esc_attr( $widget_id ); ?> .abtw-modal-body{padding:14px 14px 22px}#<?php echo esc_attr( $widget_id ); ?> .abtw-modal-card,#<?php echo esc_attr( $widget_id ); ?> .abtw-side-price{padding:18px}#<?php echo esc_attr( $widget_id ); ?> .abtw-modal-photo--main{min-height:240px}#<?php echo esc_attr( $widget_id ); ?> .abtw-modal-thumbs{display:flex;grid-template-columns:none;overflow-x:auto;padding-bottom:2px}#<?php echo esc_attr( $widget_id ); ?> .abtw-modal-thumb{min-width:86px;height:76px}#<?php echo esc_attr( $widget_id ); ?> .abtw-gallery-nav{width:42px;height:42px;font-size:30px}#<?php echo esc_attr( $widget_id ); ?> .abtw-gallery-prev{left:12px}#<?php echo esc_attr( $widget_id ); ?> .abtw-gallery-next{right:12px}#<?php echo esc_attr( $widget_id ); ?> .abtw-open-photo{right:12px;bottom:12px;min-height:34px;padding:8px 11px;font-size:12px}#<?php echo esc_attr( $widget_id ); ?> .abtw-price-big{font-size:32px}}
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
<div class="abtw-modal" data-abtw-modal hidden>
    <div class="abtw-modal-dialog" role="dialog" aria-modal="true" aria-label="Деталі автобусного туру">
        <button type="button" class="abtw-modal-close" data-abtw-modal-close aria-label="Закрити">×</button>
        <div class="abtw-modal-body" data-abtw-modal-body></div>
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
    var offerStore = new Map();
    var tabsEl = root.querySelector('[data-abtw-tabs]');
    var panelEl = root.querySelector('[data-abtw-panel]');
    var modalEl = root.querySelector('[data-abtw-modal]');
    var modalBody = root.querySelector('[data-abtw-modal-body]');
    var bodyOverflowBeforeModal = '';

    function esc(value){ var node=document.createElement('div'); node.textContent=value==null?'':String(value); return node.innerHTML; }
    function escAttr(value){ return String(value==null?'':value).replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;'); }
    function formatMoneyUAH(value){ if(value==null||Number.isNaN(Number(value))) return '—'; return new Intl.NumberFormat('uk-UA').format(Math.round(Number(value))) + ' грн'; }
    function fixMediaUrl(value){ if(!value||typeof value!=='string') return ''; var url=value.trim(); if(url.indexOf('//')===0) url='https:'+url; if(url.indexOf('http://')===0) url='https://'+url.slice(7); if(!/^https?:\/\//i.test(url)) url=IMG_BASE.replace(/\/$/,'')+'/'+url.replace(/^\//,''); return url; }
    function formatApiDate(date){ var dd=String(date.getDate()).padStart(2,'0'); var mm=String(date.getMonth()+1).padStart(2,'0'); var yy=String(date.getFullYear()).slice(-2); return dd+'.'+mm+'.'+yy; }
    function formatHumanDate(value){ if(!value) return '—'; if(/^\d{2}\.\d{2}\.\d{2}$/.test(value)){ var p=value.split('.'); return p[0]+'.'+p[1]+'.20'+p[2]; } if(/^\d{4}-\d{2}-\d{2}$/.test(value)){ var y=value.split('-'); return y[2]+'.'+y[1]+'.'+y[0]; } return String(value); }
    function nightsLabel(value){ var n=parseInt(value,10)||0; if(!n) return ''; var mod10=n%10, mod100=n%100; var word='ночей'; if(mod10===1&&mod100!==11) word='ніч'; else if(mod10>=2&&mod10<=4&&(mod100<12||mod100>14)) word='ночі'; return n+' '+word; }
    function apiError(data){ return data&&typeof data==='object'&&!Array.isArray(data)&&data.error ? (data.error_desc||data.error) : ''; }
    function stripHtml(value){ var node=document.createElement('div'); node.innerHTML=value==null?'':String(value); node.querySelectorAll('script,style,noscript').forEach(function(el){ el.remove(); }); return (node.textContent||'').replace(/\s+/g,' ').trim(); }
    function hasHtml(value){ return /<\/?[a-z][\s\S]*>/i.test(String(value || '')); }
    function sanitizeProgramHtml(value){
        var node = document.createElement('div');
        node.innerHTML = value == null ? '' : String(value);
        node.querySelectorAll('script,style,noscript,iframe,object,embed,link,meta').forEach(function(el){ el.remove(); });
        node.querySelectorAll('*').forEach(function(el){
            Array.prototype.slice.call(el.attributes || []).forEach(function(attr){
                var name = String(attr.name || '').toLowerCase();
                var val = String(attr.value || '');
                if(name.indexOf('on') === 0 || name === 'style' || name === 'class' || name === 'id') el.removeAttribute(attr.name);
                if((name === 'href' || name === 'src') && /^\s*javascript:/i.test(val)) el.removeAttribute(attr.name);
            });
        });
        return node.innerHTML.trim();
    }
    function programTextHtml(value){
        var text = String(value || '').replace(/\r/g, '\n').trim();
        if(!text) return '';
        var chunks = text.split(/\n{2,}/).map(function(part){ return part.trim(); }).filter(Boolean);
        if(chunks.length < 2) chunks = text.split(/(?=\b\d+\s*день\b)/i).map(function(part){ return part.trim(); }).filter(Boolean);
        if(!chunks.length) chunks = [text];
        return '<div class="abtw-program-html">' + chunks.map(function(part){ return '<p>'+esc(part)+'</p>'; }).join('') + '</div>';
    }
    function parseDateValue(value){
        var text = String(value || '').trim();
        var match = text.match(/^(\d{4})-(\d{2})-(\d{2})$/);
        if(match) return new Date(Number(match[1]), Number(match[2])-1, Number(match[3]), 12, 0, 0, 0);
        match = text.match(/^(\d{2})\.(\d{2})\.(\d{2})$/);
        if(match) return new Date(2000 + Number(match[3]), Number(match[2])-1, Number(match[1]), 12, 0, 0, 0);
        return null;
    }
    function addDays(date, days){ var next = new Date(date.getTime()); next.setDate(next.getDate()+days); return next; }

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

    function namesFromList(list){
        if(!Array.isArray(list)) return [];
        return list.map(function(item){
            if(typeof item === 'string') return item.trim();
            if(item && typeof item === 'object') return String(item.name || item.title || item.city || item.country || '').trim();
            return '';
        }).filter(Boolean);
    }

    function pushImage(images, seen, raw){
        var url = '';
        if(typeof raw === 'string') {
            url = fixMediaUrl(raw);
        } else if(raw && typeof raw === 'object') {
            url = fixMediaUrl(raw.full || raw.web || raw.url || raw.src || raw.image || raw.photo || raw.large || raw.thumb || '');
        }
        if(url && !seen.has(url)){
            seen.add(url);
            images.push(url);
        }
    }

    function modalImages(offer, info){
        var images = [];
        var seen = new Set();
        pushImage(images, seen, imageFromOffer(offer));
        ['country_images','hotel_images','images','photos','gallery'].forEach(function(key){
            (Array.isArray(offer && offer[key]) ? offer[key] : []).forEach(function(item){ pushImage(images, seen, item); });
            (Array.isArray(info && info[key]) ? info[key] : []).forEach(function(item){ pushImage(images, seen, item); });
        });
        (Array.isArray(info && info.hikes) ? info.hikes : []).forEach(function(item){ pushImage(images, seen, item && (item.image || item.img || item.thumb || item.photo)); });
        (Array.isArray(info && info.day_detail) ? info.day_detail : []).forEach(function(item){ pushImage(images, seen, item && (item.image || item.img || item.thumb || item.photo)); });
        (Array.isArray(info && info.hotels) ? info.hotels : []).forEach(function(hotel){
            ['images','hotel_images','photos'].forEach(function(key){
                (Array.isArray(hotel && hotel[key]) ? hotel[key] : []).forEach(function(item){ pushImage(images, seen, item); });
            });
        });
        return images.slice(0, 8);
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
            items_per_page: '60',
            country_image_count: '5'
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

    function detailQueryForOffer(offer){
        var start = parseDateValue(offer && offer.date_from) || new Date();
        start.setHours(12,0,0,0);
        return {
            date_from: formatApiDate(start),
            date_till: formatApiDate(addDays(start, 30)),
            hikes: 'true',
            includes: 'true',
            desc: 'true',
            hotels: 'true'
        };
    }

    function fetchOfferDetail(offer){
        var key = String((offer && offer.key) || '').trim();
        if(!key) return Promise.reject(new Error('Немає ключа туру'));
        return api('tour-excursion/info/' + encodeURIComponent(key), detailQueryForOffer(offer));
    }

    function modalDateRows(info){
        var rows = [];
        (Array.isArray(info && info.dates) ? info.dates : []).forEach(function(row){
            var prices = Array.isArray(row && row.prices) ? row.prices : [];
            prices.forEach(function(priceRow){
                rows.push({
                    date: row.date_from || row.date || '',
                    price: priceUAH({prices: priceRow && priceRow.prices}),
                    priceId: priceRow && priceRow.price_id,
                    accomodationId: priceRow && priceRow.accomodation_id
                });
            });
        });
        return rows.filter(function(row){ return row.date || row.price; }).slice(0, 8);
    }

    function modalState(offer, info){
        var countries = namesFromList(info && info.countries);
        if(!countries.length && Array.isArray(offer && offer.country_names)) countries = offer.country_names;
        var cities = namesFromList(info && info.cities);
        if(!cities.length && Array.isArray(offer && offer.city_names)) cities = offer.city_names;
        var dateRows = modalDateRows(info);
        var firstRowPrice = dateRows.length ? dateRows[0].price : 0;
        return {
            name: String((info && info.name) || (offer && offer.name) || 'Автобусний тур'),
            countries: countries.filter(Boolean),
            cities: cities.filter(Boolean),
            date: formatHumanDate((offer && offer.date_from) || (dateRows[0] && dateRows[0].date) || ''),
            duration: nightsLabel((info && info.duration) || (offer && offer.duration) || 0),
            fromCity: String((info && info.from_city) || (offer && offer.from_city) || '').trim(),
            transport: String((info && info.transport_type) || (offer && offer.transport_type) || 'Автобус').trim(),
            meal: String((info && (info.meal_type_full || info.meal_type)) || (offer && (offer.meal_type_full || offer.meal_type)) || '').trim(),
            price: priceUAH(offer) || firstRowPrice,
            nightMoves: parseInt((info && info.night_moves) || (offer && offer.night_moves) || 0, 10) || 0,
            images: modalImages(offer, info),
            dateRows: dateRows
        };
    }

    function modalGalleryHtml(state){
        if(!state.images.length){
            return '<div class="abtw-modal-hero"><div class="abtw-modal-photo abtw-modal-photo--main"></div></div>';
        }
        var main = state.images[0];
        var thumbs = state.images.map(function(url, index){
            return '<button type="button" class="abtw-modal-thumb'+(index === 0 ? ' is-active' : '')+'" data-abtw-gallery-thumb data-index="'+escAttr(index)+'" data-src="'+escAttr(url)+'" data-alt="'+escAttr(state.name)+'"><img src="'+escAttr(url)+'" alt="" loading="lazy"></button>';
        }).join('');
        return '<div class="abtw-modal-hero">' +
            '<div class="abtw-modal-gallery" data-abtw-gallery data-index="0">' +
                '<div class="abtw-modal-photo abtw-modal-photo--main">' +
                    (state.images.length > 1 ? '<button type="button" class="abtw-gallery-nav abtw-gallery-prev" data-abtw-gallery-prev aria-label="Попереднє фото">‹</button>' : '') +
                    '<img data-abtw-gallery-main src="'+escAttr(main)+'" alt="'+escAttr(state.name)+'" loading="eager">' +
                    '<a class="abtw-open-photo" data-abtw-gallery-open href="'+escAttr(main)+'" target="_blank" rel="noopener">Відкрити фото</a>' +
                    (state.images.length > 1 ? '<button type="button" class="abtw-gallery-nav abtw-gallery-next" data-abtw-gallery-next aria-label="Наступне фото">›</button>' : '') +
                '</div>' +
                (state.images.length > 1 ? '<div class="abtw-modal-thumbs">'+thumbs+'</div>' : '') +
            '</div>' +
        '</div>';
    }

    function setGalleryIndex(gallery, index){
        if(!gallery) return;
        var thumbs = Array.prototype.slice.call(gallery.querySelectorAll('[data-abtw-gallery-thumb]'));
        if(!thumbs.length) return;
        if(index < 0) index = thumbs.length - 1;
        if(index >= thumbs.length) index = 0;
        var thumb = thumbs[index];
        var url = thumb ? thumb.getAttribute('data-src') : '';
        var alt = thumb ? thumb.getAttribute('data-alt') : '';
        var main = gallery.querySelector('[data-abtw-gallery-main]');
        var open = gallery.querySelector('[data-abtw-gallery-open]');
        if(main && url){
            main.src = url;
            main.alt = alt || main.alt || '';
        }
        if(open && url) open.href = url;
        thumbs.forEach(function(item, itemIndex){ item.classList.toggle('is-active', itemIndex === index); });
        gallery.setAttribute('data-index', String(index));
    }

    function modalRouteHtml(state){
        var cities = state.cities.slice(0, 12);
        if(!cities.length) return '<p class="abtw-modal-note">Маршрут уточнюється оператором.</p>';
        return '<div class="abtw-route-list">' + cities.map(function(city, index){
            return '<span class="abtw-route-step"><span>'+esc(index+1)+'</span>'+esc(city)+'</span>';
        }).join('') + '</div>';
    }

    function modalProgramHtml(info, loading, error){
        if(loading) return '<div class="abtw-modal-loading">Підтягуємо програму туру, доступні дати та умови…</div>';
        if(error) return '<p class="abtw-modal-note abtw-error">'+esc(error)+'</p>';
        var days = Array.isArray(info && info.day_detail) ? info.day_detail : [];
        if(days.length){
            return '<div class="abtw-program-html">' + days.map(function(day, index){
                var title = String((day && (day.title || day.name)) || ('День ' + (index + 1)));
                var raw = day && (day.description || day.descriptionHtml || day.description_html || day.text || day.program || '');
                var body = hasHtml(raw) ? sanitizeProgramHtml(raw) : programTextHtml(raw).replace(/^<div class="abtw-program-html">|<\/div>$/g, '');
                return '<div class="abtw-program-day"><strong class="abtw-program-day-title">'+esc(title)+'</strong><div>'+ (body || '<p>Опис дня уточнюється.</p>') +'</div></div>';
            }).join('') + '</div>';
        }
        var rawDesc = info && (info.description || info.description_html || info.desc || '');
        if(rawDesc){
            if(hasHtml(rawDesc)){
                var html = sanitizeProgramHtml(rawDesc);
                if(html) return '<div class="abtw-program-html">'+html+'</div>';
            }
            var textHtml = programTextHtml(rawDesc);
            if(textHtml) return textHtml;
        }
        return '<p class="abtw-modal-note">Детальна програма не передана в API для цього туру. Менеджер уточнить її перед бронюванням.</p>';
    }

    function modalDatesHtml(state){
        if(!state.dateRows.length) return '';
        return '<section class="abtw-modal-card"><h3>Доступні дати</h3><div class="abtw-dates-wrap"><table class="abtw-dates"><thead><tr><th>Дата</th><th>Ціна</th><th>ID ціни</th></tr></thead><tbody>' +
            state.dateRows.map(function(row){
                return '<tr><td>'+esc(formatHumanDate(row.date))+'</td><td><strong>'+esc(row.price ? formatMoneyUAH(row.price) : 'Уточнюємо')+'</strong></td><td>'+esc(row.priceId || '—')+'</td></tr>';
            }).join('') +
        '</tbody></table></div></section>';
    }

    function renderModalContent(offer, info, options){
        options = options || {};
        var state = modalState(offer, info || {});
        var fallbackHref = options.fallbackHref || buildDetailUrl(offer);
        var routeLine = state.countries.concat(state.cities.slice(0, 3)).filter(Boolean).join(' · ');
        var facts = [
            ['Дата', state.date || 'Уточнюється'],
            ['Тривалість', state.duration || 'Уточнюється'],
            ['Виїзд', state.fromCity && state.fromCity !== '.' ? state.fromCity : 'Уточнюється'],
            ['Транспорт', state.transport || 'Автобус'],
            ['Харчування', state.meal || 'За програмою'],
            ['Нічні переїзди', state.nightMoves > 0 ? String(state.nightMoves) : 'Немає']
        ];
        return '<div class="abtw-modal-grid">' +
            '<div class="abtw-modal-main">' +
                modalGalleryHtml(state) +
                '<section class="abtw-modal-card">' +
                    '<h2 class="abtw-modal-title">'+esc(state.name)+'</h2>' +
                    (routeLine ? '<p class="abtw-modal-route">'+esc(routeLine)+'</p>' : '') +
                    '<div class="abtw-modal-tags">' +
                        '<span class="abtw-modal-tag">Автобус</span>' +
                        (state.duration ? '<span class="abtw-modal-tag">'+esc(state.duration)+'</span>' : '') +
                        (state.fromCity && state.fromCity !== '.' ? '<span class="abtw-modal-tag">Виїзд з '+esc(state.fromCity)+'</span>' : '') +
                        (state.date && state.date !== '—' ? '<span class="abtw-modal-tag">'+esc(state.date)+'</span>' : '') +
                    '</div>' +
                '</section>' +
                '<section class="abtw-modal-card"><h3>Маршрут</h3>'+modalRouteHtml(state)+'</section>' +
                '<section class="abtw-modal-card"><h3>Програма туру</h3>'+modalProgramHtml(info || {}, options.loading, options.error || '')+'</section>' +
                modalDatesHtml(state) +
            '</div>' +
            '<aside class="abtw-modal-side">' +
                '<section class="abtw-side-price">' +
                    '<p class="abtw-price-hint">Ціна за 2 дорослих</p>' +
                    '<p class="abtw-price-big">'+esc(state.price ? formatMoneyUAH(state.price) : 'Уточнюємо')+'</p>' +
                    '<p class="abtw-price-caption">За весь тур. Актуальність перевіряємо перед бронюванням.</p>' +
                    '<a class="abtw-modal-primary" href="'+escAttr(fallbackHref)+'">Залишити заявку</a>' +
                '</section>' +
                '<section class="abtw-modal-card"><h3>Коротко</h3><div class="abtw-facts">' +
                    facts.map(function(item){ return '<div class="abtw-fact"><small>'+esc(item[0])+'</small><strong>'+esc(item[1])+'</strong></div>'; }).join('') +
                '</div></section>' +
            '</aside>' +
        '</div>';
    }

    function openModal(){
        if(!modalEl || !modalBody) return false;
        bodyOverflowBeforeModal = document.body.style.overflow || '';
        document.body.style.overflow = 'hidden';
        modalEl.hidden = false;
        modalEl.classList.add('is-open');
        return true;
    }

    function closeModal(){
        if(!modalEl) return;
        modalEl.classList.remove('is-open');
        modalEl.hidden = true;
        document.body.style.overflow = bodyOverflowBeforeModal;
    }

    async function openOfferModal(offer, fallbackHref){
        if(!openModal()){
            window.location.href = fallbackHref;
            return;
        }
        modalBody.innerHTML = renderModalContent(offer, null, {loading:true, fallbackHref:fallbackHref});
        try{
            var info = await fetchOfferDetail(offer);
            modalBody.innerHTML = renderModalContent(offer, info || {}, {fallbackHref:fallbackHref});
        }catch(error){
            modalBody.innerHTML = renderModalContent(offer, null, {
                fallbackHref:fallbackHref,
                error: (error && error.message) ? error.message : 'Не вдалося підтягнути детальну програму.'
            });
        }
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
            var key = String(offer.key || signature(offer));
            offerStore.set(key, offer);
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
                '<a class="abtw-action" href="'+escAttr(buildDetailUrl(offer))+'" data-abtw-offer-key="'+escAttr(key)+'">Переглянути деталі</a>' +
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
        panelEl.addEventListener('click', function(event){
            var link = event.target && event.target.closest ? event.target.closest('.abtw-action') : null;
            if(!link || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
            var offer = offerStore.get(link.getAttribute('data-abtw-offer-key') || '');
            if(!offer) return;
            event.preventDefault();
            openOfferModal(offer, link.href);
        });
        if(modalEl){
            modalEl.addEventListener('click', function(event){
                var target = event.target;
                var thumb = target && target.closest ? target.closest('[data-abtw-gallery-thumb]') : null;
                if(thumb){
                    event.preventDefault();
                    setGalleryIndex(thumb.closest('[data-abtw-gallery]'), parseInt(thumb.getAttribute('data-index'), 10) || 0);
                    return;
                }
                var prev = target && target.closest ? target.closest('[data-abtw-gallery-prev]') : null;
                var next = target && target.closest ? target.closest('[data-abtw-gallery-next]') : null;
                if(prev || next){
                    event.preventDefault();
                    var gallery = (prev || next).closest('[data-abtw-gallery]');
                    var current = parseInt(gallery && gallery.getAttribute('data-index'), 10) || 0;
                    setGalleryIndex(gallery, current + (next ? 1 : -1));
                    return;
                }
                var close = event.target && event.target.closest ? event.target.closest('[data-abtw-modal-close]') : null;
                if(close || event.target === modalEl){
                    event.preventDefault();
                    closeModal();
                }
            });
            document.addEventListener('keydown', function(event){
                if(event.key === 'Escape' && modalEl && !modalEl.hidden) closeModal();
            });
        }
        if(TABS[0]) activateTab(TABS[0]);
    }

    init();
})();
</script>
</section>
