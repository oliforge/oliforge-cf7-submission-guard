<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class OliForge_CF7SG_Forms {
    public static function all() {
        if ( ! class_exists( 'WPCF7_ContactForm' ) || ! method_exists( 'WPCF7_ContactForm', 'find' ) ) { return array(); }

        $result = array();
        $forms = WPCF7_ContactForm::find( array( 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC' ) );
        foreach ( (array) $forms as $form ) {
            if ( ! is_object( $form ) || ! method_exists( $form, 'id' ) || ! method_exists( $form, 'scan_form_tags' ) ) { continue; }
            $id = (string) absint( $form->id() );
            if ( '0' === $id ) { continue; }

            $fields = array();
            foreach ( (array) $form->scan_form_tags() as $tag ) {
                if ( empty( $tag->name ) ) { continue; }
                $name = sanitize_key( $tag->name );
                if ( '' === $name || isset( $fields[ $name ] ) ) { continue; }
                $type = isset( $tag->type ) ? (string) $tag->type : '';
                $fields[ $name ] = array(
                    'name'       => $name,
                    'type'       => $type,
                    'basetype'   => isset( $tag->basetype ) ? (string) $tag->basetype : rtrim( $type, '*' ),
                    'required'   => '*' === substr( $type, -1 ),
                    'values'     => isset( $tag->values ) ? array_values( array_map( 'strval', (array) $tag->values ) ) : array(),
                    'raw_values' => isset( $tag->raw_values ) ? array_values( array_map( 'strval', (array) $tag->raw_values ) ) : array(),
                    'options'    => isset( $tag->options ) ? array_values( array_map( 'strval', (array) $tag->options ) ) : array(),
                );
            }

            $result[ $id ] = array(
                'id'     => $id,
                'hash'   => method_exists( $form, 'hash' ) ? (string) $form->hash() : '',
                'title'  => method_exists( $form, 'title' ) ? (string) $form->title() : sprintf( __( 'Form %s', 'oliforge-cf7-submission-guard' ), $id ),
                'locale' => method_exists( $form, 'locale' ) ? (string) $form->locale() : '',
                'fields' => $fields,
            );
        }
        return $result;
    }

    /**
     * $types is a priority-ordered list (e.g. "textarea" before "text" for
     * the message field): every field is checked against the first type
     * before any field is checked against the second, so on a form with
     * your-name (text) then your-message (textarea) this correctly picks
     * your-message instead of matching your-name on the shared "text" type.
     */
    private static function first_matching( $fields, $preferred, $types ) {
        if ( $preferred && isset( $fields[ $preferred ] ) ) { return $preferred; }
        foreach ( $types as $type ) {
            foreach ( $fields as $field ) {
                if ( $field['basetype'] === $type ) { return $field['name']; }
            }
        }
        return '';
    }

    public static function default_profile( $form, $legacy = array(), $enabled = false ) {
        $fields = isset( $form['fields'] ) ? $form['fields'] : array();
        $name = self::first_matching( $fields, isset( $legacy['name_field'] ) ? sanitize_key( $legacy['name_field'] ) : '', array( 'text' ) );
        $message = self::first_matching( $fields, isset( $legacy['message_field'] ) ? sanitize_key( $legacy['message_field'] ) : '', array( 'textarea', 'text' ) );
        $email = self::first_matching( $fields, isset( $legacy['email_field'] ) ? sanitize_key( $legacy['email_field'] ) : '', array( 'email' ) );
        $consent = self::first_matching( $fields, isset( $legacy['consent_field'] ) ? sanitize_key( $legacy['consent_field'] ) : '', array( 'acceptance', 'checkbox' ) );

        // Every dedicated country_select field is protected by default — a
        // form can have more than one (e.g. billing/shipping), each
        // resolved and validated independently against its own list:.
        $country_fields = array();
        foreach ( $fields as $field ) {
            if ( 'country_select' === $field['basetype'] ) { $country_fields[] = $field['name']; }
        }
        if ( ! $country_fields && isset( $legacy['country_field'] ) ) {
            // Legacy single-field setting from before multi-field support,
            // or a plain CF7 [select] used as a country field without the
            // Country Select plugin.
            $legacy_country = self::first_matching( $fields, sanitize_key( $legacy['country_field'] ), array( 'country', 'country_select', 'select' ) );
            if ( $legacy_country ) { $country_fields[] = $legacy_country; }
        }

        $content = array();
        foreach ( preg_split( '/\R/', isset( $legacy['content_fields'] ) ? (string) $legacy['content_fields'] : '' ) as $field ) {
            $field = sanitize_key( $field );
            if ( $field && isset( $fields[ $field ] ) ) { $content[] = $field; }
        }
        if ( ! $content ) { $content = array_values( array_filter( array( $name, $message ) ) ); }

        return array(
            'enabled'        => $enabled ? 1 : 0,
            'name_field'     => $name,
            'name_max'       => isset( $legacy['name_max'] ) ? absint( $legacy['name_max'] ) : 20,
            'message_field'  => $message,
            'message_min'    => isset( $legacy['message_min'] ) ? absint( $legacy['message_min'] ) : 30,
            'message_max'    => isset( $legacy['message_max'] ) ? absint( $legacy['message_max'] ) : 0,
            'email_field'    => $email,
            'country_fields' => array_values( array_unique( $country_fields ) ),
            'consent_field'  => $consent,
            'error_field'    => self::first_matching( $fields, isset( $legacy['error_field'] ) ? sanitize_key( $legacy['error_field'] ) : '', array_keys( self::types( $fields ) ) ),
            'content_fields' => array_values( array_unique( $content ) ),
        );
    }

    private static function types( $fields ) {
        $types = array();
        foreach ( $fields as $field ) { $types[ $field['basetype'] ] = true; }
        return $types;
    }

    public static function migrate_legacy( $legacy ) {
        $profiles = array();
        foreach ( self::all() as $id => $form ) { $profiles[ $id ] = self::default_profile( $form, $legacy, true ); }
        return $profiles;
    }

    public static function for_admin( $profiles, $legacy ) {
        $profiles = is_array( $profiles ) ? $profiles : array();
        foreach ( self::all() as $id => $form ) {
            if ( ! isset( $profiles[ $id ] ) ) {
                $profiles[ $id ] = self::default_profile( $form, $legacy, false );
            } elseif ( ! isset( $profiles[ $id ]['country_fields'] ) ) {
                // Migrate a profile saved before multi-field country
                // support: carry its single legacy country_field forward
                // as-is, so the settings screen shows what's actually still
                // being validated instead of an empty checkbox group.
                $legacy_field = ! empty( $profiles[ $id ]['country_field'] ) ? sanitize_key( $profiles[ $id ]['country_field'] ) : '';
                $profiles[ $id ]['country_fields'] = $legacy_field ? array( $legacy_field ) : array();
            }
        }
        return $profiles;
    }
}
