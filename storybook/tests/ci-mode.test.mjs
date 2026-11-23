import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import test from 'node:test';
import { ciMode, comparison } from '../ci-mode.mjs';

const BASE = '1bca3d1f0c0f5b6f6f3b8b9f4f6d1f2a3b4c5d6e';

test( 'a change outside storybook/ is advisory', () => {
	assert.equal( ciMode( BASE, [ 'templates/single.html', 'assets/css/components.css', 'docs/storybook/notes.md' ] ), 'advisory' );
	assert.equal( ciMode( BASE, [ '.github/workflows/lint.yml' ] ), 'advisory' );
	assert.equal( ciMode( BASE, [] ), 'advisory' );
} );

test( 'a change under storybook/ or to its workflow is strict', () => {
	assert.equal( ciMode( BASE, [ 'templates/single.html', 'storybook/recipes.mjs' ] ), 'strict' );
	assert.equal( ciMode( BASE, [ 'storybook/fixtures/courtneyr-x-t269/manifest.json' ] ), 'strict' );
	assert.equal( ciMode( BASE, [ '.github/workflows/storybook.yml' ] ), 'strict' );
} );

test( 'a change CI cannot compare is strict', () => {
	assert.equal( ciMode( '0000000000000000000000000000000000000000', [ 'templates/single.html' ] ), 'strict' );
	assert.equal( ciMode( '', [ 'templates/single.html' ] ), 'strict' );
	assert.equal( ciMode( undefined, [ 'templates/single.html' ] ), 'strict' );
	assert.equal( ciMode( BASE, null ), 'strict' );
} );

const ZEROS = '0000000000000000000000000000000000000000';

// A branch one commit ahead of origin/main, which changes `file`.
function branchRepo( t, file ) {
	const repo = fs.mkdtempSync( path.join( os.tmpdir(), 'ci-mode-' ) );
	t.after( () => fs.rmSync( repo, { recursive: true, force: true } ) );
	const run = ( ...args ) => execFileSync( 'git', [ '-C', repo, '-c', 'user.name=test', '-c', 'user.email=test@example.invalid', '-c', 'commit.gpgsign=false', ...args ], { encoding: 'utf8' } ).trim();
	const commit = ( name ) => {
		fs.mkdirSync( path.dirname( path.join( repo, name ) ), { recursive: true } );
		fs.writeFileSync( path.join( repo, name ), name );
		run( 'add', '-A' );
		run( 'commit', '-q', '-m', name );
		return run( 'rev-parse', 'HEAD' );
	};
	run( 'init', '-q' );
	const main = commit( 'style.css' );
	run( 'update-ref', 'refs/remotes/origin/main', main );
	commit( file );
	return { repo, main };
}

const modeOf = ( repo, before, defaultBranch ) => {
	const { base, changed } = comparison( repo, before, defaultBranch );
	return ciMode( base, changed );
};

test( 'a new branch whose push changes only templates/ is advisory', ( t ) => {
	const { repo, main } = branchRepo( t, 'templates/single.html' );
	assert.deepEqual( comparison( repo, ZEROS, 'main' ).changed, [ 'templates/single.html' ] );
	assert.equal( comparison( repo, ZEROS, 'main' ).base, main );
	assert.equal( modeOf( repo, ZEROS, 'main' ), 'advisory' );
	assert.equal( modeOf( repo, '', 'main' ), 'advisory' );
	assert.equal( modeOf( repo, '1111111111111111111111111111111111111111', 'main' ), 'advisory' );
} );

test( 'a new branch whose push changes storybook/ is strict', ( t ) => {
	const { repo } = branchRepo( t, 'storybook/recipes.mjs' );
	assert.equal( modeOf( repo, ZEROS, 'main' ), 'strict' );
} );

test( 'a new branch with no merge base to compare with is strict', ( t ) => {
	const { repo } = branchRepo( t, 'templates/single.html' );
	assert.equal( modeOf( repo, ZEROS, 'trunk' ), 'strict' );
	assert.equal( modeOf( repo, ZEROS, '' ), 'strict' );
	assert.equal( modeOf( repo, ZEROS, undefined ), 'strict' );
} );

test( 'a push with a before commit is compared with it, not the merge base', ( t ) => {
	const { repo, main } = branchRepo( t, 'storybook/recipes.mjs' );
	assert.deepEqual( comparison( repo, 'HEAD', 'main' ), { base: 'HEAD', changed: [] } );
	assert.equal( comparison( repo, main, 'main' ).base, main );
} );
