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
        // Since WordPress 6.7, loading a textdomain before the init hook
        // can trigger a "_load_textdomain_just_in_time was called
        // incorrectly" notice — load_plugin_textdomain() is the first
        // statement in init() below, so moving the whole callback here is
        // enough to keep it clear of that without splitting the method.
        add_action( 'init', array( $this, 'init' ) );
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
     * Whether the optional OliForge CF7 Country Select integration is active.
     *
     * The filter allows compatibility with a renamed entry file without making
     * country validation run merely because similarly named form tags exist.
     */
    public static function country_select_is_active() {
        if ( defined( 'OLIFORGE_CF7_COUNTRY_SELECT_VERSION' ) || class_exists( 'OliForge_CF7_Country_Select' ) ) {
            return true;
        }

        if ( ! function_exists( 'is_plugin_active' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $plugin_file = (string) apply_filters(
            'oliforge_cf7sg_country_select_plugin_file',
            'oliforge-cf7-country-select/oliforge-cf7-country-select.php'
        );

        return '' !== $plugin_file && is_plugin_active( $plugin_file );
    }

    /**
     * Plugin releases and database schema changes have independent versions.
     */
    private function maybe_upgrade() {
        if ( get_option( 'oliforge_cf7sg_db_version' ) === OLIFORGE_CF7SG_DB_VERSION ) { return; }
        OliForge_CF7SG_Logger::install_table();
        OliForge_CF7SG_Rate_Limiter::install_table();
        update_option( 'oliforge_cf7sg_db_version', OLIFORGE_CF7SG_DB_VERSION, false );
    }

    public function cf7_missing_notice() {
        if ( ! current_user_can( 'activate_plugins' ) ) {
            return;
        }
        echo '<div class="notice notice-warning"><p>' . esc_html__( 'OliForge CF7 Submission Guard requires Contact Form 7 to be installed and active.', 'oliforge-cf7-submission-guard' ) . '</p></div>';
    }

    public static function activate() {
        OliForge_CF7SG_Logger::install_table();
        OliForge_CF7SG_Rate_Limiter::install_table();
        update_option( 'oliforge_cf7sg_db_version', OLIFORGE_CF7SG_DB_VERSION, false );
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
