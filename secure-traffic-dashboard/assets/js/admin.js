/**
 * SecureTraffic Dashboard - admin behaviour.
 *
 * Handles: log tables (AJAX, search, filter, pagination), analytics charts
 * (Chart.js), origin map (Leaflet), mitigation actions and the quick-start
 * wizard. All requests carry the localized nonce; all server values are
 * inserted as text nodes (never innerHTML) to avoid XSS in the admin.
 *
 * @package SecureTraffic_Dashboard
 */
( function ( $ ) {
	'use strict';

	var D = window.STD_DATA || {};
	var i18n = D.i18n || {};
	var charts = {}; // Keyed Chart.js instances so we can destroy/rebuild.
	var map = null;

	/* ---------------------------------------------------------------- *
	 * Helpers
	 * ---------------------------------------------------------------- */

	/**
	 * POST to admin-ajax with the standard nonce and action.
	 *
	 * @param {string} action
	 * @param {Object} data
	 * @return {jqXHR}
	 */
	function ajax( action, data ) {
		return $.post( D.ajaxUrl, $.extend( { action: action, nonce: D.nonce }, data || {} ) );
	}

	/**
	 * Create a <td> containing a text node (safe against HTML injection).
	 *
	 * @param {string} text
	 * @param {string} [extra] Optional HTML for trusted markup (e.g. a badge).
	 * @return {jQuery}
	 */
	function td( text, extra ) {
		var $cell = $( '<td/>' );
		if ( extra ) {
			$cell.html( extra );
		} else {
			$cell.text( text == null ? '' : String( text ) );
		}
		return $cell;
	}

	/* ---------------------------------------------------------------- *
	 * Log tables
	 * ---------------------------------------------------------------- */

	var tableState = {}; // Per-table page/search/filter state.

	function getState( table ) {
		if ( ! tableState[ table ] ) {
			tableState[ table ] = { page: 1, search: '', per_page: 25, extra: {} };
		}
		return tableState[ table ];
	}

	function loadTable( table ) {
		var $table = $( '.std-log-table[data-table="' + table + '"]' );
		if ( ! $table.length ) {
			return;
		}
		var $body = $table.find( 'tbody' );
		var st = getState( table );
		var cols = $table.find( 'thead th' ).length;

		$body.html( '<tr class="std-loading-row"><td colspan="' + cols + '">' + ( i18n.loading || 'Loading…' ) + '</td></tr>' );

		var payload = $.extend( { table: table, page: st.page, per_page: st.per_page, search: st.search }, st.extra );

		ajax( 'std_get_logs', payload )
			.done( function ( res ) {
				if ( ! res || ! res.success ) {
					$body.html( '<tr><td colspan="' + cols + '">' + ( i18n.error || 'Error' ) + '</td></tr>' );
					return;
				}
				renderRows( table, $body, res.data.rows, cols );
				renderPagination( table, res.data.total, st );
			} )
			.fail( function () {
				$body.html( '<tr><td colspan="' + cols + '">' + ( i18n.error || 'Error' ) + '</td></tr>' );
			} );
	}

	function renderRows( table, $body, rows, cols ) {
		$body.empty();
		if ( ! rows || ! rows.length ) {
			$body.append( '<tr><td colspan="' + cols + '">' + ( i18n.noData || 'No data.' ) + '</td></tr>' );
			return;
		}

		rows.forEach( function ( row ) {
			var $tr = $( '<tr/>' );

			if ( table === 'traffic' ) {
				$tr.append( td( row.time_ago ) );
				$tr.append( td( row.ip ) );
				$tr.append( td( ( row.flag || '' ) + ' ' + ( row.country || '' ) ) );
				$tr.append( td( row.method ) );
				$tr.append( td( row.request_uri ) );
				$tr.append( td( row.status_code ) );
				$tr.append( td( row.user_agent ) );
				$tr.append( td( null, row.is_blocked === '1' || row.is_blocked === 1
					? '<span class="std-badge std-badge-danger">' + ( i18n.blocked || 'Blocked' ) + '</span>'
					: '<span class="std-badge std-badge-ok">' + ( i18n.allowed || 'Allowed' ) + '</span>' ) );
			} else if ( table === 'logins' ) {
				var success = row.success === '1' || row.success === 1;
				$tr.append( td( row.time_ago ) );
				$tr.append( td( row.ip ) );
				$tr.append( td( ( row.flag || '' ) + ' ' + ( row.country || '' ) ) );
				$tr.append( td( row.username ) );
				$tr.append( td( null, success
					? '<span class="std-badge std-badge-ok">' + ( i18n.success || 'Success' ) + '</span>'
					: '<span class="std-badge std-badge-warn">' + ( i18n.failed || 'Failed' ) + '</span>' ) );
				$tr.append( td( row.user_agent ) );
				var $act = $( '<td/>' );
				if ( row.ip ) {
					$( '<button type="button" class="button-link std-inline-block"/>' )
						.text( i18n.blocked ? 'Block' : 'Block' )
						.attr( 'data-ip', row.ip )
						.appendTo( $act );
				}
				$tr.append( $act );
			}

			$body.append( $tr );
		} );
	}

	function renderPagination( table, total, st ) {
		var $pag = $( '.std-pagination[data-table="' + table + '"]' );
		if ( ! $pag.length ) {
			return;
		}
		$pag.empty();
		var pages = Math.max( 1, Math.ceil( total / st.per_page ) );
		if ( pages <= 1 ) {
			return;
		}

		var $prev = $( '<button type="button" class="button"/>' ).text( '‹' ).prop( 'disabled', st.page <= 1 );
		var $next = $( '<button type="button" class="button"/>' ).text( '›' ).prop( 'disabled', st.page >= pages );
		var $info = $( '<span class="std-page-info"/>' ).text( st.page + ' / ' + pages + ' (' + total + ')' );

		$prev.on( 'click', function () { st.page = Math.max( 1, st.page - 1 ); loadTable( table ); } );
		$next.on( 'click', function () { st.page = Math.min( pages, st.page + 1 ); loadTable( table ); } );

		$pag.append( $prev, $info, $next );
	}

	/* ---------------------------------------------------------------- *
	 * Charts
	 * ---------------------------------------------------------------- */

	function seriesToXY( series ) {
		var labels = [], data = [];
		( series || [] ).forEach( function ( pt ) {
			labels.push( pt.bucket );
			data.push( Number( pt.total ) );
		} );
		return { labels: labels, data: data };
	}

	function drawLine( canvasId, datasets, labels ) {
		var el = document.getElementById( canvasId );
		if ( ! el || typeof window.Chart === 'undefined' ) {
			return;
		}
		if ( charts[ canvasId ] ) {
			charts[ canvasId ].destroy();
		}
		charts[ canvasId ] = new window.Chart( el.getContext( '2d' ), {
			type: 'line',
			data: { labels: labels, datasets: datasets },
			options: {
				responsive: true,
				maintainAspectRatio: false,
				interaction: { mode: 'index', intersect: false },
				plugins: { legend: { display: datasets.length > 1 } },
				scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
			}
		} );
	}

	function loadCharts( range ) {
		ajax( 'std_get_charts', { range: range } ).done( function ( res ) {
			if ( ! res || ! res.success ) {
				return;
			}
			var d = res.data;

			// Overview / traffic line.
			var t = seriesToXY( d.traffic_series );
			drawLine( 'std-overview-chart', [ {
				label: 'Requests', data: t.data, borderColor: '#2271b1', backgroundColor: 'rgba(34,113,177,.15)', fill: true, tension: 0.3
			} ], t.labels );
			drawLine( 'std-analytics-traffic', [ {
				label: 'Requests', data: t.data, borderColor: '#2271b1', backgroundColor: 'rgba(34,113,177,.15)', fill: true, tension: 0.3
			} ], t.labels );

			// Login fail/ok overlay.
			var fail = seriesToXY( d.login_fail );
			var ok = seriesToXY( d.login_ok );
			drawLine( 'std-analytics-logins', [
				{ label: 'Failed', data: fail.data, borderColor: '#d63638', backgroundColor: 'rgba(214,54,56,.15)', fill: true, tension: 0.3 },
				{ label: 'Successful', data: ok.data, borderColor: '#00a32a', backgroundColor: 'rgba(0,163,42,.15)', fill: true, tension: 0.3 }
			], fail.labels.length ? fail.labels : ok.labels );

			// Top lists.
			fillTop( 'std-top-ips', d.top_ips );
			fillTop( 'std-top-countries', d.top_countries, true );
			fillTop( 'std-top-endpoints', d.top_endpoints );

			// Map.
			drawMap( d.top_countries );
		} );
	}

	function fillTop( id, items, withFlag ) {
		var $list = $( '#' + id );
		if ( ! $list.length ) {
			return;
		}
		$list.empty();
		if ( ! items || ! items.length ) {
			$list.append( $( '<li/>' ).text( i18n.noData || 'No data.' ) );
			return;
		}
		items.forEach( function ( item ) {
			var label = ( withFlag && item.flag ? item.flag + ' ' : '' ) + ( item.label || '' );
			var $li = $( '<li/>' );
			$li.append( $( '<span class="std-top-label"/>' ).text( label ) );
			$li.append( $( '<span class="std-top-count"/>' ).text( item.total ) );
			$list.append( $li );
		} );
	}

	/* ---------------------------------------------------------------- *
	 * Map (Leaflet) - approximate country centroids
	 * ---------------------------------------------------------------- */

	// A compact lookup of rough lat/lng centroids for common countries. Unknown
	// codes are simply skipped. Extend as needed.
	var COUNTRY_LL = {
		US: [ 38, -97 ], GB: [ 54, -2 ], DE: [ 51, 10 ], FR: [ 46, 2 ], RU: [ 61, 105 ],
		CN: [ 35, 105 ], IN: [ 21, 78 ], BR: [ -14, -51 ], CA: [ 56, -106 ], AU: [ -25, 133 ],
		NL: [ 52, 5 ], UA: [ 49, 32 ], JP: [ 36, 138 ], SG: [ 1.3, 103.8 ], ZA: [ -30, 25 ],
		ES: [ 40, -4 ], IT: [ 42, 12 ], SE: [ 62, 15 ], PL: [ 52, 19 ], TR: [ 39, 35 ],
		ID: [ -2, 118 ], MX: [ 23, -102 ], KR: [ 36, 128 ], VN: [ 16, 108 ], IR: [ 32, 53 ]
	};

	function drawMap( countries ) {
		var el = document.getElementById( 'std-map' );
		if ( ! el || typeof window.L === 'undefined' ) {
			return;
		}
		if ( ! map ) {
			map = window.L.map( el, { scrollWheelZoom: false } ).setView( [ 20, 0 ], 1 );
			window.L.tileLayer( 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
				attribution: '© OpenStreetMap', maxZoom: 6
			} ).addTo( map );
		} else {
			// Clear existing markers.
			map.eachLayer( function ( layer ) {
				if ( layer instanceof window.L.CircleMarker ) {
					map.removeLayer( layer );
				}
			} );
		}

		( countries || [] ).forEach( function ( c ) {
			var ll = COUNTRY_LL[ ( c.label || '' ).toUpperCase() ];
			if ( ! ll ) {
				return;
			}
			var radius = Math.min( 30, 6 + Math.log( Number( c.total ) + 1 ) * 4 );
			window.L.circleMarker( ll, {
				radius: radius, color: '#d63638', fillColor: '#d63638', fillOpacity: 0.4
			} ).bindPopup( ( c.label || '' ) + ': ' + c.total ).addTo( map );
		} );
	}

	/* ---------------------------------------------------------------- *
	 * Mitigation actions
	 * ---------------------------------------------------------------- */

	function notify( msg, isError ) {
		var $n = $( '<div class="notice is-dismissible ' + ( isError ? 'notice-error' : 'notice-success' ) + '"><p></p></div>' );
		$n.find( 'p' ).text( msg );
		$( '.std-wrap > h1' ).after( $n );
		setTimeout( function () { $n.fadeOut( function () { $n.remove(); } ); }, 4000 );
	}

	function bindMitigation() {
		$( document ).on( 'click', '#std-block-ip-btn', function () {
			var ip = $.trim( $( '#std-block-ip' ).val() );
			if ( ! ip || ! window.confirm( i18n.confirmBlock || 'Block this IP?' ) ) {
				return;
			}
			ajax( 'std_block_ip', { ip: ip, scope: $( '#std-block-scope' ).val(), duration: 3600 } )
				.done( function ( res ) {
					notify( res.data.message, ! res.success );
					if ( res.success ) { $( '#std-block-ip' ).val( '' ); }
				} );
		} );

		$( document ).on( 'click', '#std-block-country-btn', function () {
			var cc = $.trim( $( '#std-block-country' ).val() );
			if ( ! cc ) {
				return;
			}
			ajax( 'std_block_country', { country: cc } ).done( function ( res ) {
				notify( res.data.message, ! res.success );
			} );
		} );

		$( document ).on( 'click', '.std-unblock', function () {
			var id = $( this ).data( 'id' );
			var $row = $( this ).closest( 'tr' );
			if ( ! window.confirm( i18n.confirmUnblock || 'Remove this block?' ) ) {
				return;
			}
			ajax( 'std_unblock', { id: id } ).done( function ( res ) {
				notify( res.data.message, ! res.success );
				if ( res.success ) { $row.fadeOut(); }
			} );
		} );

		// Inline "Block" button in the logins table.
		$( document ).on( 'click', '.std-inline-block', function () {
			var ip = $( this ).data( 'ip' );
			if ( ! ip || ! window.confirm( i18n.confirmBlock || 'Block this IP?' ) ) {
				return;
			}
			ajax( 'std_block_ip', { ip: ip, scope: 'temp', duration: 3600 } ).done( function ( res ) {
				notify( res.data.message, ! res.success );
			} );
		} );
	}

	/* ---------------------------------------------------------------- *
	 * Wizard
	 * ---------------------------------------------------------------- */

	function bindWizard() {
		$( document ).on( 'click', '#std-wizard-dismiss, #std-wizard-dismiss-2', function () {
			ajax( 'std_complete_wizard' );
			$( '#std-wizard' ).slideUp();
		} );
	}

	/* ---------------------------------------------------------------- *
	 * Filters / search wiring
	 * ---------------------------------------------------------------- */

	function debounce( fn, wait ) {
		var t;
		return function () {
			var ctx = this, args = arguments;
			clearTimeout( t );
			t = setTimeout( function () { fn.apply( ctx, args ); }, wait );
		};
	}

	function bindTableControls() {
		$( '.std-search' ).on( 'input', debounce( function () {
			var table = $( this ).data( 'table' );
			var st = getState( table );
			st.search = $( this ).val();
			st.page = 1;
			loadTable( table );
		}, 350 ) );

		$( '.std-filter-success' ).on( 'change', function () {
			var table = $( this ).data( 'table' );
			var st = getState( table );
			var val = $( this ).val();
			if ( val === '' ) { delete st.extra.success; } else { st.extra.success = val; }
			st.page = 1;
			loadTable( table );
		} );

		$( '.std-filter-blocked' ).on( 'change', function () {
			var table = $( this ).data( 'table' );
			var st = getState( table );
			if ( $( this ).is( ':checked' ) ) { st.extra.blocked = 1; } else { delete st.extra.blocked; }
			st.page = 1;
			loadTable( table );
		} );

		$( '.std-range' ).on( 'click', function () {
			$( '.std-range' ).removeClass( 'active' );
			$( this ).addClass( 'active' );
			loadCharts( $( this ).data( 'range' ) );
		} );
	}

	/* ---------------------------------------------------------------- *
	 * Init
	 * ---------------------------------------------------------------- */

	$( function () {
		var activeTab = $( '.std-tab-content' ).data( 'active-tab' );

		bindMitigation();
		bindWizard();
		bindTableControls();

		// Load data for whichever tab is showing.
		if ( activeTab === 'traffic' ) {
			loadTable( 'traffic' );
		} else if ( activeTab === 'logins' ) {
			loadTable( 'logins' );
		} else if ( activeTab === 'overview' || activeTab === 'analytics' ) {
			loadCharts( '24h' );
		}
	} );

} )( jQuery );
