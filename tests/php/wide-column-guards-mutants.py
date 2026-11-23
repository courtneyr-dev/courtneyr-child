#!/usr/bin/env python3
"""Mutants of the wide-column decision, run against the PHP tests in a scratch copy.

Usage: python3 tests/php/wide-column-guards-mutants.py <tree> [<tests-from-tree>]

<tree> supplies inc/ (the code to mutate). <tests-from-tree> supplies tests/php/
(defaults to <tree>), so the same mutants can be scored against an older test.
Nothing in either tree is written: each mutant is applied to a fresh copy in a
temporary directory. A mutant is killed when any listed test exits nonzero.
Needs CR_WP_CORE or ~/.wp-tests/wordpress for the HTML API.
"""
import os
import shutil
import subprocess
import sys
import tempfile

WCD = 'inc/single-wide-column.php'
READ = 'inc/single-read.php'
GUARDS = 'tests/php/wide-column-guards-test.php'
READ_TEST = 'tests/php/read-single-test.php'
FAV = 'inc/single-favorite.php'
CARD = 'inc/wide-column-card.php'
FAV_TEST = 'tests/php/favorite-single-test.php'
REP = 'inc/repost.php'
REP_TEST = 'tests/php/repost-test.php'

# name, file, needle, replacement, tests that should kill it.
MUTANTS = [
    # The reviewer's eight survivors (names from independent-review-pr338-ea05225.md).
    ('applies-always', WCD, "return null !== $found && $found['wide'];", 'return true;', [GUARDS]),
    ('applies-always (decision exists)', WCD, "return null !== $found && $found['wide'];", 'return null !== $found;', [GUARDS]),
    ('wide-default-true', WCD, "(bool) ( $found['wide'] ?? false )", "(bool) ( $found['wide'] ?? true )", [GUARDS]),
    ('filter-ignored', WCD, "(array) \\apply_filters( 'courtneyr_child_wide_column_kinds', KINDS )", 'KINDS', [GUARDS]),
    ('no-callable', WCD, 'isset( $kinds[ $kind ] ) && \\is_callable( $kinds[ $kind ] )', 'isset( $kinds[ $kind ] )', [GUARDS]),
    ('widen-no-class-check', WCD, "'core/post-content' === $name && in_array( 'single-post__content', $classes, true )", "'core/post-content' === $name", [GUARDS]),
    ('widen-header-no-class', WCD, "'core/group' === $name && in_array( 'single-post__header', $classes, true )", "'core/group' === $name", [GUARDS]),
    ('enqueue-always', WCD, 'if ( column_used() ) {', 'if ( true ) {', [GUARDS]),
    ('column-used-sticky-false', WCD, '$used = $used || $set;', '$used = $set;', [GUARDS]),
    # The reviewer's should-fix 2.
    ('key-no-postid', WCD, "$key     = $post->ID . ':' .", "$key     = 'p' . ':' .", [GUARDS]),
    # The ten the #338 test already killed.
    ('no-singular', WCD, "! \\is_singular( 'post' ) || ", '', [GUARDS]),
    ('no-main-query', WCD, '! \\is_main_query() || ', '', [GUARDS]),
    ('no-in-loop', WCD, ' || ! \\in_the_loop()', '', [GUARDS]),
    ('no-queried-id', WCD, '(int) \\get_queried_object_id() !== $post->ID || ', '', [GUARDS]),
    ('no-password', WCD, ' || \\post_password_required( $post )', '', [GUARDS]),
    ('key-no-md5', WCD, " . ':' . md5( $content );", ';', [GUARDS]),
    ('key-no-page', WCD, "(int) ( $GLOBALS['page'] ?? 1 ) . ':' . ", '', [GUARDS]),
    ('no-memo', WCD, 'if ( ! array_key_exists( $key, $memo ) ) {', 'if ( true ) {', [GUARDS]),
    ('data-ignores-kind', WCD, "null !== $found && $kind === $found['kind'] ? $found['data'] : null", "null !== $found ? $found['data'] : null", [GUARDS]),
    ('resolver-result-not-array-check', WCD, 'if ( is_array( $found ) ) {', 'if ( null !== $found ) {', [GUARDS]),
    # More of the hooks.
    ('widen-ignores-applies', WCD, "if ( ! ( $content || $header ) || ! applies() ) {", 'if ( ! ( $content || $header ) ) {', [GUARDS]),
    ('widen-no-align', WCD, "$block['attrs']['align']     = 'wide';", '', [GUARDS]),
    ('widen-no-marker-class', WCD, "trim( implode( ' ', $classes ) . ' cr-wide-column' )", "trim( implode( ' ', $classes ) )", [GUARDS]),
    ('widen-content-size-kept', WCD, "$layout['contentSize']       = '100%';", '', [GUARDS]),
    ('widen-content-no-column-used', WCD, '\t\tcolumn_used( true );\n', '', [GUARDS]),
    ('widen-header-uses-column', WCD, "$layout['contentSize'] = 'var(--wp--style--global--content-size)';", "$layout['contentSize'] = 'var(--wp--style--global--content-size)'; column_used( true );", [GUARDS]),
    ('widen-header-wide-size', WCD, "$layout['contentSize'] = 'var(--wp--style--global--content-size)';", "$layout['contentSize'] = '100%';", [GUARDS]),
    ('widen-not-hooked', WCD, "add_filter( 'render_block_data', __NAMESPACE__ . '\\\\widen' );", '', [GUARDS]),
    ('mark-header-no-class', WCD, "if ( ! in_array( 'single-post__header', $classes, true ) || ! applies() ) {", 'if ( ! applies() ) {', [GUARDS]),
    ('mark-header-ignores-applies', WCD, "if ( ! in_array( 'single-post__header', $classes, true ) || ! applies() ) {", "if ( ! in_array( 'single-post__header', $classes, true ) ) {", [GUARDS]),
    ('mark-header-one-arg', WCD, "__NAMESPACE__ . '\\\\mark_header', 10, 2 );", "__NAMESPACE__ . '\\\\mark_header', 10, 1 );", [GUARDS]),
    ('enqueue-priority-10', WCD, "__NAMESPACE__ . '\\\\enqueue_styles', 20 );", "__NAMESPACE__ . '\\\\enqueue_styles' );", [GUARDS]),
    ('enqueue-wrong-handle', WCD, "'courtneyr-wide-column',", "'courtneyr-wide',", [GUARDS]),
    # The isset line in the Read renderer.
    ('isset-line-removed', READ, "\t$page = isset( $page['block'], $page['record'] ) ? $page : null;\n", '', [READ_TEST]),
    ('isset-block-only', READ, "isset( $page['block'], $page['record'] )", "isset( $page['block'] )", [READ_TEST]),
    # The Favorite resolver and the sheet reading the decision.
    ('kinds-no-favorite', WCD, "\t'favorite' => '\\\\Courtneyr\\\\Child\\\\SingleFavorite\\\\page_sheet',\n", '', [GUARDS]),
    ('top-level-ignores-depth', WCD, "return 0 === $depth ? 'top' : 'nested';", "return 'top';", [GUARDS]),
    ('top-level-last-not-first', WCD, "\t\t\tif ( 'none' !== $found ) {\n\t\t\t\treturn $found;\n\t\t\t}\n", '', [GUARDS]),
    ('favorite-resolver-ignores-nesting', FAV, "'wide' => 'nested' !== $place,", "'wide' => true,", [FAV_TEST]),
    ('favorite-resolver-plain-narrow', FAV, "'wide' => 'nested' !== $place,", "'wide' => 'top' === $place,", [FAV_TEST]),
    ('favorite-no-flow-box', FAV, "\t\t$before = flow( (string) substr( $content, 0, $card_at[0] ) );\n\t\t$after  = flow( (string) substr( $content, $card_at[1] ) );", "\t\t$before = (string) substr( $content, 0, $card_at[0] );\n\t\t$after  = (string) substr( $content, $card_at[1] );", [FAV_TEST]),
    ('favorite-flow-when-narrow', FAV, '$card_at = is_array( $decided ) && \\Courtneyr\\Child\\SingleWideColumn\\applies() ?', '$card_at = is_array( $decided ) ?', [FAV_TEST]),
    ('favorite-flow-for-another-kind', FAV, '$card_at = is_array( $decided ) && \\Courtneyr\\Child\\SingleWideColumn\\applies() ?', '$card_at = \\Courtneyr\\Child\\SingleWideColumn\\applies() ?', [FAV_TEST]),
    ('favorite-flow-empty-box', FAV, "return '' === trim( $html ) ? $html :", 'return', [FAV_TEST]),
    ('favorite-resolver-empty-facts', FAV, "\tif ( array() === $facts ) {\n\t\treturn null;\n\t}\n\t$place", '\t$place', [FAV_TEST]),
    ('favorite-sheet-ignores-decision', FAV, '$facts   = is_array( $decided ) ? $decided : single_facts();', '$facts   = single_facts();', [FAV_TEST]),
    ('favorite-sheet-no-fallback', FAV, '$facts   = is_array( $decided ) ? $decided : single_facts();', '$facts   = is_array( $decided ) ? $decided : array();', [FAV_TEST]),
    # The Repost resolver and the reading-width box around its clipping.
    ('kinds-no-repost', WCD, "\t'repost'   => '\\\\Courtneyr\\\\Child\\\\Repost\\\\page_clipping',\n", '', [GUARDS]),
    ('repost-resolver-ignores-nesting', REP, "\t\t\t'wide' => $top,", "\t\t\t'wide' => true,", [REP_TEST]),
    ('repost-resolver-any-page', REP, "\tif ( null !== $top ) {", "\tif ( true ) {", [REP_TEST]),
    ('repost-meta-ignores-card-elsewhere', REP, "has_block( 'post-kinds-indieweb/repost-card', $post ) || ( ", "( ", [REP_TEST]),
    ('repost-meta-ignores-facts', REP, " || ( '' === fact( $facts, 'url' ) && '' === fact( $facts, 'cited_title' ) )", '', [REP_TEST]),
    ('repost-meta-needs-both', REP, "'' === fact( $facts, 'url' ) && '' === fact( $facts, 'cited_title' )", "( '' === fact( $facts, 'url' ) || '' === fact( $facts, 'cited_title' ) )", [REP_TEST]),
    ('repost-flow-for-another-kind', REP, "|| null === \\Courtneyr\\Child\\SingleWideColumn\\data_for( 'repost' ) ", '', [REP_TEST]),
    ('repost-flow-when-narrow', REP, " || ! \\Courtneyr\\Child\\SingleWideColumn\\applies() ) {", ' ) {', [REP_TEST]),
    ('repost-flow-any-content-block', REP, "'core/post-content' !== ( $block['blockName'] ?? '' ) || false === strpos( (string) ( $block['attrs']['className'] ?? '' ), 'single-post__content' )\n\t\t||", "'core/post-content' !== ( $block['blockName'] ?? '' )\n\t\t||", [REP_TEST]),
    ('repost-flow-empty-box', REP, "'' === trim( $part ) ? $part : ", '', [REP_TEST]),
    ('repost-flow-last-article-end', REP, "$to   = false === $from ? false : strpos( $html, '</article>', $from );", "$to   = false === $from ? false : strrpos( $html, '</article>' );", [REP_TEST]),
    ('repost-flow-no-end-guard', REP, ' || $from < $open || $to > $end ) {', ' ) {', [REP_TEST]),
    # Not listed: dropping `false === $end` is equivalent; with no entry data $to > $end (an int against false) already returns early.
    ('repost-flow-not-hooked', REP, "add_filter( 'render_block', __NAMESPACE__ . '\\\\wide_flow', 20, 2 );", '', [REP_TEST]),
    # Round 2: unknown structure (B1), the card found by its mark at the top level.
    ('opaque-ignored', WCD, "in_array( $block_name, OPAQUE_BLOCKS, true ) || ", '', [GUARDS]),
    ('opaque-no-synced-pattern', WCD, "\t'core/block',\n", '', [GUARDS]),
    ('opaque-no-registered-pattern', WCD, "\t'core/pattern',\n", '', [GUARDS]),
    ('shortcode-ignored', WCD, " || ( '' !== $html && \\strip_shortcodes( $html ) !== $html )", '', [GUARDS]),
    ('opaque-means-none', WCD, "\t\t\t\treturn 'unknown';", "\t\t\t\tcontinue;", [GUARDS]),
    ('favorite-unknown-decides', FAV, "\tif ( 'unknown' === $place ) {\n\t\treturn null;\n\t}\n", '', [FAV_TEST]),
    ('favorite-card-not-marked', FAV, "add_filter( 'render_block_' . CARD_BLOCK, __NAMESPACE__ . '\\\\mark_card' );", '', [FAV_TEST]),
    ('favorite-split-any-card', FAV, "\\Courtneyr\\Child\\SingleWideColumn\\top_level_card( $content ) : null;", "( preg_match( '/<article\\b[^>]*\\bk-favorite\\b[^>]*>.*?<\\/article>/s', $content, $m, PREG_OFFSET_CAPTURE ) ? array( (int) $m[0][1], (int) $m[0][1] + strlen( $m[0][0] ) ) : null ) : null;", [FAV_TEST]),
    ('card-mark-any-article', CARD, "'ARTICLE' === $tags->get_tag() && $tags->has_class( $class_name )", "'ARTICLE' === $tags->get_tag()", [GUARDS, FAV_TEST]),
    ('card-mark-always', CARD, "\tif ( ! marking() ) {\n\t\treturn $html;\n\t}\n\t$tags = new", '\t$tags = new', [GUARDS, FAV_TEST]),
    ('card-found-nested', CARD, "\t\t\tif ( array() !== $open ) {\n\t\t\t\treturn null;\n\t\t\t}\n\t\t\t$from", '\t\t\t$from', [GUARDS, FAV_TEST]),
    ('card-any-token', CARD, "card_mark() === $tags->get_attribute( CARD_MARK_ATTRIBUTE )", "null !== $tags->get_attribute( CARD_MARK_ATTRIBUTE )", [GUARDS]),
    ('card-unbalanced-ok', CARD, "\t\t\tif ( array() === $open || end( $open ) !== $tag ) {\n\t\t\t\treturn null;\n\t\t\t}\n", "\t\t\tif ( array() === $open ) {\n\t\t\t\tcontinue;\n\t\t\t}\n", [GUARDS, FAV_TEST]),
    ('card-unclosed-ok', CARD, "null === $from || null === $to || array() !== $open ||", "null === $from || null === $to ||", [GUARDS]),
    ('card-void-pushed', CARD, "in_array( $tag, VOID_ELEMENTS, true ) || ( $foreign", "( $foreign", [GUARDS]),
    ('card-svg-selfclose-pushed', CARD, " || ( $foreign && $tags->has_self_closing_flag() )", '', [GUARDS]),
    ('card-end-at-first-closer', CARD, "if ( null !== $from && null === $to && array() === $open ) {", "if ( null !== $from && null === $to ) {", [GUARDS]),
    ('card-script-pushed', CARD, "array( 'SCRIPT', 'STYLE', 'TEXTAREA', 'TITLE', 'IFRAME', 'NOEMBED', 'NOFRAMES', 'XMP' )", "array( 'STYLE', 'TEXTAREA', 'TITLE', 'IFRAME', 'NOEMBED', 'NOFRAMES', 'XMP' )", [GUARDS]),
    ('marking-on-narrow', CARD, "if ( is_content_block( $block ) && applies() ) {\n\t\tmarking( true );", "if ( is_content_block( $block ) ) {\n\t\tmarking( true );", [GUARDS]),
    ('marking-any-block', CARD, "if ( is_content_block( $block ) && applies() ) {\n\t\tmarking( true );", "if ( applies() ) {\n\t\tmarking( true );", [GUARDS]),
    ('release-keeps-marks', CARD, "\treturn unmark( $html );", "\treturn $html;", [GUARDS]),
    ('release-keeps-marking', CARD, "\tmarking( false );\n\treturn unmark", "\treturn unmark", [GUARDS]),
    ('release-any-block', CARD, "\tif ( ! is_content_block( $block ) ) {\n\t\treturn $html;\n\t}\n\tmarking( false );", "\tmarking( false );", [GUARDS]),
    ('release-before-kind-filters', CARD, "__NAMESPACE__ . '\\\\release', 30, 2 );", "__NAMESPACE__ . '\\\\release', 10, 2 );", [GUARDS]),
    ('content-block-substring', CARD, "in_array( 'single-post__content', explode( ' ', (string) ( $block['attrs']['className'] ?? '' ) ), true )", "false !== strpos( (string) ( $block['attrs']['className'] ?? '' ), 'single-post__content' )", [GUARDS]),
    ('decided-record-ignored', READ, "$after .= is_array( $page ) ? $page['record'] : reading_record( $read, $post );", '$after .= reading_record( $read, $post );', [READ_TEST]),
]


