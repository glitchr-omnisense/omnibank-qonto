# Changelog

## [Unreleased]

### Changed

- On omnisense/contracts: its usage policy as data and Qonto's waits honoured: `policy.json` (1,000 requests every 10 seconds, 10,000 every 10 minutes, per IP address; read 2026-11-10), named in `composer.json`'s `extra.omnisense.policy`; `Api` takes the gateway's `Backoff` (omnisense/omnibank's `Http\Throttle`): no call while Qonto's wait lasts, a 429 a `RateLimitedException` (an `UnavailableException`, its message as before) with its `Retry-After`, never retried. Requires omnisense/contracts. `phpstan.neon` at level max, `phpstan-baseline.neon` what was there (45 errors). `Tests/QontoPolicyTest`.

### Added

- Qonto's words mapped onto omnisense/omnibank's (docs.qonto.com): its accounts' kind
  (`AccountKind::CHECKING`, an external account `UNKNOWN`), its transactions' `category` and else
  `operation_type` onto `TransactionCategory` (`Categories`, `BankTransaction::$category`), and a
  401 as `ConnectionState::WRONG_CREDENTIALS` on the `ProviderException`.

### Fixed

- An `UnavailableException` no longer carries the secret key and the staging token. The message
  quoted the HTTP client's (the whole URL), the previous was the client's own exception, and every
  trace of the chain kept the arguments of the calls under it (the request's options, the headers
  with the credentials) when `zend.exception_ignore_args` is off: a log of the whole chain wrote
  them. The message masks them as `***`, the previous is a copy of the client's exception (the same
  class and code), and no exception `Api` throws keeps its trace's arguments
  (`Omnibank\Http\Redaction`).
