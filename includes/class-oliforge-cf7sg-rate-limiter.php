<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class OliForge_CF7SG_Rate_Limiter {
    public static function table() {
        global $wpdb;
        return $wpdb->prefix . 'oliforge_cf7sg_rate_limits';
    }

    public static function install_table() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $table = self::table();
        $charset = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE {$table} (
            rate_key char(32) NOT NULL,
            window_start bigint(20) unsigned NOT NULL,
            attempt_count bigint(20) unsigned NOT NULL DEFAULT 0,
            expires_at datetime NOT NULL,
            PRIMARY KEY (rate_key, window_start),
            KEY expires_at (expires_at)
        ) {$charset};";
        dbDelta( $sql );
    }

    public static function key( $ip, $form_id ) {
        return md5( $ip . '|' . $form_id );
    }

    public static function hit( $ip, $form_id, $max, $minutes ) {
        if ( '' === $ip || $max < 1 || $minutes < 1 ) { return false; }

        global $wpdb;
        $table = self::table();
        $key = self::key( $ip, $form_id );
        $window_seconds = $minutes * MINUTE_IN_SECONDS;
        $window_start = (int) ( floor( time() / $window_seconds ) * $window_seconds );
        $expires_at = gmdate( 'Y-m-d H:i:s', $window_start + $window_seconds );

        // One atomic statement prevents parallel submissions from losing an
        // increment. The window is fixed by window_start and is never extended.
        $wpdb->query( $wpdb->prepare(
            "INSERT INTO {$table} (rate_key, window_start, attempt_count, expires_at)
             VALUES (%s, %d, 1, %s)
             ON DUPLICATE KEY UPDATE attempt_count = attempt_count + 1",
            $key,
            $window_start,
            $expires_at
        ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

        $count = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT attempt_count FROM {$table} WHERE rate_key = %s AND window_start = %d",
            $key,
            $window_start
        ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

        return $count > $max;
    }

    public static function cleanup_expired() {
        global $wpdb;
        $table = self::table();
        $wpdb->query( $wpdb->prepare(
            "DELETE FROM {$table} WHERE expires_at < %s",
            current_time( 'mysql', true )
        ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    }
}
