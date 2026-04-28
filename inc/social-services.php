<?php
/**
 * Custom social-link services for courtneyr-child.
 *
 * WordPress core's social-link block ships icons for ~45 services, but a
 * handful of niche services Courtney is on aren't included. Rather than
 * fork the block, we use the supported `chain` service slug as a carrier
 * and rewrite the rendered HTML based on URL match — replacing the chain
 * icon with our custom SVG and the screen-reader label with the real name.
 *
 * Editor side: each `wp:social-link` with `service="chain"` shows a
 * generic chain icon. Frontend side: this filter rewrites it.
 *
 * @package Courtneyr\Child
 * @since 0.2.0
 */

declare( strict_types = 1 );

namespace Courtneyr\Child\SocialServices;

defined( 'ABSPATH' ) || exit;

/**
 * URL-domain → custom service definition.
 *
 * Each entry's `domain` is matched against the social-link URL (substring,
 * case-insensitive). The first match wins. `svg` is the full <svg> element
 * with the same 24x24 viewBox WP core uses; `label` is the screen-reader
 * accessible name.
 *
 * @return array<string, array{domain: string, label: string, svg: string}>
 */
function get_custom_services(): array {
	return array(
		'readwise' => array(
			'domain' => 'readwise.io',
			'label'  => 'Readwise',
			'svg'    => <<<'SVG'
<svg width="24" height="24" viewBox="0 0 24 24" version="1.1" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false" fill="currentColor"><path d="M3,4 L11,4 C11.5,4 12,4.4 12,5 L12,20 C12,20.6 11.5,21 11,21 C10.5,21 10,20.6 10,20.5 C9.5,19.5 8.5,19 7,19 L3,19 L3,4 Z M5,6 L5,17 L7,17 C7.7,17 8.4,17.1 9,17.3 L9,6 L5,6 Z M13,4 L21,4 L21,19 L17,19 C15.5,19 14.5,19.5 14,20.5 C13.9,20.6 13.5,21 13,21 C12.4,21 12,20.6 12,20 L12,5 C12,4.4 12.5,4 13,4 Z M15,6 L15,17.3 C15.6,17.1 16.3,17 17,17 L19,17 L19,6 L15,6 Z"/></svg>
SVG,
		),
		'boardgamegeek' => array(
			'domain' => 'boardgamegeek.com',
			'label'  => 'BoardGameGeek',
			'svg'    => <<<'SVG'
<svg width="24" height="24" viewBox="0 0 24 24" version="1.1" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false" fill="currentColor"><path d="M5,3 L19,3 C20.1,3 21,3.9 21,5 L21,19 C21,20.1 20.1,21 19,21 L5,21 C3.9,21 3,20.1 3,19 L3,5 C3,3.9 3.9,3 5,3 Z M5,5 L5,19 L19,19 L19,5 L5,5 Z M8,7.5 C8.8,7.5 9.5,8.2 9.5,9 C9.5,9.8 8.8,10.5 8,10.5 C7.2,10.5 6.5,9.8 6.5,9 C6.5,8.2 7.2,7.5 8,7.5 Z M16,7.5 C16.8,7.5 17.5,8.2 17.5,9 C17.5,9.8 16.8,10.5 16,10.5 C15.2,10.5 14.5,9.8 14.5,9 C14.5,8.2 15.2,7.5 16,7.5 Z M12,10.5 C12.8,10.5 13.5,11.2 13.5,12 C13.5,12.8 12.8,13.5 12,13.5 C11.2,13.5 10.5,12.8 10.5,12 C10.5,11.2 11.2,10.5 12,10.5 Z M8,13.5 C8.8,13.5 9.5,14.2 9.5,15 C9.5,15.8 8.8,16.5 8,16.5 C7.2,16.5 6.5,15.8 6.5,15 C6.5,14.2 7.2,13.5 8,13.5 Z M16,13.5 C16.8,13.5 17.5,14.2 17.5,15 C17.5,15.8 16.8,16.5 16,16.5 C15.2,16.5 14.5,15.8 14.5,15 C14.5,14.2 15.2,13.5 16,13.5 Z"/></svg>
SVG,
		),
		'snipd' => array(
			'domain' => 'snipd.com',
			'label'  => 'Snipd',
			'svg'    => <<<'SVG'
<svg width="24" height="24" viewBox="0 0 24 24" version="1.1" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false" fill="currentColor"><path d="M12,3 C10.3,3 9,4.3 9,6 L9,12 C9,13.7 10.3,15 12,15 C13.7,15 15,13.7 15,12 L15,6 C15,4.3 13.7,3 12,3 Z M5,11 L5,12 C5,15.5 7.6,18.4 11,18.9 L11,21 L13,21 L13,18.9 C16.4,18.4 19,15.5 19,12 L19,11 L17,11 L17,12 C17,14.8 14.8,17 12,17 C9.2,17 7,14.8 7,12 L7,11 L5,11 Z"/></svg>
SVG,
		),
		'openprofile' => array(
			'domain' => 'openprofile.dev',
			'label'  => 'OpenProfile.dev',
			'svg'    => <<<'SVG'
<svg width="24" height="24" viewBox="0 0 24 24" version="1.1" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false" fill="currentColor"><path d="M9.4,7.5 L4.9,12 L9.4,16.5 L8,18 L2,12 L8,6 L9.4,7.5 Z M14.6,7.5 L19.1,12 L14.6,16.5 L16,18 L22,12 L16,6 L14.6,7.5 Z M12,9 C13.1,9 14,9.9 14,11 C14,12.1 13.1,13 12,13 C10.9,13 10,12.1 10,11 C10,9.9 10.9,9 12,9 Z"/></svg>
SVG,
		),
	);
}

/**
 * Rewrite the rendered social-link block when its URL matches a custom
 * service's domain.
 *
 * @param string $block_content Block HTML as core rendered it.
 * @param array  $block         Block attributes/data.
 * @return string Possibly-modified block HTML.
 */
function rewrite_custom_service( string $block_content, array $block ): string {
	$url = (string) ( $block['attrs']['url'] ?? '' );
	if ( '' === $url ) {
		return $block_content;
	}

	$lc_url = strtolower( $url );

	foreach ( get_custom_services() as $slug => $info ) {
		if ( false === stripos( $lc_url, strtolower( $info['domain'] ) ) ) {
			continue;
		}

		// Replace the SVG.
		$block_content = preg_replace(
			'#<svg\b[^>]*>.*?</svg>#s',
			$info['svg'],
			$block_content,
			1
		);

		// Replace the screen-reader label.
		$block_content = preg_replace(
			'#(<span class="wp-block-social-link-label[^"]*"[^>]*>)[^<]*(</span>)#',
			'$1' . esc_html( $info['label'] ) . '$2',
			$block_content,
			1
		);

		// Add a class hook so CSS can target this specific service.
		$block_content = preg_replace(
			'#(class="[^"]*?wp-social-link-chain[^"]*?")#',
			'$1 data-service="' . esc_attr( $slug ) . '"',
			$block_content,
			1
		);
		$block_content = str_replace(
			'wp-social-link-chain',
			'wp-social-link-chain wp-social-link-' . $slug,
			$block_content
		);

		break;
	}

	return $block_content;
}
add_filter( 'render_block_core/social-link', __NAMESPACE__ . '\\rewrite_custom_service', 10, 2 );
