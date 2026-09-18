<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) { exit; }

delete_option( 'oliforge_cf7sg_settings' );
delete_option( 'oliforge_cf7sg_db_version' );
wp_clear_scheduled_hook( 'oliforge_cf7sg_daily_cleanup' );

global $wpdb;
$table = $wpdb->prefix . 'oliforge_cf7sg_logs';
$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$rate_table = $wpdb->prefix . 'oliforge_cf7sg_rate_limits';
$wpdb->query( "DROP TABLE IF EXISTS {$rate_table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
