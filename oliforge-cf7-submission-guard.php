<?php
/**
 * Plugin Name: OliForge CF7 Submission Guard
 * Description: Configurable server-side submission validation and lightweight logging for Contact Form 7.
 * Version: 0.3.0
 * Requires at least: 6.2
 * Requires PHP: 7.4
 * Author: OliForge
 * License: GPL-2.0-or-later
 * Text Domain: oliforge-cf7-submission-guard
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'OLIFORGE_CF7SG_VERSION', '0.3.0' );
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

register_activation_hook( __FILE__, array( 'OliForge_CF7SG_Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'OliForge_CF7SG_Plugin', 'deactivate' ) );

OliForge_CF7SG_Plugin::instance()->boot();
