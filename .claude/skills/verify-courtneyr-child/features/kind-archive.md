# Kind archive

A visitor opens the archive for one post kind, such as Read, and pages through its posts twelve at a time.

## Sub-features

- `archive-list` lists only posts of that kind, newest first.
- `archive-paginate` moves between pages with the `Pagination` navigation.
- `archive-empty` shows a kind with no posts.

## How to get to it (user POV)

- Open `/kind/<kind>/`, for example `/kind/read/`.
- Choose a kind link in the `Browse all topics, formats and kinds` navigation.
- Choose `Next` or a page number in the `Pagination` navigation.

## Driving it with verify.mjs

Preconditions:

- A launched site with `DOCTOR PASS`. The seed has 27 Read posts and 12 Note posts.

- **List.** Open `<baseUrl>/kind/read/`. HTTP 200, the `<h1>` starts with `Read`, and the query list holds 12 `li.wp-block-post.kind-read` items, each with an `article.pk-card.k-read` card.
- **Paginate.** Choose the link named `Next` in the navigation named `Pagination`. The URL becomes `/kind/read/page/2/` with 12 more Read items. Page 3 holds the last 2, one of them a password-protected card.
- **Server HTML.** `curl -s <baseUrl>/kind/read/` returns the same markup without JavaScript, which is the input for a fixture.
- **Journey.** None yet. Add a `kind-archive.mjs` journey beside `drive/theme-toggle.mjs`, in the same shape.

## Gotchas

- `/kind/mood/` returns 200 with no entries on this seed. That proves the empty state, not the Mood design.
- Read archives carry a `Read order` navigation with `All` and `A–Z by author` (`?pkiw_read_order=author`). Compare pages in the same order.
- The seed has 27 Read posts and the archive lists 26. Count list items, not database rows.
