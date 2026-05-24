<?php
/**
 * Admin: tour (excursion) sync UI + AJAX.
 *
 * @package AnexTour
 */

defined( 'ABSPATH' ) || exit;

require_once ANEX_PLUGIN_DIR . 'includes/sync/class-anex-sync-log-tours.php';
require_once ANEX_PLUGIN_DIR . 'includes/sync/class-anex-sync-tours.php';

add_action( 'admin_menu', 'anex_tour_sync_admin_menu', 26 );

function anex_tour_sync_admin_menu(): void {
	add_submenu_page(
		'anex-tour',
		'Синхронізація турів',
		'Синхронізація турів',
		'manage_options',
		'anex-tour-sync',
		'anex_tour_sync_admin_page'
	);
}

add_action( 'wp_ajax_anex_tour_sync_start', 'anex_ajax_tour_sync_start' );
add_action( 'wp_ajax_anex_tour_sync_step', 'anex_ajax_tour_sync_step' );
add_action( 'wp_ajax_anex_tour_sync_stats', 'anex_ajax_tour_sync_stats' );

function anex_ajax_tour_sync_start(): void {
	check_ajax_referer( 'anex_tour_sync', 'nonce' );
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( [ 'message' => 'Недостатньо прав.' ] );
	}
	$ids   = anex_tour_sync_country_ids();
	$state = Anex_Tour_Sync_Log::reset_for_run( $ids );
	wp_send_json_success( [ 'state' => $state ] );
}

function anex_ajax_tour_sync_step(): void {
	check_ajax_referer( 'anex_tour_sync', 'nonce' );
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( [ 'message' => 'Недостатньо прав.' ] );
	}
	$state = Anex_Sync_Tours::process_next_country();
	wp_send_json_success( [ 'state' => $state ] );
}

function anex_ajax_tour_sync_stats(): void {
	check_ajax_referer( 'anex_tour_sync', 'nonce' );
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( [ 'message' => 'Недостатньо прав.' ] );
	}
	wp_send_json_success( Anex_Sync_Tours::get_tour_stats() );
}

