# Templates

Custom templates for the 18 post types declared in `theme.json` under
`customTemplates`. Each `.html` file here overrides the corresponding
template from the parent (Ollie) for users who select that template
in the post editor's "Template" panel.

## What goes here

`.html` files written in block markup (the same format Site Editor
exports). Each file maps to a `customTemplates` entry by name:

| File                     | Template entry            | Use                  |
| ------------------------ | ------------------------- | -------------------- |
| `longform.html`          | `longform`                | Full-width articles  |
| `single-aside.html`      | `single-aside`            | Aside posts          |
| `single-status.html`     | `single-status`           | Status updates       |
| `single-link.html`       | `single-link`             | Link posts           |
| `single-bookmark.html`   | `single-bookmark`         | IndieWeb bookmarks   |
| `single-quote.html`      | `single-quote`            | Quote posts          |
| `single-image.html`      | `single-image`            | Image posts          |
| `single-gallery.html`    | `single-gallery`          | Galleries            |
| `single-video.html`      | `single-video`            | Video posts          |
| `single-audio.html`      | `single-audio`            | Audio (podcast)      |
| `single-chat.html`       | `single-chat`             | Chat-format          |
| `single-speaking.html`   | `single-speaking`         | Talks and conf notes |
| `single-book.html`       | `single-book`             | Book notes           |
| `single-event.html`      | `single-event`            | h-event              |
| `single-review.html`     | `single-review`           | h-review             |
| `single-like.html`       | `single-like`             | IndieWeb likes       |
| `single-repost.html`     | `single-repost`           | IndieWeb reposts     |
| `single-reply.html`      | `single-reply`            | IndieWeb replies     |
| `blank.html`             | `blank`                   | Stripped page        |

## Until a template is added here

The parent (Ollie) template chain handles the post. Falls back to
`single.html` → `index.html`. Site stays usable; just won't carry the
post-format-specific styling.

## Authoring approach

1. Build the layout in the Site Editor.
2. Export via Site Editor → "Export" or read it from the wp_template
   custom post type.
3. Save as `{template-name}.html` here.
4. Commit to the repo.

Avoid hand-writing block markup unless you enjoy debugging
serialization. The Site Editor produces output that round-trips
cleanly.
