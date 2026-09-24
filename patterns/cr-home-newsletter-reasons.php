<?php
/**
 * Title: Newsletter reasons (four panels)
 * Slug: courtneyr-child/cr-home-newsletter-reasons
 * Categories: cr-zine, cr-sections
 * Description: Homepage “How this newsletter can help you grow”: intro with the Every Saturday label, four audience panels with line drawings, and the personalized-guidance contact row. Insert inside the homepage's blue newsletter section; the section keeps its own background and torn edges.
 * Keywords: home, newsletter, reasons, panels
 * Viewport Width: 1200
 * Block Types: core/post-content
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );
?>
<!-- wp:group {"templateLock":"all","lock":{"move":true,"remove":true},"metadata":{"name":"Newsletter reasons"},"align":"wide","className":"cr-reasons","layout":{"type":"default"}} -->
<div class="wp-block-group alignwide cr-reasons">

	<!-- wp:group {"metadata":{"name":"Intro"},"className":"cr-reasons__intro","layout":{"type":"default"}} -->
	<div class="wp-block-group cr-reasons__intro">

		<!-- wp:group {"className":"cr-reasons__intro-title","layout":{"type":"default"}} -->
		<div class="wp-block-group cr-reasons__intro-title">
			<!-- wp:group {"className":"cr-reasons__eyebrow","layout":{"type":"flex","flexWrap":"wrap","verticalAlignment":"center"}} -->
			<div class="wp-block-group cr-reasons__eyebrow">
				<!-- wp:html -->
				<div class="cr-reasons__edition"><?php esc_html_e( 'Every Saturday', 'courtneyr-child' ); ?></div>
				<!-- /wp:html -->

				<!-- wp:paragraph {"className":"cr-reasons__kicker"} -->
				<p class="cr-reasons__kicker"><?php esc_html_e( 'Free weekly newsletter', 'courtneyr-child' ); ?></p>
				<!-- /wp:paragraph -->
			</div>
			<!-- /wp:group -->

			<!-- wp:heading {"level":2,"anchor":"h-how-this-newsletter-can-help-you-grow","className":"cr-reasons__title","fontFamily":"accent"} -->
			<h2 class="wp-block-heading cr-reasons__title has-accent-font-family" id="h-how-this-newsletter-can-help-you-grow"><?php echo wp_kses_post( sprintf( /* translators: %s: the highlighted word "grow" */ __( 'How this newsletter can help you %s', 'courtneyr-child' ), '<span class="cr-highlight">' . esc_html__( 'grow', 'courtneyr-child' ) . '</span>' ) ); ?></h2>
			<!-- /wp:heading -->
		</div>
		<!-- /wp:group -->

		<!-- wp:group {"className":"cr-reasons__intro-copy","layout":{"type":"default"}} -->
		<div class="wp-block-group cr-reasons__intro-copy">
			<!-- wp:paragraph {"className":"cr-reasons__lede"} -->
			<p class="cr-reasons__lede"><?php esc_html_e( 'Every Saturday, I send a free weekly newsletter packed with practical tips, community insights, and real-world strategies for open source contributors, WordPress developers, educators, developer advocates, learners, and leaders alike.', 'courtneyr-child' ); ?></p>
			<!-- /wp:paragraph -->

			<!-- wp:paragraph {"className":"cr-reasons__hand"} -->
			<p class="cr-reasons__hand"><?php esc_html_e( 'Learn. Share. Keep going.', 'courtneyr-child' ); ?></p>
			<!-- /wp:paragraph -->
		</div>
		<!-- /wp:group -->

	</div>
	<!-- /wp:group -->

	<!-- wp:group {"metadata":{"name":"Reasons"},"className":"cr-reasons__panels","layout":{"type":"grid","columnCount":2}} -->
	<div class="wp-block-group cr-reasons__panels">

		<!-- wp:group {"metadata":{"name":"01 Contributors"},"className":"cr-reasons__panel","layout":{"type":"default"}} -->
		<div class="wp-block-group cr-reasons__panel">
			<!-- wp:group {"className":"cr-reasons__audience","layout":{"type":"flex","flexWrap":"nowrap","verticalAlignment":"stretch"}} -->
			<div class="wp-block-group cr-reasons__audience">
				<!-- wp:paragraph {"className":"cr-reasons__audience-name"} -->
				<p class="cr-reasons__audience-name"><?php esc_html_e( 'Contributors', 'courtneyr-child' ); ?></p>
				<!-- /wp:paragraph -->
			</div>
			<!-- /wp:group -->

			<!-- wp:group {"className":"cr-reasons__panel-body","layout":{"type":"default"}} -->
			<div class="wp-block-group cr-reasons__panel-body">
				<!-- wp:group {"className":"cr-reasons__panel-copy","layout":{"type":"default"}} -->
				<div class="wp-block-group cr-reasons__panel-copy">
					<!-- wp:heading {"level":3,"className":"cr-reasons__panel-title","fontFamily":"accent"} -->
					<h3 class="wp-block-heading cr-reasons__panel-title has-accent-font-family"><?php esc_html_e( 'Keep contributing.', 'courtneyr-child' ); ?></h3>
					<!-- /wp:heading -->

					<!-- wp:paragraph {"className":"cr-reasons__panel-text"} -->
					<p class="cr-reasons__panel-text"><?php esc_html_e( 'Explore sustainable funding and community support so you can keep making an impact.', 'courtneyr-child' ); ?></p>
					<!-- /wp:paragraph -->
				</div>
				<!-- /wp:group -->

				<!-- wp:group {"className":"cr-reasons__drawing cr-reasons__drawing--01","templateLock":"all","layout":{"type":"default"}} -->
				<div class="wp-block-group cr-reasons__drawing cr-reasons__drawing--01"></div>
				<!-- /wp:group -->
			</div>
			<!-- /wp:group -->
		</div>
		<!-- /wp:group -->

		<!-- wp:group {"metadata":{"name":"02 Educators"},"className":"cr-reasons__panel","layout":{"type":"default"}} -->
		<div class="wp-block-group cr-reasons__panel">
			<!-- wp:group {"className":"cr-reasons__audience","layout":{"type":"flex","flexWrap":"nowrap","verticalAlignment":"stretch"}} -->
			<div class="wp-block-group cr-reasons__audience">
				<!-- wp:paragraph {"className":"cr-reasons__audience-name"} -->
				<p class="cr-reasons__audience-name"><?php esc_html_e( 'Educators', 'courtneyr-child' ); ?></p>
				<!-- /wp:paragraph -->
			</div>
			<!-- /wp:group -->

			<!-- wp:group {"className":"cr-reasons__panel-body","layout":{"type":"default"}} -->
			<div class="wp-block-group cr-reasons__panel-body">
				<!-- wp:group {"className":"cr-reasons__panel-copy","layout":{"type":"default"}} -->
				<div class="wp-block-group cr-reasons__panel-copy">
					<!-- wp:heading {"level":3,"className":"cr-reasons__panel-title","fontFamily":"accent"} -->
					<h3 class="wp-block-heading cr-reasons__panel-title has-accent-font-family"><?php esc_html_e( 'Make learning click.', 'courtneyr-child' ); ?></h3>
					<!-- /wp:heading -->

					<!-- wp:paragraph {"className":"cr-reasons__panel-text"} -->
					<p class="cr-reasons__panel-text"><?php esc_html_e( 'Bring practical teaching methods, collaborative activities, and more inclusive learning into your classroom or community.', 'courtneyr-child' ); ?></p>
					<!-- /wp:paragraph -->
				</div>
				<!-- /wp:group -->

				<!-- wp:group {"className":"cr-reasons__drawing cr-reasons__drawing--02","templateLock":"all","layout":{"type":"default"}} -->
				<div class="wp-block-group cr-reasons__drawing cr-reasons__drawing--02"></div>
				<!-- /wp:group -->
			</div>
			<!-- /wp:group -->
		</div>
		<!-- /wp:group -->

		<!-- wp:group {"metadata":{"name":"03 Leaders"},"className":"cr-reasons__panel","layout":{"type":"default"}} -->
		<div class="wp-block-group cr-reasons__panel">
			<!-- wp:group {"className":"cr-reasons__audience","layout":{"type":"flex","flexWrap":"nowrap","verticalAlignment":"stretch"}} -->
			<div class="wp-block-group cr-reasons__audience">
				<!-- wp:paragraph {"className":"cr-reasons__audience-name"} -->
				<p class="cr-reasons__audience-name"><?php esc_html_e( 'Leaders', 'courtneyr-child' ); ?></p>
				<!-- /wp:paragraph -->
			</div>
			<!-- /wp:group -->

			<!-- wp:group {"className":"cr-reasons__panel-body","layout":{"type":"default"}} -->
			<div class="wp-block-group cr-reasons__panel-body">
				<!-- wp:group {"className":"cr-reasons__panel-copy","layout":{"type":"default"}} -->
				<div class="wp-block-group cr-reasons__panel-copy">
					<!-- wp:heading {"level":3,"className":"cr-reasons__panel-title","fontFamily":"accent"} -->
					<h3 class="wp-block-heading cr-reasons__panel-title has-accent-font-family"><?php esc_html_e( 'Make room for open source.', 'courtneyr-child' ); ?></h3>
					<!-- /wp:heading -->

					<!-- wp:paragraph {"className":"cr-reasons__panel-text"} -->
					<p class="cr-reasons__panel-text"><?php esc_html_e( 'Build a case for funding, support your contributors, and help your team take part in the wider community.', 'courtneyr-child' ); ?></p>
					<!-- /wp:paragraph -->
				</div>
				<!-- /wp:group -->

				<!-- wp:group {"className":"cr-reasons__drawing cr-reasons__drawing--03","templateLock":"all","layout":{"type":"default"}} -->
				<div class="wp-block-group cr-reasons__drawing cr-reasons__drawing--03"></div>
				<!-- /wp:group -->
			</div>
			<!-- /wp:group -->
		</div>
		<!-- /wp:group -->

		<!-- wp:group {"metadata":{"name":"04 Developers"},"className":"cr-reasons__panel","layout":{"type":"default"}} -->
		<div class="wp-block-group cr-reasons__panel">
			<!-- wp:group {"className":"cr-reasons__audience","layout":{"type":"flex","flexWrap":"nowrap","verticalAlignment":"stretch"}} -->
			<div class="wp-block-group cr-reasons__audience">
				<!-- wp:paragraph {"className":"cr-reasons__audience-name"} -->
				<p class="cr-reasons__audience-name"><?php esc_html_e( 'Developers', 'courtneyr-child' ); ?></p>
				<!-- /wp:paragraph -->
			</div>
			<!-- /wp:group -->

			<!-- wp:group {"className":"cr-reasons__panel-body","layout":{"type":"default"}} -->
			<div class="wp-block-group cr-reasons__panel-body">
				<!-- wp:group {"className":"cr-reasons__panel-copy","layout":{"type":"default"}} -->
				<div class="wp-block-group cr-reasons__panel-copy">
					<!-- wp:heading {"level":3,"className":"cr-reasons__panel-title","fontFamily":"accent"} -->
					<h3 class="wp-block-heading cr-reasons__panel-title has-accent-font-family"><?php esc_html_e( 'Keep your skills moving.', 'courtneyr-child' ); ?></h3>
					<!-- /wp:heading -->

					<!-- wp:paragraph {"className":"cr-reasons__panel-text"} -->
					<p class="cr-reasons__panel-text"><?php esc_html_e( 'Explore WordPress and the open web, deepen your technical skills, and put what you learn into practice.', 'courtneyr-child' ); ?></p>
					<!-- /wp:paragraph -->
				</div>
				<!-- /wp:group -->

				<!-- wp:group {"className":"cr-reasons__drawing cr-reasons__drawing--04","templateLock":"all","layout":{"type":"default"}} -->
				<div class="wp-block-group cr-reasons__drawing cr-reasons__drawing--04"></div>
				<!-- /wp:group -->
			</div>
			<!-- /wp:group -->
		</div>
		<!-- /wp:group -->

	</div>
	<!-- /wp:group -->

	<!-- wp:group {"metadata":{"name":"Personalized guidance"},"className":"cr-reasons__contact","layout":{"type":"flex","flexWrap":"wrap","justifyContent":"space-between","verticalAlignment":"center"}} -->
	<div class="wp-block-group cr-reasons__contact">
		<!-- wp:group {"className":"cr-reasons__contact-copy","layout":{"type":"default"}} -->
		<div class="wp-block-group cr-reasons__contact-copy">
			<!-- wp:heading {"level":3,"className":"cr-reasons__contact-title","fontFamily":"accent"} -->
			<h3 class="wp-block-heading cr-reasons__contact-title has-accent-font-family"><?php esc_html_e( 'Looking for personalized guidance?', 'courtneyr-child' ); ?></h3>
			<!-- /wp:heading -->

			<!-- wp:paragraph {"className":"cr-reasons__contact-text"} -->
			<p class="cr-reasons__contact-text"><?php esc_html_e( 'I can help with developer relations, web development curriculum, and open source involvement.', 'courtneyr-child' ); ?></p>
			<!-- /wp:paragraph -->
		</div>
		<!-- /wp:group -->

		<!-- wp:buttons {"className":"cr-reasons__contact-action"} -->
		<div class="wp-block-buttons cr-reasons__contact-action">
			<!-- wp:button {"className":"is-style-cr-cta"} -->
			<div class="wp-block-button is-style-cr-cta"><a class="wp-block-button__link wp-element-button" href="<?php echo esc_url( home_url( '/contact/' ) ); ?>"><?php esc_html_e( 'Contact me', 'courtneyr-child' ); ?></a></div>
			<!-- /wp:button -->
		</div>
		<!-- /wp:buttons -->
	</div>
	<!-- /wp:group -->

</div>
<!-- /wp:group -->
