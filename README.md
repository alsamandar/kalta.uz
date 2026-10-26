# Kalta.uz

Free tools for the web, in Uzbek, Russian and English:

| Tool | Page | What it does |
|------|------|--------------|
| Link shortener | `/` | Short link + QR code, instant (no page reload), recent links kept in the browser |
| File sharing | `/files/create` | Upload up to 20 MB, get a download link |
| QR codes | `/qr` | Live preview, PNG / SVG download |
| Text & code | `/codes/create` | Unlisted snippets with syntax highlighting and expiry |
| Link in bio | `/bio/create` | One page for all social links, 5 colour themes, live preview |
| Wedding invitations | `/invitations/create` | Invitation page, personal guest links, RSVP, e-mail / Telegram / WhatsApp sending |
| My links | `/dashboard` | Optional account: see and delete your links |

Built with Laravel 10, plain CSS and a little vanilla JavaScript. **There is no front-end build step**: edit
`public/css/app.css` or `public/js/app.js` and upload them.

## Run it locally

```bash
composer install
cp .env.example .env && php artisan key:generate
# set DB_* in .env, then:
php artisan migrate
php artisan serve
php artisan test          # SQLite in memory, never touches your database
```

## Languages

* Uzbek (Latin) is served from the root, Russian from `/ru`, English from `/en`. Each page links to the others with
  `hreflang`, and `/sitemap.xml` lists every page in every language.
* In views always build links with `lroute('route.name')` (keeps the current language), never `route()`
  for pages that exist in all three languages. Routes are registered per language in `routes/web.php`
  (`home`, `ru.home`, `en.home`, ...).
* Strings are plain English keys: `{{ __('Shorten link') }}`. Add the Uzbek and Russian text to `lang/uz.json` and
  `lang/ru.json`. `tests/Feature/InfrastructureTest.php` fails if a string is missing, or a `:placeholder` is lost.
* Uzbek uses `oʻ` / `gʻ` (U+02BB) and `ʼ` (U+02BC); a test checks for stray `'` and `‘`.

## Configuration (`.env`, see `.env.example`)

| Variable | Purpose |
|----------|---------|
| `SEO_INDEX=false` | Staging/test copies: `noindex` everywhere and `Disallow: /` in robots.txt |
| `ADS_YANDEX=false` | Remove the Yandex ad blocks (they load after the page is interactive) |
| `KALTA_TELEGRAM`, `KALTA_CONTACT_EMAIL` | Footer and structured data |
| `KALTA_TIMEZONE` | Wedding times are entered and shown in this zone (default `Asia/Tashkent`) |
| `MAIL_*` | Needed for e-mailing invitations (the buttons for Telegram/WhatsApp work without it) |

More settings (upload limit, blocked file types, reserved short codes, Cloudflare ranges) are in `config/kalta.php`.

## Deploying (FTP is fine)

1. Upload the changed files. Do not upload `.env`.
   **Delete the old static `public/robots.txt` and `public/sitemap.xml` on the server**: a real file wins over a route,
   and both are now generated (correct host, every page, every language). The old assets are unused too:
   `public/css/{style,normalize,bio,nopaste}.css`, `public/js/{main,nopaste}.js`, `public/img/{upload,qr_code,link_in_bio,transparent,search}.png`,
   `public/img/site.webmanifest`.
2. Run `php artisan migrate` once (adds invitations, a click counter, longer text snippets and long links, and the
   sent/opened tracking and own message for wedding invitations: four migrations in all).
3. `storage/` and `bootstrap/cache/` must be writable by the web server. Uploads (files, avatars, covers) are stored in
   `storage/app/public` and served by the app, so `public/` needs no write access and no `storage:link`.
4. `public/.user.ini` raises PHP's upload limits to 25 MB (works with PHP-FPM; with Apache add the same to `php.ini`).
   The upload page shows the limit that is really in effect.
5. Behind Cloudflare the app reads the visitor's IP from `CF-Connecting-IP` (only when the request really comes from
   Cloudflare's ranges, see `config/kalta.php`), so rate limits and stored IPs are per visitor.
6. Optional, in the Caddyfile, to cache static files for a year (asset URLs carry a `?v=` version):

   ```
   @static path /css/* /js/* /fonts/* /img/* /vendor/*
   header @static Cache-Control "public, max-age=31536000, immutable"
   ```

## Where things are

```
app/Http/Controllers   one controller per tool (Short, File, Qr, Code, Bio, Invitation) + Kalta (short codes)
app/Http/Middleware    SetLocale, SecurityHeaders, NoIndex, CloudflareClientIp
app/Helpers/functions.php   lroute(), qr_svg(), normalize_url(), randomString(), upload_limit_kb() ...
resources/views        layouts/app.blade.php is the page shell (SEO head, JSON-LD); one folder per tool
public/css/app.css     the whole design system (light/dark); invitation.css (guest page) and manage.css (hosts' pages) only where used
public/js/app.js       progressive enhancement; every page also works without it
public/js/manage.js    hosts' pages only: guest search/filters, "sent" marks with undo, new-reply banner, date hint
app/Support            Submission, Images, Icons, GuestList (paste parser + dedupe), Ics (calendar file), Visitors (who is a person), PreviewImage (link-preview picture)
lang/                  uz.json, ru.json (+ validation/auth/passwords messages)
```

