/**
 * Icons for this site's custom kind terms, registered through PKIW's
 * postKindsIndieweb.kindIcons filter (plugin PR #163). Built-in kinds
 * keep their plugin icons; only site-created terms are added here.
 */
( function ( wp ) {
	'use strict';

	if ( ! wp || ! wp.hooks || ! wp.element ) {
		return;
	}

	var el = wp.element.createElement;

	function svg( children ) {
		return el(
			'svg',
			{
				viewBox: '0 0 24 24',
				xmlns: 'http://www.w3.org/2000/svg',
				'aria-hidden': true,
				focusable: false,
				width: 24,
				height: 24,
			},
			children
		);
	}

	/* Chicken: the emoji IS the icon. */
	function ChickenIcon() {
		return el(
			'span',
			{ 'aria-hidden': true, style: { fontSize: '20px', lineHeight: 1 } },
			'🐔'
		);
	}

	/* Comics: POW! starburst. */
	function ComicsIcon() {
		return svg( [
			el( 'path', {
				key: 'burst',
				d: 'M12 1.5 14 7l4.2-3-1.6 5 5.6-.4-4.3 3.3 4.7 2.6-5.4.8 2.6 4.8-5-2.3L14 22.5l-2-4.7-4.2 3 1.6-5-5.6.4 4.3-3.3-4.7-2.6 5.4-.8L6.2 4.7l5 2.3Z',
				fill: 'none',
				stroke: 'currentColor',
				strokeWidth: 1.6,
				strokeLinejoin: 'round',
			} ),
			el(
				'text',
				{
					key: 'pow',
					x: 12,
					y: 14.5,
					textAnchor: 'middle',
					fontSize: 6,
					fontWeight: 800,
					fontFamily: 'inherit',
					fill: 'currentColor',
				},
				'POW'
			),
		] );
	}

	/* Quotation: reversed quote — filled block, knocked-out marks. */
	function QuotationIcon() {
		return svg( [
			el( 'rect', {
				key: 'block',
				x: 2,
				y: 3,
				width: 20,
				height: 18,
				rx: 3,
				fill: 'currentColor',
			} ),
			el( 'path', {
				key: 'marks',
				d: 'M7 15.5c1.7-.4 2.7-1.5 2.9-3.2h-2A1.9 1.9 0 0 1 6 10.4C6 9.1 7 8 8.3 8c1.5 0 2.6 1.2 2.6 3.1 0 2.9-1.6 4.9-3.9 5.6V15.5Zm6.5 0c1.7-.4 2.7-1.5 2.9-3.2h-2a1.9 1.9 0 0 1-1.9-1.9C12.5 9.1 13.5 8 14.8 8c1.5 0 2.6 1.2 2.6 3.1 0 2.9-1.6 4.9-3.9 5.6V15.5Z',
				fill: '#fbfaf5',
			} ),
		] );
	}

	/* Presentation: screen + microphone. */
	function PresentationIcon() {
		return svg( [
			el( 'path', {
				key: 'screen',
				d: 'M3 4h18c.6 0 1 .4 1 1v10c0 .6-.4 1-1 1h-7.6l1.4 3H16a1 1 0 1 1 0 2H8a1 1 0 1 1 0-2h1.2l1.4-3H3c-.6 0-1-.4-1-1V5c0-.6.4-1 1-1Zm1 2v8h16V6H4Z',
				fill: 'currentColor',
			} ),
			el( 'rect', {
				key: 'mic',
				x: 15.4,
				y: 7,
				width: 3.2,
				height: 5,
				rx: 1.6,
				fill: '#fbfaf5',
				stroke: 'currentColor',
				strokeWidth: 1.2,
			} ),
			el( 'path', {
				key: 'micstand',
				d: 'M14 10.5a3 3 0 0 0 6 0M17 13.5V15',
				fill: 'none',
				stroke: 'currentColor',
				strokeWidth: 1.2,
				strokeLinecap: 'round',
			} ),
		] );
	}

	wp.hooks.addFilter(
		'postKindsIndieweb.kindIcons',
		'courtneyr-child/custom-kind-icons',
		function ( icons ) {
			return Object.assign( {}, icons, {
				chicken: ChickenIcon,
				comics: ComicsIcon,
				quotation: QuotationIcon,
				presentation: PresentationIcon,
			} );
		}
	);
} )( window.wp );
