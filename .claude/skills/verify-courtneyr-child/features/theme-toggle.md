# Theme toggle

A visitor picks light, dark or system appearance from the header. The choice applies at once, survives a reload, and is applied before first paint on later visits.

## Sub-features

- `toggle-default` shows System pressed on a first visit, with `data-theme="system"` on `<html>`.
- `toggle-desktop` applies Light, Dark or System from three segment buttons in the `Theme` group.
- `toggle-persist` keeps the choice in `localStorage` key `courtneyr-theme` and reapplies it after a reload.
- `toggle-mobile` cycles light, dark and system from one button at 1024 px and below.

## How to get to it (user POV)

- At desktop width, choose a button in the header group named `Theme`.
- At 1024 px and below, choose the button whose name starts with `Appearance:`.

## Driving it with verify.mjs

Preconditions:

- A launched site with `DOCTOR PASS`, and the `runId` from launch.

- **Full journey.** Run `node .claude/skills/verify-courtneyr-child/verify.mjs drive theme-toggle --run <runId>`. Four PASS lines print, then `DRIVE PASS theme-toggle`.
- **First visit.** The journey loads `/` in a fresh 1280 by 800 context. `first-visit-system` passes when `data-theme=system`, storage is empty, and only `System theme (follows OS)` is pressed.
- **Choose Dark.** The journey clicks the button named `Dark theme`. `click-dark-applies` passes on `data-theme=dark` with only `Dark theme` pressed. `click-dark-stores` passes on `courtneyr-theme=dark`.
- **Reload.** `reload-keeps-dark` passes when dark is still applied and pressed after a reload.
- **Proof.** `1-before.png`, `2-after-dark.png`, `3-after-reload.png`, both `.aria.yml` snapshots of the `Theme` group, and `result.json` land in `<evidenceDir>/theme-toggle/`.
- **Mobile cycle.** No journey yet. Set the viewport to 375 by 812, choose the `Appearance:` button three times, and read the `[data-theme-cycle-status]` live region after each press. `tests/playwright/theme-toggle-store.spec.mjs` asserts the expected order.

## Gotchas

- The segments are hidden at 1024 px and below, so a role query for `Dark theme` at mobile width finds a hidden button.
- The context's `colorScheme` changes what System looks like, not the `data-theme` value. Assert the attribute, not colors.
- A context that reuses storage starts from the last choice, so `first-visit-system` fails. Always use a fresh context.
