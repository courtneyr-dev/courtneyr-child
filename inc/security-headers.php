<?php
/**
 * Security response headers for WordPress-rendered responses.
 *
 * Single source of truth for the site's security headers. The
 * site-performance-security plugin's send_headers block was removed in its
 * v1.7.0 in favor of this file, so exactly one layer emits the CSP.
 *
 * Response-path reality (verified July 2026): requests flow
 * Cloudflare -> Sucuri (courtneyr.dev edge) -> GoDaddy MWP gateway -> PHP.
 * PHP-emitted headers survive that chain (confirmed via archived live
 * responses), but responses answered before PHP — nginx-level 404s for
 * missing static files such as /404javascript.js — can only be covered by
 * Sucuri/GoDaddy edge configuration, never by theme code.
 *
 * CSP nonces are deliberately NOT used: page HTML is publicly cached for up
 * to 31 days (gateway + Cloudflare), so a cached page would carry a stale
 * nonce that can never match a freshly generated header. Hashes are held
 * back for the same reason 'unsafe-inline' remains in the enforced
 * script-src: third-party plugins (Perfmatters delay-JS bootstrap,
 * Complianz, wp-consent-api, Accessibility Checker, DesignSetGo, GoDaddy's
 * injected tccl tracker) print inline scripts, one of which (webmcp-bridge)
 * embeds a rotating REST nonce, so its content cannot be hashed. Per the
 * CSP spec, the presence of any hash disables 'unsafe-inline' in modern
 * browsers, so hashes cannot be mixed in as a partial step. The
 * report-only policy below drops 'unsafe-inline'/'unsafe-eval' to collect
 * the violation evidence needed before the enforced policy can follow.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace Courtneyr\Child\SecurityHeaders;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * HSTS value. includeSubDomains matches the value this site already served
 * in production (archived May 2026 responses); www.courtneyr.dev is a CNAME
 * to the apex and serves HTTPS. No preload: that is a separate long-lived
 * operational commitment.
 */
const HSTS_VALUE = 'max-age=31536000; includeSubDomains';

/**
 * Content-Security-Policy directives, enforced.
 *
 * Host lists come from the policy that ran in production for months (via the
 * site-performance-security plugin) plus a live resource inventory of the
 * rendered site, so every source here is known-needed:
 * - img1.wsimg.com / csp.secureserver.net: GoDaddy MWP-injected tccl
 *   analytics (script + beacon).
 * - google-analytics / googletagmanager: site analytics.
 * - youtube / vimeo / calendly / spotify: embeds.
 * - fonts.googleapis.com / fonts.gstatic.com: font fallback (primary fonts
 *   are self-hosted).
 * - img-src https:: IndieWeb post kinds render arbitrary external images
 *   (bookmarks, reposts, avatars); enumerating hosts is not maintainable.
 * - frame-ancestors 'self' pairs with X-Frame-Options: SAMEORIGIN below;
 *   the Site Editor / Customizer preview frames the front end same-origin.
 *   Both are skipped for /embed/ responses, which exist to be framed
 *   cross-origin (oEmbed / IndieWeb embeddability).
 * - worker-src is intentionally not enforced yet (falls back to script-src,
 *   the previously proven behavior); the report-only policy trials 'self'.
 */
const CSP_ENFORCED = array(
	'default-src'               => "'self'",
	'script-src'                => "'self' 'unsafe-inline' 'unsafe-eval' img1.wsimg.com www.google-analytics.com www.googletagmanager.com csp.secureserver.net www.youtube.com www.youtube-nocookie.com s.ytimg.com",
	'style-src'                 => "'self' 'unsafe-inline' fonts.googleapis.com",
	'img-src'                   => "'self' data: blob: https:",
	'font-src'                  => "'self' data: fonts.gstatic.com",
	'connect-src'               => "'self' www.google-analytics.com analytics.google.com region1.google-analytics.com csp.secureserver.net",
	'frame-src'                 => "'self' www.youtube.com youtube.com www.youtube-nocookie.com player.vimeo.com calendly.com open.spotify.com",
	'media-src'                 => "'self' www.youtube.com",
	'object-src'                => "'none'",
	'base-uri'                  => "'self'",
	'form-action'               => "'self'",
	'manifest-src'              => "'self'",
	'frame-ancestors'           => "'self'",
	'upgrade-insecure-requests' => '',
);

