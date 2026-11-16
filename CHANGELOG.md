# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.6.0] - 2026-11-16

BreezeDoc added a second REST API in October 2026, the Team API v1 (developer preview), with
team API keys, webhooks and an MCP server ([developer docs](https://breezedoc.com/developers),
[OpenAPI spec](https://breezedoc.com/developer/openapi.yaml)). This release adds the Team API v1
and webhook verification. All changes are backward compatible: existing methods keep their
signatures and behavior, apart from the 409 exception class below (a subclass of the old one).

**Preview status.** The Team API v1 is a BreezeDoc developer preview, and BreezeDoc does not yet
publish the field names of its JSON responses. Some v1 model getters read the most likely field
names; every v1 model keeps the raw response in `toArray()` and `get()`. This release was not
run against the live API. Please report a getter that returns null for data you can see in
`toArray()`. The webhook verifier follows BreezeDoc's published signature scheme and reference
code exactly.

### Fixed

- The README no longer claims automatic retry on HTTP 429: the client throws
  `RateLimitException` (with `getRetryAfter()`), and `setMaxRetries()` is not used yet.

### Added

- **Team API v1 client** (`Breezedoc\V1`), for `https://breezedoc.com/api/v1` with a team API
  key (`bd_live_...`). BreezeDoc documents that team keys work only with `/api/v1` and personal
  access tokens only with `/api`.
  - `Breezedoc::teamClient($key)` and `Client::team()` return a `TeamClient` with `me()`,
    `templates()`, `documents()` and `webhooks()`.
  - `Configuration::setTeamApiKey()`, `getTeamApiKey()`, `hasTeamApiKey()`,
    `forTeamApiKey()`, `getTeamBaseUrl()`, `setTeamBaseUrl()`. `RequestBuilder::forTeamApi()`,
    `isTeamApi()` and `createStream()`.
  - Documents: `list()` / `all()` (cursor pagination: `limit`, `cursor`, `status`,
    `created_after`), `find()`, `create()`, `createFromTemplate()`, `createFromHtml()`,
    `createFromPdf()` (multipart upload), `send()`, `remind()`, `cancel()`, `events()` /
    `allEvents()`, `getDownloadLink()`, `downloadPdf()` and `downloadPdfTo()`.
  - **Signed-PDF download through the API** for team documents: `downloadPdf()` gets the
    15-minute signed URL and fetches it without the API key. Exception messages never contain
    the URL.
  - Templates: `list()` / `all()` and `find()`.
  - Webhook endpoint management: `list()`, `create()` (returns the `whsec_` secret once),
    `destroy()`, `test()`, `rotateSecret()`, `deliveries()` and `redeliver()`.
  - Every POST method takes an optional `Idempotency-Key`.
  - Models `TeamDocument`, `TeamRecipient`, `TeamTemplate`, `TeamAccount`, `DocumentEvent`,
    `DownloadLink`, `WebhookEndpoint`, `WebhookDelivery`, the `DocumentStatus` constants and
    `CursorPage`. The published spec has no response schemas for the Team API v1, so the models
    read their fields leniently and keep the raw response (`toArray()`, `get()`). See
    `docs/api-spec-issues.md`.
- **Webhook verification**: `Breezedoc\Webhooks\WebhookVerifier` checks the
  `BreezeDoc-Signature` header (HMAC-SHA256 of `"<t>." + raw body`, five-minute tolerance, any
  matching `v1` signature, `hash_equals()`), and `constructEvent()` /
  `constructEventFromRequest()` return a typed `WebhookEvent`. Also `WebhookEventType`,
  `WebhookRecipient` and `WebhookVerifier::generateSignatureHeader()` for tests.
- `ConflictException` (HTTP 409) and `WebhookVerificationException`.
- `ApiException::getErrorCode()` returns the `error.code` of a Team API v1 error. The typed
  exceptions now keep the decoded response body (new optional trailing constructor argument).
- Invoice fields from the current spec: `Invoice::getTitle()`, `getInvoiceNumber()`,
  `getInvoiceDate()`, `getPaymentTerms()`, `getSubtotal()`, `getLocalizedSubtotal()`,
  `getDiscountType()`, `getDiscountPercentage()`, `getDiscountAmountCents()`,
  `getDiscountLabel()`, `getDiscount()`, `getLocalizedDiscount()`, `getCategory()`,
  `isOpen()` and `STATUS_OPEN`; `InvoiceItem::getQuantityDecimal()` and
  `getFormattedQuantity()` (quantities can now be fractional).
- A read-only `TeamApiIntegrationTest`, skipped unless `BREEZEDOC_TEAM_API_KEY` is set.

### Changed

- HTTP 409 responses now throw `ConflictException` (a subclass of `ApiException`) instead of a
  plain `ApiException`.
- Error messages are also read from the Team API v1 envelope `{"error": {"message"}}`.
- `openapi.json` is now a JSON copy of BreezeDoc's published spec of 2026-11-16 (the original
  API, the Team API v1, webhooks and the MCP OAuth endpoints).

### Notes

- Neither API has a document delete; the Team API v1 can only cancel a sent document.
  `Documents::deleteDocument()` (website) stays the only delete.
- `Documents::downloadPdf(int)` (website) stays for documents of a personal account. The Team
  API v1 sees only team documents, and BreezeDoc does not document how an integer document ID
  maps to a `doc_` ID.
- BreezeDoc's MCP server (`https://breezedoc.com/mcp`) uses OAuth for AI clients; the SDK only
  points to it in the README.

## [0.5.0] - 2026-11-12

### Added

- `Documents::deleteDocument(int $id): void` deletes a document. The BreezeDoc REST API has no document delete (`DELETE /api/documents/{id}` answers 405), so the delete goes through the BreezeDoc **website** with the web login from `Configuration::setWebLogin()`, as `downloadPdf()` does. The REST API decides the result: `find($id)` before the delete, the website delete, then `find($id)` again.
  - `NotFoundException` only when the REST API does not find the document before the delete. No website call is made then.
  - Returns normally when the REST API answers 404 after the website delete. A website 404 is only a signal and is not rethrown.
  - `DeleteNotConfirmedException` when the document still exists after the delete (the REST API still returns it, or the website still shows it).
  - `AuthenticationException` when the web login fails. Other website refusals (403, 419 after one token refresh, 5xx, an unexpected page) throw an `ApiException` that carries the HTTP status (`AuthorizationException` for 403 and `RateLimitException` for 429, at every step). Other REST errors propagate.
- `WebSession::deleteDocument(int $id): void` is the low-level website step. It reads the CSRF token from `/documents/{id}/activity`, submits the form of the site's "Delete" button (`POST /documents/{id}` with `_method=DELETE`), and returns normally only when a follow-up GET of the activity page answers 404. Its `NotFoundException` means only that the website answered 404 for the activity page when it read the token. A dead session is re-logged-in once per call, and an expired CSRF token (HTTP 419) is refreshed once. Exception messages carry the document id, the failed step and the HTTP status only.
- `Breezedoc\Exceptions\DeleteNotConfirmedException` (extends `BreezedocException`).

### Changed

- Bumped the **dev** dependencies `guzzlehttp/guzzle` to `7.15.6` and `squizlabs/php_codesniffer` to `4.0.4`. New security advisories cover the previous `7.15.1` and `4.0.1` pins, and Composer's advisory policy then refused `composer install`. Both stay `require-dev` only; the runtime requirements do not change.

## [0.4.0] - 2026-07-24

### Added

- `Documents::downloadPdf(int $id)` and `Documents::downloadPdfTo(int $id, string $directory, ?string $filename)` download the signed/completed PDF of a document. The BreezeDoc REST API does not expose PDFs, so this authenticates against the BreezeDoc **website** with your web login and fetches the file the site's "Download" button serves.
- `Configuration::setWebLogin(string $email, string $password)` configures the website credentials for the PDF download feature (distinct from the API token). Also `setSessionStore()`, `setWebSessionTtl()`, and `setWebBaseUrl()`.
- `Breezedoc\Web\SessionStore` interface with `FileSessionStore` (default `~/.breezedoc/session.json`, written `0600`) and `ArraySessionStore`. The login session is cached long-term and reused across runs; expired sessions are detected and refreshed automatically (a stored session is only re-used if it belongs to the configured account and is within the TTL). The password is never persisted.

The PDF download feature runs over the SDK's existing PSR-18 HTTP stack — **no new runtime dependency** is added. Cookies and the login/redirect handling are done in `WebSession`, so it works with any PSR-18 client whose `sendRequest()` does not follow redirects (the norm, e.g. Guzzle's PSR-18 adapter).

### Changed

- Bumped the `guzzlehttp/guzzle` **dev** dependency to `7.15.1`, clearing the security advisories that affected the previous `7.10.4` pin (which broke `composer install` under Composer's advisory policy). Guzzle remains a `require-dev` dependency only.

### Notes

- Login-based PDF download automates the BreezeDoc website login. It may be against BreezeDoc's Terms of Service and is inherently brittle — a change to their login page or bot protection can break it. Use with that understanding.

## [0.3.1] - 2026-06-03

### Changed

- User-Agent header now clearly identifies this SDK as an unofficial, community-maintained package: `asyncalchemist/breezedoc-sdk/{version} (+https://github.com/AsyncAlchemist/breezedoc-sdk)`. The previous `breezedoc-php-sdk/{version}` form could be mistaken for an official BreezeDoc-published SDK.

## [0.3.0] - 2026-06-03

### Added

- `Breezedoc::getVersion()` returns the installed SDK version, sourced from Composer's runtime version data (no hand-maintained constant to keep in sync)
- API requests now send a `User-Agent` header identifying the SDK and version so the BreezeDoc API can identify which clients are in use

### Changed

- **Breaking:** `RecipientField::getDate()` and `SubmittedField::getDate()` now return `?DateTimeImmutable` instead of `?string`, parsing the API's `m-d-Y` wire format. Previously callers had to know the format and were prone to the `strtotime()` footgun where dashed dates parse as `d-m-Y` and silently produce the wrong date (#12). `getValue()` continues to return the raw date string for callers that want the normalized stringly-typed accessor.
- Bumped `guzzlehttp/guzzle` constraint to `7.10.4` (#10) and `phpstan/phpstan` dev constraint to `2.2.1` (#11)

## [0.2.1] - 2026-04-13

### Fixed

- `RateLimitHandler::wait()` now sleeps for the correct duration — `usleep($seconds * 1000)` was sleeping for milliseconds instead of seconds, causing rate-limited retries to fire almost instantly and exhaust all attempts

## [0.2.0] - 2026-04-01

### Added

- `Documents::downloadPageImages(int $id)` downloads all page images as JPEG binary strings
- `Documents::downloadPageImagesTo(int $id, string $directory, string $basename)` downloads page images and saves them to a directory
- `AbstractApi::fetchExternalUrl()` for fetching resources from external URLs (e.g. pre-signed S3 URLs)
- `RequestBuilder::buildExternalRequest()` for building PSR-7 requests without API auth headers
- `SubmittedField` model for convenient access to submitted field data
- `Document::getSubmittedFields()` returns all submitted fields across all recipients
- `Document::getSubmittedField(string $name)` looks up a submitted field by name
- `Document::getSubmittedFieldsFor(Recipient $recipient)` filters submitted fields by recipient
- `Field::getRecipientField()` and `Field::isSubmitted()` to access the embedded recipient submission
- `Recipient::getSentAt()`, `Recipient::isSent()`, `Recipient::getOpenedAt()`, `Recipient::isOpened()` for tracking delivery status
- Type-specific accessors on `RecipientField`: `getText()`, `getDate()`, `isChecked()`, `getImage()`, `getFontFamily()`, `isCommitted()`
- `RecipientField::getValue()` returns a normalized string for any field type

### Changed

- **Breaking:** `RecipientField` model reworked to match actual API structure — submitted data is now in `properties` (keyed by type: `text`, `date`, `checked`, `image`) instead of flat `value`/`image_url` fields
- **Breaking:** `Recipient::getFields()` removed — submitted data is accessed via `Field::getRecipientField()` or `Document::getSubmittedFields()`
- **Breaking:** `Recipient::getSlug()` now returns `?string` (nullable) as slug is not present in all API responses
- **Breaking:** `RecipientField::getId()` replaced by `RecipientField::getFieldId()`

## [0.1.1] - 2026-03-21

### Fixed

- `FieldProperties::fromArray()` now handles non-string values (e.g. `false`) returned by the API for nullable string properties (`defaultValue`, `label`, `fontFamily`) without throwing a `TypeError` under `strict_types=1` (#1)

## [0.1.0] - 2025-01-10

### Added

- Initial release
