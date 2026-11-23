#!/usr/bin/env python3
"""Mutants of a kind's wide-column CSS and renderer, killed (or not) by the live-site geometry check.

usage: python3 mutants-browser.py <kind> <head-tree> <site-theme-dir> <urls.tsv> <base.json> <scratch-dir>

<kind> is favorite or repost. For each mutant: copy <head-tree> to <scratch-dir>, apply the mutant
there, rsync it over the Studio site's theme, probe a subset of pages and widths with probe.mjs and run
check-<kind>.mjs against <base.json>. Killed = the check exits nonzero. The head tree itself is never
written. The last step mounts the unmutated head tree again. Local Studio only.
"""
import os
import shutil
import subprocess
import sys
import time
import urllib.request

HERE = os.path.dirname(os.path.abspath(__file__))
FAV_CSS = 'assets/css/cr-favorite.css'
FAV_PHP = 'inc/single-favorite.php'
REP_CSS = 'assets/css/cr-repost.css'
REP_PHP = 'inc/repost.php'
WCD = 'inc/single-wide-column.php'
G = '.cr-wide-column > .pkiw-singular-entry > .cr-fav-sheet:has(> .cr-fav-sheet__body > .cr-fav-sheet__mount:not(.cr-cover--failed))'

MUTANTS = {
    'favorite': {
        'pages': 'fav-card,fav-siblings,fav-plain,fav-nested,fav-noimg',
        'widths': '1448,1024,768',
        'list': [
            ('sheet-capped-at-reading-width', FAV_CSS, '> .cr-fav-sheet {\n\tmax-width: none;\n}', '> .cr-fav-sheet {\n\tmax-width: var(--wp--style--global--content-size);\n}'),
            ('no-grid', FAV_CSS, '\t\tdisplay: grid;\n\t\tgrid-template-columns: minmax(0, 1fr) minmax(0, 1fr);', '\t\tgrid-template-columns: minmax(0, 1fr) minmax(0, 1fr);'),
            ('columns-3fr-2fr', FAV_CSS, 'grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);\n\t\tcolumn-gap', 'grid-template-columns: minmax(0, 3fr) minmax(0, 2fr);\n\t\tcolumn-gap'),
            ('mount-not-raised', FAV_CSS, '\t\tmargin-block-start: calc(var(--cr-space-lg) - var(--cr-space-3xl));\n', ''),
            ('mount-in-column-1', FAV_CSS, '\t\tgrid-column: 2;\n\t\tgrid-row: 1 / span 2;', '\t\tgrid-column: 1;\n\t\tgrid-row: 1 / span 2;'),
            ('mount-below-h1', FAV_CSS, '\t\tgrid-column: 2;\n\t\tgrid-row: 1 / span 2;', '\t\tgrid-column: 2;\n\t\tgrid-row: 2;'),
            ('star-and-tape-in-grid', FAV_CSS, ' > :where(:not(.cr-fav-sheet__star):not(.cr-fav-sheet__tape)) {', ' > * {'),
            ('breakpoint-40rem', FAV_CSS, '@media (min-width: 64rem) {\n\n\t.cr-wide-column > .pkiw-singular-entry > .cr-fav-sheet {', '@media (min-width: 40rem) {\n\n\t.cr-wide-column > .pkiw-singular-entry > .cr-fav-sheet {'),
            ('unscoped-sheet-grid', FAV_CSS, G + ' {\n\t\tdisplay: grid;', '.cr-fav-sheet:has(> .cr-fav-sheet__body > .cr-fav-sheet__mount:not(.cr-cover--failed)) {\n\t\tdisplay: grid;'),
            ('plain-prose-uncapped', FAV_CSS, '\t.cr-wide-column > .pkiw-singular-entry > .cr-fav-sheet > :is(p, ul, ol, figure, blockquote, h2, h3, h4) {\n\t\tmax-inline-size: var(--wp--style--global--content-size);', '\t.cr-wide-column > .pkiw-singular-entry > .cr-fav-sheet > :is(p, ul, ol, figure, blockquote, h2, h3, h4) {\n\t\tmax-inline-size: none;'),
            ('title-spans-both-columns', FAV_CSS, ' > .cr-fav-sheet__title {\n\t\tgrid-column: 1;', ' > .cr-fav-sheet__title {\n\t\tgrid-column: 1 / -1;'),
            ('padding-not-widened', FAV_CSS, '\t\tpadding-inline: var(--cr-space-2xl);\n', ''),
            ('renderer-no-flow-box', FAV_PHP, '\t\t\t$before = flow( $before );\n\t\t\t$after  = flow( $after );\n', ''),
            ('resolver-ignores-nesting', FAV_PHP, "'wide' => false !== \\Courtneyr\\Child\\SingleWideColumn\\first_block_is_top_level( $content, 'post-kinds-indieweb/favorite-card' ),", "'wide' => true,"),
            ('resolver-unregistered', WCD, "\t'favorite' => '\\\\Courtneyr\\\\Child\\\\SingleFavorite\\\\page_sheet',\n", ''),
        ],
    },
}


def main():
    kind, head, theme, tsv, base, scratch = sys.argv[1:7]
    conf = MUTANTS[kind]
    out = os.path.join(scratch, 'mutant.json')

    def mount(tree):
        subprocess.run(['rsync', '-a', '--delete', '--exclude', '.git', '--exclude', 'node_modules', '--exclude', 'vendor',
                        '--exclude', 'tests', '--exclude', '.github', tree + '/', theme + '/'], check=True)
        time.sleep(3)
        for u in ('http://localhost:8887/', 'http://localhost:8887/kind/favorite/'):
            urllib.request.urlopen(u).read()

    killed = survived = 0
    only = [n for n in os.environ.get('CR_MUTANTS', '').split(',') if n]
    for name, path, needle, repl in conf['list']:
        if only and name not in only:
            continue
        tree = os.path.join(scratch, 'tree-mut')
        shutil.rmtree(tree, ignore_errors=True)
        shutil.copytree(head, tree, ignore=shutil.ignore_patterns('.git', 'node_modules', 'vendor', 'tests'), symlinks=True)
        f = os.path.join(tree, path)
        src = open(f, encoding='utf-8').read()
        if src.count(needle) != 1:
            print(f'n/a      {name} (needle found {src.count(needle)} times)', flush=True)
            continue
        open(f, 'w', encoding='utf-8').write(src.replace(needle, repl))
        mount(tree)
        subprocess.run(['node', os.path.join(HERE, 'probe.mjs'), tsv, conf['pages'], out, conf['widths'], 'light'], check=True, capture_output=True)
        r = subprocess.run(['node', os.path.join(HERE, f'check-{kind}.mjs'), base, out], capture_output=True, text=True)
        if r.returncode:
            killed += 1
            first = next((l for l in r.stdout.splitlines() if l.startswith('FAIL')), '')
            print(f'killed   {name}   [{r.stdout.strip().splitlines()[-1]}; first: {first[:150]}]', flush=True)
        else:
            survived += 1
            print(f'SURVIVED {name}   [{r.stdout.strip().splitlines()[-1]}]', flush=True)
    mount(head)
    print(f'{killed} killed, {survived} survived, of {len(conf["list"])}')
    sys.exit(1 if survived else 0)


if __name__ == '__main__':
    main()
