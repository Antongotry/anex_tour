<?php
/**
 * Admin: hotel sync UI + AJAX.
 *
 * @package AnexTour
 */

defined( 'ABSPATH' ) || exit;

require_once ANEX_PLUGIN_DIR . 'includes/sync/class-anex-sync-log.php';
require_once ANEX_PLUGIN_DIR . 'includes/sync/class-anex-sync-hotels.php';

add_action( 'admin_menu', 'anex_hotel_sync_admin_menu', 25 );

function anex_hotel_sync_admin_menu(): void {
	add_submenu_page(
		'anex-tour',
		'Синхронізація готелів',
		'Синхронізація готелів',
		'manage_options',
		'anex-hotel-sync',
		'anex_hotel_sync_admin_page'
	);
}

add_action( 'wp_ajax_anex_hotel_sync_start', 'anex_ajax_hotel_sync_start' );
add_action( 'wp_ajax_anex_hotel_sync_step', 'anex_ajax_hotel_sync_step' );
add_action( 'wp_ajax_anex_hotel_sync_status', 'anex_ajax_hotel_sync_status' );
add_action( 'wp_ajax_anex_hotel_sync_photos', 'anex_ajax_hotel_sync_photos' );
add_action( 'wp_ajax_anex_hotel_sync_stats', 'anex_ajax_hotel_sync_stats' );

function anex_ajax_hotel_sync_start(): void {
	check_ajax_referer( 'anex_hotel_sync', 'nonce' );
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( [ 'message' => 'Недостатньо прав.' ] );
	}
	$ids   = anex_hotel_sync_country_ids();
	$state = Anex_Sync_Log::reset_for_run( $ids );
	wp_send_json_success( [ 'state' => $state ] );
}

function anex_ajax_hotel_sync_step(): void {
	check_ajax_referer( 'anex_hotel_sync', 'nonce' );
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( [ 'message' => 'Недостатньо прав.' ] );
	}
	$state = Anex_Sync_Hotels::process_next_country();
	wp_send_json_success( [ 'state' => $state ] );
}

function anex_ajax_hotel_sync_status(): void {
	check_ajax_referer( 'anex_hotel_sync', 'nonce' );
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( [ 'message' => 'Недостатньо прав.' ] );
	}
	wp_send_json_success( [ 'state' => Anex_Sync_Log::get_state() ] );
}

function anex_ajax_hotel_sync_photos(): void {
	check_ajax_referer( 'anex_hotel_sync', 'nonce' );
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( [ 'message' => 'Недостатньо прав.' ] );
	}
	$limit  = isset( $_POST['limit'] ) ? max( 1, min( 30, (int) $_POST['limit'] ) ) : 15;
	$result = Anex_Sync_Hotels::sync_photos_batch( $limit );
	wp_send_json_success( $result );
}

function anex_ajax_hotel_sync_stats(): void {
	check_ajax_referer( 'anex_hotel_sync', 'nonce' );
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( [ 'message' => 'Недостатньо прав.' ] );
	}
	wp_send_json_success( Anex_Sync_Hotels::get_hotel_stats() );
}

