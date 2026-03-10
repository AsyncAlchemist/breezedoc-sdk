# Breezedoc API Reference

Complete API reference based on live testing and the official OpenAPI specification.

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

The API does **not** support PDF file upload. Documents can only be:
1. Created with title/recipients metadata (empty document shell)
2. Created from existing templates

File upload is only available through the Breezedoc web UI.

### No Document Deletion

Documents cannot be deleted via API. Only draft invoices can be deleted.

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

All monetary values (`unit_price`, `total_price`) are **integers in cents**.

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
- **Headers**: `X-RateLimit-Limit`, `X-RateLimit-Remaining`, `X-RateLimit-Reset`
- **Exceeded**: HTTP 429

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
