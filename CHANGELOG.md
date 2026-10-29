# Changelog

## [Unreleased]

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
