/**
 * SecureTraffic Dashboard - admin behaviour.
 *
 * Handles: log tables (AJAX, search, filter, pagination), analytics charts
 * (in-house STDCharts), origin map (in-house STDGeo), mitigation actions, toast
 * notifications and the multi-step quick-start wizard.
 *
 * All requests carry the localized nonce; all server values are inserted as
 * text nodes (never innerHTML) to avoid XSS in the admin. The plugin ships with
 * zero third-party JS — STDCharts and STDGeo are our own modules.
 *
 * @package SecureTraffic_Dashboard
 */
( function ( $ ) {
	'use strict';

	var D = window.STD_DATA || {};
	var i18n = D.i18n || {};

	/* ---------------------------------------------------------------- *
	 * Helpers
	 * ---------------------------------------------------------------- */

	function ajax( action, data ) {
		return $.post( D.ajaxUrl, $.extend( { action: action, nonce: D.nonce }, data || {} ) );
	}

	function td( text, extra ) {
		var $cell = $( '<td/>' );
		if ( extra ) { $cell.html( extra ); } else { $cell.text( text == null ? '' : String( text ) ); }
		return $cell;
	}

	/**
	 * Toast notification (replaces inline admin notices). Accessible via
	 * role="status"; auto-dismisses.
	 *
	 * @param {string} msg
	 * @param {string} [type] success|error|info
	 */
	function toast( msg, type ) {
		var $stack = $( '#std-toasts' );
		if ( ! $stack.length ) {
			$stack = $( '<div id="std-toasts" class="std-toasts" aria-live="polite"></div>' ).appendTo( document.body );
		}
		var $t = $( '<div class="std-toast" role="status"></div>' ).addClass( 'std-toast-' + ( type || 'info' ) );
		$t.text( msg );
		$stack.append( $t );
		// Force reflow then show for the transition.
		window.requestAnimationFrame( function () { $t.addClass( 'is-visible' ); } );
		setTimeout( function () {
			$t.removeClass( 'is-visible' );
			setTimeout( function () { $t.remove(); }, 300 );
		}, 4000 );
	}

	function debounce( fn, wait ) {
		var t;
		return function () {
			var ctx = this, args = arguments;
			clearTimeout( t );
			t = setTimeout( function () { fn.apply( ctx, args ); }, wait );
		};
	}

	/* ---------------------------------------------------------------- *
	 * Log tables
	 * ---------------------------------------------------------------- */

	var tableState = {};

	function getState( table ) {
		if ( ! tableState[ table ] ) {
			tableState[ table ] = { page: 1, search: '', per_page: 25, extra: {} };
		}
		return tableState[ table ];
	}

	function skeletonRows( $body, cols, rows ) {
		$body.empty();
		for ( var r = 0; r < ( rows || 6 ); r++ ) {
			var $tr = $( '<tr class="std-skeleton-row"/>' );
			for ( var c = 0; c < cols; c++ ) {
				$tr.append( '<td><span class="std-skeleton"></span></td>' );
			}
			$body.append( $tr );
		}
	}

	function emptyState( $body, cols, msg ) {
		$body.html(
			'<tr><td colspan="' + cols + '"><div class="std-empty">' +
			'<span class="dashicons dashicons-search"></span>' +
			'<p></p></div></td></tr>'
		);
		$body.find( '.std-empty p' ).text( msg || ( i18n.noData || 'No data.' ) );
	}

	function loadTable( table ) {
		var $table = $( '.std-log-table[data-table="' + table + '"]' );
		if ( ! $table.length ) { return; }
		var $body = $table.find( 'tbody' );
		var st = getState( table );
		var cols = $table.find( 'thead th' ).length;

		skeletonRows( $body, cols );

		var payload = $.extend( { table: table, page: st.page, per_page: st.per_page, search: st.search }, st.extra );

		ajax( 'std_get_logs', payload )
			.done( function ( res ) {
				if ( ! res || ! res.success ) { emptyState( $body, cols, i18n.error ); return; }
				renderRows( table, $body, res.data.rows, cols );
				renderPagination( table, res.data.total, st );
			} )
			.fail( function () { emptyState( $body, cols, i18n.error ); } );
	}

	function renderRows( table, $body, rows, cols ) {
		$body.empty();
		if ( ! rows || ! rows.length ) { emptyState( $body, cols ); return; }

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
				$tr.append( td( null, ( row.is_blocked === '1' || row.is_blocked === 1 )
					? '<span class="std-badge std-badge-danger">' + ( i18n.blocked || 'Blocked' ) + '</span>'
					: '<span class="std-badge std-badge-ok">' + ( i18n.allowed || 'Allowed' ) + '</span>' ) );
			} else if ( table === 'logins' ) {
				var success = ( row.success === '1' || row.success === 1 );
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
						.text( 'Block' ).attr( 'data-ip', row.ip ).appendTo( $act );
				}
				$tr.append( $act );
			}

			$body.append( $tr );
		} );
	}

	function renderPagination( table, total, st ) {
		var $pag = $( '.std-pagination[data-table="' + table + '"]' );
		if ( ! $pag.length ) { return; }
		$pag.empty();
		var pages = Math.max( 1, Math.ceil( total / st.per_page ) );
		if ( pages <= 1 ) { return; }

		var $prev = $( '<button type="button" class="button"/>' ).text( '‹' ).prop( 'disabled', st.page <= 1 );
		var $next = $( '<button type="button" class="button"/>' ).text( '›' ).prop( 'disabled', st.page >= pages );
		var $info = $( '<span class="std-page-info"/>' ).text( st.page + ' / ' + pages + ' (' + total + ')' );

		$prev.on( 'click', function () { st.page = Math.max( 1, st.page - 1 ); loadTable( table ); } );
		$next.on( 'click', function () { st.page = Math.min( pages, st.page + 1 ); loadTable( table ); } );

		$pag.append( $prev, $info, $next );
	}

	/* ---------------------------------------------------------------- *
	 * Charts (STDCharts) + map (STDGeo)
	 * ---------------------------------------------------------------- */

	function series( raw ) {
		var labels = [], data = [];
		( raw || [] ).forEach( function ( pt ) { labels.push( pt.bucket ); data.push( Number( pt.total ) ); } );
		return { labels: labels, data: data };
	}

	function lineChart( id, datasets, labels ) {
		var el = document.getElementById( id );
		if ( ! el || ! window.STDCharts ) { return; }
		if ( ! labels || ! labels.length ) { return; }
		STDCharts.line( el, { labels: labels, datasets: datasets } );
	}

	function loadCharts( range ) {
		ajax( 'std_get_charts', { range: range } ).done( function ( res ) {
			if ( ! res || ! res.success ) { return; }
			var d = res.data;
			var colors = window.STDCharts ? STDCharts.themeColors( document.querySelector( '.std-wrap' ) ) : {};

			var t = series( d.traffic_series );
			[ 'std-overview-chart', 'std-analytics-traffic' ].forEach( function ( id ) {
				lineChart( id, [ { label: 'Requests', data: t.data, color: colors.accent, fill: true } ], t.labels );
			} );

			var fail = series( d.login_fail );
			var ok = series( d.login_ok );
			lineChart( 'std-analytics-logins', [
				{ label: 'Failed', data: fail.data, color: colors.danger, fill: true },
				{ label: 'Successful', data: ok.data, color: colors.ok, fill: true }
			], fail.labels.length ? fail.labels : ok.labels );

			fillTop( 'std-top-ips', d.top_ips );
			fillTop( 'std-top-countries', d.top_countries, true );
			fillTop( 'std-top-endpoints', d.top_endpoints );

			if ( window.STDGeo ) {
				STDGeo.map( document.getElementById( 'std-map' ), ( d.top_countries || [] ).map( function ( c ) {
					return { code: c.label, total: c.total };
				} ) );
			}
		} );
	}

	function fillTop( id, items, withFlag ) {
		var $list = $( '#' + id );
		if ( ! $list.length ) { return; }
		$list.empty();
		if ( ! items || ! items.length ) {
			$list.append( $( '<li class="std-top-empty"/>' ).text( i18n.noData || 'No data.' ) );
			return;
		}
		var peak = items.reduce( function ( m, it ) { return Math.max( m, Number( it.total ) ); }, 0 ) || 1;
		items.forEach( function ( item ) {
			var label = ( withFlag && item.flag ? item.flag + ' ' : '' ) + ( item.label || '' );
			var $li = $( '<li/>' );
			var $bar = $( '<span class="std-top-bar"/>' ).css( 'width', Math.round( ( item.total / peak ) * 100 ) + '%' );
			$li.append( $bar );
			$li.append( $( '<span class="std-top-label"/>' ).text( label ) );
			$li.append( $( '<span class="std-top-count"/>' ).text( item.total ) );
			$list.append( $li );
		} );
	}

	/* ---------------------------------------------------------------- *
	 * Mitigation
	 * ---------------------------------------------------------------- */

	function bindMitigation() {
		$( document ).on( 'click', '#std-block-ip-btn', function () {
			var ip = $.trim( $( '#std-block-ip' ).val() );
			if ( ! ip || ! window.confirm( i18n.confirmBlock || 'Block this IP?' ) ) { return; }
			ajax( 'std_block_ip', { ip: ip, scope: $( '#std-block-scope' ).val(), duration: 3600 } )
				.done( function ( res ) {
					toast( res.data.message, res.success ? 'success' : 'error' );
					if ( res.success ) { $( '#std-block-ip' ).val( '' ); }
				} );
		} );

		$( document ).on( 'click', '#std-block-country-btn', function () {
			var cc = $.trim( $( '#std-block-country' ).val() );
			if ( ! cc ) { return; }
			ajax( 'std_block_country', { country: cc } ).done( function ( res ) {
				toast( res.data.message, res.success ? 'success' : 'error' );
			} );
		} );

		$( document ).on( 'click', '.std-unblock', function () {
			var id = $( this ).data( 'id' );
			var $row = $( this ).closest( 'tr' );
			if ( ! window.confirm( i18n.confirmUnblock || 'Remove this block?' ) ) { return; }
			ajax( 'std_unblock', { id: id } ).done( function ( res ) {
				toast( res.data.message, res.success ? 'success' : 'error' );
				if ( res.success ) { $row.fadeOut(); }
			} );
		} );

		$( document ).on( 'click', '.std-inline-block', function () {
			var ip = $( this ).data( 'ip' );
			if ( ! ip || ! window.confirm( i18n.confirmBlock || 'Block this IP?' ) ) { return; }
			ajax( 'std_block_ip', { ip: ip, scope: 'temp', duration: 3600 } ).done( function ( res ) {
				toast( res.data.message, res.success ? 'success' : 'error' );
			} );
		} );
	}

	/* ---------------------------------------------------------------- *
	 * Multi-step wizard
	 * ---------------------------------------------------------------- */

	function bindWizard() {
		var $wiz = $( '#std-wizard' );
		if ( ! $wiz.length ) { return; }

		var $steps = $wiz.find( '.std-wizard-step' );
		var total = $steps.length;
		var current = 0;

		function show( i ) {
			current = Math.max( 0, Math.min( total - 1, i ) );
			$steps.removeClass( 'is-active' ).eq( current ).addClass( 'is-active' );
			$wiz.find( '.std-wizard-prev' ).prop( 'disabled', current === 0 );
			$wiz.find( '.std-wizard-next' ).text( current === total - 1
				? ( i18n.finish || 'Finish' )
				: ( i18n.next || 'Next' ) );
			$wiz.find( '.std-wizard-progress-bar' ).css( 'width', ( ( current + 1 ) / total ) * 100 + '%' );
			$wiz.find( '.std-wizard-dots span' ).removeClass( 'is-on' ).slice( 0, current + 1 ).addClass( 'is-on' );
		}

		$wiz.on( 'click', '.std-wizard-next', function () {
			if ( current === total - 1 ) { finish(); } else { show( current + 1 ); }
		} );
		$wiz.on( 'click', '.std-wizard-prev', function () { show( current - 1 ); } );
		$wiz.on( 'click', '.std-wizard-close', function () { finish(); } );

		function finish() {
			ajax( 'std_complete_wizard' );
			$wiz.slideUp();
		}

		show( 0 );
	}

	/* ---------------------------------------------------------------- *
	 * Controls
	 * ---------------------------------------------------------------- */

	function bindControls() {
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
		bindControls();

		if ( activeTab === 'traffic' ) {
			loadTable( 'traffic' );
		} else if ( activeTab === 'logins' ) {
			loadTable( 'logins' );
		} else if ( activeTab === 'overview' || activeTab === 'analytics' ) {
			loadCharts( '24h' );
		}
	} );

} )( jQuery );
