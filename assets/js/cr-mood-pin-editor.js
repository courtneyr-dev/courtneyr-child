/**
 * Mood pin, in the editor: a searchable "Pick a mood" control on the
 * mood-card block (inc/mood-pin.php feeds it the catalog) that sets the
 * block's own `mood` and `emoji` attributes, plus a live preview of the pin
 * rendered by the same PHP as the front end. The plugin's free-text Mood
 * field keeps working for moods outside the catalog.
 *
 * A mood picked here that matches one of Post Kinds for IndieWeb's
 * vocabulary moods (fetched once from its /moods REST route) also gets that
 * mood's stable `moodKey`, and `mood` is set to the plugin's own current
 * label for it -- so PHP resolves the pin by identity (inc/mood-pin.php)
 * instead of by literal text, matching #207. A request failure, or a picked
 * mood with no vocabulary match, falls back to today's behavior and leaves
 * `moodKey` cleared.
 */
( function ( wp ) {
	'use strict';

	if ( ! wp || ! wp.hooks || ! wp.element || ! wp.components || ! wp.blockEditor ) {
		return;
	}

	var el = wp.element.createElement;
	var Fragment = wp.element.Fragment;
	var useState = wp.element.useState;
	var useEffect = wp.element.useEffect;
	var __ = wp.i18n.__;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var PanelBody = wp.components.PanelBody;
	var ComboboxControl = wp.components.ComboboxControl;
	var data = window.courtneyrMoodPins || { moods: [] };
	var BLOCK = 'post-kinds-indieweb/mood-card';

	var options = data.moods.map( function ( mood ) {
		return { value: mood.label, label: mood.emoji + ' ' + mood.label + ( mood.family ? '  ·  ' + mood.family : '' ) };
	} );

	function normalize( mood ) {
		return String( mood || '' )
			.replace( /<[^>]+>/g, '' )
			.replace( /\s+/g, ' ' )
			.trim()
			.toLowerCase();
	}

	// Post Kinds for IndieWeb's mood vocabulary: fetched once, keyed both by
	// mood key and by every known spelling, so a catalog pick or a saved
	// moodKey can find its plugin identity without asking twice.
	var vocabulary = { ready: false, byKey: {}, byVariant: {} };

	wp.apiFetch( { path: '/post-kinds-indieweb/v1/moods' } )
		.then( function ( response ) {
			var moods = ( response && response.moods ) || [];
			moods.forEach( function ( mood ) {
				vocabulary.byKey[ mood.key ] = mood;
				( mood.variants || [ mood.label ] ).forEach( function ( variant ) {
					vocabulary.byVariant[ normalize( variant ) ] = mood;
				} );
			} );
			vocabulary.ready = true;
		} )
		.catch( function () {
			// Post Kinds absent, an older version without the route, or the
			// request failed: today's text-only behavior applies.
		} );

	/**
	 * The catalog entry a Post Kinds mood key's known spellings resolve to.
	 */
	function catalogEntryForVocabularyKey( key ) {
		var mood = vocabulary.byKey[ key ];
		if ( ! mood ) {
			return null;
		}
		var variants = mood.variants && mood.variants.length ? mood.variants : [ mood.label ];
		for ( var i = 0; i < variants.length; i++ ) {
			var found = data.moods.find( function ( entry ) {
				return normalize( entry.label ) === normalize( variants[ i ] );
			} );
			if ( found ) {
				return found;
			}
		}
		return null;
	}

	/**
	 * The catalog entry for a saved mood + moodKey, preferring identity
	 * (moodKey, or text that matches a vocabulary mood's known spellings)
	 * over a plain text lookup.
	 */
	function findMood( label, moodKey ) {
		var normalized = normalize( label );
		var key = moodKey ? normalize( moodKey ) : '';
		if ( ! key && vocabulary.ready ) {
			var byText = vocabulary.byVariant[ normalized ];
			key = byText ? byText.key : '';
		}
		if ( key && vocabulary.ready && vocabulary.byKey[ key ] ) {
			var viaKey = catalogEntryForVocabularyKey( key );
			if ( viaKey ) {
				return viaKey;
			}
		}
		return data.moods.find( function ( mood ) {
			return normalize( mood.label ) === normalized;
		} );
	}

	function Preview( props ) {
		var state = useState( '' );
		var html = state[ 0 ];
		var setHtml = state[ 1 ];

		useEffect(
			function () {
				var live = true;
				wp.apiFetch( {
					path:
						'/courtneyr/v1/mood-pin?mood=' +
						encodeURIComponent( props.mood || '' ) +
						'&emoji=' +
						encodeURIComponent( props.emoji || '' ) +
						'&moodKey=' +
						encodeURIComponent( props.moodKey || '' ),
				} )
					.then( function ( response ) {
						if ( live && response && response.html ) {
							setHtml( response.html );
						}
					} )
					.catch( function () {} );
				return function () {
					live = false;
				};
			},
			[ props.mood, props.emoji, props.moodKey ]
		);

		if ( ! html ) {
			return null;
		}
		// The endpoint returns markup the theme escaped server-side.
		return el( 'div', {
			className: 'cr-mood-pin-preview',
			dangerouslySetInnerHTML: { __html: html },
		} );
	}

	wp.hooks.addFilter( 'editor.BlockEdit', 'courtneyr/mood-pin', function ( BlockEdit ) {
		return function ( props ) {
			if ( props.name !== BLOCK ) {
				return el( BlockEdit, props );
			}
			var mood = props.attributes.mood;
			var emoji = props.attributes.emoji;
			var moodKey = props.attributes.moodKey;
			var match = findMood( mood, moodKey );

			return el(
				Fragment,
				null,
				el( BlockEdit, props ),
				el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{ title: __( 'Mood pin', 'courtneyr-child' ), initialOpen: true },
						el( ComboboxControl, {
							label: __( 'Pick a mood', 'courtneyr-child' ),
							help: __(
								'194 moods from the LiveJournal and Facebook lists. Type to search; the Mood field above still takes anything.',
								'courtneyr-child'
							),
							value: match ? match.label : null,
							options: options,
							allowReset: true,
							__next40pxDefaultSize: true,
							__nextHasNoMarginBottom: true,
							onChange: function ( value ) {
								var picked = data.moods.find( function ( mood ) {
									return mood.label === value;
								} );
								if ( ! picked ) {
									return;
								}
								var vocab = vocabulary.ready ? vocabulary.byVariant[ normalize( picked.label ) ] : null;
								if ( vocab ) {
									// A catalog pick that's also a Post Kinds vocabulary
									// mood: store its key and the plugin's own current
									// label, so PHP resolves by identity, not by text.
									props.setAttributes( { mood: vocab.label, moodKey: vocab.key, emoji: picked.emoji } );
								} else {
									props.setAttributes( { mood: picked.label, emoji: picked.emoji, moodKey: '' } );
								}
							},
						} ),
						el(
							'p',
							{ className: 'cr-mood-pin-help' },
							match
								? __( 'Printed from the catalog.', 'courtneyr-child' )
								: __(
										'Not in the catalog: the pin prints your mood word as typed, with the emoji you chose.',
										'courtneyr-child'
								  )
						),
						el( Preview, { mood: mood, emoji: emoji, moodKey: moodKey } )
					)
				)
			);
		};
	} );
} )( window.wp );
