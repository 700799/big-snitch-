<?php
/**
 * Report export. Streams the log tables as CSV or JSON downloads, gated by
 * capability and a nonce.
 *
 * @package SecureTraffic_Dashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * CSV / JSON exporter.
 */
class STD_Export {

	/**
	 * Register the admin-post handler.
	 *
	 * @return void
	 */
	public function hooks() {
		add_action( 'admin_post_std_export', array( $this, 'handle' ) );
	}

	/**
	 * Validate the request and stream the requested export.
	 *
	 * @return void
	 */
	public function handle() {
		// Capability + nonce.
		if ( ! current_user_can( STD_CAPABILITY ) && ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to export reports.', 'secure-traffic-dashboard' ), 403 );
		}
		check_admin_referer( 'std_export' );

		$table  = isset( $_GET['table'] ) ? sanitize_key( wp_unslash( $_GET['table'] ) ) : 'traffic';
		$format = isset( $_GET['format'] ) ? sanitize_key( wp_unslash( $_GET['format'] ) ) : 'csv';

		if ( ! in_array( $table, array( 'traffic', 'logins', 'blocks' ), true ) ) {
			$table = 'traffic';
		}

		// For exports we want everything, not just one page; loop pages until we
		// have the full set (hard-capped to protect memory). A running counter
		// avoids calling count() inside the loop condition.
		$orderby = ( 'blocks' === $table ) ? 'created' : 'event_time';
		$rows    = array();
		$fetched = 0;
		$total   = null;
		for ( $page = 1; $page <= 500; $page++ ) {
			$batch   = STD_Logger::get_rows(
				$table,
				array(
					'page'     => $page,
					'per_page' => 200,
					'orderby'  => $orderby,
					'order'    => 'DESC',
				)
			);
			$total   = ( null === $total ) ? (int) $batch['total'] : $total;
			$rows    = array_merge( $rows, $batch['rows'] );
			$fetched = $fetched + count( $batch['rows'] );
			if ( $fetched >= $total || empty( $batch['rows'] ) ) {
				break;
			}
		}

		if ( 'json' === $format ) {
			$this->stream_json( $table, $rows );
		} else {
			$this->stream_csv( $table, $rows );
		}

		exit;
	}

	/**
	 * Stream rows as a CSV download.
	 *
	 * @param string $table Logical table name (for the filename).
	 * @param array  $rows  Rows.
	 * @return void
	 */
	private function stream_csv( $table, $rows ) {
		$filename = 'secure-traffic-' . $table . '-' . gmdate( 'Ymd-His' ) . '.csv';

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );

		// Streaming directly to the output buffer; WP_Filesystem is not
		// applicable to php://output.
		$out = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen

		if ( ! empty( $rows ) ) {
			// Header row from the first record's keys.
			fputcsv( $out, array_keys( $rows[0] ) );
			foreach ( $rows as $row ) {
				fputcsv( $out, $row );
			}
		} else {
			fputcsv( $out, array( 'no_data' ) );
		}

		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
	}

	/**
	 * Stream rows as a JSON download.
	 *
	 * @param string $table Logical table name (for the filename).
	 * @param array  $rows  Rows.
	 * @return void
	 */
	private function stream_json( $table, $rows ) {
		$filename = 'secure-traffic-' . $table . '-' . gmdate( 'Ymd-His' ) . '.json';

		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );

		echo wp_json_encode(
			array(
				'table'     => $table,
				'generated' => gmdate( 'c' ),
				'site'      => home_url(),
				'row_count' => count( $rows ),
				'rows'      => $rows,
			),
			JSON_PRETTY_PRINT
		);
	}
}
