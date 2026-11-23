# Kind single

A visitor opens one post and sees it in its kind's design, with links to the neighboring posts.

## Sub-features

- `single-render` shows the post title, date and kind design.
- `single-nav` links to the previous and next posts in the `Post navigation` region.
- `single-protected` asks for a password before it shows a protected post.

## How to get to it (user POV)

- Choose a post title on the home page, the Stream, a kind archive, or search results.
- Open `/<slug>/` directly, for example `/seed-269-s5/` (Watch) or `/seed-269-f1/` (Note).

## Driving it with verify.mjs

Preconditions:

- A launched site with `DOCTOR PASS`.

- **Watch single.** Open `<baseUrl>/seed-269-s5/`. HTTP 200, the `<h1>` reads `Fictional Watch 5`, and the body carries a `kind-watch` element.
- **Note single.** Open `<baseUrl>/seed-269-f1/`. HTTP 200 and the `<h1>` reads `Fold Note 01`.
- **Post navigation.** On either single, the navigation named `Post navigation` holds `Previous` and `Next` links. On `/seed-269-s5/` they read `Previous Fictional Listen 4` and `Next Sentinel Hidden Read Qx7`.
- **Protected page.** Open `<baseUrl>/seed-269-stream-password/`. The `<h1>` reads `Protected: Seed 269 stream password` and a `form.post-password-form` with one password field replaces the content.
- **Journey.** None yet.

## Gotchas

- The `seed-269-*` slugs exist only on sites seeded from `courtneyr-x-t269`. The `cr-seed-24x` slugs in `tests/routes.mjs` are not on this seed.
