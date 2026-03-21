# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.1.1] - 2026-03-21

### Fixed

- `FieldProperties::fromArray()` now handles non-string values (e.g. `false`) returned by the API for nullable string properties (`defaultValue`, `label`, `fontFamily`) without throwing a `TypeError` under `strict_types=1` (#1)

## [0.1.0] - 2025-01-10

### Added

- Initial release
