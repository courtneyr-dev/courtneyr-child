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
FAV_TEST = 'tests/php/favorite-single-test.php'

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
    ('top-level-ignores-depth', WCD, 'return 0 === $depth;', 'return true;', [GUARDS]),
    ('top-level-last-not-first', WCD, "\t\t\tif ( null !== $found ) {\n\t\t\t\treturn $found;\n\t\t\t}\n", '', [GUARDS]),
    ('favorite-resolver-ignores-nesting', FAV, "'wide' => false !== \\Courtneyr\\Child\\SingleWideColumn\\first_block_is_top_level( $content, 'post-kinds-indieweb/favorite-card' ),", "'wide' => true,", [FAV_TEST]),
    ('favorite-resolver-plain-narrow', FAV, "'wide' => false !== \\Courtneyr\\Child\\SingleWideColumn\\first_block_is_top_level(", "'wide' => true === \\Courtneyr\\Child\\SingleWideColumn\\first_block_is_top_level(", [FAV_TEST]),
    ('favorite-no-flow-box', FAV, '\t\t\t$before = flow( $before );\n\t\t\t$after  = flow( $after );\n', '', [FAV_TEST]),
    ('favorite-flow-when-narrow', FAV, 'if ( is_array( $decided ) && \\Courtneyr\\Child\\SingleWideColumn\\applies() ) {', 'if ( is_array( $decided ) ) {', [FAV_TEST]),
    ('favorite-flow-for-another-kind', FAV, 'if ( is_array( $decided ) && \\Courtneyr\\Child\\SingleWideColumn\\applies() ) {', 'if ( \\Courtneyr\\Child\\SingleWideColumn\\applies() ) {', [FAV_TEST]),
    ('favorite-flow-empty-box', FAV, "return '' === trim( $html ) ? $html :", 'return', [FAV_TEST]),
    ('favorite-resolver-empty-facts', FAV, "\tif ( array() === $facts ) {\n\t\treturn null;\n\t}\n\treturn array(", '\treturn array(', [FAV_TEST]),
    ('favorite-sheet-ignores-decision', FAV, '$facts   = is_array( $decided ) ? $decided : single_facts();', '$facts   = single_facts();', [FAV_TEST]),
    ('favorite-sheet-no-fallback', FAV, '$facts   = is_array( $decided ) ? $decided : single_facts();', '$facts   = is_array( $decided ) ? $decided : array();', [FAV_TEST]),
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
        if not run(base, [GUARDS, READ_TEST] + ([FAV_TEST] if os.path.exists(os.path.join(base, FAV_TEST)) else [])):
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
