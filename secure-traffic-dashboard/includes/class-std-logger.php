<?php
/**
 * Central data-access layer. Every read/write to the plugin's custom tables
 * goes through here using $wpdb with prepared statements.
 *
 * @package SecureTraffic_Dashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Logging and query API for traffic, login and block events.
 */
class STD_Logger {

	/**
	 * Insert a traffic event.
	 *
	 * @param array $data {
	 *     Event data. Missing keys fall back to safe defaults.
	 *
	 *     @type string $ip          Client IP.
	 *     @type string $method      HTTP method.
	 *     @type string $request_uri Request URI.
	 *     @type string $user_agent  User agent.
	 *     @type string $referer     Referer.
	 *     @type int    $status_code HTTP status code.
	 *     @type int    $is_blocked  1 if the request was blocked.
	 *     @type string $country     ISO country code.
	 *     @type string $city        City name.
	 * }
	 * @return int|false Inserted row ID, or false on failure.
	 */
	public static function log_traffic( $data ) {
		global $wpdb;

		/**
		 * Filter (or short-circuit) a traffic event before it is written.
		 * Returning an empty value skips logging.
		 *
		 * @param array $data Event data.
		 */
		$data = apply_filters( 'std_before_log_event', $data, 'traffic' );
		if ( empty( $data ) ) {
			return false;
		}

		$ok = $wpdb->insert(
			STD_Helpers::table( 'traffic' ),
			array(
				'event_time'  => current_time( 'mysql' ),
				'ip'          => substr( (string) ( $data['ip'] ?? '' ), 0, 45 ),
				'method'      => substr( (string) ( $data['method'] ?? '' ), 0, 10 ),
				'request_uri' => substr( (string) ( $data['request_uri'] ?? '' ), 0, 255 ),
				'user_agent'  => substr( (string) ( $data['user_agent'] ?? '' ), 0, 255 ),
				'referer'     => substr( (string) ( $data['referer'] ?? '' ), 0, 255 ),
				'status_code' => absint( $data['status_code'] ?? 0 ),
				'is_blocked'  => empty( $data['is_blocked'] ) ? 0 : 1,
				'country'     => substr( (string) ( $data['country'] ?? '' ), 0, 2 ),
				'city'        => substr( (string) ( $data['city'] ?? '' ), 0, 100 ),
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s' )
		);

		return $ok ? (int) $wpdb->insert_id : false;
	}

	/**
	 * Insert a login attempt event.
	 *
	 * @param array $data Login event data (ip, username, success, user_agent,
	 *                    country, city).
	 * @return int|false Inserted row ID, or false on failure.
	 */
	public static function log_login( $data ) {
		global $wpdb;

		$data = apply_filters( 'std_before_log_event', $data, 'login' );
		if ( empty( $data ) ) {
			return false;
		}

		$ok = $wpdb->insert(
			STD_Helpers::table( 'logins' ),
			array(
				'event_time' => current_time( 'mysql' ),
				'ip'         => substr( (string) ( $data['ip'] ?? '' ), 0, 45 ),
				'username'   => substr( (string) ( $data['username'] ?? '' ), 0, 180 ),
				'success'    => empty( $data['success'] ) ? 0 : 1,
				'user_agent' => substr( (string) ( $data['user_agent'] ?? '' ), 0, 255 ),
				'country'    => substr( (string) ( $data['country'] ?? '' ), 0, 2 ),
				'city'       => substr( (string) ( $data['city'] ?? '' ), 0, 100 ),
			),
			array( '%s', '%s', '%s', '%d', '%s', '%s', '%s' )
		);

		return $ok ? (int) $wpdb->insert_id : false;
	}

	/**
	 * Count rows in a table matching an optional time window and conditions.
	 *
	 * @param string $table   Logical table (traffic|logins|blocks).
	 * @param int    $since   Only count rows newer than this many seconds ago
	 *                        (0 = all time).
	 * @param array  $where   Additional equality conditions (column => value).
	 * @return int
	 */
	public static function count( $table, $since = 0, $where = array() ) {
		global $wpdb;

		$table_name = STD_Helpers::table( $table );
		$conditions = array();
		$params     = array();

		if ( $since > 0 ) {
			$conditions[] = 'event_time >= %s';
			$params[]     = self::since_datetime( $since );
		}

		foreach ( $where as $col => $val ) {
			$conditions[] = self::safe_column( $col ) . ' = %s';
			$params[]     = $val;
		}

		$sql = "SELECT COUNT(*) FROM {$table_name}";
		if ( $conditions ) {
			$sql .= ' WHERE ' . implode( ' AND ', $conditions );
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $table_name and columns are whitelisted; values are prepared.
		return (int) ( $params ? $wpdb->get_var( $wpdb->prepare( $sql, $params ) ) : $wpdb->get_var( $sql ) );
	}

	/**
	 * Paginated, filterable fetch of log rows for the admin tables.
	 *
	 * @param string $table Logical table (traffic|logins|blocks).
	 * @param array  $args  {
	 *     @type int    $page     1-based page number.
	 *     @type int    $per_page Rows per page.
	 *     @type string $search   Free-text search across indexed text columns.
	 *     @type string $orderby  Column to sort by (whitelisted).
	 *     @type string $order    ASC|DESC.
	 *     @type array  $where    Equality filters (column => value).
	 * }
	 * @return array {
	 *     @type array $rows  Result rows (associative arrays).
	 *     @type int   $total Total matching rows (for pagination).
	 * }
	 */
	public static function get_rows( $table, $args = array() ) {
		global $wpdb;

		$defaults = array(
			'page'     => 1,
			'per_page' => 25,
			'search'   => '',
			'orderby'  => 'event_time',
			'order'    => 'DESC',
			'where'    => array(),
		);
		$args = wp_parse_args( $args, $defaults );

		$table_name = STD_Helpers::table( $table );
		$page       = max( 1, absint( $args['page'] ) );
		$per_page   = min( 200, max( 1, absint( $args['per_page'] ) ) );
		$offset     = ( $page - 1 ) * $per_page;

		$conditions = array();
		$params     = array();

		// Equality filters.
		foreach ( (array) $args['where'] as $col => $val ) {
			$conditions[] = self::safe_column( $col ) . ' = %s';
			$params[]     = $val;
		}

		// Free-text search across the searchable columns for the table.
		$search = trim( (string) $args['search'] );
		if ( '' !== $search ) {
			$cols     = self::searchable_columns( $table );
			$like     = '%' . $wpdb->esc_like( $search ) . '%';
			$or_parts = array();
			foreach ( $cols as $col ) {
				$or_parts[] = $col . ' LIKE %s';
				$params[]   = $like;
			}
			if ( $or_parts ) {
				$conditions[] = '(' . implode( ' OR ', $or_parts ) . ')';
			}
		}

		$where_sql = $conditions ? ' WHERE ' . implode( ' AND ', $conditions ) : '';

		// Whitelist orderby/order to prevent injection.
		$orderby = self::safe_column( $args['orderby'], 'event_time' );
		$order   = strtoupper( $args['order'] ) === 'ASC' ? 'ASC' : 'DESC';

		// Total count (same filters, no limit).
		$count_sql = "SELECT COUNT(*) FROM {$table_name}{$where_sql}";
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$total = (int) ( $params ? $wpdb->get_var( $wpdb->prepare( $count_sql, $params ) ) : $wpdb->get_var( $count_sql ) );

		// Page of rows.
		$data_sql      = "SELECT * FROM {$table_name}{$where_sql} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";
		$data_params   = array_merge( $params, array( $per_page, $offset ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( $data_sql, $data_params ), ARRAY_A );

		return array(
			'rows'  => $rows ? $rows : array(),
			'total' => $total,
		);
	}

	/**
	 * Aggregate event counts grouped by a time bucket for charting.
	 *
	 * @param string $table  Logical table (traffic|logins).
	 * @param int    $since  Seconds back from now.
	 * @param string $bucket 'hour' or 'day'.
	 * @param array  $where  Equality filters.
	 * @return array Array of objects with `bucket` and `total`.
	 */
	public static function time_series( $table, $since, $bucket = 'hour', $where = array() ) {
		global $wpdb;

		$table_name = STD_Helpers::table( $table );
		$format     = 'day' === $bucket ? '%Y-%m-%d' : '%Y-%m-%d %H:00';

		$conditions = array( 'event_time >= %s' );
		$params     = array( self::since_datetime( $since ) );

		foreach ( $where as $col => $val ) {
			$conditions[] = self::safe_column( $col ) . ' = %s';
			$params[]     = $val;
		}

		$where_sql = ' WHERE ' . implode( ' AND ', $conditions );

		$sql = "SELECT DATE_FORMAT(event_time, %s) AS bucket, COUNT(*) AS total
				FROM {$table_name}{$where_sql}
				GROUP BY bucket ORDER BY bucket ASC";

		$all_params = array_merge( array( $format ), $params );

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $all_params ) );

		return $rows ? $rows : array();
	}

	/**
	 * Top values for a column (e.g. top IPs, top countries, top endpoints).
	 *
	 * @param string $table  Logical table.
	 * @param string $column Column to group by (whitelisted).
	 * @param int    $since  Seconds back from now (0 = all time).
	 * @param int    $limit  Number of rows.
	 * @param array  $where  Equality filters.
	 * @return array Array of objects with `label` and `total`.
	 */
	public static function top( $table, $column, $since = 0, $limit = 10, $where = array() ) {
		global $wpdb;

		$table_name = STD_Helpers::table( $table );
		$column     = self::safe_column( $column );
		$limit      = min( 100, max( 1, absint( $limit ) ) );

		$conditions = array( $column . " <> ''" );
		$params     = array();

		if ( $since > 0 ) {
			$conditions[] = 'event_time >= %s';
			$params[]     = self::since_datetime( $since );
		}

		foreach ( $where as $col => $val ) {
			$conditions[] = self::safe_column( $col ) . ' = %s';
			$params[]     = $val;
		}

		$where_sql = ' WHERE ' . implode( ' AND ', $conditions );

		$sql = "SELECT {$column} AS label, COUNT(*) AS total
				FROM {$table_name}{$where_sql}
				GROUP BY {$column} ORDER BY total DESC LIMIT %d";

		$params[] = $limit;

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $params ) );

