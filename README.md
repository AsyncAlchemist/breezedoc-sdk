# Breezedoc PHP SDK

An unofficial PHP SDK for the [Breezedoc](https://breezedoc.com) e-signature API. [Official Breezedoc API Documentation](https://breezedoc.com/developer/docs/)

## Requirements

- PHP 7.4 or higher
- A Breezedoc account with API access

## Installation

Install via Composer:

```bash
composer require asyncalchemist/breezedoc-sdk
```

## Quick Start

```php
<?php

use Breezedoc\Breezedoc;

// Create a client with your API token
$client = Breezedoc::client('your-api-token');

// Get the current user
$user = $client->users()->me();
echo $user->getName();

// List documents
$documents = $client->documents()->list();
foreach ($documents as $document) {
    echo $document->getTitle() . "\n";
}
```

## Authentication

Generate a Personal Access Token at [https://breezedoc.com/integrations/api](https://breezedoc.com/integrations/api).
It works with the original API at `https://breezedoc.com/api`. For the newer Team API v1, use a
team API key: see [Team API v1](#team-api-v1-team-api-keys).

```php
use Breezedoc\Breezedoc;
use Breezedoc\Config\Configuration;

// Simple: just pass the token
$client = Breezedoc::client('your-api-token');

// Advanced: use Configuration for custom settings
$config = new Configuration('your-api-token');
$config->setTimeout(60)       // Request timeout in seconds
       ->setMaxRetries(5);    // Reserved: the client does not retry a 429 yet

$client = Breezedoc::client($config);
```

## Team API v1 (team API keys)

In October 2026 BreezeDoc added a second REST API, the **Team API v1**, as a developer
preview ([developer docs](https://breezedoc.com/developers),
[help article](https://help.breezedoc.com/article/1023-api-access-for-developers)). It uses a
**team API key** (`bd_live_...`) and works with the documents and templates of one team. It
adds PDF upload, reminders, cancelling, a signed-PDF download link, audit events, idempotency
keys and webhooks.

> **Preview.** BreezeDoc does not publish the field names of the v1 JSON responses yet. Some
> getters read the most likely names; `toArray()` and `get()` always give the raw response. This
> part of the SDK has not been run against the live API yet.

| | Original API | Team API v1 |
|---|---|---|
| Base URL | `https://breezedoc.com/api` | `https://breezedoc.com/api/v1` |
| Credential | Personal access token (or OAuth token) | Team API key `bd_live_...` with read, or read and write, scope |
| Plans | Every plan | Agency or Pro (a team is needed; only the team owner creates keys) |
| Documents | Your own account | The key's team only (other IDs answer 404) |
| IDs | Integers | Strings with `doc_`, `tpl_`, `rcp_`, `evt_` prefixes |
| Lists | Page numbers | Cursor: `data`, `next_cursor`, `limit` 1 to 100 |
| Errors | `{"message": "..."}` | `{"error": {"code": "...", "message": "..."}}` |
| Signed PDF | Website login (`Documents::downloadPdf()`) | `GET /documents/{id}/download` (15-minute link) |
| Delete | Website login (`Documents::deleteDocument()`) | No delete; `cancel` stops signing |
| Webhooks | No | Yes |

BreezeDoc documents that team keys work only with `/api/v1`, and personal access tokens only
with `/api`. Create a team key in Settings → Integrations → Manage API Keys → Team API keys.
The key is shown once.

```php
use Breezedoc\Breezedoc;
use Breezedoc\Config\Configuration;

// Team API v1 only:
$team = Breezedoc::teamClient('bd_live_...');

// Or one client for both APIs (and the website features):
$config = new Configuration('your-personal-access-token');
$config->setTeamApiKey('bd_live_...');
$client = Breezedoc::client($config);
$team = $client->team();
```

### Send a document from a template

```php
// Read the template's numbered parties, then give exactly one recipient per party.
$template = $team->templates()->find('tpl_123');
$parties = $template->getParties();

// Creating a draft never sends email. The last argument is an Idempotency-Key.
$draft = $team->documents()->createFromTemplate('tpl_123', [
    ['party' => 1, 'name' => 'Jordan', 'email' => 'jordan@example.com'],
], 'NDA', 'nda-jordan-001');

echo $draft->getReviewUrl(); // review the unsent draft in BreezeDoc

// Send it. Options: signing_order (bool), message (custom-email plan), expires_in_days (1-365).
$team->documents()->send($draft->getId(), ['expires_in_days' => 30], 'nda-jordan-send-001');

// Later:
$document = $team->documents()->find($draft->getId());
$document->getStatus();      // draft, sent, completed, cancelled or expired
$document->isCompleted();
$team->documents()->remind($draft->getId());   // once per 24 hours
$team->documents()->cancel($draft->getId());   // nobody can sign it after this
```

Other ways to create a draft:

```php
// From an agreement's wording: only h1, h2, h3, p, ul, ol, li, strong, em, u and br, no
// attributes. BreezeDoc adds a signature page for parties 1 and 2.
$team->documents()->createFromHtml('<h1>Agreement</h1><p>...</p>', $recipients, 'Agreement');

// From a PDF (at most 25 MB and 100 pages). Field coordinates are page fractions (0 to 1).
$team->documents()->createFromPdf(file_get_contents('contract.pdf'), $recipients, [
    ['party' => 1, 'page' => 2, 'type' => 'signature',
     'properties' => ['x' => 0.1, 'y' => 0.8, 'w' => 0.25, 'h' => 0.05]],
], 'Contract');
```

> **Note on PDF upload.** BreezeDoc's spec does not say how the `recipients` and `fields`
> arrays are encoded in the multipart body. The SDK sends them in the bracket form that PHP
> and Laravel parse into arrays (`recipients[0][email]`, `fields[0][properties][x]`). This has
> not been checked against the live API yet.

### Download the signed PDF

```php
// Gets a fresh 15-minute signed URL and fetches it (without your API key).
$pdf = $team->documents()->downloadPdf('doc_456');
$path = $team->documents()->downloadPdfTo('doc_456', '/secure/path'); // doc_456.pdf

// Or just the link. BreezeDoc says to treat it as a credential: do not log it.
$link = $team->documents()->getDownloadLink('doc_456');
$url = $link->getUrl();
```

The PDF includes the signing audit certificate. Wait for the `document.completed` webhook (or
the `completed` status) before you download it.

### Lists, cursors and audit events

```php
use Breezedoc\V1\Models\DocumentStatus;

$page = $team->documents()->list([
    'status' => DocumentStatus::COMPLETED,
    'created_after' => new DateTimeImmutable('-7 days'), // sent as ISO 8601 UTC
    'limit' => 100,
]);
foreach ($page as $document) { /* ... */ }
$next = $page->getNextCursor(); // pass as 'cursor' for the next page; null on the last page

// Or walk every page:
foreach ($team->documents()->all(['status' => DocumentStatus::SENT]) as $document) { /* ... */ }

foreach ($team->documents()->allEvents('doc_456') as $event) {
    echo $event->getType(), ' ', $event->getCreatedAt()->format(DATE_ATOM), "\n";
}

$account = $team->me(); // the key's team, the owner's plan and the team usage
```

### Idempotency keys and errors

Every POST method takes an optional `Idempotency-Key` as its last argument. BreezeDoc replays
the first response for 24 hours, so a retry with the same key never creates or sends twice. A
key reused with a different payload answers 409, which throws `ConflictException`. 409 is
also the answer to a state conflict, for example a download of a document that is not
completed.

```php
use Breezedoc\Exceptions\ApiException;
use Breezedoc\Exceptions\ConflictException;

try {
    $team->documents()->send('doc_456', [], 'send-doc_456');
} catch (ConflictException $e) {
    echo $e->getErrorCode(); // the error.code of the Team API v1 envelope
} catch (ApiException $e) {
    echo $e->getStatusCode(), ' ', $e->getErrorCode(), ' ', $e->getMessage();
}
```

403 "This key needs the write scope" means a read-only key tried to create, send, remind,
cancel or manage webhooks.

### Response fields

The published OpenAPI spec has no response schemas for the Team API v1. It says only that
responses are "JSON metadata only", that IDs carry the prefixes above, that timestamps are ISO
8601 UTC, and that document responses include `review_url`. The SDK's models (`TeamDocument`,
`TeamTemplate`, `TeamRecipient`, `TeamAccount`, `DocumentEvent`, `DownloadLink`,
`WebhookEndpoint`, `WebhookDelivery`) therefore read their fields leniently: a missing field
gives `null` (or an empty array). `toArray()` and `get('field')` always give the full response.

## Webhooks

Webhooks tell your server when something happens to a **team** document. Documents under a
personal account outside the team send no webhooks. Webhooks need a team (Agency or Pro plan),
and a team can have up to ten endpoints. An endpoint must be a public HTTPS URL on port 443.

| Event | Meaning |
|---|---|
| `document.sent` | The document has been sent. |
| `recipient.viewed` | A recipient opened the document. |
| `recipient.signed` | A recipient finished signing. |
| `document.completed` | All recipients signed and the completed PDF is stored. |
| `document.cancelled` | The document was cancelled through the API or an AI assistant. |
| `document.expired` | An incomplete document expired. |
| `webhook.test` | A test delivery that you requested. |

The constants are in `Breezedoc\Webhooks\WebhookEventType`.

### Create an endpoint

Add it on the Webhooks page beside the team's API keys, or with the API (write scope):

```php
use Breezedoc\Webhooks\WebhookEventType;

$endpoint = $team->webhooks()->create('https://example.com/breezedoc/webhook', [
    WebhookEventType::RECIPIENT_SIGNED,
    WebhookEventType::DOCUMENT_COMPLETED,
]);
$secret = $endpoint->getSecret(); // whsec_..., shown once: store it now

$team->webhooks()->list();
$team->webhooks()->test($endpoint->getId());          // queues a webhook.test event
$team->webhooks()->rotateSecret($endpoint->getId());  // old secret still valid for 24 hours
$team->webhooks()->deliveries($endpoint->getId());    // logs, kept for 30 days
$team->webhooks()->redeliver($endpoint->getId(), 'delivery-id'); // same event ID
$team->webhooks()->destroy($endpoint->getId());
```

To change the URL or the events, remove the endpoint and create it again.

### Verify and handle a delivery

Every delivery has a `BreezeDoc-Signature` header with a timestamp and an HMAC-SHA256
signature. Verify it against the **raw request body**, before any JSON parsing:

```php
use Breezedoc\Exceptions\WebhookVerificationException;
use Breezedoc\Webhooks\WebhookVerifier;

$verifier = new WebhookVerifier(getenv('BREEZEDOC_WEBHOOK_SECRET'));

$payload = file_get_contents('php://input');
$signature = $_SERVER['HTTP_BREEZEDOC_SIGNATURE'] ?? '';

try {
    $event = $verifier->constructEvent($payload, $signature);
} catch (WebhookVerificationException $e) {
    http_response_code(400);
    exit;
}

// Deliveries can repeat or arrive out of order: skip event IDs you have already stored.
if (!alreadyHandled($event->getId())) {
    storeOrQueue($event); // then answer 2xx
}

if ($event->isDocumentCompleted()) {
    $pdf = $team->documents()->downloadPdf($event->getDocumentId());
}
http_response_code(200);
```

With a PSR-7 request, use `$verifier->constructEventFromRequest($request)`. The event gives
`getId()`, `getType()`, `getCreatedAt()`, `getDocumentId()`, `getDocumentStatus()` and, for
`recipient.*` events, `getRecipient()` (ID and email). Payloads never contain the document text
or field values; read the current status from the API when the order matters.

How the verifier checks a request, as BreezeDoc documents it:

- The header has exactly one `t=<unix seconds>` part and one or more `v1=<64 hex>` parts.
- Each `v1` value is `hex(HMAC-SHA256(secret, "<t>." + raw body))`. The secret is used as
  given, `whsec_` prefix included.
- A timestamp more than 300 seconds from now is rejected (`new WebhookVerifier($secret, $seconds)`
  changes the tolerance).
- After a secret rotation, BreezeDoc sends a signature for each secret for 24 hours. Any match
  is enough. You can also pass several secrets: `new WebhookVerifier([$new, $old])`.
- Signatures are compared with `hash_equals()`.

`WebhookVerifier::generateSignatureHeader($secret, $payload)` builds a valid header for your
own tests.

BreezeDoc retries a failed delivery (any status other than 2xx) after 1 minute, 5 minutes,
30 minutes, 2 hours, 6 hours, 12 hours and 24 hours. It does not follow redirects. After three
days of continuous failure it disables the endpoint and emails the team owner.

## AI assistants (MCP)

BreezeDoc also runs an MCP server at `https://breezedoc.com/mcp` for ChatGPT, Claude and Cursor
([setup guide](https://help.breezedoc.com/article/1122-how-to-connect-breezedoc-to-chatgpt-claude-or-cursor)).
It uses OAuth (a team owner signs in and approves read or write access), not an API key, so it
is not part of this SDK. Its tools are `get_account`, `list_templates`, `get_template`,
`create_document`, `list_documents`, `get_document_status`, `send_document`, `send_reminder`,
`cancel_document` and `get_download_link`. Sending, reminding and cancelling ask for approval.

## API Resources

### Users

```php
// Get current authenticated user
$user = $client->users()->me();
echo $user->getName();
echo $user->getEmail();
```

### Documents

```php
// List documents
$result = $client->documents()->list([
    'page' => 1,
    'order_by' => 'created_at',
    'direction' => 'desc',
]);

foreach ($result as $document) {
    echo $document->getTitle();
}

// Get a single document
$document = $client->documents()->find(123);

// Create a document
$document = $client->documents()->create([
    'title' => 'My Document',
    'recipients' => [
        [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'party' => 1,
        ],
    ],
]);

// Send a document for signing
$document = $client->documents()->send(123, [
    ['name' => 'John Doe', 'email' => 'john@example.com'],
]);

// List document recipients
$recipients = $client->documents()->recipients(123);

// Download page images (returns array of JPEG binary strings)
$images = $client->documents()->downloadPageImages(123);

// Download page images and save to a directory
$paths = $client->documents()->downloadPageImagesTo(123, '/path/to/output', 'contract');
// Saves: contract-1.jpg, contract-2.jpg, ...
```

#### Downloading the signed PDF

The original REST API does not expose document PDFs — only per-page JPEG images (above).
To get the actual signed/completed PDF (with signatures and the audit certificate), the SDK
can log into the BreezeDoc **website** with your web credentials and fetch the file the site's
"Download" button serves.

> **Team documents:** the Team API v1 now has a signed-PDF download, with no website login.
> Use `$team->documents()->downloadPdf('doc_...')` for documents in a team (see
> [Download the signed PDF](#download-the-signed-pdf)). The method below stays for documents of
> a personal account, which the Team API v1 cannot see. BreezeDoc does not document how an
> integer document ID of the original API maps to a `doc_` ID.

```php
use Breezedoc\Breezedoc;
use Breezedoc\Config\Configuration;

$config = new Configuration('your-api-token');
// Your breezedoc.com website email + password (NOT the API token):
$config->setWebLogin('you@example.com', 'your-website-password');

$client = Breezedoc::client($config);

// Get the signed PDF as raw bytes:
$pdf = $client->documents()->downloadPdf(123);

// ...or save it to a directory (default filename: document-123.pdf):
$path = $client->documents()->downloadPdfTo(123, '/path/to/output');
```

The website login session is cached to `~/.breezedoc/session.json` (written `0600`) and
reused across runs, so you are not logged in on every call; an expired session is detected and
refreshed automatically. Customize or relocate the cache:

```php
use Breezedoc\Web\FileSessionStore;

$config->setSessionStore(new FileSessionStore('/secure/path/session.json'))
       ->setWebSessionTtl(1800); // seconds a cached session is trusted before a proactive re-login
```

Like the rest of the SDK, this uses your configured/auto-discovered PSR-18 client — no extra
dependency is required. It expects a client whose `sendRequest()` does not follow redirects
(the standard behavior, e.g. Guzzle's PSR-18 adapter).

> **⚠️ Caveat.** This feature automates the BreezeDoc website login. It may be against
> BreezeDoc's Terms of Service and is inherently brittle — a change to their login page or bot
> protection can break it at any time. The cached session file is a live credential (treat it
> like a password and keep it out of version control). Your password is never written to disk —
> only the session cookies are cached.

#### Deleting a document

Neither REST API has a document delete. The Team API v1 can only cancel a sent document
(`$team->documents()->cancel()`), which stops signing but keeps the document. Use
`Documents::deleteDocument()`. It sends the
delete through the website, with the same web login and session cache as `downloadPdf()`, and
uses the REST API (authoritative for your API token's account) to decide the result. The delete
cannot be undone.

```php
use Breezedoc\Exceptions\BreezedocException;
use Breezedoc\Exceptions\DeleteNotConfirmedException;
use Breezedoc\Exceptions\NotFoundException;

try {
    $client->documents()->deleteDocument(123); // returns only when the REST API no longer finds it
} catch (NotFoundException $e) {
    // The REST API did not find the document before the delete. No website call was made.
} catch (DeleteNotConfirmedException $e) {
    // The delete was sent, but the document still exists.
} catch (BreezedocException $e) {
    // Login failed (AuthenticationException), the site refused a step, or another API error.
}
```

`Documents::deleteDocument()` works in three steps:

1. `find($id)` on the REST API. A 404 throws `NotFoundException`, and no website call is made. This
   is the only `NotFoundException` that means "the document is not there".
2. The website delete: it reads the CSRF token from the document's activity page, submits the form
   of the site's "Delete" button, and checks the activity page again. A website 404 is only a
   signal here: a renamed route or a web login on another account also gives one.
3. `find($id)` again. A REST 404 means deleted, and the call returns normally. A document that is
   still returned throws `DeleteNotConfirmedException`. Other REST errors propagate.

On the website, a dead session is re-logged-in once and an expired CSRF token (HTTP 419) is
refreshed once. Exception messages carry the document id, the failed step and the HTTP status
only. `WebSession::deleteDocument()` is the low-level website step; do not call it directly to
delete a document, because its `NotFoundException` only means that the website answered 404.

### Templates

```php
// List templates
$templates = $client->templates()->list();

// Get a template
$template = $client->templates()->find(123);

// Create a document from a template
$document = $client->templates()->createDocument(123);
```

### Recipients

```php
// List all recipients across all documents
$recipients = $client->recipients()->list([
    'order_by' => 'completed_at',
    'direction' => 'desc',
]);
```

### Invoices

```php
// List invoices
$invoices = $client->invoices()->list([
    'status' => 'draft',
]);

// Get an invoice
$invoice = $client->invoices()->find(123);

// Create an invoice
$invoice = $client->invoices()->create([
    'customer_email' => 'customer@example.com',
    'customer_name' => 'John Doe',
    'currency' => 'USD',
    'description' => 'Invoice for services',
    'payment_due' => '2026-12-31',
    'items' => [
        [
            'description' => 'Consulting services',
            'quantity' => 10,
            'unit_price' => 10000, // $100.00 in cents
        ],
    ],
]);

// Update an invoice
$invoice = $client->invoices()->update(123, [
    'description' => 'Updated description',
]);

// Delete a draft invoice
$client->invoices()->destroy(123);

// Send an invoice
$invoice = $client->invoices()->send(123);
```

Invoices also accept and return `title`, `invoice_number`, `payment_terms` and a discount
(`discount_type` `percentage` or `flat`, with `discount_percentage` or `discount_amount_cents`,
and `discount_label`). Item quantities can be fractional (minimum 0.01): use
`InvoiceItem::getQuantityDecimal()`, because `getQuantity()` returns an integer.

### Teams (Agency Plan)

```php
// List team documents
$documents = $client->teams()->documents($teamId);

// List team templates
$templates = $client->teams()->templates($teamId);
```

**Note:** Teams endpoints require an Agency plan subscription. They are part of the original
API (personal access token, integer team ID). For the Team API v1 with a team API key, see
[Team API v1](#team-api-v1-team-api-keys).

## Pagination

All list endpoints return paginated results:

```php
$result = $client->documents()->list(['page' => 1]);

// Access items
foreach ($result as $document) {
    // ...
}

// Or get the array
$items = $result->getItems();

// Pagination info
$result->getCurrentPage();  // Current page number
$result->getLastPage();     // Total pages
$result->getTotal();        // Total items
$result->getPerPage();      // Items per page
$result->hasNextPage();     // Check if there's a next page
$result->hasPreviousPage(); // Check if there's a previous page
```

## Error Handling

The SDK throws typed exceptions for different error scenarios:

```php
use Breezedoc\Exceptions\AuthenticationException;
use Breezedoc\Exceptions\AuthorizationException;
use Breezedoc\Exceptions\ConflictException;
use Breezedoc\Exceptions\NotFoundException;
use Breezedoc\Exceptions\ValidationException;
use Breezedoc\Exceptions\RateLimitException;
use Breezedoc\Exceptions\ApiException;

try {
    $document = $client->documents()->find(999);
} catch (AuthenticationException $e) {
    // 401 - Invalid or expired token
    echo "Authentication failed: " . $e->getMessage();
} catch (AuthorizationException $e) {
    // 403 - Access denied
    echo "Access denied: " . $e->getMessage();
} catch (NotFoundException $e) {
    // 404 - Resource not found
    echo "Not found: " . $e->getMessage();
} catch (ConflictException $e) {
    // 409 - State or Idempotency-Key conflict (Team API v1)
    echo "Conflict: " . $e->getMessage();
} catch (ValidationException $e) {
    // 422 - Validation errors
    echo "Validation failed: " . $e->getMessage();
    foreach ($e->getErrors() as $field => $errors) {
        echo "$field: " . implode(', ', $errors) . "\n";
    }
} catch (RateLimitException $e) {
    // 429 - Rate limit exceeded
    echo "Rate limited. Retry after: " . $e->getRetryAfter() . " seconds";
} catch (ApiException $e) {
    // Other API errors
    echo "API error ({$e->getStatusCode()}): " . $e->getMessage();
}
```

Team API v1 errors also carry a machine-readable code: `$e->getErrorCode()`.

## Rate Limiting

The original API allows 60 requests per minute per account; creating documents is capped at
100 a day and sending at 20 an hour. The Team API v1 allows 60 requests per minute per key, and
each team can create 100 documents a day and send 20 an hour across all its keys. A 429
response carries `Retry-After`.

The SDK does not retry a 429 by itself yet: it throws a `RateLimitException`, and
`$e->getRetryAfter()` gives the seconds to wait. `Configuration::setMaxRetries()` exists, but the
client does not use it yet (see the issue tracker).

## Bring Your Own HTTP Client

The SDK uses [PSR-18](https://www.php-fig.org/psr/psr-18/) HTTP client interface, allowing you to provide your own HTTP client:

```php
use GuzzleHttp\Client as GuzzleClient;
use Breezedoc\Breezedoc;

$guzzle = new GuzzleClient([
    'timeout' => 60,
    'verify' => false, // Disable SSL verification (not recommended)
]);

$client = Breezedoc::client('your-token', $guzzle);
```

## Field Types

When working with document fields, you can use the `FieldType` constants:

```php
use Breezedoc\Config\FieldType;

// Available field types
FieldType::SIGNATURE;  // Signature field
FieldType::INITIALS;   // Initials field
FieldType::DATE;       // Date field
FieldType::TEXT;       // Text input field
FieldType::EMAIL;      // Email field
FieldType::DROPDOWN;   // Dropdown select field
FieldType::CHECKBOX;   // Checkbox field

// Check if a field is a signature
if ($field->isSignature()) {
    // ...
}

// Get the human-readable name
echo FieldType::getName(FieldType::SIGNATURE); // "Signature"
```

## Working with Submitted Field Data

After a document has been signed, you can retrieve the data that recipients entered — text values, dates, signatures, checkboxes, and more.

### Retrieving a Specific Field by Label

When you know the field labels on your document (e.g., you created it from a known template), you can look up submitted values by iterating and matching:

```php
$document = $client->documents()->find(123);

// Look up by field label
foreach ($document->getSubmittedFields() as $field) {
    if ($field->getLabel() === 'First and Last Name') {
        echo $field->getValue(); // "Alexander Bojer"
    }
}

// Or use getSubmittedField() to find by field name
$signature = $document->getSubmittedField('Signature');
if ($signature !== null) {
    echo $signature->getImage();      // "signatures/abc123.png"
    echo $signature->isSignature();   // true
}
```

### Iterating All Submitted Fields

When you don't know the field structure ahead of time, iterate all submitted data:

```php
$document = $client->documents()->find(123);

foreach ($document->getSubmittedFields() as $field) {
    $label = $field->getLabel() ?? $field->getName();
    $type  = $field->getFieldTypeName(); // "Text", "Signature", "Date", "Checkbox", etc.

    if ($field->isSignature()) {
        echo "{$label}: signed (image: {$field->getImage()})\n";
    } elseif ($field->isChecked()) {
        echo "{$label}: checked\n";
    } else {
        echo "{$label}: {$field->getValue()}\n";
    }
}
```

### Type-Specific Accessors

Each field type stores its value differently. `getValue()` returns a normalized string for any type, but you can also use type-specific methods:

```php
$field->getValue();     // Normalized string for any field type
$field->getText();      // Text/email/dropdown value
$field->getDate();      // Date string (e.g., "03-31-2026")
$field->isChecked();    // Checkbox boolean
$field->getImage();     // Signature/initials image path
```

### Per-Recipient Fields

For multi-signer documents, get submitted fields for a specific recipient:

```php
foreach ($document->getRecipients() as $recipient) {
    echo $recipient->getName() . ":\n";

    foreach ($document->getSubmittedFieldsFor($recipient) as $field) {
        $label = $field->getLabel() ?? $field->getName();
        echo "  {$label}: {$field->getValue()}\n";
    }
}
```

### Underlying Objects

Each `SubmittedField` provides access to the raw objects if you need full details:

```php
$field->getField();          // The Field definition (position, properties, type, etc.)
$field->getRecipientField(); // The raw RecipientField (submitted properties)
$field->getRecipient();      // The full Recipient object
```


## Important Limitations

### No File Upload via the Original API

The original API does **not** support PDF file upload. Documents can only be:
1. Created with metadata (title, recipients) - results in an empty document shell
2. Created from existing templates

**Workflow:**
1. Upload PDFs and create templates via the Breezedoc web UI
2. Use the SDK to create documents from those templates
3. Send documents for signing via the SDK

The Team API v1 accepts PDF uploads and html: see
[Team API v1](#team-api-v1-team-api-keys).

### No Document Deletion via the REST API

Documents cannot be deleted via the original REST API (`DELETE /api/documents/{id}` answers
405), and the Team API v1 has no delete either (only cancel). Only invoices (in draft status)
can be deleted. To delete a document, use `Documents::deleteDocument()`, which goes through the
website (see [Deleting a document](#deleting-a-document)).

## Testing

```bash
# Run unit tests (no API token required)
composer test:unit

# Run integration tests (requires API token)
export BREEZEDOC_API_TOKEN="your-token"
# Optional: also exercise the signed-PDF download flow
export BREEZEDOC_WEB_EMAIL="you@example.com"
export BREEZEDOC_WEB_PASSWORD="your-website-password"
# Optional: read-only Team API v1 checks
export BREEZEDOC_TEAM_API_KEY="bd_live_..."
composer test:integration

# Run all tests
composer test

# Code style check
composer cs

# Static analysis
composer analyse
```

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## License

MIT License - see [LICENSE](LICENSE) for details.
