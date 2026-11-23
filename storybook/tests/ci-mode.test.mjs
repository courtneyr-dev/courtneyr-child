import assert from 'node:assert/strict';
import test from 'node:test';
import { ciMode } from '../ci-mode.mjs';

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
