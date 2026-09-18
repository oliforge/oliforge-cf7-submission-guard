<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class OliForge_CF7SG_Validator {
    private $settings;
    private $events = array();
    private $request_checked = false;
    private $duplicate_key = '';
    private $profile = array();

    public function __construct() {
        $this->settings = OliForge_CF7SG_Settings::get();
        add_filter( 'wpcf7_form_elements', array( $this, 'inject_timing_token' ) );
        add_filter( 'wpcf7_validate', array( $this, 'validate' ), 50, 2 );
        add_action( 'wpcf7_mail_sent', array( $this, 'handle_mail_sent' ) );
    }

    public function inject_timing_token( $html ) {
        if ( empty( $this->settings['enabled'] ) || empty( $this->settings['min_time_enabled'] ) ) { return $html; }
        $profile = $this->active_profile();
        if ( ! $profile ) { return $html; }
        $form_id = $this->numeric_form_id();
        $ts = time();
        $sig = hash_hmac( 'sha256', $form_id . '|' . $ts, wp_salt( 'nonce' ) );
        return $html . sprintf(
            '<input type="hidden" name="_oliforge_cf7sg_ts" value="%1$d"><input type="hidden" name="_oliforge_cf7sg_sig" value="%2$s">',
            $ts,
            esc_attr( $sig )
        );
    }

    private function raw( $name ) {
        if ( ! $name || ! isset( $_POST[ $name ] ) ) { return ''; }
        $value = wp_unslash( $_POST[ $name ] );
        if ( is_array( $value ) ) {
            $value = implode( ', ', array_map( 'sanitize_text_field', $value ) );
        }
        return trim( (string) $value );
    }

    private function lines( $value ) {
        return array_values( array_filter( array_map( 'trim', preg_split( '/\R/', (string) $value ) ) ) );
    }

    private function ip() {
        return isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
    }

    private function form_id() {
        $form = function_exists( 'wpcf7_get_current_contact_form' ) ? wpcf7_get_current_contact_form() : null;
        if ( ! $form ) { return ''; }
        if ( method_exists( $form, 'hash' ) ) {
            $hash = $form->hash();
            if ( $hash ) { return $hash; }
        }
        return method_exists( $form, 'id' ) ? (string) $form->id() : '';
    }

    private function numeric_form_id() {
        $form = function_exists( 'wpcf7_get_current_contact_form' ) ? wpcf7_get_current_contact_form() : null;
        return $form && method_exists( $form, 'id' ) ? (string) absint( $form->id() ) : '';
    }

    private function active_profile() {
        $form_id = $this->numeric_form_id();
        $profiles = isset( $this->settings['forms'] ) && is_array( $this->settings['forms'] ) ? $this->settings['forms'] : array();
        if ( '' === $form_id || empty( $profiles[ $form_id ] ) || empty( $profiles[ $form_id ]['enabled'] ) ) { return array(); }

        $profile = wp_parse_args(
            $profiles[ $form_id ],
            array(
                'name_field' => '', 'name_max' => 0, 'message_field' => '', 'message_min' => 0,
                'message_max' => 0, 'email_field' => '', 'country_field' => '', 'consent_field' => '',
                'error_field' => '', 'content_fields' => array(),
            )
        );
        $form = function_exists( 'wpcf7_get_current_contact_form' ) ? wpcf7_get_current_contact_form() : null;
        if ( ! $form || ! method_exists( $form, 'scan_form_tags' ) ) { return array(); }
        $valid_fields = array();
        foreach ( (array) $form->scan_form_tags() as $tag ) {
            if ( ! empty( $tag->name ) ) { $valid_fields[] = sanitize_key( $tag->name ); }
        }
        foreach ( array( 'name_field', 'message_field', 'email_field', 'country_field', 'consent_field', 'error_field' ) as $key ) {
            if ( empty( $profile[ $key ] ) || ! in_array( $profile[ $key ], $valid_fields, true ) ) { $profile[ $key ] = ''; }
        }
        // Belt-and-suspenders against stale saved data: a field with its own
        // dedicated check (email domain, country, consent) must never also
        // run through the generic content rules, e.g. "block @ character"
        // would reject every legitimate email address.
        $single_purpose_fields = array_filter( array( $profile['email_field'], $profile['country_field'], $profile['consent_field'] ) );
        $profile['content_fields'] = array_values( array_diff(
            array_intersect( isset( $profile['content_fields'] ) ? (array) $profile['content_fields'] : array(), $valid_fields ),
            $single_purpose_fields
        ) );
        return $profile;
    }

    private function add_event( $rule, $field, $message ) {
        $this->events[] = array( 'rule' => $rule, 'field' => $field, 'message' => $message );
    }

    private function tag_by_name( $name ) {
        $form = function_exists( 'wpcf7_get_current_contact_form' ) ? wpcf7_get_current_contact_form() : null;
        if ( ! $form || ! method_exists( $form, 'scan_form_tags' ) ) { return null; }
        foreach ( (array) $form->scan_form_tags() as $tag ) {
            if ( isset( $tag->name ) && $name === $tag->name ) { return $tag; }
        }
        return null;
    }

    private function invalidate( $result, $field, $message ) {
        if ( 'monitor' === $this->settings['mode'] ) { return; }
        $tag = $this->tag_by_name( $field );
        if ( ! $tag && ! empty( $this->profile['error_field'] ) ) { $tag = $this->tag_by_name( $this->profile['error_field'] ); }
        // CF7 core silently ignores invalidate() unless $tag resolves to a real tag
        // name on the form. If the configured field and error_field both mismatch
        // the actual form tags, fall back to the first eligible tag so a matched
        // rule always blocks the submission instead of being logged as blocked
        // while the mail still sends.
        if ( ! $tag ) { $tag = $this->first_field_tag(); }
        if ( $tag ) { $result->invalidate( $tag, $message ); }
    }

    private function first_field_tag() {
        $form = function_exists( 'wpcf7_get_current_contact_form' ) ? wpcf7_get_current_contact_form() : null;
        if ( ! $form || ! method_exists( $form, 'scan_form_tags' ) ) { return null; }
        foreach ( (array) $form->scan_form_tags() as $tag ) {
            if ( ! empty( $tag->name ) && ! in_array( $tag->basetype, array( 'submit', 'hidden' ), true ) ) {
                return $tag;
            }
        }
        return null;
    }

    private function detect_url( $text ) {
        if ( preg_match( '~(?:https?\s*:\s*//|www\s*\.)~iu', $text ) ) { return true; }
        return (bool) preg_match( '~\b(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+(?:com|net|org|io|co|biz|info|me|xyz|online|site|store|ru|ua|de|fr|it|es|pl|nl|uk|us|ca|app|dev)\b~iu', $text );
    }

    private function detect_email( $text ) {
        return (bool) preg_match( '/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/iu', $text );
    }

    private function percent_digits( $text ) {
        $len = max( 1, mb_strlen( preg_replace( '/\s+/u', '', $text ) ) );
        preg_match_all( '/\p{N}/u', $text, $m );
        return ( count( $m[0] ) / $len ) * 100;
    }

    private function percent_uppercase( $text ) {
        preg_match_all( '/\p{L}/u', $text, $letters );
        $total = count( $letters[0] );
        if ( 0 === $total ) { return 0; }
        preg_match_all( '/\p{Lu}/u', $text, $upper );
        return ( count( $upper[0] ) / $total ) * 100;
    }

    private function validate_country( $field ) {
        $value = $this->raw( $field );
        if ( '' === $value ) { return false; }
        $allowed = $this->lines( $this->settings['allowed_countries'] );
        if ( ! $allowed ) {
            $tag = $this->tag_by_name( $field );
            if ( $tag && isset( $tag->values ) ) { $allowed = array_map( 'strval', (array) $tag->values ); }
        }
        if ( ! $allowed ) { return false; }
        return ! in_array( $value, $allowed, true );
    }

    private function email_domain( $email ) {
        $at = strrpos( $email, '@' );
        if ( false === $at ) { return ''; }
        return strtolower( trim( substr( $email, $at + 1 ), ". \t\n\r\0\x0B" ) );
    }

    private function blocked_domain( $email ) {
        $domain = $this->email_domain( $email );
        if ( '' === $domain ) { return false; }
        foreach ( $this->lines( strtolower( $this->settings['blocked_domains'] ) ) as $blocked ) {
            $blocked = ltrim( $blocked, '.@ ' );
            if ( $domain === $blocked || ( strlen( $domain ) > strlen( $blocked ) && substr( $domain, -( strlen( $blocked ) + 1 ) ) === '.' . $blocked ) ) {
                return true;
            }
        }
        return false;
    }

    private function timing_invalid( $form_id ) {
        if ( empty( $this->settings['min_time_enabled'] ) ) { return false; }
        $ts = isset( $_POST['_oliforge_cf7sg_ts'] ) ? absint( $_POST['_oliforge_cf7sg_ts'] ) : 0;
        $sig = isset( $_POST['_oliforge_cf7sg_sig'] ) ? sanitize_text_field( wp_unslash( $_POST['_oliforge_cf7sg_sig'] ) ) : '';
        if ( ! $ts || ! $sig ) { return true; }
        $expected = hash_hmac( 'sha256', $form_id . '|' . $ts, wp_salt( 'nonce' ) );
        if ( ! hash_equals( $expected, $sig ) ) { return true; }
        $elapsed = time() - $ts;
        return $elapsed < absint( $this->settings['min_time_seconds'] ) || $elapsed > DAY_IN_SECONDS;
    }

    private function is_duplicate( $ip, $form_id, $message ) {
        if ( empty( $this->settings['duplicate_enabled'] ) || '' === $message ) { return false; }
        $this->duplicate_key = 'oliforge_cf7sg_dup_' . md5( $ip . '|' . $form_id . '|' . hash( 'sha256', $message ) );
        return (bool) get_transient( $this->duplicate_key );
    }

    private function remember_submission() {
        if ( '' === $this->duplicate_key || get_transient( $this->duplicate_key ) ) { return; }
        set_transient(
            $this->duplicate_key,
            1,
            max( 1, absint( $this->settings['duplicate_minutes'] ) ) * MINUTE_IN_SECONDS
        );
    }

    public function validate( $result, $tags ) {
        if ( $this->request_checked || empty( $this->settings['enabled'] ) ) { return $result; }
        $profile = $this->active_profile();
        if ( ! $profile ) { return $result; }
        $this->request_checked = true;
        $this->profile = $profile;
        $s = $this->settings;
        $p = $this->profile;

        $name = $this->raw( $p['name_field'] );
        if ( $p['name_field'] && $p['name_max'] > 0 && mb_strlen( $name ) > $p['name_max'] ) {
            $this->add_event( 'name_max', $p['name_field'], $s['msg_name_max'] );
        }

        $message = $this->raw( $p['message_field'] );
        if ( $p['message_field'] && $p['message_min'] > 0 && mb_strlen( $message ) < $p['message_min'] ) {
            $this->add_event( 'message_min', $p['message_field'], $s['msg_message_min'] );
        }
        if ( $p['message_field'] && $p['message_max'] > 0 && mb_strlen( $message ) > $p['message_max'] ) {
            $this->add_event( 'message_max', $p['message_field'], $s['msg_message_max'] );
        }

        foreach ( (array) $p['content_fields'] as $field ) {
            $text = $this->raw( sanitize_key( $field ) );
            if ( '' === $text ) { continue; }
            if ( ! empty( $s['block_urls'] ) && $this->detect_url( $text ) ) { $this->add_event( 'url_detected', $field, $s['msg_url'] ); }
            if ( ! empty( $s['block_at'] ) && false !== strpos( $text, '@' ) ) { $this->add_event( 'at_detected', $field, $s['msg_at'] ); }
            if ( ! empty( $s['block_email_patterns'] ) && $this->detect_email( $text ) ) { $this->add_event( 'email_pattern', $field, $s['msg_email_pattern'] ); }
            if ( ! empty( $s['block_html'] ) && preg_match( '/<\/?[a-z][^>]*>/iu', $text ) ) { $this->add_event( 'html_detected', $field, $s['msg_html'] ); }
            if ( ! empty( $s['block_bbcode'] ) && preg_match( '/\[(?:url|link|img|code|quote)(?:=[^\]]+)?\]/iu', $text ) ) { $this->add_event( 'bbcode_detected', $field, $s['msg_bbcode'] ); }
            if ( $s['max_digits_percent'] > 0 && $this->percent_digits( $text ) > $s['max_digits_percent'] ) { $this->add_event( 'digits_ratio', $field, $s['msg_forbidden'] ); }
            if ( $s['max_uppercase_percent'] > 0 && $this->percent_uppercase( $text ) > $s['max_uppercase_percent'] ) { $this->add_event( 'uppercase_ratio', $field, $s['msg_forbidden'] ); }
            foreach ( $this->lines( $s['forbidden_words'] ) as $needle ) {
                if ( '' !== $needle && false !== mb_stripos( $text, $needle ) ) { $this->add_event( 'forbidden_content', $field, $s['msg_forbidden'] ); break; }
            }
        }

        $email = $this->raw( $p['email_field'] );
        if ( $p['email_field'] && $email && $this->blocked_domain( $email ) ) { $this->add_event( 'blocked_domain', $p['email_field'], $s['msg_domain'] ); }

        if ( OliForge_CF7SG_Plugin::country_select_is_active() && $p['country_field'] && $this->validate_country( $p['country_field'] ) ) {
            $this->add_event( 'invalid_country', $p['country_field'], $s['msg_country'] );
        }

        if ( ! empty( $s['required_consent'] ) && $p['consent_field'] ) {
            $consent = $this->raw( $p['consent_field'] );
            if ( '' === $consent ) { $this->add_event( 'consent_missing', $p['consent_field'], $s['msg_consent'] ); }
        }

        $numeric_form_id = $this->numeric_form_id();
        if ( $this->timing_invalid( $numeric_form_id ) ) { $this->add_event( 'too_fast', $p['error_field'], $s['msg_too_fast'] ); }

        $ip = $this->ip();
        $form_id = $this->form_id();
        if ( ! empty( $s['rate_limit_enabled'] ) && OliForge_CF7SG_Rate_Limiter::hit( $ip, $form_id, absint( $s['rate_limit_count'] ), absint( $s['rate_limit_minutes'] ) ) ) {
            $this->add_event( 'rate_limit', $p['error_field'], $s['msg_rate_limit'] );
        }
        if ( $this->is_duplicate( $ip, $form_id, $message ) ) { $this->add_event( 'duplicate', $p['message_field'], $s['msg_duplicate'] ); }

        $domain = $this->email_domain( $email );
        $event_result = 'monitor' === $s['mode'] ? 'monitored' : 'blocked';
        foreach ( $this->events as $event ) {
            $this->invalidate( $result, sanitize_key( $event['field'] ), $event['message'] );
            OliForge_CF7SG_Logger::add( $form_id, $event_result, $event['rule'], $event['field'], $ip, $s, $domain );
        }

        return $result;
    }

    public function handle_mail_sent( $contact_form ) {
        $s = $this->settings;
        if ( ! $this->profile ) { return; }
        $this->remember_submission();
        if ( empty( $s['logging_enabled'] ) || empty( $s['log_success'] ) || ! empty( $this->events ) ) { return; }
        $form_id = method_exists( $contact_form, 'hash' ) && $contact_form->hash() ? $contact_form->hash() : ( method_exists( $contact_form, 'id' ) ? $contact_form->id() : '' );
        $domain = $this->email_domain( $this->raw( $this->profile['email_field'] ) );
        OliForge_CF7SG_Logger::add( $form_id, 'allowed', 'passed', '', $this->ip(), $s, $domain );
    }
}
