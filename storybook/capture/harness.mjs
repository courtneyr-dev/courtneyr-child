import crypto from 'node:crypto';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { MARK_BEGIN, MARK_END } from './format.mjs';
import { sha256 } from './inputs.mjs';

export const ALLOWED_CAPS = [ 'read', 'edit_posts' ];

const PHP = String.raw`<?php
/**
 * Plugin Name: courtneyr-child fixture capture harness
 * Description: Written by storybook/capture/capture.mjs for one capture run and deleted when it ends. Acts only on a front-end GET from this machine that carries the run's key. The key is the boundary: a keyed request's block markup is rendered as given, without kses.
 */

defined( 'ABSPATH' ) || exit;

( static function (): void {
	$header = static fn( string $name ): string => isset( $_SERVER[ 'HTTP_X_CR_CAPTURE_' . $name ] ) ? (string) $_SERVER[ 'HTTP_X_CR_CAPTURE_' . $name ] : '';
	if ( '' === $header( 'KEY' ) || 'GET' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
		return;
	}
	// A page on another origin that points its host name at this machine still sends its own Host header.
	if ( ! in_array( $_SERVER['REMOTE_ADDR'] ?? '', array( '127.0.0.1', '::1' ), true ) || '__HOST__' !== ( $_SERVER['HTTP_HOST'] ?? '' ) ) {
		return;
	}
	if ( is_admin() || wp_doing_ajax() || wp_doing_cron() || defined( 'XMLRPC_REQUEST' ) || defined( 'WP_CLI' ) ) {
		return;
	}
	$key_hash = trim( (string) @file_get_contents( '__KEY_HASH_FILE__' ) );
	if ( 64 !== strlen( $key_hash ) || ! hash_equals( $key_hash, hash( 'sha256', $header( 'KEY' ) ) ) ) {
		return;
	}

	$fail = static function ( string $message ): void {
		status_header( 500 );
		header( 'Content-Type: text/plain; charset=utf-8' );
		header( 'X-Content-Type-Options: nosniff' );
		exit( 'capture harness: ' . $message );
	};

	if ( 'site' === $header( 'PROBE' ) ) {
		add_action(
			'template_redirect',
			static function (): void {
				global $wpdb;
				if ( ! function_exists( 'get_plugin_data' ) ) {
					require_once ABSPATH . 'wp-admin/includes/plugin.php';
				}
				$plugins = array();
				foreach ( (array) get_option( 'active_plugins', array() ) as $file ) {
					$data      = get_plugin_data( WP_PLUGIN_DIR . '/' . $file, false, false );
					$plugins[] = array(
						'slug'    => dirname( $file ),
						'version' => (string) $data['Version'],
						'dir'     => (string) realpath( WP_PLUGIN_DIR . '/' . dirname( $file ) ),
					);
				}
				$origin = untrailingslashit( home_url() );
				$rows   = array();
				foreach ( $wpdb->get_results( "SELECT ID, post_type, post_status, post_name, post_parent, post_modified_gmt, post_title, post_content FROM {$wpdb->posts} WHERE post_type <> 'revision' ORDER BY ID" ) as $post ) {
					$rows[] = implode( '|', array( $post->ID, $post->post_type, $post->post_status, $post->post_name, $post->post_parent, $post->post_modified_gmt, md5( str_replace( $origin, '', $post->post_title . "\n" . $post->post_content ) ) ) );
				}
				foreach ( $wpdb->get_results( "SELECT t.term_id, t.slug, tt.taxonomy, tt.parent, tt.count FROM {$wpdb->terms} t JOIN {$wpdb->term_taxonomy} tt ON tt.term_id = t.term_id ORDER BY tt.term_taxonomy_id" ) as $term ) {
					$rows[] = implode( '|', (array) $term );
				}
				foreach ( array( 'show_on_front', 'page_on_front', 'page_for_posts', 'permalink_structure', 'blogname', 'blogdescription', 'posts_per_page', 'timezone_string', 'gmt_offset' ) as $option ) {
					$rows[] = $option . '=' . str_replace( $origin, '', (string) get_option( $option ) );
				}
				$parent = wp_get_theme( get_template() );
				wp_send_json(
					array(
						'wordpress' => get_bloginfo( 'version' ),
						'php'       => PHP_VERSION,
						'parent'    => array( 'slug' => get_template(), 'version' => (string) $parent->get( 'Version' ), 'dir' => (string) realpath( get_template_directory() ) ),
						'plugins'   => $plugins,
						'muDir'     => (string) realpath( WPMU_PLUGIN_DIR ),
						'content'   => array(
							'posts'       => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'post' AND post_status = 'publish'" ),
							'fingerprint' => hash( 'sha256', implode( "\n", $rows ) ),
						),
					)
				);
			},
			0
		);
		return;
	}

	$json    = (string) base64_decode( $header( 'REQUEST' ), true );
	$request = json_decode( $json, true );
	if ( ! is_array( $request ) ) {
		$fail( 'X-Cr-Capture-Request is not base64 JSON.' );
	}
	$focus   = isset( $request['focus']['name'] ) ? $request['focus'] : null;
	$content = isset( $request['content'] ) ? (string) $request['content'] : null;
	$caps    = array_map( 'strval', (array) ( $request['caps'] ?? array() ) );
	$refused = array_diff( $caps, array( __ALLOWED_CAPS__ ) );
	if ( $refused ) {
		$fail( 'the capability ' . implode( ', ', $refused ) . ' is not on the allowlist (__ALLOWED_CAPS_TEXT__).' );
	}

	$matches = static function ( array $block, array $wanted ): bool {
		if ( ( $block['blockName'] ?? null ) !== $wanted['name'] ) {
			return false;
		}
		foreach ( (array) ( $wanted['attrs'] ?? array() ) as $key => $value ) {
			if ( ( $block['attrs'][ $key ] ?? null ) !== $value ) {
				return false;
			}
		}
		return true;
	};
	$raw = static fn( string $html ): array => array( 'blockName' => null, 'attrs' => array(), 'innerBlocks' => array(), 'innerHTML' => $html, 'innerContent' => array( $html ) );

	$wanted_block_with_its_wrappers = static function ( array $blocks, array $wanted, bool $mark ) use ( &$wanted_block_with_its_wrappers, $matches, $raw ): ?array {
		foreach ( $blocks as $block ) {
			if ( $matches( $block, $wanted ) ) {
				return $mark ? array( $raw( '__BEGIN__' ), $block, $raw( '__END__' ) ) : array( $block );
			}
			$kept = empty( $block['innerBlocks'] ) ? null : $wanted_block_with_its_wrappers( $block['innerBlocks'], $wanted, $mark );
			if ( null === $kept ) {
				continue;
			}
			$open                  = is_string( $block['innerContent'][0] ?? null ) ? $block['innerContent'][0] : '';
			$last                  = end( $block['innerContent'] );
			$block['innerBlocks']  = $kept;
			$block['innerContent'] = array_merge( array( $open ), array_fill( 0, count( $kept ), null ), array( is_string( $last ) ? $last : '' ) );
			return array( $block );
		}
		return null;
	};

	// Everything below hangs off template_include, which WordPress reaches for a themed page only:
	// never for the REST API, admin, AJAX, XML-RPC, cron or a feed.
	add_filter(
		'template_include',
		static function ( $template ) use ( $json, $focus, $content, $caps, $wanted_block_with_its_wrappers, $fail ) {
			if ( ! is_string( $template ) || ! str_ends_with( $template, 'template-canvas.php' ) ) {
				$fail( 'this route did not resolve to a block template.' );
			}
			$census = array( 'template' => (string) ( $GLOBALS['_wp_current_template_id'] ?? '' ), 'rendered' => array() );
			$wanted = null !== $content ? array( 'name' => 'core/post-content' ) : $focus;
			if ( null !== $wanted ) {
				$pruned = $wanted_block_with_its_wrappers( parse_blocks( (string) $GLOBALS['_wp_current_template_content'] ), $wanted, null === $content );
				if ( null === $pruned ) {
					$fail( 'the template ' . $census['template'] . ' holds no block ' . wp_json_encode( $wanted ) . ' outside a template part.' );
				}
				// The template id stays, so get_the_block_template_html() still starts the main loop on a singular route.
				$GLOBALS['_wp_current_template_content'] = serialize_blocks( $pruned );
			}
			if ( null !== $content ) {
				foreach ( $GLOBALS['wp_query']->posts as $post ) {
					$post->post_content = '__BEGIN__' . $content . '__END__';
				}
			}
			if ( $caps ) {
				add_filter(
					'user_has_cap',
					static fn( $all, $required, $args, $user ) => 0 === (int) $user->ID ? array_merge( (array) $all, array_fill_keys( $caps, true ) ) : $all,
					PHP_INT_MAX,
					4
				);
			}
			add_filter(
				'render_block',
				static function ( $html, $block ) use ( &$census ) {
					$name = (string) ( $block['blockName'] ?? '' );
					$keys = array();
					if ( 'core/pattern' === $name ) {
						$keys[] = 'pattern:' . ( $block['attrs']['slug'] ?? '' );
					} elseif ( 'core/template-part' === $name ) {
						$keys[] = 'part:' . ( $block['attrs']['slug'] ?? '' );
					} elseif ( str_starts_with( $name, 'courtneyr/' ) ) {
						$keys[] = 'block:' . $name;
					}
					if ( isset( $block['attrs']['metadata']['patternName'] ) ) {
						$keys[] = 'pattern:' . $block['attrs']['metadata']['patternName'];
					}
					foreach ( $keys as $key ) {
						$census['rendered'][ $key ]['calls'] = 1 + ( $census['rendered'][ $key ]['calls'] ?? 0 );
						$census['rendered'][ $key ]['empty'] = ( '' === trim( (string) $html ) ? 1 : 0 ) + ( $census['rendered'][ $key ]['empty'] ?? 0 );
					}
					return $html;
				},
				PHP_INT_MAX,
				2
			);
			// template-canvas.php renders every block before it prints <head>, so the census is complete in wp_head.
			add_action(
				'wp_head',
				static function () use ( &$census, $json ): void {
					header(
						'X-Cr-Capture: ' . wp_json_encode(
							array(
								'harness'  => basename( __FILE__ ),
								'request'  => hash( 'sha256', $json ),
								'template' => $census['template'],
								'rendered' => (object) $census['rendered'],
							)
						)
					);
				},
				0
			);
			// template-canvas.php prints the doctype before wp_head(), and the census header goes out there.
			ob_start();
			return $template;
		},
		PHP_INT_MAX
	);
} )();
`
	.replaceAll( '__BEGIN__', MARK_BEGIN )
	.replaceAll( '__END__', MARK_END )
	.replaceAll( '__ALLOWED_CAPS__', ALLOWED_CAPS.map( ( cap ) => `'${ cap }'` ).join( ', ' ) )
	.replaceAll( '__ALLOWED_CAPS_TEXT__', ALLOWED_CAPS.join( ', ' ) );

