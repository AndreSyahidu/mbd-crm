<?php
/**
 * CSV read/write helpers.
 *
 * @package MBD\CRM
 */

namespace MBD\CRM\IO;

defined( 'ABSPATH' ) || exit;

/**
 * Parses uploaded CSV files and builds CSV downloads.
 */
class Csv {

	/**
	 * Read an uploaded CSV file into a header row and associative data rows.
	 *
	 * @param string $field $_FILES field name.
	 * @return array{0: string[], 1: array<int, array<string,string>>}|\WP_Error
	 */
	public static function read_upload( string $field ) {
		if ( empty( $_FILES[ $field ]['tmp_name'] ) || ! is_uploaded_file( $_FILES[ $field ]['tmp_name'] ) ) {
			return new \WP_Error( 'mbd_crm_no_file', __( 'No CSV file was uploaded.', 'mbd-crm' ) );
		}

		// Validate type/extension.
		$name = isset( $_FILES[ $field ]['name'] ) ? sanitize_file_name( wp_unslash( $_FILES[ $field ]['name'] ) ) : '';
		if ( '' === $name || strtolower( (string) pathinfo( $name, PATHINFO_EXTENSION ) ) !== 'csv' ) {
			return new \WP_Error( 'mbd_crm_bad_type', __( 'Please upload a .csv file.', 'mbd-crm' ) );
		}

		$handle = fopen( $_FILES[ $field ]['tmp_name'], 'r' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		if ( false === $handle ) {
			return new \WP_Error( 'mbd_crm_unreadable', __( 'The CSV file could not be read.', 'mbd-crm' ) );
		}

		$header = fgetcsv( $handle );
		if ( false === $header || array() === $header ) {
			fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
			return new \WP_Error( 'mbd_crm_empty', __( 'The CSV file is empty.', 'mbd-crm' ) );
		}

		$header = array_map(
			static function ( $col ) {
				return sanitize_key( trim( (string) $col ) );
			},
			$header
		);

		$rows = array();
		while ( ( $data = fgetcsv( $handle ) ) !== false ) {
			if ( array( null ) === $data ) {
				continue; // Blank line.
			}
			$row = array();
			foreach ( $header as $i => $col ) {
				$row[ $col ] = isset( $data[ $i ] ) ? sanitize_text_field( (string) $data[ $i ] ) : '';
			}
			$rows[] = $row;
		}

		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose

		return array( $header, $rows );
	}

	/**
	 * Stream rows as a CSV download and stop.
	 *
	 * @param string                  $filename Download filename.
	 * @param array<int, array<mixed>> $rows     Rows (first row is the header).
	 * @return void
	 */
	public static function send_download( string $filename, array $rows ): void {
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $filename ) . '"' );

		$out = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		foreach ( $rows as $row ) {
			fputcsv( $out, $row );
		}
		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose

		exit;
	}
}
