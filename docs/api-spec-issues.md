# Breezedoc API — OpenAPI Spec Issues

Issues discovered during SDK development where the actual API behavior differs from the OpenAPI specification.

## Issue 1: Invoice Currency Must Be Uppercase

**Severity:** Breaking

**Endpoints:** `POST /api/invoices`, `PUT /api/invoices/{id}`, `PATCH /api/invoices/{id}`

### Spec Says

```json
"currency": {
  "type": "string",
  "description": "Three-letter ISO currency code (lowercase)",
  "example": "usd"
}
```

### Actual Behavior

Lowercase currency codes are rejected:

```json
{
  "message": "The selected currency is invalid.",
  "errors": {
    "currency": ["The selected currency is invalid."]
  }
}
```

Only uppercase ISO 4217 codes work (e.g., `"USD"`, not `"usd"`).

---

## Issue 2: Invoice Responses Wrapped in Data Object

**Severity:** Minor (but affects client implementation)

**Endpoints:** All single-resource `/api/invoices*` endpoints

### Spec Says

Invoice endpoints return the Invoice schema directly.

### Actual Behavior

All single-invoice responses are wrapped in a `data` object:

```json
{
  "data": {
    "id": 123,
    "slug": "abc",
    "currency": "USD"
  }
}
```

Affected endpoints:
- `POST /api/invoices` (201)
- `GET /api/invoices/{id}` (200)
- `PUT /api/invoices/{id}` (200)
- `PATCH /api/invoices/{id}` (200)
- `POST /api/invoices/{id}/send` (200)

---

## Issue 3: Invoice Status Values Differ Between Schema and Filter

**Severity:** Minor

The `Invoice` schema lists `status` as `draft, open, paid, uncollectible, void`, but the
`status` filter of `GET /api/invoices` lists `draft, sent, paid, uncollectible, void`. The SDK
adds `Invoice::STATUS_OPEN` and passes the filter value through unchanged. Not live-tested.

---

## Team API v1: Gaps in the Published Spec

The items below come from reading the published documentation on 2026-11-16
([openapi.yaml](https://breezedoc.com/developer/openapi.yaml),
[developers](https://breezedoc.com/developers)). They were **not** live-tested. The SDK
handles each one leniently, as noted.

### Gap 1: No Response Schemas

Every `/v1/*` operation describes its 200/201 response only as "Success; JSON metadata only"
(documents add `review_url`; lists contain `data` and `next_cursor`). No field names are given
for documents, recipients, templates, `/v1/me`, audit events, webhook endpoints or deliveries.

**SDK:** the `Breezedoc\V1\Models` classes read fields leniently (missing → null) and keep the
raw response in `toArray()` / `get()`. Single-resource responses are used as they are, or the
`data` object when the response has no `id` but a `data` object.

### Gap 2: Download Link Field Name

`GET /v1/documents/{id}/download` returns "a 15-minute signed URL", but the field name is not
given.

**SDK:** `DownloadLink::getUrl()` reads `url`, `download_url` or `signed_url`, else the only
top-level `https://` string value; with several candidates it returns null and
`downloadPdf()` throws.

### Gap 3: Webhook Secret Field Name

`POST /v1/webhooks` ("secret returned once") and `POST /v1/webhooks/{id}/rotate` do not name the
secret field. The help article says the secret starts with `whsec_`.

**SDK:** `WebhookEndpoint::getSecret()` reads `secret` or `signing_secret`, else the first
top-level string that starts with `whsec_`.

### Gap 4: Multipart Encoding of Arrays

The `multipart/form-data` body of `POST /v1/documents` has `recipients` and `fields` as arrays
of objects, but the encoding (bracket names, JSON-encoded parts, or something else) is not
stated.

**SDK:** sends bracket names (`recipients[0][email]`, `fields[0][properties][x]`), which PHP
and Laravel parse into arrays. Booleans are sent as `1`/`0`.

### Gap 5: No Mapping Between Original and v1 Document IDs

The original API uses integer document IDs; the Team API v1 uses `doc_` strings. The docs do
not say whether one can be derived from the other, and a team key sees only team documents.

**SDK:** the original `Documents::downloadPdf(int)` and `deleteDocument(int)` keep the website
path. `$team->documents()->downloadPdf(string)` is a separate method for team documents.

### Gap 6: Webhook List and Delivery Pagination

`GET /v1/webhooks` and `GET /v1/webhooks/{id}/deliveries` take no `cursor`/`limit` parameters
and have no response shape. The help article says the UI shows the last 100 deliveries.

**SDK:** reads the `data` list (or a top-level list) and returns an array.

### Gap 7: Webhook Status Codes

The webhook operations list `200 Success` and `202 Queued` together for every webhook path, so
it is not clear which call answers which.

**SDK:** treats any 2xx as success.

---

## Testing Environment

- **API Base URL:** https://breezedoc.com/api
- **OpenAPI Spec Version:** 1.0.0
- **Account Type:** Pro
