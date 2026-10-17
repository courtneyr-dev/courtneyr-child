# Changelog

## 0.7.113

What you see change on courtneyr.dev:

- Mood posts print as a year grid at /kind/mood/, one cell per day coloured by mood family, with a legend and a card for each day. The mood single gains the family name and an "Intensity N of 5" line beside the pin.
- Weather posts print as an almanac month at /kind/weather/, with one station log per weather single and a thermometer card on the Stream.
- Exercise posts print as a training log at /kind/exercise/, four weeks a page, with a medal row for personal bests and a race bib for each workout. The bib has a matching corner on the Stream.
- Sleep posts print as monthly chart sheets at /kind/sleep/, 31 nights a page, with a night record and moon phase on each single. A sleep sticker for the Stream is in the theme and appears once the site repo adds sleep to the Stream kinds at release.
- Craft posts print as a pegboard at /kind/craft/, with a three-column project single (cover and facts, materials and tools, numbered steps) and a tool tag on the Stream.
- Notes print as week boards of sticky notes at /kind/note/.
- The Previous and Next links on an untitled exercise or sleep post name it by kind and date, without the time. A note keeps its time.
- An untitled sleep post on a Blog card reads "Sleep, <date>". An untitled note keeps "Entry from <date>".
- Untitled mood posts, and protected or private untitled singles, print a real H1 (#170).

Under the hood: functions.php requires the 10 new kind files (the three craft files arrive with #173, and note needs no new file). tests/routes.mjs adds the mood, weather, exercise, sleep, craft and note archives, page 2 for weather, exercise, sleep, craft and note, and a seeded single for each kind. a11y-markup.spec.mjs covers the new archives and points the generic-template checks at /kind/article/, which still serves taxonomy-kind.html.

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
