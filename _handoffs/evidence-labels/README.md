# Evidence labels: implementation and remaining site work

## Status

Prepared against theme `main` at `3484887618fd7cbfe234bb7fcad727cc9222e117`
(0.7.154). The patch proposes 0.7.155; recheck the release number before merging.
This is the presentation component. Site deployment and migration of the three
existing Merge Conflicts posts remain separate work. Preserve each post's actual
status when editing; do not assume an already authored post is still a draft.

## What this patch supplies

- Six native Group/Paragraph block patterns, under **Courtneyr · Cards and marks**
  and **Zine Layouts (Courtneyr)**, named **Evidence — [label]**.
- A dedicated stylesheet shared by the editor and frontend. Wide containers use
  a left margin with the normal reading column retained; narrower containers
  put the label above its passage. A passage can contain multiple native blocks.
- Decorative SVG backgrounds with live, lowercase label links. The account
  speech bubble points right toward the passage. Hypothesis uses a thought cloud
  and two detached circles to its right.
- Existing color and type tokens, visible keyboard focus, forced-colors fallback,
  and a text-first print treatment. These require browser verification below.
- Separate SVG source files and a positioning manifest in
  `assets/evidence-labels/`. The stylesheet embeds the SVGs as data URIs to survive
  WordPress/Perfmatters CSS inlining. Keep both representations synchronized.

The stylesheet follows the existing native per-block enqueue approach and
attaches to `core/group`. It can therefore load on pages containing ordinary
Groups even when they have no evidence labels. Its selectors remain scoped to
the evidence component; the additional loading cost is a known limitation.

The six labels keep their approved meanings:

| Label | Meaning |
| --- | --- |
| verified | Read in a primary governing document, filing, record or official decision, cited with section or passage |
| source-derived | Stated by a reliable secondary source, not yet checked against a primary record |
| account | A named participant’s attributed account. Not a finding |
| analysis | The researcher’s interpretation of cited facts |
| hypothesis | A claim to test. Not a finding |
| unknown | Searched for and not established |

These describe the basis for a statement. They are not a quality ranking.

## Native architecture decision

- **Ollie evaluated:** source `theme.json` and native constrained/wide layouts.
  Reuse the child theme's 720px reading measure and 1100px wide measure.
- **Core evaluated:** `core/group` and `core/paragraph` source metadata, including
  alignment, custom classes, layout, metadata, and paragraph placeholders.
- **Selected:** native patterns with scoped CSS. A paragraph style alone cannot
  keep a label paired with a passage of several blocks. Groups supply that
  association without a new block, custom JavaScript, or a content render filter.
- **Existing custom code kept:** generic tape styling and existing evidence floats
  remain for unmigrated content. The new component resets those floats only
  inside its own rows. No plugin data or metadata ownership changes.
- **Open architecture checks:** the project architecture standard and the real
  WordPress Studio/wp-env workflow remain to be checked. Source inspection and
  the static fixture do not substitute for those checks.

## Apply to the three posts once access is available

1. Identify the exact three posts in the authenticated site. Record IDs, titles,
   current statuses, modification times, and a before-copy of their complete
   serialized content. Inspect the real allowed blocks and rendered previews.
2. Locate the old evidence paragraphs, commonly
   `is-style-cr-tape-label cr-evidence-label`. Read each labeled passage to establish
   the existing label's scope; do not infer or alter its evidence classification.
3. Wrap each existing label and its associated native blocks using the matching
   pattern structure: `cr-evidence` > `cr-evidence__row` > the label paragraph and
   `cr-evidence__body`. Move the original passage blocks intact. Remove the old
   tape style from that label and add the matching `cr-evidence-label--TYPE` class.
4. Keep the visible label lowercase. Preserve existing link targets, IDs,
   citations, anchors, and inline formatting. New patterns default to
   `/merge-conflicts-methods/`; do not replace existing specific methods anchors.
5. Compare before/after article text, footnote IDs and links, and block nesting.
   Only label presentation and the necessary grouping should change. Leave
   titles, wording, dates, SEO, media, classifications, and statuses intact.
6. Recheck modification times before saving to avoid overwriting concurrent work.
   Save the three updates and read them back. Verify both editor block validity
   and frontend behavior before marking the migration complete.

Do not apply a global regex replacement to arbitrary paragraphs or silently
classify unlabeled text. If one label covers several paragraphs, group the whole
existing passage; do not extend its scope into the next claim or section.

## Preview and verification

Serve this checkout locally and open `_handoffs/evidence-labels/preview.html`.
The fixture imports the real local theme fonts, tokens, and components. It
contains the six definitions, a multi-paragraph sample, the old tape class reset,
an editor-wrapper example, and a narrow container. Query options:
`?theme=dark`, `?text=200`, or both.

Static validation performed here:

- SVG structure and a rendered asset sheet were inspected.
- CSS minification and the repository's size/pruning guardrails passed.
- CSS lint and native-block structural review are recorded in the pull request.

Not verified here: PHP execution/PHPCS, a running WordPress editor, frontend at
1440px and 375px, dark mode, 200% zoom, forced colors, print, keyboard operation,
axe, or console output. Local browser installation failed; a static HTML file is
not proof of those behaviors. Run the repository's required workflow before
deploying and capture the actual three-post result.

## Deployment and rollback

The source repository does not automatically deploy to courtneyr.dev. Use the
current deployment checkout and its established theme export, minification,
review, staged verification, promotion, and cache process. Verify the deployment
checkout's freshness before exporting this committed source. A source-repository
commit or pull request alone does not change the site.

For rollback, use the three recorded before-copies/revisions to restore content
and revert the theme deployment sync commit through the existing process.
Coordinate content and stylesheet changes so readers do not see the new
markup without its styles.
