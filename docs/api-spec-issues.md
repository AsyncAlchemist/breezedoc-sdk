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

## Testing Environment

- **API Base URL:** https://breezedoc.com/api
- **OpenAPI Spec Version:** 1.0.0
- **Account Type:** Pro