/**
 * Report-only overrides: the stricter policy being trialed before it can be
 * enforced. script-src drops 'unsafe-inline'/'unsafe-eval'; worker-src
 * trials 'self'. Violations surface in the browser console (Report-Only is
 * never allowed to break the page). Exit criteria: once the third-party
 * inline scripts listed in the file header are externalized or retired,
 * fold these values into CSP_ENFORCED and delete this constant.
 */
const CSP_REPORT_ONLY_OVERRIDES = array(
	'script-src' => "'self' img1.wsimg.com www.google-analytics.com www.googletagmanager.com csp.secureserver.net www.youtube.com www.youtube-nocookie.com s.ytimg.com",
	'worker-src' => "'self'",
);

/**
 * Build a CSP header value from a directive map.
 *
 * @param array $directives Directive name => source list ('' for valueless).
 * @return string Serialized policy.
 */
function build_policy( array $directives ): string {
	$parts = array();
	foreach ( $directives as $directive => $sources ) {
		$parts[] = '' === $sources ? $directive : $directive . ' ' . $sources;
	}
	return implode( '; ', $parts );
}

/**
 * Baseline headers shared by front end and login: MIME-sniffing protection
 * and HSTS. HSTS only ever goes out on HTTPS responses (on HTTP it is
 * ignored per spec, and this host 301s to HTTPS before WordPress runs).
 */
function send_baseline_headers(): void {
	header( 'X-Content-Type-Options: nosniff' );
	if ( is_ssl() ) {
		header( 'Strict-Transport-Security: ' . HSTS_VALUE );
	}
}

/**
 * Send security headers on front-end main requests (pages, archives, feeds,
 * and WordPress-rendered 404s — send_headers fires before handle_404, and
 * the later status_header() call only replaces the status line).
 *
 * Runs at priority 999 so its header() calls (replace = true) win over any
 * plugin that emits the same header earlier, keeping each header
 * single-valued instead of duplicated.
 *
 * Note: the main query has not run yet inside send_headers, so template
 * conditionals like is_embed() are not usable here; embed requests are
 * detected from the parsed query vars on the passed WP instance.
 *
 * @param \WP $wp Current WordPress environment instance (by reference).
 */
function send_frontend_headers( \WP $wp ): void {
	send_baseline_headers();

	header( 'Permissions-Policy: interest-cohort=(), browsing-topics=()' );

	// The Sucuri edge in front of courtneyr.dev injects
	// Referrer-Policy: strict-origin-when-cross-origin on every response,
	// and a server-side plugin emits the identical value at origin, so
	// clients were seeing the header twice. Drop the origin copy here
	// (runs last at priority 999); the edge copy remains the single
	// authoritative one. If the site ever moves off Sucuri, replace this
	// removal with an explicit header() call.
	header_remove( 'Referrer-Policy' );

	$csp = CSP_ENFORCED;

	// /embed/ responses exist to be iframed by other sites: no framing
	// restriction there. Everywhere else, CSP and X-Frame-Options stay
	// semantically aligned ('self' <=> SAMEORIGIN).
	if ( ! empty( $wp->query_vars['embed'] ) ) {
		unset( $csp['frame-ancestors'] );
		// A server-side plugin outside this repo also emits
		// X-Frame-Options: SAMEORIGIN; strip it here (this callback runs
		// last at priority 999) so /embed/ responses stay frameable
		// cross-origin, which is the whole point of the endpoint.
		header_remove( 'X-Frame-Options' );
	} else {
		header( 'X-Frame-Options: SAMEORIGIN' );
	}

	header( 'Content-Security-Policy: ' . build_policy( $csp ) );

	// upgrade-insecure-requests only exists for enforced policies (W3C
	// upgrade-insecure-requests spec §3.1): in a Report-Only header the
	// browser ignores it and Chrome logs a console error on every page
	// load, so it stays out of the trial policy.
	$csp_report_only = array_merge( $csp, CSP_REPORT_ONLY_OVERRIDES );
	unset( $csp_report_only['upgrade-insecure-requests'] );
	header( 'Content-Security-Policy-Report-Only: ' . build_policy( $csp_report_only ) );
}

/**
 * Login screen: core already sends X-Frame-Options: DENY here; add the
 * baseline pair so wp-login.php also carries nosniff + HSTS.
 */
function send_login_headers(): void {
	send_baseline_headers();
}

add_action( 'send_headers', __NAMESPACE__ . '\\send_frontend_headers', 999 );
add_action( 'login_init', __NAMESPACE__ . '\\send_login_headers' );
