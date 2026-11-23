<?php
/**
 * Local Studio only, temporary: copy into wp-content/mu-plugins for a preview run, delete after.
 *
 * ?cr_r2_login=1 makes the request the first administrator's (so WordPress serves that user's
 * autosave as the preview). ?cr_r2_preview_of=<post ID>&cr_r2_login=1 prints the post's real
 * preview link, nonce included, as plain text.
 */
if ( 'local' !== wp_get_environment_type() || empty( $_GET['cr_r2_login'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	return;
}
add_filter(
	'determine_current_user',
	static function () {
		return (int) ( get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) )[0] ?? 0 );
	},
	99
);
add_action(
	'init',
	static function () {
		$id = (int) ( $_GET['cr_r2_preview_of'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( $id > 0 ) {
			header( 'Content-Type: text/plain' );
			echo esc_url_raw( add_query_arg( 'cr_r2_login', '1', get_preview_post_link( $id ) ) );
			exit;
		}
	}
);
