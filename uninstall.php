<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) { exit; }

/**
 * Removes the plugin's options, cron event and custom tables.
 */
function oliforge_cf7sg_uninstall() {
	delete_option( 'oliforge_cf7sg_settings' );
	delete_option( 'oliforge_cf7sg_db_version' );
	wp_clear_scheduled_hook( 'oliforge_cf7sg_daily_cleanup' );

	global $wpdb;
	foreach ( array( 'oliforge_cf7sg_logs', 'oliforge_cf7sg_rate_limits' ) as $suffix ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Dropping the plugin's own tables on uninstall.
		$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $wpdb->prefix . $suffix ) );
	}
}
oliforge_cf7sg_uninstall();
