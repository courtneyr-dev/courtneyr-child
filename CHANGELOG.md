# Changelog

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
