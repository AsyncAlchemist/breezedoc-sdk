# Design: Delete a Document Through the Website

**Date:** 2026-11-12
**Status:** Implemented (the live success answer is still to be confirmed end to end)

## Problem

The BreezeDoc REST API has no document delete. `DELETE https://breezedoc.com/api/documents/{id}`
answers **405** ("The DELETE method is not supported for route api/documents/{id}. Supported
methods: GET, HEAD."), and no `/delete` or `/void` route exists. A caller that keeps the signed
PDF in its own storage (see the login-based PDF download,
`2026-07-23-login-pdf-download-design.md`) needs to remove the document from BreezeDoc after
that.

## Facts (2026-11-12)

- The website's documents list (`GET /documents`, logged in) has one Laravel form per document:
  `<form action="https://breezedoc.com/documents/{id}" method="post">` with the hidden inputs
  `_token` (equal to the page's `<meta name="csrf-token" content="...">`) and `_method=DELETE`,
  and a "Delete" submit button. The pages are classic server-rendered HTML (no Livewire,
  Inertia, Alpine or Vue).
- `GET /documents/{id}/activity` answers **200** for a document of the account and **404** for
  a missing id.
- The real success answer of the delete POST is **not known** yet (the Laravel norm is a 302 to
  `/documents`). So the result is decided by a follow-up check, not by the answer to the POST.

## Public API

```php
$config = new Configuration('api-token');
$config->setWebLogin('email@example.com', 'password');   // same web login as downloadPdf()
$client = Breezedoc::client($config);

$client->documents()->deleteDocument(311939);              // void; returns only when gone
```

- `Documents::deleteDocument(int $id): void` delegates to the shared `WebSession` (as
  `downloadPdf()` does) and throws `InvalidArgumentException` when no web login is configured.
- `WebSession::deleteDocument(int $id): void` holds the flow.

## Flow

```
deleteDocument(id):
  ensureSession()                                   # cached session or login (as getPdf)
  page = GET /documents/{id}/activity               # dead session -> re-login once, retry
    404        -> NotFoundException (no POST)
    non-2xx    -> refusal (ApiException with the status)
    200        -> token = meta csrf-token, else a form _token (none -> ApiException)
  loop (bounded):
    resp = POST /documents/{id}  _token, _method=DELETE
           headers: Origin = site, Referer = site/documents, browser UA, cookies
    dead session (302 -> /login, or the login form) -> re-login once, new token, retry
    419 (token expired)                              -> new token once, retry
                                                        (second 419 -> refusal)
  resp 2xx/3xx/404 -> go on; anything else -> refusal (403, 429, 5xx, ...)
  check = GET /documents/{id}/activity              # dead session -> the same one re-login
    404   -> persist the session, return
    2xx   -> BreezedocException("The website still shows document {id} after the delete.")
    other -> ApiException("Could not confirm the delete of document {id} (HTTP n).")
```

- The one re-login is spent per call, wherever the dead session shows (first GET, POST, or the
  check). A second dead session throws `AuthenticationException`.
- Laravel checks the CSRF token before `auth`, so a dead session on the POST can show as a 419.
  The token refresh then meets the dead session on its GET and spends the one re-login there.
- Redirects are not followed (the SDK expects a PSR-18 client that does not follow). A client
  that follows lands on a 200 page after the POST; that also goes to the follow-up check.

## Errors

| Condition | Exception |
|---|---|
| No web login configured (`Documents`) | `InvalidArgumentException` |
| Activity page 404 before the delete | `NotFoundException` |
| Login fails, or the session is dead after the one re-login | `AuthenticationException` |
| 403 on the page or the POST | `AuthorizationException` (an `ApiException`, code 403) |
| 429 | `RateLimitException` (code 429) |
| 419 after the one token refresh, 5xx, other status | `ApiException` with that status |
| Activity page without a CSRF token | `ApiException` (code 200) |
| Activity page still 2xx after the delete | `BreezedocException` |
| Unexpected status on the follow-up check | `ApiException` with that status |

All of them are a `BreezedocException`. Messages carry the document id and the HTTP status only,
never the password, a cookie or a token.

## Testing

Unit tests with the `php-http/mock-client` PSR-18 mock and Nyholm PSR-17 factories (no live
calls): success (302 then 404); the POST form fields and browser headers; meta token before a
form token, and the form-token fallback; a redirect-following client; already gone (404 first,
no POST); a fresh login; a dead cached session, then success; a dead session at the POST, then
success; the one re-login spent; a failed login; 419 then success; 419 twice; 403 on the POST and
on the page; 500 on the POST; a page without a token; still there after the delete; an
unexpected follow-up check; a POST that answers 404; and no secret in any exception message.
`Documents::deleteDocument()` delegates to the `WebSession` and needs a web login.

No integration test runs against the live site; the success answer is to be confirmed in an
end-to-end test by the caller.
