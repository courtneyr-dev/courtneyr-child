# Mood pin artwork

103 emoji, one per file, named by Unicode code point. They print on the
mood pins (`inc/mood-pin.php`, `assets/data/moods.json`).

**Source:** [Twemoji](https://github.com/jdecked/twemoji) graphics by
Twitter and contributors, at commit `b6b55fef1e8636b540a6d016a4729ca8cdf2e60b`.
Licensed [CC BY 4.0](https://creativecommons.org/licenses/by/4.0/) — a copy
of the license is in `LICENSE-TWEMOJI-GRAPHICS` beside this file.

**Changes from the originals:**

- Every fill was remapped to the eleven-color courtneyr.dev palette
  (Russian violet `#241c4a`, periwinkle `#bcb5e3`, glaucous `#647baf`, sky
  blue `#8ecae6`, blue-green `#219ebc`, cerulean `#126782`, Prussian blue
  `#023047`, selective yellow `#ffb703`, UT orange `#fb8500`, light orange
  `#fee2c3`, light gray `#ebebeb`); inherited black became Russian violet.
- In 66 face emoji the full-circle face disk was removed, so the features
  print straight onto the pin's colored face. The remaining 37 files
  (hands, objects, symbols) are complete motifs.
- At render time the theme swaps `#241c4a` for the pin's ink color and, on
  dark pins, `#ebebeb` for the pin's accent. Files on disk are otherwise
  what ships.
