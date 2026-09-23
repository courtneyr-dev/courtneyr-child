<?php
/**
 * Title: Representative h-card
 * Slug: courtneyr-child/cr-hcard
 * Categories: cr-indieweb
 * Description: The site's IndieWeb identity card (name, canonical URL, photo) for the footer. Rendered at request time so the URL follows the site.
 * Inserter: yes
 *
 * @package CourtneyrChild
 */

$cr_hcard_name  = get_the_author_meta( 'display_name', 1 );
$cr_hcard_name  = '' !== (string) $cr_hcard_name ? $cr_hcard_name : get_bloginfo( 'name' );
$cr_hcard_photo = get_site_icon_url( 96 );
?>
<!-- wp:html -->
<p class="h-card cr-hcard has-text-align-center">
	<?php if ( '' !== (string) $cr_hcard_photo ) : ?><img class="u-photo cr-hcard__photo" src="<?php echo esc_url( $cr_hcard_photo ); ?>" alt="" aria-hidden="true" role="presentation" width="24" height="24" loading="lazy" /><?php endif; ?>
	<a class="u-url u-uid p-name" rel="me" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php echo esc_html( $cr_hcard_name ); ?></a>
	<span class="p-note"><?php echo esc_html( get_bloginfo( 'description' ) ); ?></span>
</p>
<!-- /wp:html -->
