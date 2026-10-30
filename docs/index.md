# omnibank/qonto

[Qonto](https://qonto.com) for [omnisense/omnibank](https://github.com/glitchr-omnisense/omnibank):
the organization's accounts, their balances and their settled transactions, over Qonto's Business
API with an API key. The gateway calls:

| Operation | Path |
|---|---|
| accounts, balances | `GET /v2/organization` (its `bank_accounts`) |
| transactions | `GET /v2/transactions` |

on `https://thirdparty.qonto.com`, or the sandbox `https://thirdparty-sandbox.staging.qonto.co`
(`sandbox: true`). Every call sends `Authorization: <login>:<secret key>`, `Accept:
application/json` and, when set, `X-Qonto-Staging-Token`, with a 20-second timeout.

> [!WARNING]
> Unverified: written from Qonto's published API documentation and recorded answers (`Tests/Fixtures`); not yet run against a real Qonto organization or the sandbox.

## Installation

```sh
composer require omnisense/omnibank omnibank/qonto
```

Requires PHP 8.2, `omnisense/omnibank` (1.x) and `symfony/http-client` (6.4, 7 or 8). In a Symfony
application with `Omnibank\Bridge\Symfony\OmnibankBundle`, the factory is registered on the
application's `http_client` as soon as the package is installed.

## Credentials

In Qonto, Settings, Integrations & partnerships, API key: the **login** (slug-like) and the
**secret key** (an owner or an admin can see them). For the sandbox, an account on Qonto's developer
portal gives a sandbox organization and its **staging token**. The key is the access: there is no
consent to give and nothing to keep in the connection.

## Options

`new QontoGatewayFactory(?HttpClientInterface $http = null)` (none given: `HttpClient::create()`);
options of `create()`:

| Option | Type | Default | Required | |
|---|---|---|---|---|
| `login` | string | - | yes | the organization's login |
| `secret_key` | string | - | yes | |
| `sandbox` | bool | `false` | no | `true`: the sandbox host |
| `staging_token` | ?string | `null` | no | the sandbox's `X-Qonto-Staging-Token` |
| `host` | ?string | `null` | no | another host, whatever `sandbox` says |

## Capabilities

| Request | Supported |
|---|---|
| `Connect` | yes: nothing to open, the connection back with its consent ACTIVE |
| `FetchAccounts` | yes |
| `FetchBalances` | yes |
| `FetchTransactions` | yes (an account with an IBAN) |
| `Transfer` | no, in this version |
| `Notify` | no |

## Behaviour

**Accounts.** The organization's `bank_accounts`: `id` (else `slug`, else the IBAN) as the account's
id, `iban`, `bic`, `name` (else `slug`), `currency` (EUR by default), `type` `external` for
`is_external_account`, `checking` otherwise; the whole entry in `raw`.

**Balances.** The organization is asked again and the account found by its id: `balance_cents`
`booked`, `authorized_balance_cents` `available`, both at the account's `updated_at` (now when it
has none). An id the organization does not have is a `ProviderException`.

**Transactions.** By the account's IBAN (an account without one is a `ProviderException`),
`status[]=completed`, `settled_at_from` / `settled_at_to` (the first and last instant of the days
asked, in their time zone, sent in UTC), `sort_by=settled_at:asc`, `includes[]=transfer`,
`per_page=100`, `current_page` from 1 while `meta.next_page` says there is a next one (up to 1000
pages). Lists in the query are written as Qonto (Rails) reads them, `key[]=value`. Each line:

- `id`: `transaction_id`, else `id`;
- `bookedOn`: `settled_at` (a line without it is left out); no value date;
- `amount`: `amount_cents` (else `amount` times 100), negative when `side` is `debit`, in the line's
  `currency`;
- `label`: Qonto's `label`; `counterpartyName`: the transfer's `counterparty_account_name`, else the
  label; `counterpartyIban`: the transfer's `counterparty_account_number`, when Qonto includes the
  transfer; `reference`: `reference`;
- `raw`: the line.

The lines are kept between the days asked and sorted oldest first.

## Kinds, categories, states

- **Kind**: Qonto's own accounts are current accounts (`AccountKind::CHECKING`); an external account
  shown in Qonto (`is_external_account`) says nothing of its kind (`UNKNOWN`).
- **Categories** (`Categories`, docs.qonto.com, List transactions): a transaction's `category`
  (`restaurant_and_bar`, `food_and_grocery` FOOD; `transport`, `gas_station` TRANSPORT;
  `office_rental`, `utility` HOUSING; `online_service`, `subscription` SUBSCRIPTIONS; `tax` TAXES;
  `salary`, `sales`, `other_income`, `refund` INCOME; `fees`, `finance` BANK_FEES; `atm` CASH;
  `treasury_and_interco` TRANSFER; the business spendings BUSINESS...); `fallback` and `pending`
  are none yet. Without one, the `operation_type`: `transfer`, `swift_income` TRANSFER; `income`,
  `card_acquirer_payout`, `account_remuneration` INCOME; `qonto_fee` BANK_FEES; `cheque` CASH;
  `financing_installment`, `pay_later` LOAN; `investment` SAVINGS; `f24` TAXES.
- **State**: a 401 (the API key refused) is a `ProviderException` whose `connectionState` is
  `WRONG_CREDENTIALS`; an outage stays an `UnavailableException` (`ConnectionState::ofFailure()`:
  BANK_UNAVAILABLE).

