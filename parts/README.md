# Template parts

Template parts declared in `theme.json` under `templateParts`. Each
`.html` file here is a reusable fragment (header, footer, sidebar)
that templates compose via the `core/template-part` block.

## Declared parts

| File                    | `templateParts` entry  | Area     |
| ----------------------- | ---------------------- | -------- |
| `header.html`           | `header`               | header   |
| `footer.html`           | `footer`               | footer   |
| `sidebar-indieweb.html` | `sidebar-indieweb`     | other    |
| `post-meta-zine.html`   | `post-meta-zine`       | other    |

## Until a part is added here

The parent (Ollie) part is used. Inheritance works the same way as
templates — only the parts you override need to live here.

## When to add a part vs. extend the parent

- **Add an override** when the structure of the part needs to change
  (e.g. adding the IndieWeb sidebar with h-card, social links, theme
  toggle).
- **Extend the parent** (via theme.json + components.css) when only
  the styling changes. Inheritance keeps Ollie's HTML structure but
  applies your tokens.

The header part is the most likely first override since it carries
the theme toggle (Interactivity API), the masking-tape branding,
and the IndieWeb h-card.
