<?php
/**
 * Sync state + log for tour (excursion) import.
 *
 * @package AnexTour
 */

defined( 'ABSPATH' ) || exit;

class Anex_Tour_Sync_Log {

	public static function get_state(): array {
		$state = get_option( ANEX_TOUR_SYNC_OPTION, [] );
		if ( ! is_array( $state ) ) {
			$state = [];
		}
		return wp_parse_args(
			$state,
			[
				'status'          => 'idle',
				'started_at'      => '',
				'finished_at'     => '',
				'country_ids'     => [],
				'country_index'   => 0,
				'current_country' => '',
				'created'         => 0,
				'updated'         => 0,
				'api_calls'       => 0,
				'api_errors'      => 0,
				'last_error'      => '',
				'log'             => [],
			]
		);
	}

	public static function save_state( array $state ): void {
		$current = self::get_state();
		if ( ! isset( $state['log'] ) || ! is_array( $state['log'] ) || $state['log'] === [] ) {
			$state['log'] = $current['log'];
		}
		if ( count( $state['log'] ) > 80 ) {
			$state['log'] = array_slice( $state['log'], -80 );
		}
		update_option( ANEX_TOUR_SYNC_OPTION, $state, false );
	}

	/**
	 * @param array<string, mixed> $patch
	 */
	public static function patch( array $patch ): array {
		$state = self::get_state();
		foreach ( $patch as $key => $value ) {
			$state[ $key ] = $value;
		}
		self::save_state( $state );
		return $state;
	}

	public static function append( string $line ): void {
		$state          = self::get_state();
		$state['log'][] = '[' . gmdate( 'H:i:s' ) . '] ' . $line;
		self::save_state( $state );
	}

	public static function reset_for_run( array $country_ids ): array {
		$state = [
			'status'          => 'running',
			'started_at'      => current_time( 'mysql' ),
			'finished_at'     => '',
			'country_ids'     => array_values( array_map( 'intval', $country_ids ) ),
			'country_index'   => 0,
			'current_country' => '',
			'created'         => 0,
			'updated'         => 0,
			'api_calls'       => 0,
			'api_errors'      => 0,
			'last_error'      => '',
			'log'             => [],
		];
		self::save_state( $state );
		self::append( 'Старт sync турів. Країн: ' . count( $country_ids ) );
		return self::get_state();
	}
}