## Example

On the answers the package's tests keep (`Tests/Fixtures`, written from Qonto's documentation),
each request printed:

```php
<?php // qonto.php

require __DIR__.'/vendor/autoload.php';

use Omnibank\Model\Connection;
use Omnibank\Qonto\QontoGatewayFactory;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

// The answers omnibank/qonto's tests keep, written from Qonto's documentation; each request printed
$fixtures = '/ws/qonto/Tests/Fixtures';   // the package's Tests/Fixtures, here in the family's workspace
$http = new MockHttpClient(function (string $method, string $url, array $options) use ($fixtures) {
    echo '  ', $method, ' ', urldecode($url), "\n";
    $page = preg_match('/current_page=(\d+)/', $url, $m) ? $m[1] : null;

    return new MockResponse(file_get_contents($fixtures.'/'.(null === $page ? 'organization.json' : "transactions-page-$page.json")));
});
$qonto = (new QontoGatewayFactory($http))->create(['login' => 'example-organization', 'secret_key' => 'qonto_test_not_a_real_key']);
$connection = new Connection();

[$account] = $qonto->accounts($connection);
echo $account->name, ' ', $account->iban, ' ', $account->type, "\n";
foreach ($qonto->balances($connection, $account) as $balance) {
    echo '  ', $balance->type, ' ', $balance->amount, "\n";
}
foreach ($qonto->transactions($connection, $account, new DateTimeImmutable('2026-09-01'), new DateTimeImmutable('2026-09-30')) as $t) {
    printf("  %s %9s  %-24s %s\n", $t->bookedOn->format('Y-m-d'), $t->amount->decimal(), $t->label, $t->counterpartyIban ?? '-');
}
```

```
$ php qonto.php
  GET https://thirdparty.qonto.com/v2/organization
Compte principal FR5116958000011234567890142 checking
  GET https://thirdparty.qonto.com/v2/organization
  booked 18432.17 EUR
  available 18102.17 EUR
  GET https://thirdparty.qonto.com/v2/transactions?iban=FR5116958000011234567890142&status[]=completed&settled_at_from=2026-09-01T00:00:00.000Z&settled_at_to=2026-09-30T23:59:59.999Z&sort_by=settled_at:asc&includes[]=transfer&per_page=100&current_page=1
  GET https://thirdparty.qonto.com/v2/transactions?iban=FR5116958000011234567890142&status[]=completed&settled_at_from=2026-09-01T00:00:00.000Z&settled_at_to=2026-09-30T23:59:59.999Z&sort_by=settled_at:asc&includes[]=transfer&per_page=100&current_page=2
  2026-09-02   1250.00  Camille Durand           FR1420041010050500013M02606
  2026-09-08    -23.40  Carrefour City           -
  2026-09-10  -5300.00  Lucie Martin             FR6010278060410002051020134
```

(run on 2026-10-26, PHP 8.4.27, no network, the container in UTC; the organization, the IBANs and
the amounts are made up)

Against Qonto, give the factory a real client (or none) and the organization's keys:

```yaml
omnibank:
    gateways:
        treasury:
            factory: qonto
            options:
                login: '%env(QONTO_LOGIN)%'
                secret_key: '%env(QONTO_SECRET_KEY)%'
                sandbox: false
                staging_token: '%env(QONTO_STAGING_TOKEN)%'
```

## Errors

| Qonto's answer | Exception |
|---|---|
| a transport failure (network, DNS, timeout) | `UnavailableException` (`[qonto] Qonto cannot be reached: ...`) |
| HTTP 5xx, 429 | `UnavailableException` (`Qonto answered HTTP 503: try again later.`), the status as `providerCode` |
| a body that is not JSON | `UnavailableException` |
| another 4xx | `ProviderException`, the message `errors[0].detail` (else `errors[0].message`, `message`), `providerCode` `errors[0].code` or the status |
| missing `login` or `secret_key` | `InvalidConfigException` (`The "qonto" gateway needs: secret_key.`) |
| `transfer()`, `notify()` | `RequestNotSupportedException` |

No failure carries the secret key and the staging token: a transport failure's message has them
masked as `***` (the key parameters of a URL too), its previous is a copy of the HTTP client's
exception (the same class and code), and no trace of the chain keeps its arguments, where the
request's options and headers stood ([No secret in a
failure](https://github.com/glitchr-omnisense/omnibank/blob/1.x/docs/errors.md#no-secret-in-a-failure)).

## Verified, and not

- Verified (the package's tests, 8, green on 2026-10-26 in the family's workspace): connect has
  nothing to do; the organization's accounts and balances; transactions every page, signed by
  side; the sandbox's staging token sent; a 5xx and a network failure as `UnavailableException`;
  wrong credentials as the provider's refusal; no transfers nor webhooks.
- Not verified: against a real organization or the sandbox; Qonto's real pagination and
  `includes[]=transfer` answers beyond the documentation's.

## Responsibility

This package is an independent, unofficial client, not affiliated with, endorsed by or sponsored by
Qonto (Olinda SAS). Whoever runs it is responsible for complying with Qonto's terms
([API License Agreement](https://legal.qonto.com/#template-3jkrm-6fj)) and for what is done with
what it returns; see the README's Responsibility section.
