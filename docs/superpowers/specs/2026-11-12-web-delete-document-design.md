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
  `/documents`). So the result is decided by follow-up checks, not by the answer to the POST.
- A website 404 alone does not prove a delete: a renamed route, a web login on another account
  than the API token's, or a retry after a delete that already went through also give one. The
  REST API is authoritative for the API token's account, so it decides the result.

## Public API

```php
$config = new Configuration('api-token');
$config->setWebLogin('email@example.com', 'password');   // same web login as downloadPdf()
$client = Breezedoc::client($config);

$client->documents()->deleteDocument(311939);              // void; returns only when gone
```

- `Documents::deleteDocument(int $id): void` is the entry point for callers. It throws
  `InvalidArgumentException` when no web login is configured (before any HTTP call).
- `WebSession::deleteDocument(int $id): void` is the low-level website step. Callers do not use
  it to delete a document: its `NotFoundException` only means "the website answered 404".

## Flow

```
Documents::deleteDocument(id):
  find(id)                                  # REST; 404 -> NotFoundException, no web call
  try web.deleteDocument(id)                # see below
  catch NotFoundException: (signal only)    # other web exceptions propagate
  find(id)                                  # REST
    404           -> return (deleted)
    document      -> DeleteNotConfirmedException("Document {id} still exists after the website delete.")
    other error   -> propagates

WebSession::deleteDocument(id):
  ensureSession()                                   # cached session or login (as getPdf)
  page = GET /documents/{id}/activity               # dead session -> re-login once, retry
    404        -> NotFoundException (no POST)
    non-2xx    -> refusal naming the step ("... activity page of document {id} before the delete.")
    200        -> token = meta csrf-token, else a form _token (none -> ApiException)
  loop (bounded):
    resp = POST /documents/{id}  _token, _method=DELETE
           headers: Origin = site, Referer = site/documents, browser UA, cookies
    dead session (302 -> /login, or the login form) -> re-login once, new token, retry
    419 (token expired)                              -> new token once, retry
                                                        (second 419 -> refusal)
  resp 2xx/3xx/404 -> go on; anything else -> refusal ("refused the delete of document {id}")
  check = GET /documents/{id}/activity              # dead session -> the same one re-login
    404   -> persist the session, return
    2xx   -> DeleteNotConfirmedException("The website still shows document {id} after the delete.")
    other -> refusal ("... activity page of document {id} after the delete.")
```

- The one re-login is spent per call, wherever the dead session shows (first GET, POST, token
  refresh, or the check). A second dead session throws `AuthenticationException`.
- Laravel checks the CSRF token before `auth`, so a dead session on the POST can show as a 419.
  The token refresh then meets the dead session on its GET and spends the one re-login there.
- Redirects are not followed (the SDK expects a PSR-18 client that does not follow). A client
  that follows lands on a 200 page after the POST; that also goes to the follow-up check, and a
  landed-on login form counts as a dead session at every step.
- A refusal maps the same way at every step: 403 -> `AuthorizationException`, 429 ->
  `RateLimitException`, any other status -> `ApiException` with that status.

## Errors of `Documents::deleteDocument()`

| Condition | Exception |
|---|---|
| No web login configured | `InvalidArgumentException` |
| REST 404 before the delete | `NotFoundException` (the only one; no web call) |
| Website 404 on the activity page | none: a signal only, the REST check decides |
| REST still returns the document after the delete | `DeleteNotConfirmedException` |
| Website still shows the document after the delete | `DeleteNotConfirmedException` |
| Web login fails, or the session is dead after the one re-login | `AuthenticationException` |
| 403 at any website step | `AuthorizationException` (an `ApiException`, code 403) |
| 429 at any website step | `RateLimitException` (code 429) |
| 419 after the one token refresh, 5xx, other status | `ApiException` with that status |
| Activity page without a CSRF token | `ApiException` (code 200) |
| Other REST errors (before or after) | as the REST client throws them |

All SDK exceptions here are a `BreezedocException`. Messages carry the document id, the failed
step and the HTTP status only, never the password, a cookie or a token.

## Testing

Unit tests with the `php-http/mock-client` PSR-18 mock and Nyholm PSR-17 factories (no live
calls).

`WebSession::deleteDocument()`: success (302 then 404); the POST form fields and browser
headers; meta token before a form token, and the form-token fallback; a redirect-following
client; a 404 page (no POST); a fresh login; a dead cached session, then success; a dead session
at the POST, then success; a dead session that shows as a 419 on the POST; a dead session at the
follow-up check (redirect, and a followed login page); the one re-login spent (first GET, and at
the follow-up check); a redirect-following client with a dead session (first GET, and POST); a
failed login; 419 then success; 419 twice; 403 on the POST and on the page (the page message names
its step); 500 on the POST (the message names the POST); a page without a token; still there after
the delete (`DeleteNotConfirmedException`); 403, 429 and 500 at the follow-up check; a POST that
answers 404; the session saved after success (a cookie rolled by the check reaches the store); and
no secret in any exception message.

`Documents::deleteDocument()`: no web login (no HTTP call); REST 404 first (no web call); web
success then REST 404 (success); web 404 then REST 404 (success); web 404 with the document still
in REST; web success with the document still in REST (`DeleteNotConfirmedException`); a REST
error after the delete; a web refusal (no second REST call).

No integration test runs against the live site. The end-to-end test by the caller still has to
confirm the website's success answer and that the REST API answers 404 for a deleted document.