def run(tree, tests):
    for t in tests:
        r = subprocess.run(['php', os.path.join(tree, t)], capture_output=True, text=True)
        if r.returncode != 0:
            return False
    return True


def main():
    code = os.path.abspath(sys.argv[1])
    tests_from = os.path.abspath(sys.argv[2]) if len(sys.argv) > 2 else code
    with tempfile.TemporaryDirectory() as tmp:
        def fresh():
            tree = tempfile.mkdtemp(dir=tmp)
            shutil.copytree(os.path.join(code, 'inc'), os.path.join(tree, 'inc'))
            shutil.copytree(os.path.join(tests_from, 'tests', 'php'), os.path.join(tree, 'tests', 'php'))
            return tree

        base = fresh()
        if not run(base, [GUARDS, READ_TEST] + [t for t in (FAV_TEST, REP_TEST) if os.path.exists(os.path.join(base, t))]):
            print('BASELINE FAILS: the unmutated tree does not pass')
            sys.exit(2)
        killed = survived = skipped = 0
        for name, path, needle, repl, tests in MUTANTS:
            tree = fresh()
            target = os.path.join(tree, path)
            if not os.path.exists(target):
                print(f'n/a      {name} (no {path})')
                skipped += 1
                continue
            src = open(target, encoding='utf-8').read()
            if src.count(needle) != 1:
                print(f'n/a      {name} (needle found {src.count(needle)} times)')
                skipped += 1
                continue
            open(target, 'w', encoding='utf-8').write(src.replace(needle, repl))
            if run(tree, tests):
                print(f'SURVIVED {name}')
                survived += 1
            else:
                print(f'killed   {name}')
                killed += 1
        print(f'{killed} killed, {survived} survived, {skipped} did not apply, of {len(MUTANTS)}')
        sys.exit(1 if survived else 0)


if __name__ == '__main__':
    main()
