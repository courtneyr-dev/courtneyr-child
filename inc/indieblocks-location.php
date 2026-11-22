<?php
/**
 * IndieBlocks location: stop the editor's automatic browser geolocation.
 *
 * IndieBlocks 0.13.x tells its Location panel to fill latitude and longitude
 * from the browser's geolocation whenever a post is under an hour old and has
 * no location yet. On courtneyr.dev that wrote the author's coordinates into
 * every new draft. Returning false from the plugin's own
 * `indieblocks_location_should_update` filter stops only that automatic fill;
 * the Location panel, its manual lookup button and the save-time place name
 * and weather lookup keep working.
 *
 * Architecture note: no theme.json, block or pattern primitive covers this;
 * the plugin exposes a filter for exactly this case, so one line of
 * integration PHP is the smallest option. It writes no post meta.
 *
 * @package CourtneyrChild
 * @since   0.7.177
 */

defined( 'ABSPATH' ) || exit;

add_filter( 'indieblocks_location_should_update', '__return_false' );
