# Design: Login-based PDF Download

**Date:** 2026-07-23
**Status:** Approved

## Problem

The Breezedoc REST API does not expose the signed/completed PDF of a document — the
only file access is per-page JPEG images via pre-signed S3 URLs (see
`docs/verifying-pdf-download-support.md`). Users who want the actual signed PDF
(with signatures burned in and the audit certificate) have no API-native way to get it.

The Breezedoc **website**, however, serves the signed PDF directly at
`GET https://breezedoc.com/documents/{id}/download` to an authenticated browser session.

## Goal

Add an SDK feature that logs into the Breezedoc website with the user's web
credentials and downloads the signed/completed PDF for a document, reusing a cached
session across process runs so we don't re-login every time.

## Validated facts (live probe, 2026-07-23)

- **Login is a standard Laravel form login**, no headless browser required:
  1. `GET /login` returns HTML containing a hidden `<input name="_token">` (40-char CSRF
     token) and sets cookies `XSRF-TOKEN` + `breezedoc_session` (+ Cloudflare `__cf_bm`).
  2. `POST /login` with form fields `email`, `password`, `_token` (carrying the cookies +
     a real browser `User-Agent`) returns **302 → `/documents`** on success.
- **Cloudflare** fronts the site and **blocks non-browser User-Agents** (default
  curl/PHP UAs get 403). A browser-like `User-Agent` header is mandatory on every request.
- **Download:** `GET /documents/{id}/download` with the session cookie returns the PDF
  **directly** — `200 application/pdf`, no S3 redirect. The signed PDF includes an extra
  certificate page beyond the document's own pages.
- **Auth-failure signal:** an expired/absent session on any authenticated route returns
  **302 with `Location: …/login`** (not the resource).
- **Authorization vs. not-found:** a valid session requesting a document owned by another
  account returns **403**; a missing document returns **404**.
- **Cookie lifetime:** `breezedoc_session` + `XSRF-TOKEN` carry a 24h Max-Age, but the
  authoritative lifetime is server-side (Laravel, rolling) — the cookie clock is only a hint.

## Non-goals

- Downloading the *original* (pre-signature) upload — out of scope; we fetch the signed PDF
  (what the website "Download" button yields).
- A headless-browser fallback — explicitly rejected; pure HTTP only.
- Working around a hard Cloudflare challenge if Breezedoc later adds one — that would be a
  future change; this design targets the current (non-challenging) login.

## Approach

Pure HTTP via **Guzzle**, used directly for its native cookie jar and per-request redirect
control — capabilities the SDK's PSR-18 abstraction does not model. The web flow is isolated
in a `Breezedoc\Web` namespace so the core API client stays PSR-18 / token-only.

**Packaging impact:** Guzzle is currently a `require-dev` dependency (the core client is
PSR-18-agnostic via `php-http/discovery`). Because `WebSession` references
`GuzzleHttp\Client` directly in `src/`, **`guzzlehttp/guzzle` moves from `require-dev` to
`require`** (pinned `7.15.1`). This adds a hard runtime dependency on Guzzle for all SDK
consumers. Accepted because: (a) the chosen approach is explicitly Guzzle-based, (b) Guzzle
is the de-facto PSR-18 client discovery already selects, and (c) the alternative
(hand-rolled cookie/redirect handling over PSR-18) is more code and fragile across PSR-18
client implementations with differing redirect semantics. The core API path is unchanged
and still routes through PSR-18. `WebSession` accepts an injected `GuzzleHttp\ClientInterface`
so tests supply a `MockHandler`-backed client.

## Public API

```php
$config = new Configuration('api-token');
$config->setWebLogin('email@example.com', 'password');          // website creds (not the PAT)

// optional overrides:
$config->setSessionStore(new FileSessionStore('/custom/path.json')); // default: ~/.breezedoc/session.json
$config->setWebSessionTtl(3600);                                     // seconds; default 3600

$client = Breezedoc::client($config);

$bytes = $client->documents()->downloadPdf(311939);                 // raw PDF bytes (string)
$path  = $client->documents()->downloadPdfTo(311939, '/tmp');       // saves, returns path
```

- `downloadPdf(int $id): string` — returns raw PDF bytes.
- `downloadPdfTo(int $id, string $directory, ?string $filename = null): string` — saves to
  `$directory/$filename` (default filename `document-{id}.pdf`), returns the saved path.
  Mirrors the existing `downloadPageImages()` / `downloadPageImagesTo()` pair.

## Components

New namespace `Breezedoc\Web` (directory `src/Web/`):

- **`WebSession`** — owns a Guzzle client bound to the web origin (`https://breezedoc.com`)
  with a cookie jar, browser `User-Agent`, and `allow_redirects => false`. Responsibilities:
  - `getPdf(int $id): string` — ensure authenticated, GET the download route, branch on the
    response, run the single reactive re-login+retry, return bytes.
  - `login(): void` — GET `/login`, scrape `_token`, POST credentials, verify 302 success,
    capture cookies, persist session state.
  - `validate(): bool` — cheap authenticated probe (`GET /documents`, 200 vs 302→login).
  - `logout(): void` — clear the store + in-memory jar.
  - Encapsulates CSRF scraping, dead-session detection, and the single-flight retry guard.
- **`SessionStore`** (interface) — `load(): ?SessionState`, `save(SessionState): void`,
  `clear(): void`.
  - **`FileSessionStore`** — JSON file at a configurable path (default `~/.breezedoc/session.json`),
    written with `0600` permissions; parent dir created `0700` if missing.
  - **`ArraySessionStore`** — in-memory, for tests and stateless use.