function anex_hotel_sync_admin_page(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	if ( isset( $_POST['anex_save_hotel_countries'] ) ) {
		check_admin_referer( 'anex_hotel_sync_settings' );
		$raw = isset( $_POST['anex_country_ids'] ) ? wp_unslash( $_POST['anex_country_ids'] ) : '';
		$ids = array_filter( array_map( 'intval', preg_split( '/[\s,;]+/', (string) $raw, -1, PREG_SPLIT_NO_EMPTY ) ) );
		update_option( ANEX_HOTEL_COUNTRIES_OPT, $ids ?: anex_hotel_default_country_ids() );
		update_option( ANEX_HOTEL_SYNC_PHOTOS_OPT, ! empty( $_POST['anex_sync_with_photos'] ) ? 1 : 0 );
		echo '<div class="notice notice-success is-dismissible"><p>Збережено налаштування sync.</p></div>';
	}

	$state        = Anex_Sync_Log::get_state();
	$stats        = Anex_Sync_Hotels::get_hotel_stats();
	$ids          = anex_hotel_sync_country_ids();
	$nonce        = wp_create_nonce( 'anex_hotel_sync' );
	$default      = implode( ', ', anex_hotel_default_country_ids() );
	$sync_photos  = anex_hotel_sync_photos_enabled();
	?>
	<div class="wrap">
		<h1>Синхронізація готелів</h1>
		<p>Картки: <a href="<?php echo esc_url( admin_url( 'edit.php?post_type=' . ANEX_HOTEL_POST_TYPE ) ); ?>"><strong>Anex Tour → Готелі</strong></a> — колонка «Прев’ю» показує <strong>завантажене</strong> фото (після кнопки нижче).</p>
		<p class="description">Якщо в sync мало готелів (3–11 на країну) — це ліміт одного search-list на країну, не баг. Фото: спочатку з офера, інакше <code>hotel/{id}/hotel-images</code>.</p>

		<table class="widefat" style="max-width:720px;margin:1em 0">
			<tbody>
				<tr><th>Всього готелів у WP</th><td id="anex-stats-total"><strong><?php echo (int) $stats['total']; ?></strong></td></tr>
				<tr><th>З URL прев’ю (meta)</th><td id="anex-stats-url"><?php echo (int) $stats['with_thumb_url']; ?></td></tr>
				<tr><th>З фото в медіатеці</th><td id="anex-stats-featured"><?php echo (int) $stats['with_featured']; ?></td></tr>
				<tr><th>Чекають завантаження фото</th><td id="anex-stats-pending"><strong><?php echo (int) $stats['pending_photos']; ?></strong></td></tr>
			</tbody>
		</table>

		<form method="post" style="max-width:720px;margin:1em 0;padding:12px;background:#fff;border:1px solid #c3c4c7">
			<?php wp_nonce_field( 'anex_hotel_sync_settings' ); ?>
			<h2>Whitelist країн (IT-Tour country_id)</h2>
			<p><input type="text" class="large-text" id="anex_country_ids" name="anex_country_ids" value="<?php echo esc_attr( implode( ', ', $ids ) ); ?>" /></p>
			<p class="description">За замовчуванням: <?php echo esc_html( $default ); ?></p>
			<p>
				<label>
					<input type="checkbox" name="anex_sync_with_photos" value="1" <?php checked( $sync_photos ); ?> />
					Завантажувати фото під час sync (до 15 на країну за крок; решта — кнопкою нижче)
				</label>
			</p>
			<p><button type="submit" name="anex_save_hotel_countries" class="button">Зберегти</button></p>
		</form>

		<p>
			<button type="button" class="button button-primary" id="anex-sync-start">Запустити sync готелів</button>
			<button type="button" class="button" id="anex-sync-photos">Завантажити всі фото</button>
			<span id="anex-sync-spinner" class="spinner" style="float:none"></span>
		</p>

		<table class="widefat" style="max-width:720px">
			<tbody>
				<tr><th>Статус</th><td id="anex-st-status"><?php echo esc_html( (string) ( $state['status'] ?? 'idle' ) ); ?></td></tr>
				<tr><th>Поточна країна</th><td id="anex-st-country"><?php echo esc_html( (string) ( $state['current_country'] ?? '' ) ); ?></td></tr>
				<tr><th>Створено / оновлено</th><td id="anex-st-counts"><?php echo esc_html( (int) ( $state['created'] ?? 0 ) . ' / ' . (int) ( $state['updated'] ?? 0 ) ); ?></td></tr>
				<tr><th>API викликів / помилок</th><td id="anex-st-api"><?php echo esc_html( (int) ( $state['api_calls'] ?? 0 ) . ' / ' . (int) ( $state['api_errors'] ?? 0 ) ); ?></td></tr>
				<tr><th>Остання помилка</th><td id="anex-st-error"><?php echo esc_html( (string) ( $state['last_error'] ?? '' ) ); ?></td></tr>
			</tbody>
		</table>

		<h2>Лог</h2>
		<pre id="anex-sync-log" style="max-width:900px;max-height:360px;overflow:auto;background:#1e1e1e;color:#d4d4d4;padding:12px;font-size:12px;line-height:1.5"><?php
			$log = $state['log'] ?? [];
			if ( is_array( $log ) ) {
				echo esc_html( implode( "\n", $log ) );
			}
		?></pre>
	</div>
	<script>
	(function() {
		const nonce = <?php echo wp_json_encode( $nonce ); ?>;
		const ajaxUrl = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
		const spinner = document.getElementById('anex-sync-spinner');
		const logEl = document.getElementById('anex-sync-log');
		let running = false;

		function post(action, extra) {
			const body = new URLSearchParams({ action, nonce, ...(extra || {}) });
			return fetch(ajaxUrl, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body, credentials: 'same-origin' })
				.then(r => r.json());
		}

		function renderState(st) {
			if (!st) return;
			document.getElementById('anex-st-status').textContent = st.status || '';
			document.getElementById('anex-st-country').textContent = st.current_country || '';
			document.getElementById('anex-st-counts').textContent = (st.created|0) + ' / ' + (st.updated|0);
			document.getElementById('anex-st-api').textContent = (st.api_calls|0) + ' / ' + (st.api_errors|0);
			document.getElementById('anex-st-error').textContent = st.last_error || '';
			if (Array.isArray(st.log)) logEl.textContent = st.log.join('\n');
		}

		function renderStats(s) {
			if (!s) return;
			document.getElementById('anex-stats-total').innerHTML = '<strong>' + (s.total|0) + '</strong>';
			document.getElementById('anex-stats-url').textContent = s.with_thumb_url|0;
			document.getElementById('anex-stats-featured').textContent = s.with_featured|0;
			document.getElementById('anex-stats-pending').innerHTML = '<strong>' + (s.pending_photos|0) + '</strong>';
		}

		async function refreshStats() {
			const res = await post('anex_hotel_sync_stats');
			if (res.success) renderStats(res.data);
		}

		async function runAllPhotos() {
			let remaining = 1;
			let total = 0;
			while (remaining > 0) {
				const res = await post('anex_hotel_sync_photos', { limit: '15' });
				if (!res.success) {
					alert(res.data?.message || 'Помилка фото');
					break;
				}
				total += res.data.processed|0;
				remaining = res.data.remaining|0;
				await refreshStats();
				if (remaining > 0) await new Promise(r => setTimeout(r, 800));
			}
			alert('Фото готово. Завантажено за сесію: ' + total + '. Залишилось без фото: ' + remaining);
		}

		async function runSteps() {
			if (running) return;
			running = true;
			spinner.classList.add('is-active');
			try {
				let st = (await post('anex_hotel_sync_start')).data?.state;
				renderState(st);
				while (st && st.status === 'running') {
					await new Promise(r => setTimeout(r, 2000));
					const res = await post('anex_hotel_sync_step');
					if (!res.success) {
						alert(res.data?.message || 'Помилка кроку sync');
						break;
					}
					st = res.data.state;
					renderState(st);
					await refreshStats();
				}
				await refreshStats();
				if (st && st.status === 'completed') {
					const pending = parseInt(document.getElementById('anex-stats-pending').textContent, 10) || 0;
					if (pending > 0 && confirm('Sync завершено. Завантажити ' + pending + ' фото в медіатеку?')) {
						await runAllPhotos();
					}
				}
			} finally {
				running = false;
				spinner.classList.remove('is-active');
			}
		}

		document.getElementById('anex-sync-start').addEventListener('click', runSteps);
		document.getElementById('anex-sync-photos').addEventListener('click', async function() {
			if (running) return;
			running = true;
			spinner.classList.add('is-active');
			try {
				await runAllPhotos();
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
