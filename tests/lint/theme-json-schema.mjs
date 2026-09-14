// Validates theme.json and every styles/**/*.json variation against a pinned,
// checked-in copy of the schema each file declares in `$schema`. No network.
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import Ajv from 'ajv';
import addFormats from 'ajv-formats';

const root = path.resolve( path.dirname( fileURLToPath( import.meta.url ) ), '..', '..' );

// Declared `$schema` URL -> pinned copy. Refresh steps are in docs/testing.md.
const PINNED = {
	'https://schemas.wp.org/trunk/theme.json': {
		file: 'tests/schemas/theme.json',
		source: 'WordPress/gutenberg@d0ceb5c86f534dfa7f69ed7c852865c1d7a9da6a schemas/json/theme.json',
	},
};
const DEFAULT_SCHEMA = 'https://schemas.wp.org/trunk/theme.json';

function walk( dir ) {
	if ( ! fs.existsSync( dir ) ) {
		return [];
	}
	return fs.readdirSync( dir, { withFileTypes: true } ).flatMap( ( entry ) => {
		const full = path.join( dir, entry.name );
		if ( entry.isDirectory() ) {
			return walk( full );
		}
		return entry.name.endsWith( '.json' ) ? [ full ] : [];
	} );
}

const files = [ path.join( root, 'theme.json' ), ...walk( path.join( root, 'styles' ) ) ];
const ajv = new Ajv( { allErrors: true, strict: false } );
addFormats( ajv );
const validators = {};
let failed = 0;

for ( const file of files ) {
	const rel = path.relative( root, file );
	let data;
	try {
		data = JSON.parse( fs.readFileSync( file, 'utf8' ) );
	} catch ( error ) {
		console.log( `FAIL ${ rel }: not valid JSON (${ error.message })` );
		failed++;
		continue;
	}
	const declared = data.$schema ?? DEFAULT_SCHEMA;
	const pinned = PINNED[ declared ];
	if ( ! pinned ) {
		console.log( `FAIL ${ rel }: no pinned copy of ${ declared }. Add one to tests/schemas and PINNED in ${ path.relative( root, fileURLToPath( import.meta.url ) ) }.` );
		failed++;
		continue;
	}
	validators[ declared ] ??= ajv.compile( JSON.parse( fs.readFileSync( path.join( root, pinned.file ), 'utf8' ) ) );
	const validate = validators[ declared ];
	if ( validate( data ) ) {
		console.log( `ok   ${ rel } (${ declared }${ data.$schema ? '' : ', no $schema declared' } -> ${ pinned.source })` );
		continue;
	}
	failed++;
	console.log( `FAIL ${ rel } (${ declared } -> ${ pinned.source })` );
	for ( const e of validate.errors ) {
		console.log( `     ${ e.instancePath || '/' } ${ e.message }${ e.params && Object.keys( e.params ).length ? ' ' + JSON.stringify( e.params ) : '' }` );
	}
}

console.log( `\n${ files.length - failed } of ${ files.length } files match their schema.` );
process.exit( failed ? 1 : 0 );
