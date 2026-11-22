#!/usr/bin/env python3
"""Mutation proof for the wide-column decision: each mutant patches the MOUNTED copy of the theme on the local Studio site,
runs decision-invariants.mjs and must make it fail. usage: decision-mutants.py <site-theme-dir> <worktree> <urls> <prev> <wide-list> """
import subprocess, sys, time, shutil, urllib.request
site, tree, urls, prev, wide = sys.argv[1:6]
WC, SR = 'inc/single-wide-column.php', 'inc/single-read.php'
M = {
 'saved-content-by-id': (WC, "$content = (string) \\get_the_content();", "$content = (string) \\get_post_field( 'post_content', $post->ID );"),
 'own-page-split': (WC, "$content = (string) \\get_the_content();", "$content = (string) ( explode( '<!--nextpage-->', (string) $post->post_content )[ max( 1, (int) ( $GLOBALS['page'] ?? 1 ) ) - 1 ] ?? '' );"),
 'renderer-recomputes-first-card': (SR, "$read = is_array( $page ) ? $page['block'] : find_read_block( $post );", "$read = find_read_block( $post ); if ( is_array( $page ) ) { $page['record'] = reading_record( $read, $post ); $page['record'] = '' !== $page['record'] && 'Notes' === $post->post_title ? '' : $page['record']; }"),
 'wide-when-card-exists': (SR, "'wide' => '' !== $record,", "'wide' => true,"),
 'no-loop-guard': (WC, "|| ! \\in_the_loop()", ""),
 'any-read-term': (WC, "$kind         = primary_kind_slug( $post->ID );", "$kind         = \\has_term( 'read', 'kind', $post->ID ) ? 'read' : primary_kind_slug( $post->ID );"),
 'no-main-query-guard': (WC, "|| ! \\is_main_query()", ""),
 'no-loop-guards-at-all': (WC, ['|| ! \\in_the_loop()','|| ! \\is_main_query()','(int) \\get_queried_object_id() !== $post->ID || '], ['','','']),
 'sheet-not-enqueued': (WC, "\\wp_enqueue_style(", "\\wp_register_style("),
 'header-not-marked': (WC, "$tags->add_class( 'cr-wide-column' );", ""),
 'content-ignores-page-hash': (WC, "$key     = $post->ID . ':' . (int) ( $GLOBALS['page'] ?? 1 ) . ':' . md5( $content );", "$key = 'one';"),
}
def warm():
    time.sleep(4); urllib.request.urlopen('http://localhost:8887/').read(); time.sleep(3)
killed = 0
for name, (f, old, new) in M.items():
    p = f'{site}/{f}'; src = open(p).read()
    olds, news = (old, new) if isinstance(old, list) else ([old], [new])
    if any(o not in src for o in olds): print('SKIP (pattern missing)', name); continue
    for o, n in zip(olds, news): src = src.replace(o, n, 1)
    open(p, 'w').write(src); warm()
    r = subprocess.run(['node', 'decision-invariants.mjs', urls, prev, open(wide).read().strip(), 'read-like'], capture_output=True, text=True)
    shutil.copyfile(f'{tree}/{f}', p)
    out = [l for l in r.stdout.splitlines() if l.startswith('FAIL')]
    print(('KILLED ' if r.returncode else 'SURVIVED'), name, '|', (out[0][:110] if out else ''))
    killed += bool(r.returncode)
warm()
print(f'{killed} of {len(M)} mutants killed')
