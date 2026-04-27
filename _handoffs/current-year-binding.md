# TODO: register a `current-year` block-binding source

The footer colophon currently hardcodes "© 2026" because `wp:post-date {"format":"Y"}`
inside a template part that renders outside a single-post context falls back to the
queried object's date — which on archive pages can be the oldest post (2012 for
courtneyr.dev's 18-year-old install).

The clean fix is a custom block-binding source. ~10 lines of PHP:

```php
// inc/block-bindings.php
add_action( 'init', function () {
    register_block_bindings_source( 'courtneyr/current-year', array(
        'label'              => __( 'Current Year', 'courtneyr-child' ),
        'get_value_callback' => static function () {
            return date( 'Y' );
        },
    ) );
} );
```

Then in `parts/footer.html` replace the literal `2026` with:

```html
<!-- wp:paragraph {"metadata":{"bindings":{"content":{"source":"courtneyr/current-year"}}},"align":"center"} -->
<p class="has-text-align-center">© <span>2026</span> Courtney Robertson · Built with WordPress · Powered by the <a href="https://indieweb.org/">IndieWeb</a></p>
<!-- /wp:paragraph -->
```

(The `<span>2026</span>` is a fallback for the editor; the binding overrides on render.)

Block bindings are WP 6.5+. Confirm version requirement is fine before deploying.
