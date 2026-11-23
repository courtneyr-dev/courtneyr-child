# Search

A visitor searches the site from the header and reads a paged list of matching posts.

## Sub-features

- `search-header` submits a query from the header search box at desktop width.
- `search-mobile` submits a query from the mobile search form at 1024 px and below.
- `search-results` lists matches with a heading that repeats the query.

## How to get to it (user POV)

- Type in the search box named `Search the site` in the header and press Enter.
- Open `/?s=<query>`.

## Driving it with verify.mjs

Preconditions:

- A launched site with `DOCTOR PASS`.

- **Results.** Open `<baseUrl>/?s=Fictional`. HTTP 200, the `<h1>` reads `Search results for: “Fictional”`, and the list holds `read`, `note`, `bookmark` and `follow` entries with a `Pagination` navigation.
- **Header entry.** At 1280 by 800, fill the searchbox named `Search the site` with `Fictional` and press Enter. The URL becomes `/?s=Fictional` with the same heading.
- **Journey.** None yet.

## Gotchas

- The markup has two inputs labeled `Search the site`, one in the header block and one in the mobile form. At 1280 by 800 a role query finds one, because the mobile form is hidden. Do not select by `id`.
- The results `<h1>` text repeats the query once more inside a decorative `cr-cutout` span, so its `textContent` reads the heading twice. Match with `startsWith`.