- **`SessionState`** — value object: serialized cookies (name/value/expiry), the owning
  account **email**, and the login timestamp. Serializes to/from a plain array (JSON).
- **`WebClientFactory`** — builds the configured Guzzle client (injectable so unit tests
  pass a `MockHandler`-backed client).

Modified:

- **`Configuration`** — add web email/password, session store (lazy default `FileSessionStore`),
  TTL (default 3600), and web base URL (default `https://breezedoc.com`).
- **`Client`** — lazily construct a single shared `WebSession` from `Configuration`, exposed
  to `Documents`.
- **`Documents`** — add `downloadPdf()` / `downloadPdfTo()` delegating to the `WebSession`.

## Session validity strategy (layered)

1. **Reactive re-login (authoritative, always on).** Use the cached session optimistically.
   Detect the dead-session signal on the actual request: a **302 whose `Location` contains
   `/login`**, or a `200` whose `Content-Type` is not `application/pdf`. On detection:
   `login()` once → persist → **retry the original request once**. A second failure throws
   (`AuthenticationException`) — a single-flight boolean prevents loops. **403 and 404 are
   NOT treated as expiry** (re-login would not help) and map to their exceptions directly.
2. **Proactive TTL skip (optimization).** Store the login timestamp; if the cached session is
   older than the configured TTL, `login()` *before* the request instead of wasting a
   guaranteed redirect-to-login round-trip. Within TTL, use the session and rely on (1).
   TTL is never the sole gate.
3. **Optional `validate()`** — off the hot path, for callers that want to warm up / health-check.

The cached session is **bound to the account email**: if `SessionState.email` differs from
the configured web email, it is discarded and a fresh login performed. The **password is
never persisted** — only cookies — so the store holds a session credential, not the password.

## Download data flow

```
downloadPdf(id):
  session = ensureSession()                 # load store; use if email matches AND within TTL; else login()+save
  resp = GET /documents/{id}/download        # jar, browser UA, allow_redirects=false
  switch:
    200 + application/pdf   -> return body
    302 Location ~ /login   -> login(); save; retry GET once
                                 retry 200 pdf -> return body
                                 retry dead    -> throw AuthenticationException
    200 non-pdf             -> treat as dead session (same as 302 branch)
    403                     -> throw AuthorizationException   # not your document
    404                     -> throw NotFoundException
    429                     -> throw RateLimitException
    other                  -> throw ApiException
  persist rolled cookies back to store
```

## Error handling

| Condition | Exception |
|---|---|
| Web credentials not configured | `InvalidArgumentException` (clear message) |
| Login POST did not 302 / bad credentials | `AuthenticationException` |
| Login response shows a Cloudflare/JS challenge (no `_token`, challenge markers) | `AuthenticationException` with a hint that automated login is being blocked |
| Download 403 (document not owned) | `AuthorizationException` |
| Download 404 | `NotFoundException` |
| Download 429 | `RateLimitException` |
| Filesystem errors (`downloadPdfTo`, store writes) | `RuntimeException` |

Reuses existing `Breezedoc\Exceptions\*`. No new exception types.

## Testing

**Unit** (Guzzle `MockHandler` + history middleware for request assertions, no live
credentials; inject the mocked `GuzzleHttp\Client` into `WebSession` and use an
`ArraySessionStore`):

- login success: GET form (HTML with `_token`) → POST 302 → cookies captured, state saved.
- login failure: POST returns 200 login page / 302 back to `/login` → `AuthenticationException`.
- login blocked: GET `/login` has no `_token` / challenge markers → `AuthenticationException`.
- `downloadPdf` happy path: 200 `application/pdf` → returns bytes.
- reactive re-login: first GET → 302 `/login`, then login, then GET → 200 pdf → returns bytes;
  asserts exactly one re-login occurred.
- `downloadPdf` 403 → `AuthorizationException` with **no** re-login attempt; 404 → `NotFoundException`.
- TTL: within TTL reuses cached session (no login request); past TTL triggers a proactive login.
- email mismatch: cached session for a different email is discarded and a fresh login performed.
- `FileSessionStore`: round-trips `SessionState`; created file is `0600`.
- `downloadPdfTo`: saves bytes to `document-{id}.pdf`; bad directory → `RuntimeException`.

**Integration** (guarded by `BREEZEDOC_WEB_EMAIL` / `BREEZEDOC_WEB_PASSWORD`; skips if unset,
matching the existing `IntegrationTestCase` pattern): real login + `downloadPdf()` on a
completed document → assert the body starts with `%PDF` and is non-empty. Uses a document id
discovered via the API (first completed document) or a configured id.

## Security & docs

- The session file is a live credential (≈ a bearer token for the account): `0600`, documented
  as sensitive, and covered by `.gitignore` guidance. The password is never written to disk.
- README section: usage, credential handling, session-cache location/sensitivity, and an
  explicit **caveat** that this automates the website login — it may be against Breezedoc's
  ToS and is inherently brittle (any login/Cloudflare change can break it).
- `.env.example` documents `BREEZEDOC_WEB_EMAIL` / `BREEZEDOC_WEB_PASSWORD` (already added).
- CHANGELOG entry under a new version.

## Compatibility

- PHP 7.4+ (matches the SDK floor; no PHP 8-only syntax).
- Guzzle 7.15.1 — **promoted from `require-dev` to `require`**. `WebSession` uses
  `GuzzleHttp\Client`, `GuzzleHttp\Cookie\CookieJar`, and PSR-7 messages.
- No change to the existing PSR-18 API client path.
```
