// Vite serves `/fixtures/…/manifest.json` as a JavaScript module. A static mount at that same URL
// answers first with raw JSON, and the import fails. So the fixtures are served under another name.
export const FIXTURES_MOUNT = 'captured';