const KEY_DIR_PREFIX = 'cr-capture-key-';
const muPluginsDir = ( sitePath ) => path.join( sitePath, 'wp-content', 'mu-plugins' );
const isHarness = ( name ) => /^cr-capture-harness-[0-9a-f]{12}\.php$/.test( name );

export function removeHarness( sitePath ) {
	for ( const name of fs.readdirSync( muPluginsDir( sitePath ) ).filter( isHarness ) ) {
		const file = path.join( muPluginsDir( sitePath ), name );
		const keyDir = new RegExp( `file_get_contents\\( '([^']*/${ KEY_DIR_PREFIX }[^'/]+)/hash' \\)` ).exec( fs.readFileSync( file, 'utf8' ) );
		if ( keyDir ) {
			fs.rmSync( keyDir[ 1 ], { recursive: true, force: true } );
		}
		fs.rmSync( file, { force: true } );
	}
}

export function installHarness( sitePath, baseUrl ) {
	removeHarness( sitePath );
	const key = crypto.randomBytes( 32 ).toString( 'hex' );
	const keyDir = fs.mkdtempSync( path.join( fs.realpathSync( os.tmpdir() ), KEY_DIR_PREFIX ) );
	const keyHashFile = path.join( keyDir, 'hash' );
	if ( /['\\]/.test( keyHashFile ) || keyHashFile.startsWith( fs.realpathSync( sitePath ) + path.sep ) ) {
		throw new Error( `the temporary directory ${ keyDir } cannot hold the key hash: it is inside the site or its path needs escaping.` );
	}
	fs.writeFileSync( keyHashFile, sha256( key ), { mode: 0o600 } );
	const php = PHP.replaceAll( '__KEY_HASH_FILE__', keyHashFile ).replaceAll( '__HOST__', new URL( baseUrl ).host );
	// PHP runs a changed file's old code for about 2 seconds (verify SKILL.md, checkout-unchanged), so each version gets its own name.
	const file = `cr-capture-harness-${ sha256( php ).slice( 0, 12 ) }.php`;
	fs.writeFileSync( path.join( muPluginsDir( sitePath ), file ), php, { flag: 'wx' } );
	return { key, file, keyHashFile };
}

export function harnessHeaders( key, request ) {
	if ( request === 'site' ) {
		return { headers: { 'X-Cr-Capture-Key': key, 'X-Cr-Capture-Probe': 'site' }, requestHash: null };
	}
	const json = JSON.stringify( request );
	return { headers: { 'X-Cr-Capture-Key': key, 'X-Cr-Capture-Request': Buffer.from( json ).toString( 'base64' ) }, requestHash: sha256( json ) };
}
