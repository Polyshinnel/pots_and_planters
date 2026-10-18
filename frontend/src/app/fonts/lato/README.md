# Local Lato

Downloaded 2026-10-17 from the Google Fonts source repository:

- https://github.com/google/fonts/tree/main/ofl/lato
- `Lato-Regular.ttf` (400), `Lato-SemiBold.ttf` (600), `Lato-Bold.ttf` (700)

Converted to WOFF2 with FontTools/Brotli without subsetting; all 3023 glyphs retained per font. Only the weights used by the current UI are included. Original font names and embedded metadata are preserved.

License: SIL Open Font License 1.1. Full copyright and license text: `frontend/public/licenses/Lato-OFL.txt` (served at `/licenses/Lato-OFL.txt`).

Loaded through `next/font/local` in `src/app/layout.tsx`, exposed as `--font-lato`, and used by the Mantine theme for body and headings. Font files are emitted as same-origin Next.js static assets; no CDN or Google Fonts requests are needed at build or runtime.