		return $rows ? $rows : array();
	}

	/**
	 * Delete events older than a number of days from a table.
	 *
	 * @param string $table Logical table.
	 * @param int    $days  Age threshold in days.
	 * @return int Rows deleted.
	 */
	public static function purge_older_than( $table, $days ) {
		global $wpdb;

		$days = absint( $days );
		if ( $days <= 0 ) {
			return 0;
		}

		$table_name = STD_Helpers::table( $table );
		$cutoff     = gmdate( 'Y-m-d H:i:s', time() - ( $days * DAY_IN_SECONDS ) );

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- table name whitelisted.
		return (int) $wpdb->query( $wpdb->prepare( "DELETE FROM {$table_name} WHERE event_time < %s", $cutoff ) );
	}

	/**
	 * Build a MySQL datetime string for "N seconds ago" in site time.
	 *
	 * @param int $seconds Seconds back from now.
	 * @return string
	 */
	private static function since_datetime( $seconds ) {
		return gmdate( 'Y-m-d H:i:s', strtotime( current_time( 'mysql' ) ) - absint( $seconds ) );
	}

	/**
	 * Whitelist a column name to a known, safe identifier.
	 *
	 * Any column not present in the allow-list is replaced with $fallback,
	 * which guarantees ORDER BY / GROUP BY clauses can never be injected.
	 *
	 * @param string $column   Requested column.
	 * @param string $fallback Safe default column.
	 * @return string
	 */
	private static function safe_column( $column, $fallback = 'id' ) {
		$allowed = array(
			'id',
			'event_time',
			'ip',
			'method',
			'request_uri',
			'user_agent',
			'referer',
			'status_code',
			'is_blocked',
			'country',
			'city',
			'username',
			'success',
			'block_type',
			'value',
			'scope',
			'reason',
			'created',
			'expires',
			'active',
		);

		return in_array( $column, $allowed, true ) ? $column : $fallback;
	}

	/**
	 * Columns eligible for free-text search per table.
	 *
	 * @param string $table Logical table.
	 * @return array
	 */
	private static function searchable_columns( $table ) {
		switch ( $table ) {
			case 'logins':
				return array( 'ip', 'username', 'user_agent', 'country' );
			case 'blocks':
				return array( 'value', 'block_type', 'reason' );
			case 'traffic':
			default:
				return array( 'ip', 'request_uri', 'user_agent', 'country', 'method' );
		}
	}
}
