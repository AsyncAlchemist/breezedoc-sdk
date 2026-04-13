# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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
