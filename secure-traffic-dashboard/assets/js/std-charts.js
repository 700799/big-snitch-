/**
 * STDCharts — a tiny, dependency-free canvas charting module for the
 * SecureTraffic Dashboard.
 *
 * Renders line, bar and sparkline charts on a 2D canvas with no third-party
 * libraries. Retina-aware (devicePixelRatio), theme-aware (all colours are
 * passed in so the caller can derive them from CSS custom properties for dark
 * mode), with axis gridlines, a hover tooltip/crosshair, an optional legend
 * and a short enter animation. Charts re-render on container resize.
 *
 * Usage:
 *   STDCharts.line( canvas, {
 *     labels: ['00:00', '01:00', ...],
 *     datasets: [ { label: 'Requests', data: [1,2,3], color: '#2271b1', fill: true } ],
 *     colors: { text: '#1d2327', grid: '#dcdcde', tooltipBg: '#1d2327', tooltipFg: '#fff' }
 *   } );
 *
 * The module is intentionally small and self-contained — it is OUR code, so the
 * plugin ships with zero external/runtime license dependencies.
 *
 * @package SecureTraffic_Dashboard
 */
( function ( window ) {
	'use strict';

	var DPR = window.devicePixelRatio || 1;

	/**
	 * Read resolved theme colours from a CSS-variable host element so charts
	 * match light/dark mode automatically.
	 *
	 * @param {Element} [host] Element to read computed styles from.
	 * @return {Object} Colour map.
	 */
	function themeColors( host ) {
		var cs = window.getComputedStyle( host || document.documentElement );
		function v( name, fallback ) {
			var val = cs.getPropertyValue( name );
			return ( val && val.trim() ) || fallback;
		}
		return {
			text: v( '--std-muted', '#646970' ),
			fg: v( '--std-fg', '#1d2327' ),
			grid: v( '--std-border', '#dcdcde' ),
			accent: v( '--std-accent', '#2271b1' ),
			danger: v( '--std-danger', '#d63638' ),
			ok: v( '--std-ok', '#00a32a' ),
			tooltipBg: v( '--std-fg', '#1d2327' ),
			tooltipFg: v( '--std-bg', '#ffffff' )
		};
	}

	/**
	 * Prepare a canvas for crisp rendering at the current pixel ratio and
	 * return its 2D context plus CSS pixel dimensions.
	 *
	 * @param {HTMLCanvasElement} canvas
	 * @return {{ctx:CanvasRenderingContext2D,w:number,h:number}}
	 */
	function setup( canvas ) {
		var rect = canvas.getBoundingClientRect();
		var w = Math.max( 1, Math.floor( rect.width ) );
		// Honour the height attribute (CSS px) if width-driven layout gives 0.
		var h = Math.max( 1, Math.floor( rect.height || canvas.height || 200 ) );

		canvas.width = w * DPR;
		canvas.height = h * DPR;
		var ctx = canvas.getContext( '2d' );
		ctx.setTransform( DPR, 0, 0, DPR, 0, 0 );
		ctx.clearRect( 0, 0, w, h );
		return { ctx: ctx, w: w, h: h };
	}

	/**
	 * Compute a "nice" axis maximum and step for a given data peak.
	 *
	 * @param {number} max Raw maximum value.
	 * @param {number} ticks Desired tick count.
	 * @return {{max:number,step:number}}
	 */
	function niceScale( max, ticks ) {
		max = Math.max( 1, max );
		ticks = ticks || 4;
		var rawStep = max / ticks;
		var mag = Math.pow( 10, Math.floor( Math.log( rawStep ) / Math.LN10 ) );
		var norm = rawStep / mag;
		var niceNorm = norm < 1.5 ? 1 : norm < 3 ? 2 : norm < 7 ? 5 : 10;
		var step = niceNorm * mag;
		return { max: Math.ceil( max / step ) * step, step: step };
	}

	var PAD = { top: 14, right: 14, bottom: 26, left: 40 };

	/**
	 * Shared line/area renderer.
	 *
	 * @param {HTMLCanvasElement} canvas
	 * @param {Object} cfg
	 */
	function renderLine( canvas, cfg ) {
		var s = setup( canvas );
		var ctx = s.ctx;
		var colors = cfg.colors || themeColors( canvas.parentNode );
		var labels = cfg.labels || [];
		var datasets = cfg.datasets || [];

		var plotW = s.w - PAD.left - PAD.right;
		var plotH = s.h - PAD.top - PAD.bottom;
		if ( plotW <= 0 || plotH <= 0 ) {
			return;
		}

		// Determine peak across datasets.
		var peak = 0;
		datasets.forEach( function ( d ) {
			( d.data || [] ).forEach( function ( y ) { if ( y > peak ) { peak = y; } } );
		} );
		var scale = niceScale( peak, 4 );

		var n = labels.length;
		function xAt( i ) { return PAD.left + ( n <= 1 ? plotW / 2 : ( plotW * i ) / ( n - 1 ) ); }
		function yAt( val ) { return PAD.top + plotH - ( val / scale.max ) * plotH; }

		// Gridlines + y labels.
		ctx.font = '11px -apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif';
		ctx.fillStyle = colors.text;
		ctx.strokeStyle = colors.grid;
		ctx.lineWidth = 1;
		ctx.textBaseline = 'middle';
		for ( var g = 0; g <= scale.max + 0.0001; g += scale.step ) {
			var gy = yAt( g );
			ctx.globalAlpha = 0.5;
			ctx.beginPath();
			ctx.moveTo( PAD.left, gy );
			ctx.lineTo( s.w - PAD.right, gy );
			ctx.stroke();
			ctx.globalAlpha = 1;
			ctx.textAlign = 'right';
			ctx.fillText( String( Math.round( g ) ), PAD.left - 6, gy );
		}

		// X labels (thinned to avoid crowding).
		ctx.textAlign = 'center';
		ctx.textBaseline = 'top';
		var every = Math.ceil( n / Math.max( 1, Math.floor( plotW / 60 ) ) );
		labels.forEach( function ( lab, i ) {
			if ( i % every !== 0 && i !== n - 1 ) { return; }
			ctx.fillText( shortLabel( lab ), xAt( i ), s.h - PAD.bottom + 6 );
		} );

		// Each dataset: optional fill then stroke.
		var prog = cfg._progress == null ? 1 : cfg._progress;
		datasets.forEach( function ( d ) {
			var data = d.data || [];
			var color = d.color || colors.accent;

			if ( d.fill ) {
				ctx.beginPath();
				data.forEach( function ( y, i ) {
					var py = yAt( y * prog );
					if ( i === 0 ) { ctx.moveTo( xAt( i ), py ); } else { ctx.lineTo( xAt( i ), py ); }
				} );
				ctx.lineTo( xAt( data.length - 1 ), yAt( 0 ) );
				ctx.lineTo( xAt( 0 ), yAt( 0 ) );
				ctx.closePath();
				ctx.globalAlpha = 0.12;
				ctx.fillStyle = color;
				ctx.fill();
				ctx.globalAlpha = 1;
			}

			ctx.beginPath();
			ctx.lineWidth = 2;
			ctx.strokeStyle = color;
			ctx.lineJoin = 'round';
			data.forEach( function ( y, i ) {
				var py = yAt( y * prog );
				if ( i === 0 ) { ctx.moveTo( xAt( i ), py ); } else { ctx.lineTo( xAt( i ), py ); }
			} );
			ctx.stroke();
		} );

		// Stash geometry for hover handling.
		canvas._std = { type: 'line', cfg: cfg, xAt: xAt, yAt: yAt, scale: scale, colors: colors, plot: { x: PAD.left, y: PAD.top, w: plotW, h: plotH } };
	}

	/**
	 * Bar renderer (single dataset).
	 *
	 * @param {HTMLCanvasElement} canvas
	 * @param {Object} cfg
	 */
	function renderBar( canvas, cfg ) {
		var s = setup( canvas );
		var ctx = s.ctx;
		var colors = cfg.colors || themeColors( canvas.parentNode );
		var labels = cfg.labels || [];
		var data = ( cfg.datasets && cfg.datasets[ 0 ] && cfg.datasets[ 0 ].data ) || [];
		var color = ( cfg.datasets && cfg.datasets[ 0 ] && cfg.datasets[ 0 ].color ) || colors.accent;

		var plotW = s.w - PAD.left - PAD.right;
		var plotH = s.h - PAD.top - PAD.bottom;
		if ( plotW <= 0 || plotH <= 0 ) { return; }

		var peak = 0;
		data.forEach( function ( y ) { if ( y > peak ) { peak = y; } } );
		var scale = niceScale( peak, 4 );

		ctx.font = '11px -apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif';
		ctx.fillStyle = colors.text;
		ctx.strokeStyle = colors.grid;
		ctx.textBaseline = 'middle';
		for ( var g = 0; g <= scale.max + 0.0001; g += scale.step ) {
			var gy = PAD.top + plotH - ( g / scale.max ) * plotH;
			ctx.globalAlpha = 0.5; ctx.beginPath(); ctx.moveTo( PAD.left, gy ); ctx.lineTo( s.w - PAD.right, gy ); ctx.stroke(); ctx.globalAlpha = 1;
			ctx.textAlign = 'right'; ctx.fillText( String( Math.round( g ) ), PAD.left - 6, gy );
		}

		var prog = cfg._progress == null ? 1 : cfg._progress;
		var bw = ( plotW / Math.max( 1, data.length ) ) * 0.6;
		var gap = ( plotW / Math.max( 1, data.length ) );
		ctx.fillStyle = color;
		data.forEach( function ( y, i ) {
			var bh = ( y / scale.max ) * plotH * prog;
			var bx = PAD.left + gap * i + ( gap - bw ) / 2;
			var by = PAD.top + plotH - bh;
			roundRect( ctx, bx, by, bw, bh, 3 );
			ctx.fill();
		} );

		ctx.fillStyle = colors.text;
		ctx.textAlign = 'center';
		ctx.textBaseline = 'top';
		labels.forEach( function ( lab, i ) {
			ctx.fillText( shortLabel( lab ), PAD.left + gap * i + gap / 2, s.h - PAD.bottom + 6 );
		} );

		canvas._std = { type: 'bar', cfg: cfg, colors: colors };
	}

	/**
	 * Minimal sparkline (no axes), for compact widgets.
	 *
	 * @param {HTMLCanvasElement} canvas
	 * @param {Object} cfg {data:[], color:''}
	 */
	function renderSparkline( canvas, cfg ) {
		var s = setup( canvas );
		var ctx = s.ctx;
		var colors = cfg.colors || themeColors( canvas.parentNode );
		var data = cfg.data || [];
		if ( ! data.length ) { return; }
		var peak = Math.max.apply( null, data ) || 1;
		var n = data.length;
		var color = cfg.color || colors.accent;
		ctx.beginPath();
		ctx.lineWidth = 1.5;
		ctx.strokeStyle = color;
		data.forEach( function ( y, i ) {
			var px = ( s.w * i ) / Math.max( 1, n - 1 );
			var py = s.h - ( y / peak ) * ( s.h - 4 ) - 2;
			if ( i === 0 ) { ctx.moveTo( px, py ); } else { ctx.lineTo( px, py ); }
		} );
		ctx.stroke();
	}

	/* ---- helpers ---- */

	function roundRect( ctx, x, y, w, h, r ) {
		if ( h < r ) { r = Math.max( 0, h ); }
		ctx.beginPath();
		ctx.moveTo( x + r, y );
		ctx.arcTo( x + w, y, x + w, y + h, r );
		ctx.arcTo( x + w, y + h, x, y + h, r );
		ctx.arcTo( x, y + h, x, y, r );
		ctx.arcTo( x, y, x + w, y, r );
		ctx.closePath();
	}

	function shortLabel( lab ) {
		lab = String( lab );
		// "2026-06-07 13:00" -> "13:00"; "2026-06-07" -> "06-07".
		if ( /\d{4}-\d{2}-\d{2} \d{2}:\d{2}/.test( lab ) ) { return lab.slice( 11 ); }
		if ( /\d{4}-\d{2}-\d{2}/.test( lab ) ) { return lab.slice( 5 ); }
		return lab.length > 10 ? lab.slice( 0, 9 ) + '…' : lab;
	}

	/**
	 * Animate an enter transition then bind hover, and re-render on resize.
	 *
	 * @param {HTMLCanvasElement} canvas
	 * @param {Function} renderer
	 * @param {Object} cfg
	 */
	function mount( canvas, renderer, cfg ) {
		// Animate progress 0..1.
		var start = null;
		var duration = 450;
		function frame( ts ) {
			if ( start === null ) { start = ts; }
			var p = Math.min( 1, ( ts - start ) / duration );
			cfg._progress = easeOut( p );
			renderer( canvas, cfg );
			if ( p < 1 ) { window.requestAnimationFrame( frame ); } else { bindHover( canvas ); }
		}
		window.requestAnimationFrame( frame );

		// Debounced resize re-render.
		if ( canvas._stdResize ) { window.removeEventListener( 'resize', canvas._stdResize ); }
		var t;
		canvas._stdResize = function () {
			clearTimeout( t );
			t = setTimeout( function () { cfg._progress = 1; renderer( canvas, cfg ); }, 150 );
		};
		window.addEventListener( 'resize', canvas._stdResize );
	}

	function easeOut( p ) { return 1 - Math.pow( 1 - p, 3 ); }

	/**
	 * Hover tooltip + crosshair for line charts.
	 *
	 * @param {HTMLCanvasElement} canvas
	 */
	function bindHover( canvas ) {
		var meta = canvas._std;
		if ( ! meta || meta.type !== 'line' ) { return; }

		var tip = ensureTooltip();
		canvas.onmousemove = function ( e ) {
			var rect = canvas.getBoundingClientRect();
			var x = e.clientX - rect.left;
			var labels = meta.cfg.labels || [];
			var n = labels.length;
			if ( ! n ) { return; }
			var i = Math.round( ( ( x - meta.plot.x ) / meta.plot.w ) * ( n - 1 ) );
			i = Math.max( 0, Math.min( n - 1, i ) );

			// Re-render and overlay a crosshair dot.
			meta.cfg._progress = 1;
			renderLine( canvas, meta.cfg );
			var ctx = canvas.getContext( '2d' );
			var lines = [];
			( meta.cfg.datasets || [] ).forEach( function ( d ) {
				var y = ( d.data || [] )[ i ] || 0;
				var cx = meta.xAt( i ), cy = meta.yAt( y );
				ctx.beginPath();
				ctx.fillStyle = d.color || meta.colors.accent;
				ctx.arc( cx, cy, 3.5, 0, Math.PI * 2 );
				ctx.fill();
				lines.push( ( d.label ? d.label + ': ' : '' ) + y );
			} );

			tip.innerHTML = '';
			var head = document.createElement( 'strong' );
			head.textContent = labels[ i ];
			tip.appendChild( head );
			lines.forEach( function ( ln ) {
				var div = document.createElement( 'div' );
				div.textContent = ln;
				tip.appendChild( div );
			} );
			tip.style.display = 'block';
			tip.style.left = ( e.clientX + 12 ) + 'px';
			tip.style.top = ( e.clientY + 12 ) + 'px';
		};
		canvas.onmouseleave = function () {
			tip.style.display = 'none';
			meta.cfg._progress = 1;
			renderLine( canvas, meta.cfg );
		};
	}

	function ensureTooltip() {
		var tip = document.getElementById( 'std-chart-tooltip' );
		if ( ! tip ) {
			tip = document.createElement( 'div' );
			tip.id = 'std-chart-tooltip';
			tip.className = 'std-chart-tooltip';
			document.body.appendChild( tip );
		}
		return tip;
	}

	window.STDCharts = {
		line: function ( canvas, cfg ) { mount( canvas, renderLine, cfg ); },
		bar: function ( canvas, cfg ) { mount( canvas, renderBar, cfg ); },
		sparkline: function ( canvas, cfg ) { renderSparkline( canvas, cfg ); },
		themeColors: themeColors
	};

} )( window );
