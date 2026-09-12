<?php
/**
 * Title: Newsletter reasons (four panels)
 * Slug: courtneyr-child/cr-home-newsletter-reasons
 * Categories: cr-zine
 * Description: Homepage “How this newsletter can help you grow”: intro with the Every Saturday label, four audience panels with line drawings, and the personalized-guidance contact row. Insert inside the homepage's blue newsletter section; the section keeps its own background and torn edges.
 * Keywords: home, newsletter, reasons, panels
 * Viewport Width: 1200
 * Block Types: core/post-content
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );
?>
<!-- wp:group {"metadata":{"name":"Newsletter reasons"},"align":"wide","className":"cr-reasons","layout":{"type":"default"}} -->
<div class="wp-block-group alignwide cr-reasons">

	<!-- wp:group {"metadata":{"name":"Intro"},"className":"cr-reasons__intro","layout":{"type":"default"}} -->
	<div class="wp-block-group cr-reasons__intro">

		<!-- wp:group {"className":"cr-reasons__intro-title","layout":{"type":"default"}} -->
		<div class="wp-block-group cr-reasons__intro-title">
			<!-- wp:group {"className":"cr-reasons__eyebrow","layout":{"type":"flex","flexWrap":"wrap","verticalAlignment":"center"}} -->
			<div class="wp-block-group cr-reasons__eyebrow">
				<!-- wp:paragraph {"className":"cr-reasons__edition"} -->
				<p class="cr-reasons__edition">Every Saturday</p>
				<!-- /wp:paragraph -->

				<!-- wp:paragraph {"className":"cr-reasons__kicker"} -->
				<p class="cr-reasons__kicker">Free weekly newsletter</p>
				<!-- /wp:paragraph -->
			</div>
			<!-- /wp:group -->

			<!-- wp:heading {"level":2,"anchor":"h-how-this-newsletter-can-help-you-grow","className":"cr-reasons__title","fontFamily":"accent"} -->
			<h2 class="wp-block-heading cr-reasons__title has-accent-font-family" id="h-how-this-newsletter-can-help-you-grow">How this newsletter can help you <span class="cr-highlight">grow</span></h2>
			<!-- /wp:heading -->
		</div>
		<!-- /wp:group -->

		<!-- wp:group {"className":"cr-reasons__intro-copy","layout":{"type":"default"}} -->
		<div class="wp-block-group cr-reasons__intro-copy">
			<!-- wp:paragraph {"className":"cr-reasons__lede"} -->
			<p class="cr-reasons__lede">Every Saturday, I send a free weekly newsletter packed with practical tips, community insights, and real-world strategies for open source contributors, WordPress developers, educators, developer advocates, learners, and leaders alike.</p>
			<!-- /wp:paragraph -->

			<!-- wp:paragraph {"className":"cr-reasons__hand"} -->
			<p class="cr-reasons__hand">Learn. Share. Keep going.</p>
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
				<!-- wp:paragraph {"className":"cr-reasons__number"} -->
				<p class="cr-reasons__number">01</p>
				<!-- /wp:paragraph -->

				<!-- wp:paragraph {"className":"cr-reasons__audience-name"} -->
				<p class="cr-reasons__audience-name">Contributors</p>
				<!-- /wp:paragraph -->
			</div>
			<!-- /wp:group -->

			<!-- wp:group {"className":"cr-reasons__panel-body","layout":{"type":"default"}} -->
			<div class="wp-block-group cr-reasons__panel-body">
				<!-- wp:group {"className":"cr-reasons__panel-copy","layout":{"type":"default"}} -->
				<div class="wp-block-group cr-reasons__panel-copy">
					<!-- wp:heading {"level":3,"className":"cr-reasons__panel-title","fontFamily":"accent"} -->
					<h3 class="wp-block-heading cr-reasons__panel-title has-accent-font-family">Keep contributing.</h3>
					<!-- /wp:heading -->

					<!-- wp:paragraph {"className":"cr-reasons__panel-text"} -->
					<p class="cr-reasons__panel-text">Explore sustainable funding and community support so you can keep making an impact.</p>
					<!-- /wp:paragraph -->
				</div>
				<!-- /wp:group -->

				<!-- wp:html -->
				<svg class="cr-reasons__drawing" viewBox="-5 -3 108 110" aria-hidden="true" focusable="false"><path class="spot" d="M11 64 34 57 83 63 80 77 20 79Z"/><path d="M23 55 46 36 74 53M25 62 43 74 71 61"/><circle cx="19" cy="59" r="10"/><circle cx="49" cy="28" r="11"/><circle cx="80" cy="58" r="10"/><path d="m35 17 3-8m24 10 7-6m24 33 5-3M6 43 1 38M45 48l4 8 7-7"/></svg>
				<!-- /wp:html -->
			</div>
			<!-- /wp:group -->
		</div>
		<!-- /wp:group -->

		<!-- wp:group {"metadata":{"name":"02 Educators"},"className":"cr-reasons__panel","layout":{"type":"default"}} -->
		<div class="wp-block-group cr-reasons__panel">
			<!-- wp:group {"className":"cr-reasons__audience","layout":{"type":"flex","flexWrap":"nowrap","verticalAlignment":"stretch"}} -->
			<div class="wp-block-group cr-reasons__audience">
				<!-- wp:paragraph {"className":"cr-reasons__number"} -->
				<p class="cr-reasons__number">02</p>
				<!-- /wp:paragraph -->

				<!-- wp:paragraph {"className":"cr-reasons__audience-name"} -->
				<p class="cr-reasons__audience-name">Educators</p>
				<!-- /wp:paragraph -->
			</div>
			<!-- /wp:group -->

			<!-- wp:group {"className":"cr-reasons__panel-body","layout":{"type":"default"}} -->
			<div class="wp-block-group cr-reasons__panel-body">
				<!-- wp:group {"className":"cr-reasons__panel-copy","layout":{"type":"default"}} -->
				<div class="wp-block-group cr-reasons__panel-copy">
					<!-- wp:heading {"level":3,"className":"cr-reasons__panel-title","fontFamily":"accent"} -->
					<h3 class="wp-block-heading cr-reasons__panel-title has-accent-font-family">Make learning click.</h3>
					<!-- /wp:heading -->

					<!-- wp:paragraph {"className":"cr-reasons__panel-text"} -->
					<p class="cr-reasons__panel-text">Bring practical teaching methods, collaborative activities, and more inclusive learning into your classroom or community.</p>
					<!-- /wp:paragraph -->
				</div>
				<!-- /wp:group -->

				<!-- wp:html -->
				<svg class="cr-reasons__drawing" viewBox="-5 -3 108 110" aria-hidden="true" focusable="false"><path class="spot" d="m48 39 30-12 10 47-35 10Z"/><path d="M47 34c-13-7-24-7-37-4l3 49c13-4 26-1 36 6 12-8 24-10 37-6l3-49c-15-4-27-2-42 4Z"/><path d="m47 34 2 51M21 42l15 2m-15 8 15 2m-14 8 14 3m23-22 17-4m-17 14 16-4m-15 14 14-4M45 16V7m-13 14-7-7m34 6 7-7"/></svg>
				<!-- /wp:html -->
			</div>
			<!-- /wp:group -->
		</div>
		<!-- /wp:group -->

		<!-- wp:group {"metadata":{"name":"03 Leaders"},"className":"cr-reasons__panel","layout":{"type":"default"}} -->
		<div class="wp-block-group cr-reasons__panel">
			<!-- wp:group {"className":"cr-reasons__audience","layout":{"type":"flex","flexWrap":"nowrap","verticalAlignment":"stretch"}} -->
			<div class="wp-block-group cr-reasons__audience">
				<!-- wp:paragraph {"className":"cr-reasons__number"} -->
				<p class="cr-reasons__number">03</p>
				<!-- /wp:paragraph -->

				<!-- wp:paragraph {"className":"cr-reasons__audience-name"} -->
				<p class="cr-reasons__audience-name">Leaders</p>
				<!-- /wp:paragraph -->
			</div>
			<!-- /wp:group -->

			<!-- wp:group {"className":"cr-reasons__panel-body","layout":{"type":"default"}} -->
			<div class="wp-block-group cr-reasons__panel-body">
				<!-- wp:group {"className":"cr-reasons__panel-copy","layout":{"type":"default"}} -->
				<div class="wp-block-group cr-reasons__panel-copy">
					<!-- wp:heading {"level":3,"className":"cr-reasons__panel-title","fontFamily":"accent"} -->
					<h3 class="wp-block-heading cr-reasons__panel-title has-accent-font-family">Make room for open source.</h3>
					<!-- /wp:heading -->

					<!-- wp:paragraph {"className":"cr-reasons__panel-text"} -->
					<p class="cr-reasons__panel-text">Build a case for funding, support your contributors, and help your team take part in the wider community.</p>
					<!-- /wp:paragraph -->
				</div>
				<!-- /wp:group -->

				<!-- wp:html -->
				<svg class="cr-reasons__drawing" viewBox="-5 -3 108 110" aria-hidden="true" focusable="false"><path class="spot" d="m48 21 8 26 25 9-28 6-8 26-7-29-24-8 28-7Z"/><circle cx="49" cy="52" r="33"/><path d="m58 35-6 23-19 13 7-24Z"/><circle cx="46" cy="54" r="3"/><path d="M49 9V3m0 98v-8M7 52H1m96 0h-7M16 18l6 6m54 55 5 5"/></svg>
				<!-- /wp:html -->
			</div>
			<!-- /wp:group -->
		</div>
		<!-- /wp:group -->

		<!-- wp:group {"metadata":{"name":"04 Developers"},"className":"cr-reasons__panel","layout":{"type":"default"}} -->
		<div class="wp-block-group cr-reasons__panel">
			<!-- wp:group {"className":"cr-reasons__audience","layout":{"type":"flex","flexWrap":"nowrap","verticalAlignment":"stretch"}} -->
			<div class="wp-block-group cr-reasons__audience">
				<!-- wp:paragraph {"className":"cr-reasons__number"} -->
				<p class="cr-reasons__number">04</p>
				<!-- /wp:paragraph -->

				<!-- wp:paragraph {"className":"cr-reasons__audience-name"} -->
				<p class="cr-reasons__audience-name">Developers</p>
				<!-- /wp:paragraph -->
			</div>
			<!-- /wp:group -->

			<!-- wp:group {"className":"cr-reasons__panel-body","layout":{"type":"default"}} -->
			<div class="wp-block-group cr-reasons__panel-body">
				<!-- wp:group {"className":"cr-reasons__panel-copy","layout":{"type":"default"}} -->
				<div class="wp-block-group cr-reasons__panel-copy">
					<!-- wp:heading {"level":3,"className":"cr-reasons__panel-title","fontFamily":"accent"} -->
					<h3 class="wp-block-heading cr-reasons__panel-title has-accent-font-family">Keep your skills moving.</h3>
					<!-- /wp:heading -->

					<!-- wp:paragraph {"className":"cr-reasons__panel-text"} -->
					<p class="cr-reasons__panel-text">Explore WordPress and the open web, deepen your technical skills, and put what you learn into practice.</p>
					<!-- /wp:paragraph -->
				</div>
				<!-- /wp:group -->

				<!-- wp:html -->
				<svg class="cr-reasons__drawing" viewBox="-5 -3 108 110" aria-hidden="true" focusable="false"><path class="spot" d="m14 66 64-4 8 16-70 4Z"/><path d="M10 25 85 22 89 82 13 85Z M12 39l74-3"/><path d="m33 49-12 13 14 8m30-23 12 12-12 12m-12-26-8 29M22 31h1m8 0h1m8-1h1M75 9l2-6m13 13 7-3M5 65l-4 2"/></svg>
				<!-- /wp:html -->
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
			<h3 class="wp-block-heading cr-reasons__contact-title has-accent-font-family">Looking for personalized guidance?</h3>
			<!-- /wp:heading -->

			<!-- wp:paragraph {"className":"cr-reasons__contact-text"} -->
			<p class="cr-reasons__contact-text">I can help with developer relations, web development curriculum, and open source involvement.</p>
			<!-- /wp:paragraph -->
		</div>
		<!-- /wp:group -->

		<!-- wp:buttons {"className":"cr-reasons__contact-action"} -->
		<div class="wp-block-buttons cr-reasons__contact-action">
			<!-- wp:button {"className":"is-style-cr-cta"} -->
			<div class="wp-block-button is-style-cr-cta"><a class="wp-block-button__link wp-element-button" href="/contact/">Contact me</a></div>
			<!-- /wp:button -->
		</div>
		<!-- /wp:buttons -->
	</div>
	<!-- /wp:group -->

</div>
<!-- /wp:group -->