function anex_tour_sync_admin_page(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	if ( isset( $_POST['anex_save_tour_countries'] ) ) {
		check_admin_referer( 'anex_tour_sync_settings' );
		$raw = isset( $_POST['anex_tour_country_ids'] ) ? wp_unslash( $_POST['anex_tour_country_ids'] ) : '';
		$ids = array_filter( array_map( 'intval', preg_split( '/[\s,;]+/', (string) $raw, -1, PREG_SPLIT_NO_EMPTY ) ) );
		update_option( ANEX_TOUR_COUNTRIES_OPT, $ids ?: anex_tour_default_country_ids() );
		update_option( 'anex_tour_sync_with_photos', ! empty( $_POST['anex_tour_sync_with_photos'] ) ? 1 : 0 );
		echo '<div class="notice notice-success is-dismissible"><p>Збережено.</p></div>';
	}

	$state       = Anex_Tour_Sync_Log::get_state();
	$stats       = Anex_Sync_Tours::get_tour_stats();
	$ids         = anex_tour_sync_country_ids();
	$nonce       = wp_create_nonce( 'anex_tour_sync' );
	$default     = implode( ', ', anex_tour_default_country_ids() );
	$sync_photos = (bool) get_option( 'anex_tour_sync_with_photos', true );
	?>
		<div class="wrap anex-admin">
			<h1>Синхронізація турів (екскурсії)</h1>
			<p class="anex-admin-lead">Окрема панель для екскурсійних турів: країни, запуск sync, логи та коротка статистика по контенту.</p>
			<p>Картки: <a href="<?php echo esc_url( admin_url( 'edit.php?post_type=' . ANEX_TOUR_POST_TYPE ) ); ?>"><strong>Anex Tour → Тури</strong></a>.</p>
		<p class="description">
			API: <code>module-excursion/search</code> + <code>tour-excursion/info/{key}</code>.
			<strong>Це не каталог готелів:</strong> IT-Tour на вашому токені зараз віддає лише кілька екскурсійних турів на всі країни разом (типово 2–5), навіть при багатьох датах.
			Готелі — через <code>module/search-list</code> (десятки на країну), тури — окремий endpoint з малою видачею.
		</p>

			<table class="widefat anex-admin-table" style="max-width:720px;margin:1em 0">
			<tbody>
				<tr><th>Всього турів у WP</th><td id="anex-tour-stats-total"><strong><?php echo (int) $stats['total']; ?></strong></td></tr>
				<tr><th>З фото в медіатеці</th><td id="anex-tour-stats-featured"><?php echo (int) $stats['with_featured']; ?></td></tr>
				<tr><th>Без featured</th><td id="anex-tour-stats-pending"><?php echo (int) $stats['pending_photos']; ?></td></tr>
			</tbody>
		</table>

			<form method="post" class="anex-admin-form" style="max-width:720px;margin:1em 0">
			<?php wp_nonce_field( 'anex_tour_sync_settings' ); ?>
			<h2>Країни (country_id)</h2>
			<p><input type="text" class="large-text" name="anex_tour_country_ids" value="<?php echo esc_attr( implode( ', ', $ids ) ); ?>" /></p>
			<p class="description">За замовчуванням: <?php echo esc_html( $default ); ?></p>
			<p>
				<label>
					<input type="checkbox" name="anex_tour_sync_with_photos" value="1" <?php checked( $sync_photos ); ?> />
					Завантажувати фото під час sync (country_images + tour-excursion/info)
				</label>
			</p>
			<p><button type="submit" name="anex_save_tour_countries" class="button">Зберегти</button></p>
		</form>

		<p>
			<button type="button" class="button button-primary" id="anex-tour-sync-start">Запустити sync турів</button>
			<span id="anex-tour-sync-spinner" class="spinner" style="float:none"></span>
		</p>

			<table class="widefat anex-admin-table" style="max-width:720px">
			<tbody>
				<tr><th>Статус</th><td id="anex-tour-st-status"><?php echo esc_html( (string) ( $state['status'] ?? 'idle' ) ); ?></td></tr>
				<tr><th>Країна</th><td id="anex-tour-st-country"><?php echo esc_html( (string) ( $state['current_country'] ?? '' ) ); ?></td></tr>
				<tr><th>Створено / оновлено</th><td id="anex-tour-st-counts"><?php echo esc_html( (int) ( $state['created'] ?? 0 ) . ' / ' . (int) ( $state['updated'] ?? 0 ) ); ?></td></tr>
				<tr><th>API</th><td id="anex-tour-st-api"><?php echo esc_html( (int) ( $state['api_calls'] ?? 0 ) . ' / ' . (int) ( $state['api_errors'] ?? 0 ) ); ?></td></tr>
			</tbody>
		</table>

		<h2>Лог</h2>
			<pre id="anex-tour-sync-log" class="anex-admin-log" style="max-width:900px;max-height:360px;overflow:auto"><?php
			$log = $state['log'] ?? [];
			echo esc_html( is_array( $log ) ? implode( "\n", $log ) : '' );
		?></pre>
	</div>
	<script>
	(function() {
		const nonce = <?php echo wp_json_encode( $nonce ); ?>;
		const ajaxUrl = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
		const spinner = document.getElementById('anex-tour-sync-spinner');
		const logEl = document.getElementById('anex-tour-sync-log');
		let running = false;

		function post(action, extra) {
			const body = new URLSearchParams({ action, nonce, ...(extra || {}) });
			return fetch(ajaxUrl, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body, credentials: 'same-origin' }).then(r => r.json());
		}

		function renderState(st) {
			if (!st) return;
			document.getElementById('anex-tour-st-status').textContent = st.status || '';
			document.getElementById('anex-tour-st-country').textContent = st.current_country || '';
			document.getElementById('anex-tour-st-counts').textContent = (st.created|0) + ' / ' + (st.updated|0);
			document.getElementById('anex-tour-st-api').textContent = (st.api_calls|0) + ' / ' + (st.api_errors|0);
			if (Array.isArray(st.log)) logEl.textContent = st.log.join('\n');
		}

		async function refreshStats() {
			const res = await post('anex_tour_sync_stats');
			if (res.success) {
				const s = res.data;
				document.getElementById('anex-tour-stats-total').innerHTML = '<strong>' + (s.total|0) + '</strong>';
				document.getElementById('anex-tour-stats-featured').textContent = s.with_featured|0;
				document.getElementById('anex-tour-stats-pending').textContent = s.pending_photos|0;
			}
		}

		document.getElementById('anex-tour-sync-start').addEventListener('click', async function() {
			if (running) return;
			running = true;
			spinner.classList.add('is-active');
			try {
				let st = (await post('anex_tour_sync_start')).data?.state;
				renderState(st);
				while (st && st.status === 'running') {
					await new Promise(r => setTimeout(r, 2000));
					const res = await post('anex_tour_sync_step');
					if (!res.success) { alert(res.data?.message || 'Помилка'); break; }
					st = res.data.state;
					renderState(st);
					await refreshStats();
				}
				await refreshStats();
				if (st && st.status === 'done') alert('Sync турів завершено.');
			} finally {
				running = false;
				spinner.classList.remove('is-active');
			}
		});

		refreshStats();
	})();
	</script>
	<?php
}
