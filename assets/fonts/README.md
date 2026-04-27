## Web font files (woff2)

Drop self-hosted woff2 files here. The paths declared in `theme.json`
under `settings.typography.fontFamilies[].fontFace[].src` use these
names. WordPress resolves `file:./assets/fonts/{name}.woff2` relative
to the theme root.

### Required files

The Barlow + Roboto Slab + Rock Salt set used by the design system:

```
barlow-regular.woff2          (400 normal)
barlow-medium.woff2           (500 normal)
barlow-semibold.woff2         (600 normal)
barlow-bold.woff2             (700 normal)
roboto-slab-regular.woff2     (400 normal)
roboto-slab-bold.woff2        (700 normal)
rock-salt-regular.woff2       (400 normal)
```

### Where to source them

- **Barlow**: https://fonts.google.com/specimen/Barlow
- **Roboto Slab**: https://fonts.google.com/specimen/Roboto+Slab
- **Rock Salt**: https://fonts.google.com/specimen/Rock+Salt

All three are SIL Open Font License — self-hosting is allowed.

Convert TTF → WOFF2 with `woff2_compress` (Homebrew: `brew install woff2`)
or upload to https://www.fontsquirrel.com/tools/webfont-generator with
expert mode set to woff2-only.

### Why self-host

Loading from `fonts.gstatic.com` would require a CSP allowance and
violate the IndieWeb principle of content ownership. Self-hosting also
means no third-party tracking via the Google Fonts CDN, no Complianz
exemption needed, and no flash from `font-display: swap` waiting on a
slow third-party request.

### Until the files are added

The site will fall back to the next font in the family stack
(`system-ui` for body, `Georgia` for accent, `cursive` for display).
The theme remains usable; just won't carry the brand voice. Drop the
woff2 files in and the brand voice activates without a deploy.