## Notes

* Short codes use an alphabet without `0 O 1 I L`. Every kind of content (link, file, bio page, snippet, invitation)
  is a `Kalta` row pointing at its model, so one route resolves them all.
* Forms that create things are safe to repeat: each carries a `_submission` id and `App\Support\Submission` returns the
  first result for a repeat of the same data (retry after a lost connection, double tap). The long forms
  (`data-resilient`) submit with `fetch` so a network failure never wipes what was typed: they check the connection
  first (`public/up.txt`), retry on a fresh connection and refresh an expired CSRF token. File inputs marked
  `data-shrink` are downscaled in the browser before upload (a 4 MB phone photo becomes ~400 KB).
* Only link-in-bio pages are indexable (`<x-app-layout :indexable="true">`, needs `SEO_INDEX=true`) and only they are
  listed in `sitemap.xml`; every other page, file download and redirect is `noindex`.
* **Wedding invitations, the hosts' side** (`/invitations/{secret}/manage`; the secret in the address is the only key):
  the dashboard shows people coming and the sent → opened → replied progress, a searchable guest list with filters
  (not sent / waiting / coming / declined) and per guest Telegram, WhatsApp (straight to the chat when a phone number is
  known, see `InvitationGuest::whatsappNumber()`), the phone's share sheet, copy message / link and call. Tapping one
  marks the guest as *sent* (with an undo bar; `POST .../guests/{id}/sent`); a guest opening their personal link marks
  them *opened* (`views`, `opened_at`; link previews, crawlers, HEAD and `?preview` do not count, see `Visitors`).
  Hosts can edit every detail and the cover photo, edit a guest (also to record a reply given by phone), pasting
  "Name, phone" lines adds guests without duplicating anyone (an existing guest just gains the phone/e-mail), export
  the list as CSV (formula-safe, `;` for uz/ru Excel), write their own chat message (`{name}` placeholder) and delete
  everything. The guest page offers an `.ics` calendar file (`/{code}/event.ics`, `/i/{token}/event.ics`) because a
  Google Calendar link does not help the many guests on iPhones.
  **Link previews:** when an invitation link is shared (Telegram, WhatsApp...) the chat app shows a picture drawn by
  `App\Support\PreviewImage` (GD + the TrueType copies of Cormorant Garamond in `resources/fonts`, OFL): names, date,
  time, venue and address beside the cover photo, in the invitation's language, 1200×630 JPEG. It is served at
  `/preview/{code}.jpg`, stored in `storage/app/public/previews` and addressed with `?v=<hash of what is drawn>`, so an
  edit gives chat apps a new address (apps that already saw the page keep their cached card for a while; Telegram's
  `@WebpageBot` refreshes it). That is why every address the hosts share from the manage page carries a tag
  (`?v=` + the picture's hash, `Invitation::shareVersion()`, `InvitationGuest::shareLink()`): after an edit the tag changes,
  Telegram sees a new link and fetches a fresh card; the page ignores the tag, so older links keep working, and its
  `og:url` carries it too. The page's title and description say the same in words. Without GD/FreeType the cover
  photo is used instead.
  Replies a guest sends are flagged "new" on the hosts' next visit (kept in the browser's localStorage only).
  Repeated per-guest icons use an inline SVG sprite (`<x-iu>`, `invitations/_sprite`) to keep a 300-guest page small.
  E-mail only works when `MAIL_*` is configured; the page offers it only when a guest has an address, and gives up
  after three failed tries instead of waiting for every guest.
* **The guest's invitation page** (`invitations/show`, `public/css/invitation.css`): an envelope with a wax seal opens it
  (and starts the music), then one screen per block: greeting, the couple under an arch, a month calendar, a countdown,
  the venue, the hosts' message and the reply card. Line-art florals are `invitations/_flora`, `_sprig`, `_ornament`,
  `_rings`. `?name=Aziz` on the shared link greets that person by name (stripped of tags, max 60 characters). Hosts get
  "Remind via Telegram / WhatsApp" for guests who were sent the link but have not replied.
  Background music: Mendelssohn's *Wedding March*, European Archive recording, public domain (Wikimedia Commons),
  `public/audio/wedding-march.mp3`; replace the file to change it.
* Executables and scripts (`.exe`, `.php`, `.sh`, ...) cannot be uploaded; files are only ever served as downloads.
* Fonts: Inter and Cormorant Garamond (SIL OFL, licenses in `public/fonts` and `resources/fonts`), highlight.js (BSD-3, `public/vendor/hljs`).
