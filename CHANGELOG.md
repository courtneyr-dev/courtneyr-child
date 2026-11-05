# Changelog

## 0.7.158

What you see change on courtneyr.dev:

- Published web stories show in the Stream (`/stream/`) in date order among the other entries, each as a paper card with its 9:16 poster, a "Web story" tape label, the title and the date. The whole card is one link to the story. Drafts never show. (#268)
- Previous/Next rows keep a gap below them everywhere (singles, archive pagers), so the lavender "More from the blog" band no longer touches the chips.
- The lavender band at the foot of a post has a torn paper top edge, the same tear as the Merge Conflicts follow band. Any full-width periwinkle group gets the same edge.
- On the Merge Conflicts page, the cut-paper title is the same size as the Stream's and every other archive's (68 px tiles, was 35 px). On wide screens the "A field guide to decisions" tape and the "Twelve projects" patch sit to the right of the title, centered on it; on narrow screens they stack under it. The "Follow the investigation" band runs edge to edge, torn on top only, and meets the footer with no ivory gap.

Under the hood: `inc/stream-stories.php` flags the Stream page's Query Loop, adds `web-story` after every other `pre_get_posts` filter, and moves the surface meta clause into a subquery so stories pass by post type while posts keep the routing policy's own SQL. The card comes from Post Kinds' `pkiw_stream_card_kind_render` filter; `cr-stream-story.css` loads only when a story card renders and is in `RUCSS_INLINE_HANDLES`. The tear is `assets/img/paper-edge-top.svg`, an 8 px strip cut from `mc-paper-edge.svg`, used as a mask layer so the depth stays fixed at any band height. The follow section's pattern alignment is now `full`. Tests: `php tests/php/stream-stories-test.php`, `php tests/php/toc-test.php`.

## 0.7.157

What you see change on courtneyr.dev:

- A post's table of contents is a paper card with a hard shadow, its heading on a strip of tape, and each entry on a dashed ruled line with the marker highlight on hover and keyboard focus. When every entry already opens with an emoji, as Merge Conflicts headings do, the ✦ bullet drops so the emoji isn't doubled.
- Footnote numbers sit inside the footnotes box; two-digit numbers no longer cross its border.
- Post bodies get one blank line, the text's own line height, between paragraphs, lists, quotes and evidence groups, so spacer blocks aren't needed between them.

Under the hood: `inc/toc.php` adds `cr-toc` and `cr-toc--emoji` to Yoast's block through the HTML API (no new `preg_` lines) and loads `cr-toc.css` only where the block renders; the handle is in `RUCSS_INLINE_HANDLES`. The prose rhythm rule and the footnote padding live in `components.css`. Test: `php tests/php/toc-test.php`.

## 0.7.156

Presentation saved on site through temporary Additional CSS; equivalent theme deployment remains pending:

- Post-content containers containing evidence groups, including Post Kinds semantic wrappers, can use the theme's wide measure. Ordinary paragraphs retain the normal reading measure; other post-content containers keep their existing width.
- Evidence groups override the legacy generic wide-alignment cap, allowing labels to reach their margin layout when space permits.
- All 24 existing labels were migrated, saved, and reopened across three posts. Canonical content matches the expected migration, statuses remain Draft/Published/Published, and all three native visual editors show zero invalid-block warnings.
- Live checks verified the wide margin layout, stacked layouts at 375px and 720px, the draft's dark appearance, and visible keyboard focus on a label link. Actual 200% zoom, forced colors, print, and axe remain untested; see the integration handoff for the precise scope and measurements.

## 0.7.155

Prepared presentation changes; deployment and existing-post migration require verification:

- Six evidence-margin patterns use distinct stamps and stickers with live, linked label text. The account bubble points toward its passage; hypothesis uses a thought cloud.
- Labels occupy a separate margin when the content area has room and move above their passages on narrow layouts. A passage can contain multiple native blocks.
- The patterns use the same presentation in the editor and frontend. Existing tape labels keep their current behavior until their posts are migrated.

## 0.7.154

What you see change on courtneyr.dev:

- The handwritten masking-tape note sits in the horizontal center of the blue Methods & Sources box, retaining its overlap along the top edge on desktop and phones.

## 0.7.153

What you see change on courtneyr.dev:

- The Merge Conflicts Reading Desk links to Part 1 on a quiet paper surface instead of an orange box, with an underlined title and no heavy frame or offset shadow.
- Its text uses the paired surface and ink colors in light and dark appearance, including hover, active and keyboard focus. Text contrast is 15.00:1 in light appearance and 17.08:1 in dark appearance; keyboard focus keeps the two-color outline.

## 0.7.152

What you see change on courtneyr.dev:

- The “In this series” drawer uses the approved quiet paper treatment: a periwinkle label, an inked chevron, and numbered reading links. It starts closed and keeps native keyboard controls, with no outer frame or hard shadow.
- The featured image has 48px of space after the drawer on desktop and 32px on phones, whether the contents are open or closed. Other details blocks and images keep their existing layout.

## 0.7.151

What you see change on courtneyr.dev:

- Able Player transcripts start closed. The Transcript control opens and closes them, and keyboard users can focus and scroll the transcript panel. The open panel follows your light or dark appearance instead of showing a white box.
- The Merge Conflicts contents list fills with published series posts when no seasons are configured. It updates from WordPress as you publish, using the same reading order as the article list.

## 0.7.150

What you see change on courtneyr.dev:

- The Merge Conflicts series archive follows the approved zine mockup: a cut-paper masthead, a decision collage, the embroidered concert patch, and a handwritten annotation above Methods & Sources.
- Methods & Sources, the reading desk, companion research, and Follow the investigation use torn-paper surfaces, hand-drawn illustrations, tape labels, and rough marker accents. The layout adapts to phones and light or dark appearance.
- The contents list uses the seasons announced in WordPress. The reading desk and companion links show published, password-free articles only; Methods stays unlinked until its page is public. Later archive pages skip the Methods introduction.

## 0.7.149

What you see change on courtneyr.dev:

- Body text is a little bigger everywhere: the base size goes from 16px to 17px (`--cr-text-base`, 1rem to 1.0625rem).
- Blog posts use the same column as pages: 720px for text and 1100px for wide blocks, instead of 80% of the window on screens 1024px and wider.
- Footnotes sit in a closed "Footnotes (N)" details block at the end of a post, in 14px type. Following a footnote marker opens it and jumps to the note.

## 0.7.148

What you see change on courtneyr.dev:

- Merge Conflicts evidence labels ("Label: verified." and the rest) sit left of the claim they label, tilted twice as far as a plain tape label, with the claim's text wrapping beside them. It applies to a Tape label paragraph that also has the class `cr-evidence-label`; other tape labels don't change.

Version 0.7.147 is taken by the Able Player transcript PR (#258).

## 0.7.146

What you see change on courtneyr.dev:

- A post in a series loses the dark line between its series header (tape, description, "In this series") and the featured image.

## 0.7.145

What you see change on courtneyr.dev:

- The image set in PublishPress Series' "Series featured image" field now shows on the Merge Conflicts landing, taped under the cut-paper title on wide screens and after the start line on phones, with the media library's alt text. Nothing changes until an image is set.
- That image becomes the series page's share preview (Open Graph), in place of the site-wide default card. A social image set on the series term in Yoast still wins. A season archive uses its own image, or its collection's.

Under the hood: `Series\series_image()` reads the plugin's `orgseriesicons` table through `series_get_icons()` (read-only) and rebuilds the URL with `seriesicons_url()`; `Series\series_share_image()` hooks `wpseo_add_opengraph_additional_images`, which Yoast 28.5 fires after the term's own image and before its default. New `cover` view in `courtneyr/series-guide`, placed in the masthead pattern.

## 0.7.144

What you see change on courtneyr.dev (Merge Conflicts landing, copy only, Courtney's decisions of 2026-11-02):

- "How the series is organized" says the series is planned in eight seasons, without the 100-part total, which the outline still moves.
- The methods file's "why it matters" uses the methods page draft's own wording: "Every post in the series links to it. When a count or a claim changes, its log says what changed and when."

Kept as shipped in 0.7.143: the twelve-project list with the Season 3 sentence, and "Every part will link to it." under the "Not published yet" stamp.

## 0.7.143

What you see change on courtneyr.dev (the Merge Conflicts landing; other series archives only lose the sticky note):

- The masthead sets the thesis as a statement beside the cut-paper title, with its three questions (who decides, how they decide, who can review) marked in orange, and a way in under it: "Start with Part 1" and, once there are two parts, the latest one. With nothing published it says so and links to Follow along.
- Methods & Sources becomes a compact research file: why it matters, what the methods page holds (what the series compares, the six evidence labels, the twelve fairness rules, right of reply and corrections, who's writing), and the link, or a "Not published yet" stamp until the page is public.
- A new "How the series is organized" section: the planned scale (100 parts in eight seasons), the twelve projects Season 2 compares, the companion series, then a contents page that lists every season and companion arc that exists as a series term, with published counts or a "Planned" stamp.
- One Follow along block at the end replaces the launch card's links.
- Page 2 on skips the methods file and the introduction.
- Removed: the postmark, the postage stamp, the field-guide tape, the hash, the notebook drawing, the margin note and arrow, the dried flowers, the registration marks, the paperclip and the handwritten note over the parts. Row dates and reading times move from 12 px capitals to 14 px text, and rows in a season section no longer repeat the season.

Under the hood: `courtneyr/series-guide` trades the three art views for `start`, `contents` and `follow`; `cr-series-art.css` is replaced by `cr-series-front.css` (13 KB, in `RUCSS_INLINE_HANDLES`). New patterns `cr-series-mc-masthead` and `cr-series-contents`; `cr-series-methods` is rewritten from the methods page draft. Once the contents view renders, the season chips, the bottom companion list and the launch note step aside so each fact appears once. `Series\skip_front_matter_when_paged()` drops the methods file and introduction on later pages. `series-test.php` checks the landing order, the front matter and the removals.

## 0.7.142

What you see change on courtneyr.dev:

- Series archives group their parts by season, the way the Merge Conflicts mockup lays them out: each season gets a tape label ("Season 1"), its title and description, and a white panel of numbered rows. A row shows a big numeral (yellow for a season's first part), the date, season and reading time, the title, the excerpt and an arrow, and the whole row is the link. A featured image on a season's first part sits taped in the panel's left gutter on wide screens. A series without seasons gets one panel. A handwritten note on the first panel says only published essays appear and new parts join as they come out.
- The season index under the orange rule becomes one line of chips; seasons with nothing out say "Coming later" and don't link.
- The archive header puts the cut-paper title on the left and the description on the right on wide screens, and drops the parts and seasons counts (Courtney, 2026-11-02). A season's header still links back to its collection.
- Merge Conflicts only: a torn sky-blue Methods & Sources band with a notebook drawing and a pen arrow; a postmark ("Open source · People · Processes · Power"), a postage stamp and a "A field guide to decisions" tape label in the header's top-right corner; a hand-drawn hash beside the title; and, in the wide margins once parts exist, a handwritten "Same questions, different projects" with its arrow, a sprig of dried flowers, registration marks and a paperclip on the first panel. All of it is decoration, hidden from screen readers, and none of it shows where the margins can't hold it.
- The launch card gets a strip of tape.

Under the hood: `Series\group_parts()` filters `core/post-template` on series archives and wraps its rendered rows in season sections, so the main query, its ten-per-page pagination and the post template stay core blocks; it reads each row's post ID with `WP_HTML_Tag_Processor` (no new `preg_` calls). The `courtneyr/series-part` bindings source gains `numeral` ("03") and `read` ("12 min read", 238 words a minute) keys; the numeral paragraph is `aria-hidden` with `role="presentation"` and the "Part 3" label stays in the text, visually hidden. Part titles are `h3` under the season `h2`. `courtneyr/series-guide` gains `hero-art`, `methods-art` and `margin-art` views; the postmark comes from `inc/stamps.php`. New `cr-series-art.css` (9 KB) loads only where an art view renders and is in `RUCSS_INLINE_HANDLES`. `series-test.php` adds 14 checks.

## 0.7.141

What you see change on courtneyr.dev:

- Nothing visible. Accessibility Checker Pro stops flagging the dot between "Merge Conflicts" and "Part N" in a series post's tape label, and the arrows on the previous/next part cards.

Under the hood: the three `aria-hidden` spans in `inc/series-views.php` now carry `role="presentation"`, the rule 0.7.138 set for every decorative element. `series-test.php` fails if one goes back without it.

## 0.7.140

What you see change on courtneyr.dev:

- Series archives (`/series/<slug>/`) get their own page: the cut-paper title and description, a published-parts count, the published parts as numbered cards in part order, ten per page, and a launch note instead of the Stream's "No posts in this stream yet" when nothing is out. The Merge Conflicts landing adds a sky-blue Methods & Sources callout before the parts; its link appears only once the methods page is published.
- Posts in a series replace PublishPress Series' two boxes with one header (a tape label such as "Merge Conflicts · Part 1 of 1", the series description, and a collapsed "In this series" list of published parts) and add previous/next part cards after the footnotes.
- `/series-toc/` and the series category pages (`/series-category/<slug>/`) use the theme's archive layout.

Under the hood: `inc/series.php` turns the plugin's `the_content` insertions off through its own `pp_series_add_series_content` filter, dequeues its stylesheets, and orders series archives by `_series_part_{term_id}` in `pre_get_posts`. A series group with the same slug as a series makes that series a collection landing whose other group members are seasons and whose child groups hold companion arcs; groups are read through the plugin's `series_grouping` shadow posts without calling `get_groups_in_series()`, which inserts missing ones. Everything lists published, password-free posts only. New: `courtneyr/series-guide` block, `courtneyr/series-part` bindings source, `cr-series.css` (in `RUCSS_INLINE_HANDLES`), templates `taxonomy-series`, `taxonomy-series-merge-conflicts`, `taxonomy-series_group`, `series-toc`, and `tests/php/series-test.php`.

## 0.7.139

What you see change on courtneyr.dev:

- Nothing visible. This release exists to carry the theme's PHP tests into continuous integration and to prove the autopilot's release gates on a real deploy (Wave 0 rehearsal 3).

Under the hood: the new "PHP unit tests" GitHub Actions job runs all 114 `tests/php/*-test.php` files against WordPress 7.1.4 and Post Kinds for IndieWeb's `develop` branch, and fails on any nonzero exit or `SKIP` line; branch protection now requires it (#248). `collection-template-test` checks `functions.php` for its two collection requires directly, instead of a diff against `origin/main` that only held on the PR that added them.

## 0.7.138

What you see change on courtneyr.dev:

- Nothing visible. Accessibility Checker Pro stops flagging decorative icons and covers on the Stream, singles and kind archives: every `aria-hidden` ornament now also carries `role="presentation"`, as the theme's own ornaments already did.

Under the hood: `hidden_is_presentational()` in `inc/a11y-output.php` adds `role="presentation"` to each `aria-hidden="true"` element that has no role (an `<img>` excepted) on `core/post-content`, `core/post-template`, the stream card and `courtneyr/archive-identity`, at priority 999 after every adapter. The checker's `aria_hidden_valid_usage` rule tests each element on its own, so an icon `<svg>` inside a hidden badge failed too (#245). `stream-style-pruning.spec.mjs` reads a deferred sheet's `data-cr-href`.

## 0.7.137

What you see change on courtneyr.dev:

- While the cookie consent dialog is open, tabbing to a link scrolls it above the dialog instead of leaving it hidden underneath (WCAG 2.4.11, #109). The page also gains room at the end so the last links can scroll clear of the dialog.

Under the hood: `site-ux.js` watches `.cmplz-cookiebanner` with a ResizeObserver and sets `--cr-consent-covered` on `<html>` (the dialog's height, capped at 60% of the viewport) while it shows, rerunning on Complianz's `cmplz_cookie_warning_loaded` event and on resize. The `html` rule uses that variable for `padding-bottom` and `scroll-padding-bottom`, falling back to the Manage consent button's 50px (#241). The Playwright a11y spec skips tests whose dev-only fixture is missing locally instead of failing, and the focus-ring test writes its inline outline after focus (#242, #108, #110, #113).

## 0.7.136

What you see change on courtneyr.dev:

- The theme switcher in the header shows which option is on again (Light, Dark or System) and tells screen readers which one is pressed. Since #207 it lost that state as soon as the page finished loading, though clicking still changed the theme.
- /stream/, the kind archives and the home page start drawing sooner: the collection and pagination stylesheets no longer block first paint on pages that don't use them (indicative throttled Lighthouse on dev: /stream/ LCP 4.6-4.8 s vs 5.2-5.4 s on 0.7.135).
- Tables, code blocks, separators and figure captions in posts keep their styling when a post is the first of its type that Perfmatters sees.

Under the hood: the theme toggle store read `getElement().ref` in its `pressed` and `isActive` getters, which is null while bindings render; it now reads `getElement().attributes` (#237). `cr-collection.css` loads only where a collection card or group renders, `cr-nav.css` joins the deferred sheets on the front page, and Syndication Links' and IndieWeb's stylesheets are dequeued on the stream, /blog/ and kind archives (#236). `RUCSS_KEEP_SELECTORS` keeps `hr`, `figcaption`, table and code rules in post used CSS and `ol>li` markers in tax used CSS (#236). CI also checks for `:root:not([data-theme])` rules with no system variant (#238).

## 0.7.135

What you see change on courtneyr.dev:

- Dark mode, default setting: the yellow highlighter on the home page, About and the blog intro is a solid yellow block again, so the dark text stays readable (it was dark navy on near-black over the top half of each line, 1.04 to 1.37:1; now 8.98:1).
- Numbered lists in posts sit inside the text column with the numbers visible, on phones and desktop.
- Windows High Contrast: highlighted text keeps its highlight.
- The header keeps its final size while the page loads, so /blog/ no longer jumps when the main stylesheet arrives (it could shift 0.2 when the page type's used CSS lacked the header rules).

Under the hood: the highlight's dark rule matched `:root:not([data-theme])`, but `no-flash.js` writes `data-theme="system"` for every visitor with no stored choice, so it never applied; the dark-mode selector lists in `components.css` and `cr-post-kinds.css` now use `:root:not([data-theme="light"])`, as `tokens.css` does, and the twelve `cr-post-kinds.css` rules that had a separate `[data-theme="system"]` copy lose the copy. Core's `ol:where(.wp-block-list)` padding names a spacing preset this theme doesn't define, so `components.css` adds a scoped rule and `wp-block-list` joins the post keep classes. `inc/enqueue.php` prints the header's first-paint rules inline with `components.css`'s handle (#232, fix/blog-header-first-paint).

## 0.7.134

What you see change on courtneyr.dev:

- Each quote on /kind/quote/ is now the single quote's pinned notecard: the same paper color, pin, tilt, quote mark, handwriting and highlights as the quote's own page. Long quotes show their first nine lines.

Under the hood: inc/quote-book.php draws the notecard on each quote-book entry from the single's helpers in inc/quote-note.php and the post's own quote block, and cr-quote-book.css carries the single's notecard rules (#233).

## Unreleased

What you see change on courtneyr.dev:

- Nothing. This is a CI check; no stylesheet changed, so there is no version bump and nothing to deploy.

Under the hood: `tools/css-guardrails.mjs` (`npm run lint:css-guardrails`, a new CI step) enforces the 30 KiB sheet budget with a ceiling on today's oversized sheets, no new selector list naming two kinds, `RUCSS_INLINE_HANDLES` entries that resolve to a sheet, and a decision on purpose for every new `cr-*.css`. The baseline is `tools/css-guardrails.baseline.json`. `CLAUDE.md` carries the rules.

## 0.7.133

What you see change on courtneyr.dev:

- The listen archive's header sits centered over the boombox, with a short orange marker under its description, and the boombox still life is redrawn as torn-paper collage.

Under the hood: cr-archives.css composes the listen header on the boombox axis and restores the 8rem short marker; the still-life SVGs use the palette's paper-fibre, halftone, torn-edge and hard-shadow vocabulary (#229, PKIW #226, Codex mockup fidelity 98).

## 0.7.132

What you see change on courtneyr.dev:

- Quote, issue, collection and drawn-comic posts get their own designs: a commonplace book at /kind/quote/, repair-shop claim tickets for issue posts, a museum display cabinet for collections, and a sketchbook with an open spread for comics you drew. Before this release those files were in the theme but never loaded, so the plugin's default layouts showed.
- Issue posts and collections appear on /stream/, and drawn comics preview there (with the site's cr-content-surfaces 1.6.0).

Under the hood: functions.php requires inc/issue.php, single-issue.php, stream-issue.php and comic-strip.php, and tests/php/functions-requires-test.php fails if an inc/ file isn't required. The quote book's dark-mode rule uses a new --cr-paper-rule token instead of a raw hex. Each kind passed a Codex mockup-fidelity loop on the local stack (#257 99, #264 97, #240 97, #266 97). Carries 0.7.131's sync-time CSS minify, which dev and test already serve; the version moves past it so caches fetch the new sheets.

## 0.7.131

What you see change on courtneyr.dev:

- Nothing you can see. Every page looks the same; the theme's stylesheets are smaller on the wire.

Under the hood: `private/bin/sync-theme.sh` in the deploy repo now runs `tools/minify-css.mjs` (esbuild 0.28.2, pinned) on its export of this repo before it copies the theme, so deployed stylesheets are minified with the same names and paths. The sources here stay commented. CI runs the same script with `--check`. `CR_MINIFY_CSS=0` makes a sync copy the sources unchanged.

## 0.7.121

What you see change on courtneyr.dev:

- Gallery singles carry more of the zine page at every width, not only in wide margins. A contour-map fragment peeks past the masthead on wide screens and as a torn tab under its corner on narrower ones. A short torn violet scrap sits at the start edge and a gridded blue strip at the end edge. Beside the sheet: a handwritten note with a heart above it, a stamp sticker on its bottom corner, a one-line word strip from 1024 px, and a doodle (star, ring, film strip or arrow) chosen per post. The margin postmark is gone, so the masthead postmark and the sticker are the page's two stamps (#197, #199).
- Quotes come on five papers: Lavender, Ivory, Peach, Sky and Butter, each a block style on the Quote block. A quote with no paper picked gets one seeded by its post ID, and the same quote shows the same paper on its single, /kind/quote/ and the Stream (#189).
- The new keepsakes block beside the gallery sheet carries role="presentation" with aria-hidden, like the other zine ornaments since 0.7.120, so Accessibility Checker accepts it (#198).

Under the hood: the note, sticker, word strip and doodle text lives in data-text attributes and CSS draws it, so none of it joins the post content, its microformats or the accessibility tree. The release bump gives the served stylesheets a new ?ver= on test and live, which ran an earlier 0.7.120 build.

## 0.7.118

What you see change on courtneyr.dev:

- A photo post with a single image prints it in a photo-frame block style: Polaroid, Taped print, Photo corners, Postcard or Film frame. The author picks one on the Image block, or the post's seed picks one. The projector bars are gone from these singles (#190).
- A gallery-format single, and a photo post whose gallery the projector would take, prints as a zine photo essay. The H1 sits in a photocopier-black masthead with the kind patch, category stickers and a postmark of the post's date and frame count. The panels are numbered and laid out by each picture's real shape without cropping, and core's lightbox steps through all of them (#190).
- Gallery singles carry zine and field-journal pieces in the page margins on wide screens: halftone, a taped legal-pad scrap, ink spatter, a postmark, a sewn patch, index ticks and a fold. The post ID picks one of three arrangements. The gallery sits on a second sheet of graph paper held with washi tape, and related posts open on a torn, taped edge. Phones keep the paper and tape and drop the margin pieces (#192).
- On a gallery single with likes, bookmarks or reposts, the reactions sit on a taped scrap beside the newsletter band, which turns to graph paper. The scrap shows a count for each kind ("3 likes" to a screen reader) above the avatar pile, which keeps each response's link (#192).
- A single with no likes, bookmarks or reposts no longer leaves an empty band between the post and the newsletter (#191).
- A wish clipping on the Stream shows its torn top edge in light mode, and its chips and date line up with the title (#185).
- On the Stream, a single photo prints as a polaroid again and a gallery photo post as a photo booth strip, as before W3. "+N more frames" on a strip is text, so each Stream item keeps one link (#186).
- /kind/quote/ prints each quote as the single's pinned sheet: pin, the full quote with its citation, handwritten at 120 words or fewer, and the date. The Stream keeps its preview note (#187).

Under the hood: the empty-reactions drop moved from inc/like.php to the shared single filters in inc/post-kinds.php. inc/gallery-zine.php and assets/css/cr-gallery-zine.css are new, and assets/css/cr-photo-frames.css adds the frame styles. On gallery singles, IndieBlocks' facepile gets three count-only blocks ahead of its avatar block through render_block_data.

## 0.7.117

What you see change on courtneyr.dev:

- The front page also loads cr-home-sections.css, cr-paper.css and the kind sheets that survive pruning without blocking the first paint. Measured on Pantheon test, the front page went from 30 cr-* stylesheets to 7, none render-blocking, and throttled LCP from 4.26 s to about 2.5 s (#193).

## 0.7.116

What you see change on courtneyr.dev:

- The front page loads cr-post-kinds.css without blocking the first paint: the stylesheet swaps in from print media, with a `<noscript>` copy. With the sheet blocked outright, the first viewport at 412 and 1440 px stayed pixel-identical, so nothing on screen waits for it. /stream/ keeps it render-blocking (#193).

## 0.7.115

What you see change on courtneyr.dev:

- The front page and /stream/ load only the kind stylesheets their cards use, not all of them. On 2026-10-19 that was 26 render-blocking cr-*.css files on the front page for cards of five kinds (#193).

Under the hood: inc/stream-style-pruning.php records the classes of every rendered block and dequeues a kind sheet when none of its distinctive classes rendered. Shared sheets, generic classes and sheets with no distinctive class are never pruned. tests/playwright/stream-style-pruning.spec.mjs is the contract.

## 0.7.114

What you see change on courtneyr.dev:

- Video posts leave the generic archive template. /kind/video/ prints year shelves of camcorder tapes, 12 a page, with one link on each tape and a year heading that repeats when a year runs onto the next page. The video single puts its title in a tape-label strip above the player, as the page's one H1, with the date and run time beside it. On the Stream a video is a compact tape spine.
- The listen shelf at /kind/listen/ gains a type nav, All and Podcasts. Podcasts at /kind/listen/?pkiw_listen_type=podcast print as a lineup with a station for each show, 12 lines a page. A podcast single opens with a record (kind label, Show, Episode, Tuned in, rating and a listen or find-it link) ahead of the post, and a podcast on the Stream is a call-sign card with one link. Music listens keep their cassette.
- Audio posts print as a cassette log at /kind/audio/, grouped by month, with the answering machine in the header. The audio single lifts its player into an answering machine and opens the transcript as a sheet beside it when a transcript is stored. An audio cassette on the Stream grows with a long title instead of cutting it off.
- Jams print as a jukebox at /kind/jam/, with a Now Playing lead on page 1, month shelves and three, two or one columns. The jam single is a J-card with insert, details, spine and note panels, and the embedded player follows the card once. A jam on the Stream is a strip.
- Wishes print as a catalog at /kind/wish/, grouped by type in three, two or one columns, with the priority as text and a circle on high-priority items. The wish single drops its repeated picture. A clipping for the Stream is in the theme and appears once the site repo adds wish to the Stream kinds at release.
- Questions print as slips at /kind/question/, grouped by answer status, with the answers listed on each question. A question slip for the Stream is in the theme and appears once the site repo adds question to the Stream kinds at release.
- Video and audio players on video and audio posts no longer autoplay. They show controls and load metadata only.
- An untitled audio, jam or question post prints a real H1 that reads "<Kind>, <date> at <time>". An untitled wish prints its item name.

Under the hood: cr-post-kinds.css no longer paints the video, jam, wish and question Stream cards, since each kind's own stylesheet does. functions.php requires the 13 new kind files (inc/video.php, single-video.php, stream-video.php, audio.php, single-audio.php, jam.php, single-jam.php, single-podcast.php, wish.php, single-wish.php, stream-wish.php, question.php and stream-question.php). tests/routes.mjs adds the video, audio, jam, podcast, wish and question archives, page 2 for video, audio and question, and 26 seeded singles named by fixture key. a11y-markup.spec.mjs puts the six archives through the cut-paper title, header, pager and one-link tests, and checks that each seeded single prints one H1.

## 0.7.113

What you see change on courtneyr.dev:

- Mood posts print as a year grid at /kind/mood/, one cell per day coloured by mood family, with a legend and a card for each day. The mood single gains the family name and an "Intensity N of 5" line beside the pin.
- Weather posts print as an almanac month at /kind/weather/, with one station log per weather single. A thermometer card for the Stream is in the theme and appears once the site repo adds weather to the Stream kinds at release.
- Exercise posts print as a training log at /kind/exercise/, four weeks a page, with a medal row for personal bests and a race bib for each workout. The bib has a matching corner on the Stream.
- Sleep posts print as monthly chart sheets at /kind/sleep/, 31 nights a page, with a night record and moon phase on each single. A sleep sticker for the Stream is in the theme and appears once the site repo adds sleep to the Stream kinds at release.
- Craft posts print as a pegboard at /kind/craft/, with a three-column project single (cover and facts, materials and tools, numbered steps) and a tool tag on the Stream. Craft posts of 4,000 characters or more stay on the Blog until the site repo's long-post exemption for craft ships at release.
- Notes print as week boards of sticky notes at /kind/note/.
- The Previous and Next links on an untitled exercise or sleep post name it by kind and date, without the time. A note keeps its time.
- An untitled sleep post on a Blog card reads "Sleep, <date>". An untitled note keeps "Entry from <date>".
- Untitled mood posts, and protected or private untitled singles, print a real H1 (#170).
- An Aside scrap on the Stream has one link, its Read more chip, and an untitled scrap's heading reads "Aside" again (#176). An image-heavy note or scrap shows its "+N" image count as text.

Under the hood: functions.php requires the 10 new kind files (note needs no new file). tests/routes.mjs adds the mood, weather, exercise, sleep, craft and note archives, page 2 for weather, exercise, sleep, craft and note, and 18 seeded singles across the six kinds (named by fixture key, including the weather privacy singles A12 and M01). a11y-markup.spec.mjs covers the new archives and points the generic-template checks at /kind/article/, which still serves taxonomy-kind.html.

## 0.7.112

What you see change on courtneyr.dev:

- Chicken posts print as an egg-carton archive, with a coop-board single and an egg on the Stream.
- Photo posts print as contact sheets at /kind/photo/, with the photo single and a slide on the Stream. The polaroid and the booth strip are retired for photo posts.
- Favorites print as a star chart, with a scrapbook single and a clipping on the Stream.
- Acquisitions print as receipts, one bag per post, with a receipt single and a stub on the Stream.
- Reposts print as zine spreads, with a taped-clipping single and a clipping on the Stream.
- Photo singles 3925, 1820 and 1332 start printing their featured image. The file for 1332 is missing on dev, so that single shows the missing-image state there.
- The map alt text from Simple Location drops the street address on every kind unless the street is visible on the page.
- Article cards lose the Read more link and the meta strip. They keep their hero captions (W3-G10 answer).

Under the hood: functions.php requires the 14 new kind files, tests/routes.mjs adds the six W3 kind archives plus page 2 for photo, favorite, acquisition and repost, and a11y-markup.spec.mjs covers the new archives and the one-link article card.
