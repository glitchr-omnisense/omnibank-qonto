# omnibank/qonto

Qonto for [omniscient/omnibank](https://github.com/glitchr-studio/omnibank): the organization's
accounts, their balances and their settled transactions, over Qonto's Business API with an API key.

> **Unverified.** Written from Qonto's published API documentation and recorded answers
> (`Tests/Fixtures`); it has not yet been run against a real Qonto organization or the sandbox.
> Try it with real keys (`docker compose run --rm omnibank accounts qonto` in `omniscient/omnibank`'s
> `docker/`) before relying on it, and drop this notice once it holds.

```php
$gateway = (new QontoGatewayFactory($http))->create(['login' => '...', 'secret_key' => '...']);   // $http: the application's HTTP client; none given, the factory makes its own
```

No framework needed: the package requires `omniscient/omnibank` and `symfony/http-client`. In a
Symfony application, the same through the bundle's configuration:

```yaml
omnibank:
    gateways:
        treasury:
            factory: qonto
            options:
                login: '%env(QONTO_LOGIN)%'              # the organization's login (slug-like)
                secret_key: '%env(QONTO_SECRET_KEY)%'
                sandbox: false                           # true: thirdparty-sandbox.staging.qonto.co
                staging_token: '%env(QONTO_STAGING_TOKEN)%'   # the sandbox's X-Qonto-Staging-Token
```

- `connect()` has nothing to do (the key is the access): no page, the consent active.
- `accounts()` - `GET /v2/organization`: its `bank_accounts` (id, IBAN, BIC, name, currency).
- `balances()` - `balance_cents` (`booked`) and `authorized_balance_cents` (`available`).
- `transactions()` - `GET /v2/transactions?iban=…&status[]=completed&settled_at_from=…&settled_at_to=…`,
  every page (`meta.next_page`); `side: debit` is money gone out (negative), `amount_cents`, the
  `label` as counterparty, the transfer's counterparty IBAN when Qonto includes it, `reference`.
- No `transfer()` nor `notify()` in this version: `RequestNotSupportedException`.

Calls go as `Authorization: {login}:{secret-key}` to `https://thirdparty.qonto.com`. A 5xx, a 429
or a network failure is an `UnavailableException`, never an empty list.

Credentials: in Qonto, Settings → Integrations & partnerships → API key: the **login** and the
**secret key** (an owner or admin can see them). For the sandbox, an account on Qonto's developer
portal gives a sandbox organization and its **staging token**.

## Responsibility

This package is an independent, unofficial client: it is not affiliated with, endorsed by or
sponsored by Qonto (Olinda SAS). It reaches their service on behalf of whoever runs it, and it does
not redistribute their data beyond the short excerpts recorded as test fixtures. Its MIT licence
covers this package's code only and grants no right to the service or to its data.

Whoever installs and runs this package is responsible for complying with Qonto's terms ([API License
Agreement](https://legal.qonto.com/#template-3jkrm-6fj)), for obtaining whatever they require (an
account, a key, a licence or subscription, an attribution or a citation, a rate limit to respect),
and for any use made of what it returns. The authors publish it free of charge, make no commercial
use of it, and accept no liability for how it is used (see [LICENSE](LICENSE)).

License: MIT since 2026-10-09; earlier versions remain published under LGPL-3.0-or-later.
