<?php
/**
 * Plugin Name: OliForge CF7 Submission Guard
 * Plugin URI: https://oliforge.io/plugins/oliforge-cf7-submission-guard/
 * Description: A local, transparent policy firewall for Contact Form 7. Checks every submission against the rules of its form, with no external services and no subscription.
 * Version: 0.4.4
 * Requires at least: 6.2
 * Requires PHP: 7.4
 * Author: OliForge
 * License: GPL-2.0-or-later
 * Text Domain: oliforge-cf7-submission-guard
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'OLIFORGE_CF7SG_VERSION', '0.4.4' );
define( 'OLIFORGE_CF7SG_DB_VERSION', '2.0.0' );
define( 'OLIFORGE_CF7SG_FILE', __FILE__ );
define( 'OLIFORGE_CF7SG_DIR', plugin_dir_path( __FILE__ ) );
define( 'OLIFORGE_CF7SG_URL', plugin_dir_url( __FILE__ ) );

require_once OLIFORGE_CF7SG_DIR . 'includes/class-oliforge-cf7sg-logger.php';
require_once OLIFORGE_CF7SG_DIR . 'includes/class-oliforge-cf7sg-rate-limiter.php';
require_once OLIFORGE_CF7SG_DIR . 'includes/class-oliforge-cf7sg-forms.php';
require_once OLIFORGE_CF7SG_DIR . 'includes/class-oliforge-cf7sg-validator.php';
require_once OLIFORGE_CF7SG_DIR . 'includes/class-oliforge-cf7sg-settings.php';
require_once OLIFORGE_CF7SG_DIR . 'includes/class-oliforge-cf7sg-plugin.php';

add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), static function ( $links ) {
    if ( ! defined( 'OLIFORGE_CF7SG_PRO_VERSION' ) ) {
        $links[] = '<a href="' . esc_url( 'https://oliforge.io/plugins/oliforge-cf7-submission-guard-pro/' ) . '" target="_blank" rel="noopener" style="color:#C25F0A;font-weight:600;">' . esc_html__( 'Upgrade to Pro', 'oliforge-cf7-submission-guard' ) . '</a>';
    }
    return $links;
} );

register_activation_hook( __FILE__, array( 'OliForge_CF7SG_Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'OliForge_CF7SG_Plugin', 'deactivate' ) );

OliForge_CF7SG_Plugin::instance()->boot();
