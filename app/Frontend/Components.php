<?php
/**
 * Server-rendered UI components for the CRM shell.
 *
 * @package MBD\CRM
 */

namespace MBD\CRM\Frontend;

defined( 'ABSPATH' ) || exit;

/**
 * Stateless helpers that return small, pre-escaped HTML fragments so the
 * screen templates stay consistent and free of presentation noise.
 */
class Components {

	/**
	 * Recognised chip / notice colour variants.
	 */
	private const VARIANTS = array( 'default', 'info', 'success', 'warning', 'danger', 'muted' );

	/**
	 * Render a status chip.
	 *
	 * @param string $label   Chip text.
	 * @param string $variant One of self::VARIANTS.
	 * @return string
	 */
	public static function chip( string $label, string $variant = 'default' ): string {
		$variant = self::variant( $variant );

		return sprintf(
			'<span class="mbd-chip mbd-chip--%1$s">%2$s</span>',
			esc_attr( $variant ),
			esc_html( $label )
		);
	}

	/**
	 * Render an inline notice (used for blocked-action messages).
	 *
	 * @param string $message Notice text.
	 * @param string $variant One of self::VARIANTS.
	 * @return string
	 */
	public static function notice( string $message, string $variant = 'info' ): string {
		$variant = self::variant( $variant );

		return sprintf(
			'<p class="mbd-notice mbd-notice--%1$s" role="status">%2$s</p>',
			esc_attr( $variant ),
			esc_html( $message )
		);
	}

	/**
	 * Render a full-panel empty state.
	 *
	 * @param string $title   Heading.
	 * @param string $message Supporting copy.
	 * @param string $icon    Dashicon class.
	 * @return string
	 */
	public static function empty_state( string $title, string $message, string $icon = 'dashicons-portfolio' ): string {
		return self::state( 'empty', $icon, $title, $message );
	}

	/**
	 * Render a full-panel error state.
	 *
	 * @param string $title   Heading.
	 * @param string $message Supporting copy.
	 * @param string $icon    Dashicon class.
	 * @return string
	 */
	public static function error_state( string $title, string $message, string $icon = 'dashicons-warning' ): string {
		return self::state( 'error', $icon, $title, $message );
	}

	/**
	 * Render a full-panel "blocked / not authorised" state.
	 *
	 * @param string $message Supporting copy.
	 * @param string $title   Heading.
	 * @return string
	 */
	public static function blocked( string $message, string $title = '' ): string {
		$title = '' !== $title ? $title : __( 'Access blocked', 'mbd-crm' );

		return self::state( 'blocked', 'dashicons-lock', $title, $message );
	}

	/**
	 * Shared markup for the full-panel states.
	 *
	 * @param string $kind    State modifier (empty|error|blocked).
	 * @param string $icon    Dashicon class.
	 * @param string $title   Heading.
	 * @param string $message Supporting copy.
	 * @return string
	 */
	private static function state( string $kind, string $icon, string $title, string $message ): string {
		$role = 'empty' === $kind ? 'status' : 'alert';

		return sprintf(
			'<div class="mbd-state mbd-state--%1$s" role="%2$s">'
				. '<span class="mbd-state__icon dashicons %3$s" aria-hidden="true"></span>'
				. '<h2 class="mbd-state__title">%4$s</h2>'
				. '<p class="mbd-state__message">%5$s</p>'
				. '</div>',
			esc_attr( $kind ),
			esc_attr( $role ),
			esc_attr( $icon ),
			esc_html( $title ),
			esc_html( $message )
		);
	}

	/**
	 * Normalise a variant name, falling back to "default".
	 *
	 * @param string $variant Requested variant.
	 * @return string
	 */
	private static function variant( string $variant ): string {
		return in_array( $variant, self::VARIANTS, true ) ? $variant : 'default';
	}
}
