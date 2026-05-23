<?php
/**
 * wp eval-file wp-content/plugins/anex-tour/includes/cli-run-tour-sync.php
 *
 * @package AnexTour
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit( 1 );
}

if ( ! class_exists( 'Anex_Sync_Tours' ) ) {
	echo "Anex tour sync not loaded.\n";
	exit( 1 );
}

Anex_Tour_Sync_Log::reset_for_run( anex_tour_sync_country_ids() );
for ( $i = 0; $i < 20; $i++ ) {
	$s = Anex_Sync_Tours::process_next_country();
	$st = (string) ( $s['status'] ?? '' );
	if ( $st === 'done' || $st === 'failed' ) {
		break;
	}
}
echo 'Tours published: ' . (int) ( wp_count_posts( ANEX_TOUR_POST_TYPE )->publish ?? 0 ) . "\n";
