# Stream

A visitor opens the Stream to see short lifelog posts (reads, check-ins, eats, drinks, bookmarks, replies) that stay off the curated blog.

## Sub-features

- `stream-list` shows mixed kinds in one list, newest first.
- `stream-routing` keeps lifelog kinds off the home page and on the Stream.

## How to get to it (user POV)

- Choose the `Stream` link in the navigation named `Primary`.
- Open `/stream/`.

## Driving it with verify.mjs

Preconditions:

- A launched site with `DOCTOR PASS`. The `cr-content-surfaces` must-use plugin from the seed is present.

- **List.** Open `<baseUrl>/stream/`. HTTP 200, the `<h1>` starts with `the stream`, and the page holds entries of kinds `read`, `eat`, `drink`, `bookmark`, `reply` and `cite`.
- **Routing.** Open `<baseUrl>/`. The home page shows the `Courtney Robertson — Developer Relations in Open Source` heading and no `h-entry` posts, because the must-use plugin moves the seed's lifelog posts to the Stream.
- **Journey.** None yet.

## Gotchas

- The routing comes from `wp-content/mu-plugins/cr-content-surfaces.php` in the site, not from the theme. A theme change cannot move a post between the home page and the Stream.
