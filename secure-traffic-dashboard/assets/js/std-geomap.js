/**
 * STDGeo — a dependency-free "dot-grid" world map for plotting request origins.
 *
 * Instead of a tile-based map (which would require an external library and a
 * runtime tile service), this draws a self-generated equirectangular dotted
 * graticule on a 2D canvas and plots country bubbles sized by event count.
 * It is entirely our own code and uses only a built-in table of country
 * centroid coordinates (geographic facts, freely usable), so the plugin keeps
 * zero external/runtime license dependencies.
 *
 * Usage:
 *   STDGeo.map( containerEl, [ { code:'US', total:42 }, ... ] );
 *
 * @package SecureTraffic_Dashboard
 */
( function ( window ) {
	'use strict';

	var DPR = window.devicePixelRatio || 1;

	/**
	 * ISO 3166-1 alpha-2 → approximate [latitude, longitude] centroid.
	 * Coordinates are rounded geographic facts used only for plotting position.
	 */
	var CENTROIDS = {
		AE: [ 24, 54 ], AF: [ 33, 65 ], AL: [ 41, 20 ], AM: [ 40, 45 ], AO: [ -12.5, 18.5 ],
		AR: [ -34, -64 ], AT: [ 47.5, 14.5 ], AU: [ -25, 133 ], AZ: [ 40.5, 47.5 ], BA: [ 44, 18 ],
		BD: [ 24, 90 ], BE: [ 50.8, 4.5 ], BG: [ 43, 25 ], BH: [ 26, 50.5 ], BR: [ -14, -51 ],
		BY: [ 53, 28 ], CA: [ 56, -106 ], CD: [ -4, 22 ], CH: [ 47, 8 ], CI: [ 7.5, -5.5 ],
		CL: [ -35, -71 ], CM: [ 6, 12 ], CN: [ 35, 105 ], CO: [ 4, -73 ], CR: [ 10, -84 ],
		CU: [ 22, -79 ], CY: [ 35, 33 ], CZ: [ 49.8, 15.5 ], DE: [ 51, 10 ], DK: [ 56, 10 ],
		DO: [ 19, -70.5 ], DZ: [ 28, 3 ], EC: [ -1.5, -78 ], EE: [ 59, 26 ], EG: [ 27, 30 ],
		ES: [ 40, -4 ], ET: [ 8, 38 ], FI: [ 64, 26 ], FR: [ 46, 2 ], GB: [ 54, -2 ],
		GE: [ 42, 43.5 ], GH: [ 8, -1 ], GR: [ 39, 22 ], GT: [ 15.5, -90 ], HK: [ 22.3, 114.2 ],
		HN: [ 15, -86.5 ], HR: [ 45.1, 15.5 ], HU: [ 47, 20 ], ID: [ -2, 118 ], IE: [ 53, -8 ],
		IL: [ 31.5, 34.8 ], IN: [ 21, 78 ], IQ: [ 33, 44 ], IR: [ 32, 53 ], IS: [ 65, -18 ],
		IT: [ 42, 12 ], JM: [ 18, -77.3 ], JO: [ 31, 36 ], JP: [ 36, 138 ], KE: [ 0, 38 ],
		KH: [ 13, 105 ], KR: [ 36, 128 ], KW: [ 29.5, 47.7 ], KZ: [ 48, 67 ], LB: [ 33.8, 35.8 ],
		LK: [ 7, 81 ], LT: [ 55, 24 ], LU: [ 49.8, 6 ], LV: [ 57, 25 ], LY: [ 27, 17 ],
		MA: [ 32, -6 ], MD: [ 47, 28.5 ], MX: [ 23, -102 ], MK: [ 41.6, 21.7 ], MM: [ 22, 96 ],
		MY: [ 4, 109 ], NG: [ 9, 8 ], NL: [ 52.3, 5.5 ], NO: [ 62, 10 ], NP: [ 28, 84 ],
		NZ: [ -41, 174 ], OM: [ 21, 57 ], PA: [ 9, -80 ], PE: [ -10, -76 ], PH: [ 13, 122 ],
		PK: [ 30, 70 ], PL: [ 52, 19 ], PT: [ 39.5, -8 ], PY: [ -23, -58 ], QA: [ 25.3, 51.2 ],
		RO: [ 46, 25 ], RS: [ 44, 21 ], RU: [ 61, 105 ], SA: [ 24, 45 ], SE: [ 62, 15 ],
		SG: [ 1.3, 103.8 ], SI: [ 46, 15 ], SK: [ 48.7, 19.5 ], SN: [ 14, -14 ], SY: [ 35, 38 ],
		TH: [ 15, 101 ], TN: [ 34, 9 ], TR: [ 39, 35 ], TW: [ 23.7, 121 ], TZ: [ -6, 35 ],
		UA: [ 49, 32 ], UG: [ 1, 32 ], US: [ 38, -97 ], UY: [ -33, -56 ], UZ: [ 41, 64 ],
		VE: [ 8, -66 ], VN: [ 16, 108 ], YE: [ 15.5, 48 ], ZA: [ -30, 25 ], ZM: [ -13.5, 28 ],
		ZW: [ -19, 29 ]
	};

	/**
	 * Project lat/lng to canvas x/y using a simple equirectangular projection.
	 *
	 * @param {number} lat
	 * @param {number} lng
	 * @param {number} w
	 * @param {number} h
	 * @return {{x:number,y:number}}
	 */
	function project( lat, lng, w, h ) {
		return {
			x: ( ( lng + 180 ) / 360 ) * w,
			y: ( ( 90 - lat ) / 180 ) * h
		};
	}

	/**
	 * Render the map into a container element.
	 *
	 * @param {Element} container
	 * @param {Array} points Array of { code, total } (also accepts { label }).
	 */
	function map( container, points ) {
		if ( ! container ) { return; }
		points = ( points || [] ).map( function ( p ) {
			return { code: ( p.code || p.label || '' ).toUpperCase(), total: Number( p.total ) || 0 };
		} ).filter( function ( p ) { return CENTROIDS[ p.code ]; } );

		// Build / reuse a canvas filling the container.
		var canvas = container.querySelector( 'canvas.std-geo-canvas' );
		if ( ! canvas ) {
			canvas = document.createElement( 'canvas' );
			canvas.className = 'std-geo-canvas';
			container.innerHTML = '';
			container.appendChild( canvas );
		}

		var cs = window.getComputedStyle( container );
		function v( name, fb ) { var x = cs.getPropertyValue( name ); return ( x && x.trim() ) || fb; }
		var colors = {
			dot: v( '--std-border', '#dcdcde' ),
			bubble: v( '--std-danger', '#d63638' ),
			text: v( '--std-fg', '#1d2327' )
		};

		function draw() {
			var rect = container.getBoundingClientRect();
			var w = Math.max( 1, Math.floor( rect.width ) );
			var h = Math.max( 1, Math.floor( rect.height || 360 ) );
			canvas.width = w * DPR; canvas.height = h * DPR;
			canvas.style.width = w + 'px'; canvas.style.height = h + 'px';
			var ctx = canvas.getContext( '2d' );
			ctx.setTransform( DPR, 0, 0, DPR, 0, 0 );
			ctx.clearRect( 0, 0, w, h );

			// Graticule dot grid (every 15° lon, 15° lat) — our own backdrop.
			ctx.fillStyle = colors.dot;
			ctx.globalAlpha = 0.55;
			for ( var lng = -180; lng <= 180; lng += 7.5 ) {
				for ( var lat = -90; lat <= 90; lat += 7.5 ) {
					var pt = project( lat, lng, w, h );
					ctx.beginPath();
					ctx.arc( pt.x, pt.y, 0.8, 0, Math.PI * 2 );
					ctx.fill();
				}
			}
			ctx.globalAlpha = 1;

			// Country bubbles.
			var peak = points.reduce( function ( m, p ) { return Math.max( m, p.total ); }, 0 ) || 1;
			var hot = [];
			points.forEach( function ( p ) {
				var ll = CENTROIDS[ p.code ];
				var pt = project( ll[ 0 ], ll[ 1 ], w, h );
				var r = 4 + Math.sqrt( p.total / peak ) * 22;
				ctx.beginPath();
				ctx.fillStyle = colors.bubble;
				ctx.globalAlpha = 0.35;
				ctx.arc( pt.x, pt.y, r, 0, Math.PI * 2 );
				ctx.fill();
				ctx.globalAlpha = 0.9;
				ctx.beginPath();
				ctx.arc( pt.x, pt.y, 2.2, 0, Math.PI * 2 );
				ctx.fill();
				ctx.globalAlpha = 1;
				hot.push( { x: pt.x, y: pt.y, r: Math.max( r, 8 ), code: p.code, total: p.total } );
			} );

			canvas._hot = hot;
		}

		draw();

		// Hover tooltip.
		var tip = document.getElementById( 'std-chart-tooltip' ) || ( function () {
			var t = document.createElement( 'div' );
			t.id = 'std-chart-tooltip'; t.className = 'std-chart-tooltip';
			document.body.appendChild( t ); return t;
		} )();

		canvas.onmousemove = function ( e ) {
			var rect = canvas.getBoundingClientRect();
			var mx = e.clientX - rect.left, my = e.clientY - rect.top;
			var hit = ( canvas._hot || [] ).filter( function ( hp ) {
				return Math.hypot( mx - hp.x, my - hp.y ) <= hp.r;
			} ).sort( function ( a, b ) { return a.r - b.r; } )[ 0 ];
			if ( hit ) {
				tip.textContent = hit.code + ': ' + hit.total;
				tip.style.display = 'block';
				tip.style.left = ( e.clientX + 12 ) + 'px';
				tip.style.top = ( e.clientY + 12 ) + 'px';
				canvas.style.cursor = 'pointer';
			} else {
				tip.style.display = 'none';
				canvas.style.cursor = 'default';
			}
		};
		canvas.onmouseleave = function () { tip.style.display = 'none'; };

		// Re-render on resize (debounced).
		if ( container._stdResize ) { window.removeEventListener( 'resize', container._stdResize ); }
		var t;
		container._stdResize = function () { clearTimeout( t ); t = setTimeout( draw, 150 ); };
		window.addEventListener( 'resize', container._stdResize );
	}

	window.STDGeo = { map: map, centroids: CENTROIDS };

} )( window );
