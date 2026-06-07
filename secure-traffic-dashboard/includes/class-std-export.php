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

		// Pull up to a sane maximum number of rows for the export.
		$result = STD_Logger::get_rows(
			$table,
			array(
				'page'     => 1,
				'per_page' => 200,
				'orderby'  => 'blocks' === $table ? 'created' : 'event_time',
				'order'    => 'DESC',
			)
		);

		// For exports we want everything, not just one page; loop pages.
		$rows  = array();
		$page  = 1;
		$total = $result['total'];
		do {
			$batch = STD_Logger::get_rows(
				$table,
				array(
					'page'     => $page,
					'per_page' => 200,
					'orderby'  => 'blocks' === $table ? 'created' : 'event_time',
					'order'    => 'DESC',
				)
			);
			$rows = array_merge( $rows, $batch['rows'] );
			++$page;
		} while ( count( $rows ) < $total && $page <= 500 ); // hard cap: 100k rows.

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

		$out = fopen( 'php://output', 'w' );

		if ( ! empty( $rows ) ) {
			// Header row from the first record's keys.
			fputcsv( $out, array_keys( $rows[0] ) );
			foreach ( $rows as $row ) {
				fputcsv( $out, $row );
			}
		} else {
			fputcsv( $out, array( 'no_data' ) );
		}

		fclose( $out );
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
				'table'       => $table,
				'generated'   => gmdate( 'c' ),
				'site'        => home_url(),
				'row_count'   => count( $rows ),
				'rows'        => $rows,
			),
			JSON_PRETTY_PRINT
		);
	}
}
