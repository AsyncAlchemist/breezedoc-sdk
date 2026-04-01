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

```php
use Breezedoc\Breezedoc;
use Breezedoc\Config\Configuration;

// Simple: just pass the token
$client = Breezedoc::client('your-api-token');

// Advanced: use Configuration for custom settings
$config = new Configuration('your-api-token');
$config->setTimeout(60)       // Request timeout in seconds
       ->setMaxRetries(5);    // Max retries on rate limit

$client = Breezedoc::client($config);
```

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

### Teams (Agency Plan)

```php
// List team documents
$documents = $client->teams()->documents($teamId);

// List team templates
$templates = $client->teams()->templates($teamId);
```

**Note:** Teams endpoints require an Agency plan subscription.

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

## Rate Limiting

The Breezedoc API allows 60 requests per minute. The SDK includes built-in rate limit handling with automatic retry.

You can configure the maximum number of retries:

```php
$config = new Configuration('your-token');
$config->setMaxRetries(5); // Default is 3
```

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

### No File Upload via API

The Breezedoc API does **not** support PDF file upload. Documents can only be:
1. Created with metadata (title, recipients) - results in an empty document shell
2. Created from existing templates

**Workflow:**
1. Upload PDFs and create templates via the Breezedoc web UI
2. Use the SDK to create documents from those templates
3. Send documents for signing via the SDK

### No Document Deletion

Documents cannot be deleted via API. Only invoices (in draft status) can be deleted.

## Testing

```bash
# Run unit tests (no API token required)
composer test:unit

# Run integration tests (requires API token)
export BREEZEDOC_API_TOKEN="your-token"
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
