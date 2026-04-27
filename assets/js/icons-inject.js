// icons-inject.js — inlines the SVG sprite directly into every page that loads
// this script. The previous fetch-based approach failed silently on file:// origins
// (Chrome blocks fetch() between file:// URLs for security), which made icons
// disappear on local previews of UI Kit pages and Speaking.html. Inlining the
// sprite content bypasses that entirely.
//
// If you update components/icons.svg, regenerate this file by running:
//   python3 -c "import sys; svg=open('components/icons.svg').read(); print(svg)"
// and pasting the contents into the SPRITE_SVG template literal below — OR
// have Claude/Claude Code regenerate the whole file.
//
// After injection, every existing <use href="…/icons.svg#id"> is rewritten
// to a same-document <use href="#id"> reference so the symbols resolve.
(function () {
  var SPRITE_SVG = `<?xml version="1.0" encoding="UTF-8"?>
<svg xmlns="http://www.w3.org/2000/svg" width="0" height="0" style="position:absolute;width:0;height:0;overflow:hidden" aria-hidden="true">
  <defs></defs>

  
  <symbol id="post-icon-blog" viewBox="0 0 24 24">
    <title>Blog post</title>
    <path d="M6 3 H14 L19 8 V21 H6 Z"></path>
    <path d="M14 3 V8 H19"></path>
    <path d="M9 12 H16"></path>
    <path d="M9 15 H16"></path>
    <path d="M9 18 H13"></path>
  </symbol>

  
  <symbol id="post-icon-aside" viewBox="0 0 24 24">
    <title>Aside</title>
    <!-- Sticky note body -->
    <rect x="5" y="6" width="14" height="14"></rect>
    <!-- Masking tape strip across the top (wider than note for "applied" feel) -->
    <rect x="3" y="3" width="18" height="3.5"></rect>
    <!-- Two short text lines inside the note -->
    <path d="M8 11 H16"></path>
    <path d="M8 14 H13"></path>
    <!-- Bottom-right corner curl: diagonal fold line -->
    <path d="M16 20 L19 17"></path>
    <!-- Filled triangle showing the "back of paper" peeking through -->
    <path class="cr-icon-fill" d="M16 20 L19 20 L19 17 Z"></path>
  </symbol>

  
  <symbol id="post-icon-image" viewBox="0 0 24 24">
    <title>Image</title>
    <rect x="3.5" y="4.5" width="17" height="15"></rect>
    <circle cx="9" cy="9.5" r="1.6"></circle>
    <path d="M3.5 16 L8.5 12 L12.5 15 L16 12 L20.5 16"></path>
  </symbol>

  
  <symbol id="post-icon-gallery" viewBox="0 0 24 24">
    <title>Gallery</title>
    
    <rect x="7" y="3" width="14" height="14"></rect>
    <rect x="3" y="7" width="14" height="14"></rect>
  </symbol>

  
  <symbol id="post-icon-video" viewBox="0 0 24 24">
    <title>Video</title>
    
    <path d="M7 4 L11 8"></path>
    <path d="M17 4 L13 8"></path>
    <rect x="3" y="8" width="18" height="12" rx="1.5"></rect>
    <path d="M8 20 L7 22"></path>
    <path d="M16 20 L17 22"></path>
    <path class="cr-icon-fill" d="M10.5 11 L15.5 14 L10.5 17 Z"></path>
  </symbol>

  
  <symbol id="post-icon-audio" viewBox="0 0 24 24">
    <title>Audio</title>
    <path d="M4 14 V12 A8 8 0 0 1 20 12 V14"></path>
    <path d="M4 14 H7 V19 H5 A1 1 0 0 1 4 18 Z"></path>
    <path d="M20 14 H17 V19 H19 A1 1 0 0 0 20 18 Z"></path>
  </symbol>

  
  <symbol id="post-icon-chat" viewBox="0 0 24 24">
    <title>Chat</title>
    
    <path d="M9 4 H20 V12 H15 L13 14.5 V12 H9 Z"></path>
    <path d="M15 12 V16 H10 L8 18.5 V16 H4 V8 H9"></path>
  </symbol>

  
  <symbol id="post-icon-status" viewBox="0 0 24 24">
    <title>Status</title>
    <!-- Pen body (parallelogram, tilted like a pen held to write) -->
    <path d="M16 4 L20 8 L9 19 L5 15 Z"></path>
    <!-- Eraser cap line at back end -->
    <path d="M14 6 L18 10"></path>
    <!-- Sharp writing tip extending from body -->
    <path d="M5 15 L3 21 L9 19 Z"></path>
    <!-- Writing motion: wavy ink trail from tip -->
    <path d="M3 22 Q7 20 11 22 T19 22"></path>
  </symbol>

  
  <symbol id="post-icon-link" viewBox="0 0 24 24">
    <title>Link</title>
    <!-- Tilted gem-style paperclip — adapted from Lucide (MIT-licensed).
         One continuous bent wire:
           outer-tip (upper-right) -> diagonal down-left -> outer bottom hook
           -> diagonal up-right -> outer top loop (large arc) -> diagonal down-left
           -> inner bottom hook (small) -> diagonal up-right ending at inner tip. -->
    <path d="M21.44 11.05 L12.25 20.24 A6 6 0 0 1 3.76 11.75 L12.33 3.18 A4 4 0 1 1 18 8.84 L9.41 17.41 A2 2 0 0 1 6.58 14.58 L15.07 6.10"></path>
  </symbol>

  
  <symbol id="post-icon-bookmark" viewBox="0 0 24 24">
    <title>Bookmark</title>
    <path d="M6 3 H18 V21 L12 16.5 L6 21 Z"></path>
  </symbol>

  
  <symbol id="post-icon-quote" viewBox="0 0 24 24">
    <title>Quote</title>
    
    <path class="cr-icon-fill" d="M5 7 H10 V12 C10 14.5 8.5 16 6 16.5 V14.5 C7.5 14 8 13.5 8 12 H5 Z"></path>
    <path class="cr-icon-fill" d="M14 7 H19 V12 C19 14.5 17.5 16 15 16.5 V14.5 C16.5 14 17 13.5 17 12 H14 Z"></path>
  </symbol>

  
  <symbol id="post-icon-speaking" viewBox="0 0 24 24">
    <title>Speaking engagement</title>
    <rect x="9" y="3" width="6" height="11" rx="3"></rect>
    <path d="M5.5 11 A6.5 6.5 0 0 0 18.5 11"></path>
    <path d="M12 17.5 V21"></path>
    <path d="M8.5 21 H15.5"></path>
  </symbol>

  
  <symbol id="post-icon-book" viewBox="0 0 24 24">
    <title>Book</title>
    <path d="M12 6 V20"></path>
    <path d="M12 6 C9 5 5.5 5 3 6 V19 C5.5 18 9 18 12 19"></path>
    <path d="M12 6 C15 5 18.5 5 21 6 V19 C18.5 18 15 18 12 19"></path>
  </symbol>

  
  <symbol id="post-icon-like" viewBox="0 0 24 24">
    <title>Like</title>
    <path d="M12 20 C5 15.5 3 12 3 8.5 A4.5 4.5 0 0 1 12 7 A4.5 4.5 0 0 1 21 8.5 C21 12 19 15.5 12 20 Z"></path>
  </symbol>

  
  <symbol id="post-icon-repost" viewBox="0 0 24 24">
    <title>Repost</title>
    <path d="M5 9 V8 A2 2 0 0 1 7 6 H17"></path>
    <path d="M14 3 L17 6 L14 9"></path>
    <path d="M19 15 V16 A2 2 0 0 1 17 18 H7"></path>
    <path d="M10 21 L7 18 L10 15"></path>
  </symbol>

  
  <symbol id="post-icon-reply" viewBox="0 0 24 24">
    <title>Reply</title>
    <path d="M9 7 L4 11.5 L9 16"></path>
    <path d="M4 11.5 H14 A6 6 0 0 1 20 17.5 V20"></path>
  </symbol>

  
  <symbol id="post-icon-event" viewBox="0 0 24 24">
    <title>Event</title>
    <rect x="3.5" y="5.5" width="17" height="15"></rect>
    <path d="M3.5 10 H20.5"></path>
    <path d="M8 3.5 V7.5"></path>
    <path d="M16 3.5 V7.5"></path>
    <circle class="cr-icon-fill" cx="8" cy="14" r="1"></circle>
    <path d="M11 14 H17"></path>
    <path d="M7 17.5 H17"></path>
  </symbol>

  
  <symbol id="post-icon-review" viewBox="0 0 24 24">
    <title>Review</title>
    <path d="M12 3.5 L14.6 8.8 L20.5 9.7 L16.2 13.8 L17.2 19.7 L12 16.9 L6.8 19.7 L7.8 13.8 L3.5 9.7 L9.4 8.8 Z"></path>
  </symbol>

</svg>`;

  function rewriteUses() {
    var uses = document.querySelectorAll('use[href*="icons.svg#"], use[*|href*="icons.svg#"]');
    uses.forEach(function (u) {
      var href = u.getAttribute('href') || u.getAttributeNS('http://www.w3.org/1999/xlink', 'href') || '';
      var hashIdx = href.indexOf('#');
      if (hashIdx === -1) return;
      var id = href.slice(hashIdx); // "#post-icon-blog"
      u.setAttribute('href', id);
      u.removeAttributeNS('http://www.w3.org/1999/xlink', 'href');
    });
  }

  function inject() {
    try {
      var doc = new DOMParser().parseFromString(SPRITE_SVG, 'image/svg+xml');
      var rootSvg = doc.documentElement;
      if (!rootSvg || rootSvg.nodeName === 'parsererror') {
        throw new Error('inlined sprite: parse error');
      }
      var imported = document.importNode(rootSvg, true);
      imported.setAttribute('aria-hidden', 'true');
      imported.setAttribute('style', 'position:absolute;width:0;height:0;overflow:hidden');
      imported.setAttribute('data-icons-injected', '');
      document.body.insertBefore(imported, document.body.firstChild);
      rewriteUses();
    } catch (err) {
      if (window.console) console.warn('[icons-inject] could not inject sprite:', err);
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', inject);
  } else {
    inject();
  }
})();
