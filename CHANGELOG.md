# Changelog

## [Unreleased]

### Fixed
- Tracking asks by the consignment number alone: it always sent origin country `FR` and market type
  `INTERNATIONAL`, optional filters a tracking number cannot tell, which could hide a domestic or
  non-French consignment.
- The `sender` option is read: it is the account's collection address (ExpressConnect's
  `COLLECTIONADDRESS`) when it is not the shipment's sender, as the factory's docblock promised; it
  was declared and never read. An `Address` or its fields; a missing field is an
  `InvalidConfigException` when the gateway is built. `Mapping::address()` loses its unused
  `$upper` parameter.
