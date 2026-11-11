# Changelog

## [Unreleased]

### Changed
- A notice at the head of the README: TNT's customers are being moved to FedEx Express, with no
  date published; the package stays maintained for existing TNT accounts, and `composer.json`
  suggests omnibus/fedex for a new integration. The package is not marked abandoned.

### Added
- On omnisense/contracts: the carrier's usage policy as data (`policy.json`, named in
  `composer.json`'s `extra.omnisense.policy`; docs/index.md, Usage policy), and its waits honoured:
  the API client takes the gateway's Backoff, a 429 is a `RateLimitedException`, the next calls are
  held unsent, nothing is retried. Requires omnisense/contracts. `phpstan.neon` at level max,
  `phpstan-baseline.neon` what was there.
- A label fetched after the booking and held by the carrier's wait leaves the booking standing, as a
  label not ready did; the result fetch held is a `CarrierException` naming the access code (the
  consignment went to TNT).

### Fixed
- A `CarrierException` no longer carries the password. The message quoted the HTTP client's (the
  whole URL), the previous was the client's own exception, and every trace of the chain kept the
  arguments of the calls under it (the request's options, the `Api` with its credentials) when
  `zend.exception_ignore_args` is off: a log of the whole chain wrote them. The message masks them
  as `***`, the previous is a copy of the client's exception (the same class and code), and no
  exception `Api` throws keeps its trace's arguments (`Omnibus\Http\Redaction`).
- Tracking asks by the consignment number alone: it always sent origin country `FR` and market type
  `INTERNATIONAL`, optional filters a tracking number cannot tell, which could hide a domestic or
  non-French consignment.
- The `sender` option is read: it is the account's collection address (ExpressConnect's
  `COLLECTIONADDRESS`) when it is not the shipment's sender, as the factory's docblock promised; it
  was declared and never read. An `Address` or its fields; a missing field is an
  `InvalidConfigException` when the gateway is built. `Mapping::address()` loses its unused
  `$upper` parameter.
