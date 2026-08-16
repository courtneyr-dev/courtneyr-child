<?php
/**
 * Silence is golden — with security headers.
 *
 * Direct requests for this theme directory used to get a bare nginx 403
 * with no security headers: GoDaddy Managed WordPress rejects .htaccess
 * Header directives (see /html/.htaccess.log), so only PHP responses can
 * carry headers on this host. With an index file present, nginx executes
 * this instead of emitting its own 403, and we answer with the same
 * headers the rest of the site sends.
 *
 * frame-ancestors 'none' / DENY is safe here (unlike real pages, nothing
 * ever legitimately frames a directory stub).
 */
header( 'X-Content-Type-Options: nosniff' );
header( 'X-Frame-Options: DENY' );
header( 'Strict-Transport-Security: max-age=31536000; includeSubDomains' );
header( "Content-Security-Policy: default-src 'none'; base-uri 'none'; frame-ancestors 'none'" );
header( 'Referrer-Policy: strict-origin-when-cross-origin' );
http_response_code( 404 );
