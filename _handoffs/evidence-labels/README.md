# Evidence labels: implementation, migration, and deployment handoff

## Status

The initial presentation component merged as 0.7.155. The 0.7.156 container fix
is prepared against theme `main` at `0d005bd95e659d3dfca90036feacf0a8f0b3afa5`;
recheck the release number before merging. All 24 existing labels have been
migrated and saved across three Merge Conflicts posts, with their existing
statuses preserved. A comment-delimited copy of the full 0.7.156 stylesheet is
saved in Site Editor global Additional CSS; that field was originally empty.
The equivalent theme release has not been deployed. Canonical-content readback
and the live checks recorded below are complete; remaining untested behavior is
listed separately.

| Post ID | Preserved status | Labels saved |
| --- | --- | ---: |
| 40354 | Draft | 7 |
| 40333 | Published | 3 |
| 40256 | Published | 14 |

All three saved posts were reopened in the code editor. Their canonical content
matches the expected migrated content after normalizing only WordPress-native
`\u002d` escapes in JSON comment `className` values. Reopening each native visual
editor then showed zero `.block-editor-warning` elements, with statuses still
Draft / Published / Published as recorded above.
Keep article bodies, private before-copies, access details, and deployment
secrets out of this public repository.

## 0.7.156 container integration

Live inspection found a 720px native post-content container and a Post Kinds
`.pkiw-singular-entry > .e-content` wrapper around the evidence groups. A legacy
`.alignwide` rule further capped the evidence group at 90% of that reading column.
The scoped `.wp-block-post-content:has(.cr-evidence)` adapter allows those content
containers and their direct `.pkiw-singular-entry` wrappers to use the existing
wide-size token. It preserves the semantic wrappers and plugin-owned data.

Native constrained child rules still limit ordinary direct blocks to the content
size. Inside the observed semantic wrapper, the adapter applies that same measure
and automatic inline margins to ordinary `.e-content` children. It excludes
wide, full, left, and right alignments. The stronger `.wp-block-group.cr-evidence`
base selector overrides the legacy alignment cap regardless of stylesheet order.
No template or global reading-measure changes are needed.

The full component stylesheet currently runs through global Additional CSS.
Keep that copy in place until equivalent theme stylesheet deployment is verified;
only then remove the comment-delimited duplicate and recheck the affected posts.
Preserve any other CSS added since the initially empty field was recorded.
Measure the actual content box, including nested padding: the existing `68.5rem`
container-query threshold is 1096px at a 16px root, so stacking remains expected
below it.

The same adapter was verified through Additional CSS in an actual draft preview
at a 1440px viewport: the post-content container and all seven evidence groups
measured 1100px; the grid columns measured 182px / 720px / 182px. Ordinary and
labeled prose both measured 720px and shared the same 360px left edge. Document
scroll width remained 1440px. This records that frontend check, not a theme
deployment. At a 375px viewport, the draft's groups measured 343px, labels stacked
above their passages, and all labels and bodies fit. Existing admin-toolbar
overflow was observed separately and was unrelated to the evidence component.

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

## Procedure retained for future migrations

The three-post save and readback operations above are complete. Reuse this
procedure for future posts; completed and remaining verification are recorded
below.

1. Identify the target posts in the authenticated site. Record IDs, titles,
   current statuses, modification times, and a before-copy of their complete
   serialized content privately. Inspect the real allowed blocks and rendered
   previews.
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
   Save the updates and read them back. Verify both editor block validity
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

Live checks recorded during the migration:

- All three saved posts were reopened and their canonical content matched the
  expected migration, allowing only the native JSON escape normalization noted
  above. Reopened visual editors showed zero `.block-editor-warning` elements;
  label counts and Draft / Published / Published statuses were preserved.
- Draft frontend geometry passed at 1440px and 375px as recorded above.
- The published four-freedoms post at 1440px had three 720px passage bodies at
  x=360px, with labels at x=170px.
- The published introduction at 1512px had fourteen 720px passage bodies at
  x=396px, with labels at x=206px. At 720px, all fourteen rows used one 688px
  column; labels stacked above their bodies and both fit the viewport. This is
  a responsive viewport check, not an actual 200% browser-zoom check.
- The actual draft's dark-mode screenshot was inspected: stamp text remained
  readable on light stamp surfaces, while the article body used light text on
  a dark surface. Light mode was restored after the check. This was visual
  inspection, not a numerical contrast audit.
- Shift+Tab reached a live `source-derived` label link. It matched
  `:focus-visible` and displayed a 3px solid gold outline. This verifies that
  observed link interaction, not a complete keyboard-accessibility audit.

Remaining verification record (update only after observed results):

- **Untested:** actual 200% browser zoom, forced colors, print, and axe.
- **Not verified in this handoff:** PHP execution/PHPCS, console output, and a
  complete accessibility audit. The static fixture and the targeted live checks
  do not establish those results.
- **Pending deployment:** equivalent theme stylesheet loading and behavior after
  the established deployment and cache process.

## Deployment and rollback

The source repository does not automatically deploy to courtneyr.dev. Use the
current deployment checkout and its established theme export, minification,
review, staged verification, promotion, and cache process. Verify the deployment
checkout's freshness before exporting this committed source. A source-repository
commit or pull request alone does not change the site.

Keep the current comment-delimited Additional CSS until the equivalent deployed
theme stylesheet is verified. After that verification, remove only the duplicate
component copy, retain unrelated custom CSS, and recheck the migrated posts.

For rollback, use the three recorded before-copies/revisions to restore content
and revert the theme deployment sync commit through the existing process.
Coordinate content and stylesheet changes so readers do not see the new
markup without its styles.
