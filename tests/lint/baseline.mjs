// Runs PHPCS or stylelint and compares violation counts per file and rule with a
// checked-in baseline, so existing violations pass and new ones fail.
//
//   node tests/lint/baseline.mjs phpcs            check against tests/lint/phpcs-baseline.json
//   node tests/lint/baseline.mjs stylelint        check against tests/lint/stylelint-baseline.json
//   node tests/lint/baseline.mjs <tool> --update  rewrite the baseline from the current results
import { spawnSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const root = path.resolve( path.dirname( fileURLToPath( import.meta.url ) ), '..', '..' );
const [ tool, flag ] = process.argv.slice( 2 );
const update = flag === '--update';

const tools = {
	phpcs: {
		// display_errors=stderr keeps PHP notices out of the JSON report on stdout.
		command: [ 'php', '-d', 'display_errors=stderr', 'vendor/bin/phpcs', '--report=json', '-q' ],
		okExit: [ 0, 1, 2 ],
		parse: ( stdout ) =>
			Object.entries( JSON.parse( stdout ).files ).flatMap( ( [ file, report ] ) =>
				report.messages.map( ( m ) => ( { file, rule: m.source, line: m.line, text: m.message } ) )
			),
	},
	stylelint: {
		command: [ process.execPath, 'node_modules/stylelint/bin/stylelint.mjs', 'assets/css/**/*.css', '--formatter', 'json', '--allow-empty-input' ],
		okExit: [ 0, 2 ],
		// stylelint 16 prints the JSON report to stderr when a rule fails.
		parse: ( stdout, stderr ) => {
			const results = JSON.parse( stdout.trim().startsWith( '[' ) ? stdout : stderr );
			const invalid = results.flatMap( ( r ) => r.invalidOptionWarnings.map( ( w ) => w.text ) );
			if ( invalid.length ) {
				throw new Error( `stylelint config is invalid:\n${ invalid.join( '\n' ) }` );
			}
			return results.flatMap( ( r ) =>
				r.warnings.map( ( w ) => ( { file: path.relative( root, r.source ), rule: w.rule, line: w.line, text: w.text } ) )
			);
		},
	},
};

if ( ! tools[ tool ] ) {
	console.error( 'Usage: node tests/lint/baseline.mjs <phpcs|stylelint> [--update]' );
	process.exit( 1 );
}

const { command, okExit, parse } = tools[ tool ];
const run = spawnSync( command[ 0 ], command.slice( 1 ), { cwd: root, encoding: 'utf8', maxBuffer: 64 * 1024 * 1024 } );
if ( run.error || ! okExit.includes( run.status ) ) {
	console.error( `${ tool } did not run (exit ${ run.status }).`, run.error ?? '', run.stderr.slice( 0, 2000 ), run.stdout.slice( 0, 2000 ) );
	process.exit( 1 );
}

let items;
try {
	items = parse( run.stdout, run.stderr );
} catch ( error ) {
	console.error( `Could not read ${ tool } output: ${ error.message }` );
	console.error( run.stdout.slice( 0, 2000 ), run.stderr.slice( 0, 2000 ) );
	process.exit( 1 );
}

const counts = {};
for ( const { file, rule } of items ) {
	counts[ file ] ??= {};
	counts[ file ][ rule ] = ( counts[ file ][ rule ] ?? 0 ) + 1;
}
const sorted = Object.fromEntries(
	Object.keys( counts ).sort().map( ( f ) => [ f, Object.fromEntries( Object.keys( counts[ f ] ).sort().map( ( r ) => [ r, counts[ f ][ r ] ] ) ) ] )
);

const baselinePath = path.join( root, 'tests', 'lint', `${ tool }-baseline.json` );

if ( update ) {
	const baseline = { tool, regenerate: `npm run lint:${ tool === 'phpcs' ? 'php' : 'css' }:baseline`, total: items.length, files: sorted };
	fs.writeFileSync( baselinePath, JSON.stringify( baseline, null, '\t' ) + '\n' );
	console.log( `Wrote ${ path.relative( root, baselinePath ) }: ${ items.length } violations in ${ Object.keys( sorted ).length } files.` );
	process.exit( 0 );
}

const baseline = JSON.parse( fs.readFileSync( baselinePath, 'utf8' ) );
const regressions = [];
const improvements = [];
for ( const [ file, rules ] of Object.entries( sorted ) ) {
	for ( const [ rule, count ] of Object.entries( rules ) ) {
		const allowed = baseline.files[ file ]?.[ rule ] ?? 0;
		if ( count > allowed ) {
			regressions.push( { file, rule, count, allowed } );
		}
	}
}
for ( const [ file, rules ] of Object.entries( baseline.files ) ) {
	for ( const [ rule, allowed ] of Object.entries( rules ) ) {
		const count = sorted[ file ]?.[ rule ] ?? 0;
		if ( count < allowed ) {
			improvements.push( { file, rule, count, allowed } );
		}
	}
}

console.log( `${ tool }: ${ items.length } violations now, ${ baseline.total } in the baseline.` );

if ( improvements.length ) {
	console.log( `\n${ improvements.length } file/rule pairs are below the baseline. Run \`${ baseline.regenerate }\` to lock that in:` );
	for ( const i of improvements ) {
		console.log( `  ${ i.file }  ${ i.rule }  ${ i.allowed } -> ${ i.count }` );
	}
}

if ( regressions.length ) {
	console.log( `\n${ regressions.length } file/rule pairs exceed the baseline:` );
	for ( const r of regressions ) {
		console.log( `\n  ${ r.file }  ${ r.rule }  ${ r.allowed } allowed, ${ r.count } found` );
		for ( const i of items.filter( ( x ) => x.file === r.file && x.rule === r.rule ) ) {
			console.log( `    line ${ i.line }: ${ i.text }` );
			if ( process.env.GITHUB_ACTIONS === 'true' ) {
				console.log( `::error file=${ i.file },line=${ i.line }::${ r.rule }: ${ i.text.replace( /\r?\n/g, ' ' ) }` );
			}
		}
	}
	console.log( '\nFix the new violations. Lines are listed for every match of the rule in that file because counts, not lines, are baselined.' );
	process.exit( 1 );
}

console.log( 'No new violations.' );
