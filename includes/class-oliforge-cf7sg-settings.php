<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class OliForge_CF7SG_Settings {
    const OPTION = 'oliforge_cf7sg_settings';

    public function __construct() {
        add_action( 'admin_menu', array( $this, 'menu' ) );
        add_action( 'admin_init', array( $this, 'register' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
        add_action( 'admin_post_oliforge_cf7sg_clear_logs', array( $this, 'clear_logs' ) );
    }

    public static function defaults() {
        return array(
            'enabled'                 => 1,
            'mode'                    => 'enforce',
            'name_field'              => 'name',
            'name_max'                => 20,
            'message_field'           => 'message',
            'message_min'             => 30,
            'message_max'             => 0,
            'email_field'             => 'email',
            'country_field'           => 'select_country',
            'consent_field'           => '',
            'required_consent'        => 0,
            'content_fields'          => "name\nmessage",
            'block_urls'              => 1,
            'block_at'                => 1,
            'block_email_patterns'    => 1,
            'block_html'              => 1,
            'block_bbcode'            => 0,
            'max_digits_percent'      => 0,
            'max_uppercase_percent'   => 0,
            'forbidden_words'         => '',
            'blocked_domains'         => '',
            'allowed_countries'       => '',
            'rate_limit_enabled'      => 1,
            'rate_limit_count'        => 3,
            'rate_limit_minutes'      => 60,
            'min_time_enabled'        => 1,
            'min_time_seconds'        => 15,
            'duplicate_enabled'       => 0,
            'duplicate_minutes'       => 60,
            'logging_enabled'         => 1,
            'log_success'             => 0,
            'ip_storage'              => 'anonymized',
            'retention_days'          => 30,
            'error_field'             => 'name',
            'msg_name_max'            => 'Name is too long.',
            'msg_message_min'         => 'Message is too short.',
            'msg_message_max'         => 'Message is too long.',
            'msg_url'                 => 'Links are not allowed in this field.',
            'msg_at'                  => 'The @ character is not allowed in this field.',
            'msg_email_pattern'       => 'Email addresses are not allowed in this field.',
            'msg_html'                => 'HTML markup is not allowed in this field.',
            'msg_bbcode'              => 'BBCode markup is not allowed in this field.',
            'msg_forbidden'           => 'This field contains prohibited content.',
            'msg_country'             => 'Please select a valid country.',
            'msg_domain'              => 'This email domain is not allowed.',
            'msg_rate_limit'          => 'Too many submissions. Please try again later.',
            'msg_too_fast'            => 'The form was submitted too quickly. Please try again.',
            'msg_duplicate'           => 'This message was already submitted recently.',
            'msg_consent'             => 'Please accept the required consent.',
        );
    }

    public static function get() {
        return wp_parse_args( get_option( self::OPTION, array() ), self::defaults() );
    }

    public function menu() {
        add_menu_page(
            __( 'OliForge CF7 Submission Guard', 'oliforge-cf7-submission-guard' ),
            __( 'Submission Guard', 'oliforge-cf7-submission-guard' ),
            'manage_options',
            'oliforge-cf7-submission-guard',
            array( $this, 'render_dashboard' ),
            'dashicons-shield',
            80
        );
        add_submenu_page(
            'oliforge-cf7-submission-guard',
            __( 'OliForge CF7 Submission Guard Dashboard', 'oliforge-cf7-submission-guard' ),
            __( 'Dashboard', 'oliforge-cf7-submission-guard' ),
            'manage_options',
            'oliforge-cf7-submission-guard',
            array( $this, 'render_dashboard' )
        );
        add_submenu_page(
            'oliforge-cf7-submission-guard',
            __( 'OliForge CF7 Submission Guard Settings', 'oliforge-cf7-submission-guard' ),
            __( 'Settings', 'oliforge-cf7-submission-guard' ),
            'manage_options',
            'oliforge-cf7-submission-guard-settings',
            array( $this, 'render_settings' )
        );
        add_submenu_page(
            'oliforge-cf7-submission-guard',
            __( 'OliForge CF7 Submission Guard Logs', 'oliforge-cf7-submission-guard' ),
            __( 'Logs', 'oliforge-cf7-submission-guard' ),
            'manage_options',
            'oliforge-cf7-submission-guard-logs',
            array( $this, 'render_logs' )
        );
    }

    public function register() {
        register_setting( 'oliforge_cf7sg_group', self::OPTION, array( $this, 'sanitize' ) );
    }

    public function assets( $hook ) {
        if ( false === strpos( $hook, 'oliforge-cf7-submission-guard' ) ) {
            return;
        }
        wp_enqueue_style( 'oliforge-cf7sg-admin', OLIFORGE_CF7SG_URL . 'assets/admin.css', array(), OLIFORGE_CF7SG_VERSION );
    }

    private function clean_multiline( $value ) {
        $lines = preg_split( '/\R/', (string) $value );
        $lines = array_filter( array_map( 'trim', $lines ) );
        return implode( "\n", array_values( array_unique( $lines ) ) );
    }

    public function sanitize( $input ) {
        $defaults = self::defaults();
        $out = $defaults;
        // Fields whose panel is only rendered when the optional Country Select
        // integration is active; preserve their stored value instead of wiping
        // it out when the panel (and so the POST field) is absent.
        $current = self::get();
        $checkboxes = array( 'enabled','required_consent','block_urls','block_at','block_email_patterns','block_html','block_bbcode','rate_limit_enabled','min_time_enabled','duplicate_enabled','logging_enabled','log_success' );
        foreach ( $checkboxes as $key ) {
            $out[ $key ] = empty( $input[ $key ] ) ? 0 : 1;
        }

        $out['mode'] = isset( $input['mode'] ) && in_array( $input['mode'], array( 'enforce', 'monitor' ), true ) ? $input['mode'] : 'enforce';
        $out['ip_storage'] = isset( $input['ip_storage'] ) && in_array( $input['ip_storage'], array( 'anonymized', 'full', 'none' ), true ) ? $input['ip_storage'] : 'anonymized';

        foreach ( array( 'name_field','message_field','email_field','consent_field','error_field' ) as $key ) {
            $out[ $key ] = isset( $input[ $key ] ) ? sanitize_key( $input[ $key ] ) : '';
        }
        $out['country_field'] = isset( $input['country_field'] ) ? sanitize_key( $input['country_field'] ) : $current['country_field'];
        foreach ( array( 'name_max','message_min','message_max','rate_limit_count','rate_limit_minutes','min_time_seconds','duplicate_minutes','retention_days','max_digits_percent','max_uppercase_percent' ) as $key ) {
            $out[ $key ] = isset( $input[ $key ] ) ? max( 0, absint( $input[ $key ] ) ) : 0;
        }
        foreach ( array( 'content_fields','forbidden_words','blocked_domains' ) as $key ) {
            $out[ $key ] = isset( $input[ $key ] ) ? $this->clean_multiline( sanitize_textarea_field( wp_unslash( $input[ $key ] ) ) ) : '';
        }
        $out['allowed_countries'] = isset( $input['allowed_countries'] ) ? $this->clean_multiline( sanitize_textarea_field( wp_unslash( $input['allowed_countries'] ) ) ) : $current['allowed_countries'];
        foreach ( array_keys( $defaults ) as $key ) {
            if ( 0 === strpos( $key, 'msg_' ) ) {
                $out[ $key ] = isset( $input[ $key ] ) ? sanitize_text_field( wp_unslash( $input[ $key ] ) ) : $defaults[ $key ];
            }
        }
        return $out;
    }

    private function toggle( $name, $label, $s, $hint = '' ) {
        printf(
            '<label class="oliforge-toggle"><input class="oliforge-toggle__input" type="checkbox" name="%1$s[%2$s]" value="1" %3$s><span class="oliforge-toggle__track"><span class="oliforge-toggle__thumb"></span></span><span class="oliforge-toggle__label">%4$s</span></label>',
            esc_attr( self::OPTION ),
            esc_attr( $name ),
            checked( ! empty( $s[ $name ] ), true, false ),
            esc_html( $label )
        );
        if ( $hint ) {
            printf( '<p class="oliforge-field__hint">%s</p>', esc_html( $hint ) );
        }
    }

    private function text_field( $name, $label, $s, $hint = '', $type = 'text', $min = null ) {
        $id = 'oliforge_cf7sg_' . $name;
        $min_attr = null !== $min ? ' min="' . esc_attr( $min ) . '"' : '';
        echo '<div class="oliforge-field">';
        printf( '<label class="oliforge-field__label" for="%1$s">%2$s</label>', esc_attr( $id ), esc_html( $label ) );
        printf(
            '<input id="%1$s" class="regular-text" type="%2$s"%3$s name="%4$s[%5$s]" value="%6$s">',
            esc_attr( $id ),
            esc_attr( $type ),
            $min_attr,
            esc_attr( self::OPTION ),
            esc_attr( $name ),
            esc_attr( $s[ $name ] )
        );
        if ( $hint ) {
            printf( '<p class="oliforge-field__hint">%s</p>', esc_html( $hint ) );
        }
        echo '</div>';
    }

    private function textarea_field( $name, $label, $s, $hint = '', $rows = 5 ) {
        $id = 'oliforge_cf7sg_' . $name;
        echo '<div class="oliforge-field">';
        if ( $label ) {
            printf( '<label class="oliforge-field__label" for="%1$s">%2$s</label>', esc_attr( $id ), esc_html( $label ) );
        }
        if ( $hint ) {
            printf( '<p class="oliforge-field__hint">%s</p>', esc_html( $hint ) );
        }
        printf(
            '<textarea id="%1$s" class="large-text code oliforge-textarea" rows="%2$d" name="%3$s[%4$s]">%5$s</textarea>',
            esc_attr( $id ),
            absint( $rows ),
            esc_attr( self::OPTION ),
            esc_attr( $name ),
            esc_textarea( $s[ $name ] )
        );
        echo '</div>';
    }

    private function select_field( $name, $label, $s, $options, $hint = '' ) {
        $id = 'oliforge_cf7sg_' . $name;
        echo '<div class="oliforge-field">';
        printf( '<label class="oliforge-field__label" for="%1$s">%2$s</label>', esc_attr( $id ), esc_html( $label ) );
        printf( '<select id="%1$s" name="%2$s[%3$s]">', esc_attr( $id ), esc_attr( self::OPTION ), esc_attr( $name ) );
        foreach ( $options as $value => $option_label ) {
            printf( '<option value="%1$s" %2$s>%3$s</option>', esc_attr( $value ), selected( $s[ $name ], $value, false ), esc_html( $option_label ) );
        }
        echo '</select>';
        if ( $hint ) {
            printf( '<p class="oliforge-field__hint">%s</p>', esc_html( $hint ) );
        }
        echo '</div>';
    }

    /**
     * Shared branded header used by all three admin pages.
     */
    private function render_brand_header( $title_accent ) {
        ?>
        <div class="oliforge-header">
            <div class="oliforge-header__brand">
                <img class="oliforge-header__logo" src="<?php echo esc_url( OLIFORGE_CF7SG_URL . 'src/OliForge_logo.png' ); ?>" alt="<?php esc_attr_e( 'OliForge', 'oliforge-cf7-submission-guard' ); ?>" width="64" height="64">
                <div class="oliforge-header__brandtext">
                    <span class="oliforge-header__name"><?php esc_html_e( 'OliForge', 'oliforge-cf7-submission-guard' ); ?></span>
                    <span class="oliforge-header__tagline"><?php esc_html_e( 'Engineering without complexity.', 'oliforge-cf7-submission-guard' ); ?></span>
                </div>
            </div>
            <div class="oliforge-header__title">
                <h1><?php esc_html_e( 'Submission Guard', 'oliforge-cf7-submission-guard' ); ?> <span class="oliforge-accent"><?php echo esc_html( $title_accent ); ?></span></h1>
                <span class="oliforge-badge oliforge-badge--version">v<?php echo esc_html( OLIFORGE_CF7SG_VERSION ); ?></span>
            </div>
        </div>
        <hr class="wp-header-end">
        <?php
    }

    /**
     * Dashboard / Settings / Logs page-level navigation.
     */
    private function render_nav_tabs( $current ) {
        $tabs = array(
            'dashboard' => array( 'label' => __( 'Dashboard', 'oliforge-cf7-submission-guard' ), 'page' => 'oliforge-cf7-submission-guard' ),
            'settings'  => array( 'label' => __( 'Settings', 'oliforge-cf7-submission-guard' ), 'page' => 'oliforge-cf7-submission-guard-settings' ),
            'logs'      => array( 'label' => __( 'Logs', 'oliforge-cf7-submission-guard' ), 'page' => 'oliforge-cf7-submission-guard-logs' ),
        );
        ?>
        <nav class="oliforge-tabs">
            <?php foreach ( $tabs as $key => $tab ) : ?>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=' . $tab['page'] ) ); ?>" class="oliforge-tabs__link<?php echo $key === $current ? ' is-active' : ''; ?>">
                    <?php echo esc_html( $tab['label'] ); ?>
                </a>
            <?php endforeach; ?>
        </nav>
        <?php
    }

    public function render_dashboard() {
        if ( ! current_user_can( 'manage_options' ) ) { return; }
        $s = self::get();
        $stats = OliForge_CF7SG_Logger::stats( 30 );
        ?>
        <div class="wrap oliforge-cf7sg-ui">
            <?php
            $this->render_brand_header( __( 'Dashboard', 'oliforge-cf7-submission-guard' ) );
            $this->render_nav_tabs( 'dashboard' );
            ?>
            <p class="oliforge-lede">
                <?php
                if ( empty( $s['enabled'] ) ) {
                    esc_html_e( 'Protection is currently disabled.', 'oliforge-cf7-submission-guard' );
                } elseif ( 'monitor' === $s['mode'] ) {
                    esc_html_e( 'Protection is enabled in monitor mode: rule matches are logged but submissions are not blocked.', 'oliforge-cf7-submission-guard' );
                } else {
                    esc_html_e( 'Protection is enabled and enforcing rules on Contact Form 7 submissions.', 'oliforge-cf7-submission-guard' );
                }
                ?>
                <?php if ( empty( $s['enabled'] ) ) : ?>
                    <span class="oliforge-badge oliforge-badge--off"><?php esc_html_e( 'Disabled', 'oliforge-cf7-submission-guard' ); ?></span>
                <?php elseif ( 'monitor' === $s['mode'] ) : ?>
                    <span class="oliforge-badge oliforge-badge--monitor"><?php esc_html_e( 'Monitor only', 'oliforge-cf7-submission-guard' ); ?></span>
                <?php else : ?>
                    <span class="oliforge-badge oliforge-badge--on"><?php esc_html_e( 'Enforcing', 'oliforge-cf7-submission-guard' ); ?></span>
                <?php endif; ?>
            </p>

            <div class="oliforge-metrics">
                <div class="oliforge-metric"><strong><?php echo esc_html( number_format_i18n( $stats['blocked'] ) ); ?></strong><span><?php esc_html_e( 'Blocked, last 30 days', 'oliforge-cf7-submission-guard' ); ?></span></div>
                <div class="oliforge-metric"><strong><?php echo esc_html( number_format_i18n( $stats['allowed'] ) ); ?></strong><span><?php esc_html_e( 'Allowed (logged), last 30 days', 'oliforge-cf7-submission-guard' ); ?></span></div>
                <div class="oliforge-metric"><strong><?php echo empty( $s['rate_limit_enabled'] ) ? esc_html__( 'Off', 'oliforge-cf7-submission-guard' ) : esc_html( $s['rate_limit_count'] . '/' . $s['rate_limit_minutes'] . 'm' ); ?></strong><span><?php esc_html_e( 'Rate limit', 'oliforge-cf7-submission-guard' ); ?></span></div>
            </div>

            <div class="oliforge-card-grid">
                <section class="oliforge-card">
                    <h2><?php esc_html_e( 'Most triggered rules, last 30 days', 'oliforge-cf7-submission-guard' ); ?></h2>
                    <?php if ( empty( $stats['by_rule'] ) ) : ?>
                        <p class="description"><?php esc_html_e( 'No blocked submissions in this period.', 'oliforge-cf7-submission-guard' ); ?></p>
                    <?php else : ?>
                        <table class="oliforge-log-table oliforge-log-table--rules">
                            <thead><tr><th><?php esc_html_e( 'Rule', 'oliforge-cf7-submission-guard' ); ?></th><th><?php esc_html_e( 'Count', 'oliforge-cf7-submission-guard' ); ?></th></tr></thead>
                            <tbody>
                            <?php foreach ( $stats['by_rule'] as $row ) : ?>
                                <tr><td><code><?php echo esc_html( $row->rule ); ?></code></td><td><?php echo esc_html( number_format_i18n( (int) $row->total ) ); ?></td></tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </section>

                <section class="oliforge-card">
                    <h2><?php esc_html_e( 'Recent activity', 'oliforge-cf7-submission-guard' ); ?></h2>
                    <?php $recent = OliForge_CF7SG_Logger::recent( 10 ); ?>
                    <?php if ( empty( $recent ) ) : ?>
                        <p class="description"><?php esc_html_e( 'No log entries yet.', 'oliforge-cf7-submission-guard' ); ?></p>
                    <?php else : ?>
                        <table class="oliforge-log-table oliforge-log-table--recent">
                            <thead><tr><th><?php esc_html_e( 'Date', 'oliforge-cf7-submission-guard' ); ?></th><th><?php esc_html_e( 'Result', 'oliforge-cf7-submission-guard' ); ?></th><th><?php esc_html_e( 'Rule', 'oliforge-cf7-submission-guard' ); ?></th></tr></thead>
                            <tbody>
                            <?php foreach ( $recent as $row ) : ?>
                                <tr>
                                    <td><?php echo esc_html( $row->created_at ); ?></td>
                                    <td><span class="oliforge-result-badge oliforge-result-badge--<?php echo esc_attr( $row->result ); ?>"><?php echo esc_html( $row->result ); ?></span></td>
                                    <td><code><?php echo esc_html( $row->rule ); ?></code></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                        <p><a href="<?php echo esc_url( admin_url( 'admin.php?page=oliforge-cf7-submission-guard-logs' ) ); ?>"><?php esc_html_e( 'View full log', 'oliforge-cf7-submission-guard' ); ?> &rarr;</a></p>
                    <?php endif; ?>
                </section>
            </div>
        </div>
        <?php
    }

    public function render_settings() {
        if ( ! current_user_can( 'manage_options' ) ) { return; }
        $s = self::get();
        $country_select_active = OliForge_CF7SG_Plugin::country_select_is_active();
        $tabs = array(
            'general'  => __( 'General', 'oliforge-cf7-submission-guard' ),
            'fields'   => __( 'Core fields', 'oliforge-cf7-submission-guard' ),
            'content'  => __( 'Content rules', 'oliforge-cf7-submission-guard' ),
            'domains'  => __( 'Email domains', 'oliforge-cf7-submission-guard' ),
            'timing'   => __( 'Rate limit & timing', 'oliforge-cf7-submission-guard' ),
            'consent'  => __( 'Consent', 'oliforge-cf7-submission-guard' ),
            'logging'  => __( 'Logging', 'oliforge-cf7-submission-guard' ),
            'messages' => __( 'Error messages', 'oliforge-cf7-submission-guard' ),
        );
        if ( $country_select_active ) {
            $tabs = array_slice( $tabs, 0, 4, true )
                + array( 'country' => __( 'Country', 'oliforge-cf7-submission-guard' ) )
                + array_slice( $tabs, 4, null, true );
        }
        ?>
        <div class="wrap oliforge-cf7sg-ui">
            <?php
            $this->render_brand_header( __( 'Settings', 'oliforge-cf7-submission-guard' ) );
            $this->render_nav_tabs( 'settings' );
            ?>
            <p class="oliforge-lede"><?php esc_html_e( 'Server-side submission rules for Contact Form 7. Field names must match the CF7 form-tag names.', 'oliforge-cf7-submission-guard' ); ?></p>

            <form method="post" action="options.php">
                <?php settings_fields( 'oliforge_cf7sg_group' ); ?>

                <nav class="oliforge-subtabs">
                    <?php foreach ( $tabs as $slug => $label ) : ?>
                        <a href="#oliforge-cf7sg-panel-<?php echo esc_attr( $slug ); ?>" class="oliforge-subtabs__link" data-oliforge-cf7sg-tab="<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( $label ); ?></a>
                    <?php endforeach; ?>
                </nav>

                <div class="oliforge-card">
                    <section class="oliforge-panel" id="oliforge-cf7sg-panel-general" data-oliforge-cf7sg-panel="general">
                        <?php $this->toggle( 'enabled', __( 'Enable protection', 'oliforge-cf7-submission-guard' ), $s ); ?>
                        <hr class="oliforge-divider">
                        <?php $this->select_field( 'mode', __( 'Mode', 'oliforge-cf7-submission-guard' ), $s, array( 'enforce' => __( 'Enforce', 'oliforge-cf7-submission-guard' ), 'monitor' => __( 'Monitor only', 'oliforge-cf7-submission-guard' ) ) ); ?>
                        <?php $this->text_field( 'error_field', __( 'Generic error field', 'oliforge-cf7-submission-guard' ), $s, __( 'Used when a matched rule has no field of its own to attach the error to (e.g. rate limiting).', 'oliforge-cf7-submission-guard' ) ); ?>
                    </section>

                    <section class="oliforge-panel" id="oliforge-cf7sg-panel-fields" data-oliforge-cf7sg-panel="fields">
                        <div class="oliforge-field-row">
                            <?php $this->text_field( 'name_field', __( 'Name field', 'oliforge-cf7-submission-guard' ), $s ); ?>
                            <?php $this->text_field( 'name_max', __( 'Maximum name length', 'oliforge-cf7-submission-guard' ), $s, '', 'number', 0 ); ?>
                        </div>
                        <div class="oliforge-field-grid">
                            <?php $this->text_field( 'message_field', __( 'Message field', 'oliforge-cf7-submission-guard' ), $s ); ?>
                            <?php $this->text_field( 'message_min', __( 'Minimum message length', 'oliforge-cf7-submission-guard' ), $s, '', 'number', 0 ); ?>
                            <?php $this->text_field( 'message_max', __( 'Maximum message length (0 = disabled)', 'oliforge-cf7-submission-guard' ), $s, '', 'number', 0 ); ?>
                            <?php $this->text_field( 'email_field', __( 'Email field', 'oliforge-cf7-submission-guard' ), $s ); ?>
                            <?php if ( $country_select_active ) : ?>
                                <?php $this->text_field( 'country_field', __( 'Country field', 'oliforge-cf7-submission-guard' ), $s ); ?>
                            <?php endif; ?>
                        </div>
                    </section>

                    <section class="oliforge-panel" id="oliforge-cf7sg-panel-content" data-oliforge-cf7sg-panel="content">
                        <?php $this->textarea_field( 'content_fields', __( 'Fields, one per line', 'oliforge-cf7-submission-guard' ), $s, '', 4 ); ?>
                        <hr class="oliforge-divider">
                        <?php $this->toggle( 'block_urls', __( 'Block URLs/domains', 'oliforge-cf7-submission-guard' ), $s ); ?>
                        <?php $this->toggle( 'block_at', __( 'Block @ character', 'oliforge-cf7-submission-guard' ), $s ); ?>
                        <?php $this->toggle( 'block_email_patterns', __( 'Block email patterns', 'oliforge-cf7-submission-guard' ), $s ); ?>
                        <?php $this->toggle( 'block_html', __( 'Block HTML tags', 'oliforge-cf7-submission-guard' ), $s ); ?>
                        <?php $this->toggle( 'block_bbcode', __( 'Block BBCode-like markup', 'oliforge-cf7-submission-guard' ), $s ); ?>
                        <hr class="oliforge-divider">
                        <div class="oliforge-field-grid">
                            <?php $this->text_field( 'max_digits_percent', __( 'Max digits % (0 = disabled)', 'oliforge-cf7-submission-guard' ), $s, '', 'number', 0 ); ?>
                            <?php $this->text_field( 'max_uppercase_percent', __( 'Max uppercase % (0 = disabled)', 'oliforge-cf7-submission-guard' ), $s, '', 'number', 0 ); ?>
                        </div>
                        <?php $this->textarea_field( 'forbidden_words', __( 'Forbidden words/phrases, one per line', 'oliforge-cf7-submission-guard' ), $s, '', 5 ); ?>
                    </section>

                    <section class="oliforge-panel" id="oliforge-cf7sg-panel-domains" data-oliforge-cf7sg-panel="domains">
                        <?php $this->textarea_field( 'blocked_domains', __( 'Blocked domains', 'oliforge-cf7-submission-guard' ), $s, __( 'One per line. Subdomains are blocked too.', 'oliforge-cf7-submission-guard' ), 10 ); ?>
                    </section>

                    <?php if ( $country_select_active ) : ?>
                        <section class="oliforge-panel" id="oliforge-cf7sg-panel-country" data-oliforge-cf7sg-panel="country">
                            <?php $this->textarea_field( 'allowed_countries', __( 'Allowed values', 'oliforge-cf7-submission-guard' ), $s, __( 'One per line. If empty, the plugin validates against the CF7 select tag values when available.', 'oliforge-cf7-submission-guard' ), 10 ); ?>
                        </section>
                    <?php endif; ?>

                    <section class="oliforge-panel" id="oliforge-cf7sg-panel-timing" data-oliforge-cf7sg-panel="timing">
                        <?php $this->toggle( 'rate_limit_enabled', __( 'Enable IP rate limiting', 'oliforge-cf7-submission-guard' ), $s ); ?>
                        <div class="oliforge-field-grid">
                            <?php $this->text_field( 'rate_limit_count', __( 'Maximum attempts', 'oliforge-cf7-submission-guard' ), $s, '', 'number', 1 ); ?>
                            <?php $this->text_field( 'rate_limit_minutes', __( 'Period, minutes', 'oliforge-cf7-submission-guard' ), $s, '', 'number', 1 ); ?>
                        </div>
                        <hr class="oliforge-divider">
                        <?php $this->toggle( 'min_time_enabled', __( 'Enable minimum completion time', 'oliforge-cf7-submission-guard' ), $s ); ?>
                        <?php $this->text_field( 'min_time_seconds', __( 'Minimum seconds', 'oliforge-cf7-submission-guard' ), $s, '', 'number', 0 ); ?>
                        <hr class="oliforge-divider">
                        <?php $this->toggle( 'duplicate_enabled', __( 'Block duplicate messages from same IP', 'oliforge-cf7-submission-guard' ), $s ); ?>
                        <?php $this->text_field( 'duplicate_minutes', __( 'Duplicate window, minutes', 'oliforge-cf7-submission-guard' ), $s, '', 'number', 1 ); ?>
                    </section>

                    <section class="oliforge-panel" id="oliforge-cf7sg-panel-consent" data-oliforge-cf7sg-panel="consent">
                        <?php $this->toggle( 'required_consent', __( 'Require configured consent field', 'oliforge-cf7-submission-guard' ), $s ); ?>
                        <?php $this->text_field( 'consent_field', __( 'Consent field', 'oliforge-cf7-submission-guard' ), $s, __( 'This does not create consent text; it only validates the submitted CF7 field.', 'oliforge-cf7-submission-guard' ) ); ?>
                    </section>

                    <section class="oliforge-panel" id="oliforge-cf7sg-panel-logging" data-oliforge-cf7sg-panel="logging">
                        <?php $this->toggle( 'logging_enabled', __( 'Enable security logging', 'oliforge-cf7-submission-guard' ), $s ); ?>
                        <?php $this->toggle( 'log_success', __( 'Log successful submissions', 'oliforge-cf7-submission-guard' ), $s ); ?>
                        <hr class="oliforge-divider">
                        <div class="oliforge-field-grid">
                            <?php
                            $this->select_field(
                                'ip_storage',
                                __( 'IP storage', 'oliforge-cf7-submission-guard' ),
                                $s,
                                array(
                                    'anonymized' => __( 'Anonymized', 'oliforge-cf7-submission-guard' ),
                                    'full'       => __( 'Full IP', 'oliforge-cf7-submission-guard' ),
                                    'none'       => __( 'Do not store', 'oliforge-cf7-submission-guard' ),
                                )
                            );
                            ?>
                            <?php $this->text_field( 'retention_days', __( 'Retention, days', 'oliforge-cf7-submission-guard' ), $s, '', 'number', 1 ); ?>
                        </div>
                        <p class="oliforge-field__hint"><?php esc_html_e( 'Name and message contents are never written to the plugin log. The submitted email address itself is never stored either — only its domain (e.g. "example.com"), to help spot patterns.', 'oliforge-cf7-submission-guard' ); ?></p>
                    </section>

                    <section class="oliforge-panel" id="oliforge-cf7sg-panel-messages" data-oliforge-cf7sg-panel="messages">
                        <div class="oliforge-field-grid">
                        <?php
                        $labels = array(
                            'msg_name_max'      => __( 'Name too long', 'oliforge-cf7-submission-guard' ),
                            'msg_message_min'   => __( 'Message too short', 'oliforge-cf7-submission-guard' ),
                            'msg_message_max'   => __( 'Message too long', 'oliforge-cf7-submission-guard' ),
                            'msg_url'           => __( 'URL detected', 'oliforge-cf7-submission-guard' ),
                            'msg_at'            => __( '@ detected', 'oliforge-cf7-submission-guard' ),
                            'msg_email_pattern' => __( 'Email pattern detected', 'oliforge-cf7-submission-guard' ),
                            'msg_html'          => __( 'HTML detected', 'oliforge-cf7-submission-guard' ),
                            'msg_bbcode'        => __( 'BBCode detected', 'oliforge-cf7-submission-guard' ),
                            'msg_forbidden'     => __( 'Forbidden content', 'oliforge-cf7-submission-guard' ),
                            'msg_country'       => __( 'Invalid country', 'oliforge-cf7-submission-guard' ),
                            'msg_domain'        => __( 'Blocked email domain', 'oliforge-cf7-submission-guard' ),
                            'msg_rate_limit'    => __( 'Rate limit', 'oliforge-cf7-submission-guard' ),
                            'msg_too_fast'      => __( 'Too fast', 'oliforge-cf7-submission-guard' ),
                            'msg_duplicate'     => __( 'Duplicate', 'oliforge-cf7-submission-guard' ),
                            'msg_consent'       => __( 'Consent missing', 'oliforge-cf7-submission-guard' ),
                        );
                        foreach ( $labels as $key => $label ) {
                            $this->text_field( $key, $label, $s );
                        }
                        ?>
                        </div>
                    </section>
                </div>

                <?php submit_button(); ?>
            </form>
        </div>
        <script>
        ( function() {
            var nav = document.querySelector( '.oliforge-subtabs' );
            if ( ! nav ) { return; }
            var links = Array.prototype.slice.call( nav.querySelectorAll( '[data-oliforge-cf7sg-tab]' ) );
            var panels = Array.prototype.slice.call( document.querySelectorAll( '[data-oliforge-cf7sg-panel]' ) );
            var storageKey = 'oliforge_cf7sg_settings_tab';

            function activate( slug ) {
                if ( ! slug || ! links.some( function( l ) { return l.getAttribute( 'data-oliforge-cf7sg-tab' ) === slug; } ) ) {
                    slug = links.length ? links[0].getAttribute( 'data-oliforge-cf7sg-tab' ) : null;
                }
                links.forEach( function( l ) {
                    l.classList.toggle( 'is-active', l.getAttribute( 'data-oliforge-cf7sg-tab' ) === slug );
                } );
                panels.forEach( function( p ) {
                    p.classList.toggle( 'is-active', p.getAttribute( 'data-oliforge-cf7sg-panel' ) === slug );
                } );
                try { window.localStorage.setItem( storageKey, slug ); } catch ( e ) {}
            }

            links.forEach( function( link ) {
                link.addEventListener( 'click', function( e ) {
                    e.preventDefault();
                    activate( link.getAttribute( 'data-oliforge-cf7sg-tab' ) );
                } );
            } );

            var initial = window.location.hash ? window.location.hash.replace( '#oliforge-cf7sg-panel-', '' ) : '';
            if ( ! initial ) {
                try { initial = window.localStorage.getItem( storageKey ); } catch ( e ) {}
            }
            activate( initial );
        } )();
        </script>
        <?php
    }

    public function render_logs() {
        if ( ! current_user_can( 'manage_options' ) ) { return; }
        $rows = OliForge_CF7SG_Logger::recent( 200 );
        ?>
        <div class="wrap oliforge-cf7sg-ui">
            <?php
            $this->render_brand_header( __( 'Logs', 'oliforge-cf7-submission-guard' ) );
            $this->render_nav_tabs( 'logs' );
            ?>
            <p class="oliforge-lede"><?php esc_html_e( 'Latest 200 events. Name and message contents are never stored; the email address itself is never stored either, only its domain.', 'oliforge-cf7-submission-guard' ); ?></p>

            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('Clear all Submission Guard logs?');">
                <input type="hidden" name="action" value="oliforge_cf7sg_clear_logs">
                <?php wp_nonce_field( 'oliforge_cf7sg_clear_logs' ); ?>
                <?php submit_button( __( 'Clear all logs', 'oliforge-cf7-submission-guard' ), 'delete', 'submit', false ); ?>
            </form>

            <div class="oliforge-card oliforge-card--table">
                <table class="oliforge-log-table oliforge-log-table--full">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Date', 'oliforge-cf7-submission-guard' ); ?></th>
                            <th><?php esc_html_e( 'Form', 'oliforge-cf7-submission-guard' ); ?></th>
                            <th><?php esc_html_e( 'Result', 'oliforge-cf7-submission-guard' ); ?></th>
                            <th><?php esc_html_e( 'Rule', 'oliforge-cf7-submission-guard' ); ?></th>
                            <th><?php esc_html_e( 'Field', 'oliforge-cf7-submission-guard' ); ?></th>
                            <th><?php esc_html_e( 'Email domain', 'oliforge-cf7-submission-guard' ); ?></th>
                            <th><?php esc_html_e( 'IP', 'oliforge-cf7-submission-guard' ); ?></th>
                            <th><?php esc_html_e( 'User agent', 'oliforge-cf7-submission-guard' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if ( ! $rows ) : ?>
                        <tr><td colspan="8"><?php esc_html_e( 'No log entries.', 'oliforge-cf7-submission-guard' ); ?></td></tr>
                    <?php else : foreach ( $rows as $row ) : ?>
                        <tr>
                            <td><?php echo esc_html( $row->created_at ); ?></td>
                            <td><?php echo esc_html( $row->form_id ); ?></td>
                            <td><span class="oliforge-result-badge oliforge-result-badge--<?php echo esc_attr( $row->result ); ?>"><?php echo esc_html( $row->result ); ?></span></td>
                            <td><code><?php echo esc_html( $row->rule ); ?></code></td>
                            <td><?php echo esc_html( $row->field_name ); ?></td>
                            <td><?php echo esc_html( $row->email_domain ); ?></td>
                            <td><?php echo esc_html( $row->ip_value ); ?></td>
                            <td>
                                <span class="oliforge-ua-text"><?php echo esc_html( $row->user_agent ); ?></span>
                                <?php if ( '' !== $row->user_agent ) : ?><span class="oliforge-ua-info" title="<?php echo esc_attr( $row->user_agent ); ?>">&#9432;</span><?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }

    public function clear_logs() {
        if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'Unauthorized.', 'oliforge-cf7-submission-guard' ) ); }
        check_admin_referer( 'oliforge_cf7sg_clear_logs' );
        OliForge_CF7SG_Logger::clear();
        wp_safe_redirect( add_query_arg( array( 'page' => 'oliforge-cf7-submission-guard-logs' ), admin_url( 'admin.php' ) ) );
        exit;
    }
}
