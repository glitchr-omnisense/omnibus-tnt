# Changelog

## [Unreleased]

### Fixed
- Tracking asks by the consignment number alone: it always sent origin country `FR` and market type
  `INTERNATIONAL`, optional filters a tracking number cannot tell, which could hide a domestic or
  non-French consignment.
