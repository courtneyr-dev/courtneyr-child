# courtneyr-child verification map

This directory is the maintained source for verifying what a visitor sees and does on courtneyr.dev's theme. Read this index before driving the site, then use the matching feature file as the recipe.

## Baseline preconditions

- Launch with `verify.mjs launch --checkout <theme-checkout>` and keep the printed `runId` and `baseUrl`.
- `verify.mjs doctor` prints `DOCTOR PASS` for the checkout you mean to test.
- The site holds the `courtneyr-x-t269` seed: 73 published posts, a `stream` page, and a password-protected page.
- Never drive a site that your own launch did not lock. `drive` enforces this with `--run`.

## Driving conventions

- Prefer roles and accessible names (`getByRole( 'button', { name: 'Dark theme' } )`) over classes.
- Use a fresh browser context per journey. The theme keeps the visitor's choice in `localStorage`.
- Desktop means 1280 by 800. At 1024 px and below the header swaps the segment buttons for one cycle button and hides the header search.
- Each journey is `drive/<feature>.mjs`. A feature file without a journey lists the handles and end states to build one from.

## Proof and skip reporting

- Capture the state before and after each action, and read side effects back after a reload.
- Keep `home.server.html` or the route's server HTML when the proof feeds a Storybook fixture.
- Report an unreachable path with the command and the unmet precondition. Do not report a skipped entry point as verified through another one.

## Features

- [Theme toggle](./theme-toggle.md) covers light, dark and system on desktop, the mobile cycle button, and persistence. Journey: `theme-toggle`.
- [Kind archive](./kind-archive.md) covers a kind's listing and its pagination.
- [Kind single](./kind-single.md) covers one post of a kind and its post navigation.
- [Stream](./stream.md) covers the page that collects lifelog kinds.
- [Search](./search.md) covers the header search and the results page.
