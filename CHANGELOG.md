# Changelog

## 0.7.132.2

What you see change on courtneyr.dev:

- The header no longer jumps on a slow phone. Before, when the page's cached styles missed the header rules, the theme switcher showed as three buttons and the header stood 21-23 px taller until the full stylesheet arrived about 2 s later; then the whole page jumped up (CLS 0.155 on live /blog/ on 2026-10-23).

Under the hood: `header_first_paint_css()` in `inc/enqueue.php` prints 5.6 KB of the `components.css` header rules inline on the components handle, with the wordmark mask URL built from the theme URI. With Perfmatters' used CSS stripped of every `components.css` rule and `components.css` blocked, the header keeps its final height and `main` its top at 390, 768 and 1280 px in light and dark; throttled CLS on that reproduction drops from 0.2074 to 0.0025. With the normal used CSS, or none, the header's geometry and colors are unchanged.

## 0.7.132.1

What you see change on courtneyr.dev:

- Dark mode, default setting: the yellow highlighter on the home page, About and the blog intro is a solid yellow block again, so the dark text stays readable (1.04 to 1.37:1 before; 8.98:1 now). Solid chips and the Listen cards get the same fix.
- Numbered lists in posts sit inside the text column with the numbers visible, on phones and desktop.
- Windows High Contrast: highlighted text keeps its highlight.

Under the hood: 21 dark-mode selectors in `components.css` and `cr-post-kinds.css` now use `:root:not([data-theme="light"])` instead of `:root:not([data-theme])`, which never matched the `data-theme="system"` that `no-flash.js` writes by default. A scoped rule pads `ol.wp-block-list`, whose block-library padding names an undefined preset. This is the 0.7.134 change on top of 0.7.132, so dev (which runs 0.7.132) shows only this delta.

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
