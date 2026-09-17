<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class OliForge_CF7SG_Plugin {
    private static $instance;

    public static function instance() {
        if ( ! self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function boot() {
        add_action( 'plugins_loaded', array( $this, 'init' ) );
        add_action( 'oliforge_cf7sg_daily_cleanup', array( 'OliForge_CF7SG_Logger', 'cleanup_expired' ) );
    }

    public function init() {
        load_plugin_textdomain( 'oliforge-cf7-submission-guard', false, dirname( plugin_basename( OLIFORGE_CF7SG_FILE ) ) . '/languages' );

        if ( is_admin() ) {
            $this->maybe_upgrade();
            new OliForge_CF7SG_Settings();
        }

        if ( defined( 'WPCF7_VERSION' ) ) {
            new OliForge_CF7SG_Validator();
        } elseif ( is_admin() ) {
            add_action( 'admin_notices', array( $this, 'cf7_missing_notice' ) );
        }
    }

    /**
     * dbDelta() is idempotent, so re-running it whenever OLIFORGE_CF7SG_VERSION moves on
     * is enough to add new columns for sites that already had the plugin active
     * (activate() alone only runs once, on first activation).
     */
    private function maybe_upgrade() {
        if ( get_option( 'oliforge_cf7sg_db_version' ) === OLIFORGE_CF7SG_VERSION ) { return; }
        OliForge_CF7SG_Logger::install_table();
        update_option( 'oliforge_cf7sg_db_version', OLIFORGE_CF7SG_VERSION, false );
    }

    public function cf7_missing_notice() {
        if ( ! current_user_can( 'activate_plugins' ) ) {
            return;
        }
        echo '<div class="notice notice-warning"><p>' . esc_html__( 'OliForge CF7 Submission Guard requires Contact Form 7 to be installed and active.', 'oliforge-cf7-submission-guard' ) . '</p></div>';
    }

    public static function activate() {
        OliForge_CF7SG_Logger::install_table();
        update_option( 'oliforge_cf7sg_db_version', OLIFORGE_CF7SG_VERSION, false );
        if ( ! wp_next_scheduled( 'oliforge_cf7sg_daily_cleanup' ) ) {
            wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'oliforge_cf7sg_daily_cleanup' );
        }
        if ( false === get_option( 'oliforge_cf7sg_settings', false ) ) {
            add_option( 'oliforge_cf7sg_settings', OliForge_CF7SG_Settings::defaults(), '', false );
        }
    }

    public static function deactivate() {
        wp_clear_scheduled_hook( 'oliforge_cf7sg_daily_cleanup' );
    }
}
