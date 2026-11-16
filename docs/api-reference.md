# Breezedoc API Reference

Complete API reference based on live testing and the official OpenAPI specification.

BreezeDoc has two REST APIs. The sections up to "Rate Limiting" describe the **original API**
(personal access token). The **Team API v1** (team API key), **webhooks** and the **MCP server**
are described at the end: see [Team API v1](#team-api-v1).

## Base URL

```
https://breezedoc.com/api
```

## Authentication

- **Method**: Bearer Token (Personal Access Token)
- **Header**: `Authorization: Bearer {TOKEN}`
- **Required Header**: `Accept: application/json`
- **Token Type**: JWT with ~1 year expiration

Generate a token at [https://breezedoc.com/integrations/api](https://breezedoc.com/integrations/api).

---

## Endpoints

### User

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/me` | Get current user info |

### Documents

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/documents` | List documents (paginated) |
| POST | `/documents` | Create new document (metadata only) |
| GET | `/documents/{id}` | Get document details |
| GET | `/documents/{id}/recipients` | List document recipients |
| POST | `/documents/{id}/send` | Send document to recipients |

### Templates

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/templates` | List templates (paginated) |
| GET | `/templates/{id}` | Get template details |
| POST | `/templates/{id}/create-document` | Create document from template |

### Recipients

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/recipients` | List all recipients (paginated) |

### Teams (Agency Plan)

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/teams/{id}/documents` | List team documents |
| GET | `/teams/{id}/templates` | List team templates |

### Invoices

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/invoices` | List invoices (paginated) |
| POST | `/invoices` | Create invoice |
| GET | `/invoices/{id}` | Get invoice details |
| PUT | `/invoices/{id}` | Update invoice |
| PATCH | `/invoices/{id}` | Partially update invoice |
| DELETE | `/invoices/{id}` | Delete draft invoice |
| POST | `/invoices/{id}/send` | Send invoice to customer |

---

## Important Limitations

### No File Upload via API

The original API does **not** support PDF file upload (the Team API v1 does). Documents can only be:
1. Created with title/recipients metadata (empty document shell)
2. Created from existing templates

File upload is only available through the Breezedoc web UI.

### No Document Deletion

Documents cannot be deleted via API (neither API; the Team API v1 can only cancel). Only draft
invoices can be deleted.
`DELETE /api/documents/{id}` answers 405 ("Supported methods: GET, HEAD"). The SDK's
`Documents::deleteDocument()` deletes through the website instead, and checks the result with
`GET /documents/{id}` (see
`docs/superpowers/specs/2026-11-12-web-delete-document-design.md`).

---

## Detailed Specifications

### GET /me

Returns the current authenticated user.

**Response:**
```json
{
  "id": 1,
  "name": "Jane Doe",
  "email": "jane@example.com",
  "created_at": "2024-01-01T00:00:00.000000Z",
  "updated_at": "2024-01-01T00:00:00.000000Z"
}
```

### POST /documents

Create a new document (metadata only, no file upload).

**Request Body:**
```json
{
  "title": "Document Title",
  "recipients": [
    {
      "email": "signer@example.com",
      "name": "Signer Name",
      "party": 1
    }
  ]
}
```

- `title` — required, max 191 characters
- `recipients` — optional array
  - `email` — required
  - `name` — required, max 191 characters
  - `party` — required, signing order (integer)

**Response:** Document object (201 Created)

### GET /documents/{id}

Returns full document details including files, fields, and recipients.

**Response Fields:**

| Field | Type | Description |
|-------|------|-------------|
| id | integer | Document ID |
| title | string | Document title |
| slug | string | URL-safe identifier |
| created_at | datetime | Creation timestamp |
| updated_at | datetime | Last update timestamp |
| completed_at | datetime\|null | Completion timestamp |
| redirect_url | string\|null | Post-signing redirect URL |
| document_files[] | array | Files with page images |
| fields[] | array | Signature/form fields |
| recipients[] | array | Document recipients |

### GET /documents/{id}/recipients

**Query Parameters:**

| Parameter | Type | Values | Description |
|-----------|------|--------|-------------|
| order_by | string | `completed_at`, `id` | Sort field |
| direction | string | `asc`, `desc` | Sort direction |
| completed | string | `true`, `false` | Filter by completion |

**Response:** Paginated list (Format A)

### POST /documents/{id}/send

Send a document to recipients for signing.

**Request Body:**
```json
{
  "recipients": [
    {
      "name": "Signer Name",
      "email": "signer@example.com"
    }
  ]
}
```

Recipients must match the number of recipients on the document, in ascending party order.

### Downloading Page Images

The Breezedoc API does not provide a direct PDF download endpoint. Instead, each document's pages are available as JPEG images via pre-signed S3 URLs returned in the `document_file_pages` array.

The SDK provides two convenience methods for downloading these images:

```php
// Get page images as binary strings
$images = $client->documents()->downloadPageImages($documentId);
// $images[0] = JPEG binary of page 1, etc.

// Save page images to a directory
$paths = $client->documents()->downloadPageImagesTo($documentId, '/path/to/dir', 'contract');
// Saves: /path/to/dir/contract-1.jpg, /path/to/dir/contract-2.jpg, ...
```

> **Note:** The pre-signed S3 URLs expire after 30 minutes. These methods fetch the document and download images in a single call, so expiry is not a concern during normal use.

---

### Field Types

| UUID | Name | Additional Properties |
|------|------|----------------------|
| `13eb6f62-cc8b-466a-9e25-400eb8f596aa` | Signature | required |
| `195ff45a-6a44-40a6-b9c6-d24d69b6aac6` | Initials | required |
| `c96b9268-7266-4304-a0c3-2dc058c87a84` | Date | required |
| `c8ca9a67-4f54-4429-a409-ac58418bd1bc` | Text | label, fontFamily, defaultValue, required |
| `e3b2c44d-5f6a-4e8b-9d1c-2f3e4a5b6c7d` | Email | label, defaultValue, required |
| `6f0fbecf-3bba-4b8a-87b0-b7eb49662661` | Dropdown | label, options[], required |
| `6bcdb12d-7364-4ada-9427-13831827a995` | Checkbox | label, required |

**Field Positioning:**

Fields use percentage-based coordinates (0–1 range):
```json
{
  "id": 12345,
  "document_file_id": 100,
  "page": 1,
  "party": 1,
  "field_type_id": "13eb6f62-cc8b-466a-9e25-400eb8f596aa",
  "name": "Signature",
  "properties": {
    "h": 0.035,
    "w": 0.217,
    "x": 0.149,
    "y": 0.724,
    "required": true
  }
}
```

---

## Invoice API

> **Note:** All single-invoice responses are wrapped in a `data` object: `{"data": {...}}`.
> See [API Spec Issues](api-spec-issues.md) for details.

### Invoice Schema

```json
{
  "id": 123,
  "slug": "abc123",
  "currency": "USD",
  "status": "draft",
  "description": "Invoice description",
  "customer_name": "Client Name",
  "customer_email": "client@example.com",
  "payment_due": "2026-02-01",
  "footer_note": "Optional note",
  "total": 150.00,
  "localized_total": "$150.00",
  "sent_at": null,
  "created_at": "2026-01-30T12:00:00.000000Z",
  "updated_at": "2026-01-30T12:00:00.000000Z",
  "items": [],
  "payment_platforms": [],
  "pay_url": "https://..."
}
```

### Invoice Items

```json
{
  "id": 1,
  "description": "Line item description",
  "details": "Additional details",
  "quantity": 3,
  "unit_price": 10000,
  "total_price": 30000,
  "localized_unit_price": "$100.00",
  "localized_total": "$300.00"
}
```

All monetary values (`unit_price`, `total_price`) are **integers in cents**. `quantity` is a
number (minimum 0.01); `formatted_quantity` is the display form with trailing zeros trimmed.

### Invoice fields added in the current spec

| Field | Type | Notes |
|-------|------|-------|
| `title` | string | Max 50 in requests; default "Invoice" |
| `invoice_number` | string\|null | Max 20, `[a-zA-Z0-9-_]` |
| `invoice_date` | date\|null | Read-only |
| `payment_terms` | string\|null | `due_on_receipt`, `net_15`, `net_30`, `net_60`, `net_90`, `custom` |
| `subtotal`, `localized_subtotal` | number, string | Before the discount |
| `discount_type` | string\|null | `percentage` or `flat` |
| `discount_percentage` | number\|null | 0-100, with `percentage` |
| `discount_amount_cents` | integer\|null | With `flat`; max the subtotal |
| `discount_label` | string\|null | Max 50 |
| `discount`, `localized_discount` | number, string | Applied discount, clamped to the subtotal |
| `category` | object\|null | `slug`, `name` |

`status` now also has `open`. After a payment is recorded, PUT/PATCH ignore `items` and the
`discount_*` fields and still answer 200 with the stored values.

### POST /invoices

```json
{
  "customer_email": "client@example.com",
  "customer_name": "Client Name",
  "currency": "USD",
  "description": "Invoice for services",
  "payment_due": "2026-02-28",
  "footer_note": "Thank you!",
  "items": [
    {
      "description": "Service item",
      "details": "Optional details",
      "quantity": 3,
      "unit_price": 10000
    }
  ],
  "payment_platform_ids": ["uuid1", "uuid2"],
  "send": true
}
```

- `currency` — required, **must be uppercase** ISO 4217 (e.g., `"USD"`, not `"usd"`)
- `items` — required, at least one item
- `send` — optional, sends the invoice immediately if `true`

### PUT/PATCH /invoices/{id}

Same schema as POST, all fields optional. Cannot update invoices with status `paid`, `uncollectible`, or `void`.

### DELETE /invoices/{id}

Delete a draft invoice. Cannot delete invoices that have been sent.

### POST /invoices/{id}/send

Send (or re-send) an invoice. Cannot send invoices with status `paid`, `uncollectible`, or `void`.

### Payment Platforms

```json
{
  "id": "uuid",
  "name": "Stripe"
}
```

Available platforms: Stripe, PayPal, Bank, Check, Cash, Other.

---

## Pagination

The API uses two pagination formats.

### Format A (Documents, Templates, Document Recipients)

```json
{
  "current_page": 1,
  "data": [],
  "first_page_url": "...",
  "from": 1,
  "last_page": 1,
  "last_page_url": "...",
  "links": [],
  "next_page_url": null,
  "path": "...",
  "per_page": 10,
  "prev_page_url": null,
  "to": 3,
  "total": 3
}
```

### Format B (Recipients, Invoices)

```json
{
  "data": [],
  "links": {
    "first": "...",
    "last": "...",
    "prev": null,
    "next": null
  },
  "meta": {
    "current_page": 1,
    "from": 1,
    "last_page": 1,
    "links": [],
    "path": "...",
    "per_page": 10,
    "to": 4,
    "total": 4
  }
}
```

---

## Rate Limiting

- **Limit**: 60 requests per minute per account
- **Creating documents** (`POST /documents`, `POST /templates/{id}/create-document`): 100 per day
- **Sending documents** (`POST /documents/{id}/send`): 20 per hour
- **Invoices**: 100 created per day; 20 sent per hour
- **Headers**: `X-RateLimit-Limit`, `X-RateLimit-Remaining`, `X-RateLimit-Reset`
- **Exceeded**: HTTP 429 with `Retry-After`

---

## Error Responses

| Code | Description |
|------|-------------|
| 400 | Bad Request (e.g., document already sent) |
| 401 | Unauthorized / Unauthenticated |
| 403 | Forbidden (permission denied) |
| 404 | Not Found |
| 405 | Method Not Allowed |
| 422 | Validation Error |
| 429 | Rate Limit Exceeded |

**Error format:**
```json
{
  "message": "Error description"
}
```

---

## Team API v1

Published October 2026 as a "developer preview". Sources (read 2026-11-16):
[breezedoc.com/developers](https://breezedoc.com/developers),
[OpenAPI spec](https://breezedoc.com/developer/openapi.yaml) (also rendered at
[developer/docs](https://breezedoc.com/developer/docs/index.html)),
[help: API access](https://help.breezedoc.com/article/1023-api-access-for-developers),
[help: webhooks](https://help.breezedoc.com/article/1123-how-to-get-breezedoc-webhooks).
SDK entry point: `Breezedoc::teamClient()` / `Client::team()` (`Breezedoc\V1`).

### Base URL and authentication

```
https://breezedoc.com/api/v1
Authorization: Bearer bd_live_...
```

- Team API key, created by the team owner in Settings → Integrations → Manage API Keys →
  Team API keys. Shown once. Up to 10 active keys per team. Revoking takes effect at once.
- Scope: "Read documents and templates" (read), plus "Create, send, remind and cancel
  documents" (write). Write scope is needed for mutations and for all webhook management.
- Plans: Agency or Pro (keys belong to a team).
- HTTPS is required. Browser CORS access is disabled.
- "Team keys work only with `/api/v1`, and personal access tokens only with `/api`."
- Resources outside the key's team answer 404.

### Endpoints

| Method | Endpoint | Scope | Description |
|--------|----------|-------|-------------|
| GET | `/me` | read | Team, owner plan and team usage |
| GET | `/templates` | read | List team templates (`limit`, `cursor`) |
| GET | `/templates/{id}` | read | Template metadata, numbered parties and field definitions |
| GET | `/documents` | read | List team documents (`limit`, `cursor`, `status`, `created_after`) |
| POST | `/documents` | write | Create a draft from `template_id`, `html` (JSON) or `pdf` (multipart) |
| GET | `/documents/{id}` | read | Document and recipient status |
| POST | `/documents/{id}/send` | write | Send a draft (`signing_order`, `message`, `expires_in_days`) |
| POST | `/documents/{id}/remind` | write | Remind unsigned recipients, once per 24 hours |
| POST | `/documents/{id}/cancel` | write | Cancel a sent document; it can no longer be signed |
| GET | `/documents/{id}/download` | read | 15-minute signed URL for the completed PDF and certificate |
| GET | `/documents/{id}/events` | read | Cursor-paginated audit events |
| GET | `/webhooks` | write | List webhook endpoints |
| POST | `/webhooks` | write | Create an endpoint (`url`, `events`); secret returned once |
| DELETE | `/webhooks/{id}` | write | Remove an endpoint |
| POST | `/webhooks/{id}/test` | write | Queue a `webhook.test` event (202) |
| POST | `/webhooks/{id}/rotate` | write | Rotate the signing secret, 24-hour overlap |
| GET | `/webhooks/{id}/deliveries` | write | Delivery logs, kept 30 days |
| POST | `/webhooks/{id}/deliveries/{delivery}/redeliver` | write | Redeliver with the original event ID (202) |

There is no document delete and no recipient decline.

### Conventions

- **IDs** are strings with the prefixes `doc_`, `tpl_`, `rcp_`, `evt_`. Timestamps are ISO 8601 UTC.
- **Responses** are "JSON metadata only" (never document text or field values). Document
  responses include `review_url` (null unless the document is unsent). The spec has no response
  schemas; see [api-spec-issues.md](api-spec-issues.md).
- **Lists** return `data` and `next_cursor`. Pass `cursor` and `limit` (1 to 100, default 25).
- **Document status** filter values: `draft`, `sent`, `completed`, `cancelled`, `expired`.
- **Idempotency**: POST requests accept `Idempotency-Key` (max 255 characters). The first
  response is replayed for 24 hours; a reuse with a different payload answers 409.
- **Creating** never sends email. Supply exactly one recipient per numbered template party.
- **html** drafts: only `h1, h2, h3, p, ul, ol, li, strong, em, u, br`, no attributes, up to
  200,000 characters; BreezeDoc adds a signature page for parties 1 and 2, so `fields` cannot
  be passed.
- **PDF** drafts (multipart): `pdf` (real PDF, max 25 MB, 100 pages), `recipients`, `fields`
  (`party`, `page` from 1, `type` one of signature, initials, text, email, date, checkbox,
  dropdown, and `properties` with `x`, `y`, `w`, `h` as page fractions plus optional `options`).
- **Send** body (all optional): `signing_order` (bool, default true), `message` (needs the
  custom-email plan feature), `expires_in_days` (1 to 365, default 30). "Send a draft through
  existing phishing protection."
- **Download links** expire after 15 minutes, include the signing audit certificate, and must be
  treated as credentials.
- **Cancelled** documents cannot be signed.

### Errors

```json
{"error": {"code": "...", "message": "..."}}
```

| Code | Meaning |
|------|---------|
| 400 | Plain-HTTP request |
| 401 | Invalid credentials |
| 403 | Insufficient scope ("This key needs the write scope") |
| 404 | Missing resource, including another team's IDs |
| 409 | State conflict or idempotency conflict |
| 422 | Invalid input |
| 429 | Rate limit, with retry guidance |

The SDK maps these to the same exception classes as the original API, plus
`ConflictException` for 409; `ApiException::getErrorCode()` returns `error.code`.

### Rate limits

- 60 requests per minute per key.
- Per team, across all keys: 100 documents created per day, 20 sent per hour.
- Teams keep their existing monthly quota exemption; then `remaining_documents` is null.

---

## Webhooks

Team documents only. Managed on the Webhooks page beside the team's API keys, or with the
`/v1/webhooks` endpoints above. Up to 10 endpoints per team; each must be a public HTTPS URL on
port 443. To change the URL or the events, remove the endpoint and add it again.

### Events

| Event | Meaning |
|-------|---------|
| `document.sent` | The document has been sent |
| `recipient.viewed` | A recipient opened the document |
| `recipient.signed` | A recipient finished signing |
| `document.completed` | All recipients signed and the completed PDF is stored |
| `document.cancelled` | An integration (API or AI assistant) cancelled the document |
| `document.expired` | An incomplete document expired |
| `webhook.test` | A test delivery that the owner requested |

`POST /v1/webhooks` accepts the first six in `events`.

### Payload

```json
{"id":"evt_…","type":"recipient.signed","created_at":"2026-10-23T12:00:00Z","data":{"document_id":"doc_456","status":"sent","recipient":{"id":"rcp_789","email":"…"}}}
```

Metadata only. Deliveries may repeat or arrive out of order: deduplicate by event `id` and fetch
the current document status when needed.

### Signature

Header `BreezeDoc-Signature`: comma-separated parts, exactly one `t=<1-12 digit unix seconds>`
and one or more `v1=<64 lowercase hex>`.

```
v1 = hex(HMAC-SHA256(key = signing secret "whsec_...", message = "<t>" + "." + raw body))
```

- Verify the exact request bytes before parsing JSON.
- Reject `|now - t| > 300` seconds.
- Accept the request when any `v1` value matches (constant-time comparison).
- After a rotation, deliveries carry signatures from both secrets for 24 hours.

SDK: `Breezedoc\Webhooks\WebhookVerifier`.

### Delivery and retries

- Answer 2xx after the event is stored or queued.
- Retries after 1 min, 5 min, 30 min, 2 h, 6 h, 12 h and 24 h. Redirects are not followed.
- After three days of continuous failure the endpoint is disabled and the owner is emailed.
- Delivery logs keep the status, the latency and the first 1 KB of the response for 30 days.
- Redeliver keeps the original event ID.

---

## MCP server (AI assistants)

- URL: `https://breezedoc.com/mcp` (Streamable HTTP, JSON-RPC).
- OAuth only: authorization code with PKCE S256 and exact redirect URIs; dynamic client
  registration at `/mcp/oauth/register`; metadata at `/.well-known/oauth-authorization-server`
  and `/.well-known/oauth-protected-resource/mcp`. Access tokens last 1 hour; rotating refresh
  tokens last 30 days. Revoke at `https://breezedoc.com/settings/connected-apps`.
- A team owner signs in, chooses the team and approves read or write access (Agency or Pro).
- Tools: `get_account`, `list_templates`, `get_template`, `create_document`, `list_documents`,
  `get_document_status`, `send_document`, `send_reminder`, `cancel_document`,
  `get_download_link`. Sending, reminding and cancelling require confirmation.

The SDK does not wrap the MCP server; it targets AI clients, not server code.
