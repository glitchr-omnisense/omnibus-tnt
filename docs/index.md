# omnibus/tnt

TNT for [omnisense/omnibus](https://github.com/glitchr-omnisense/omnibus): prices, consignments with
their labels, and tracking, through TNT's ExpressConnect XML services on `express.tnt.com`. Since
TNT joined FedEx, ExpressConnect still answers existing TNT accounts; new accounts get FedEx's
APIs instead ([omnibus/fedex](https://github.com/glitchr-omnisense/omnibus-fedex)). The gateway
calls:

| Operation | ExpressConnect service | Address |
|---|---|---|
| rate | Pricing (`priceCheck`) | `https://express.tnt.com/expressconnect/pricing/getprice` |
| ship | Shipping | `https://express.tnt.com/expressconnect/shipping/ship` |
| track | Tracking (`TrackRequest` 3.1) | `https://express.tnt.com/expressconnect/track.do` |

Each call is an XML document posted as the form field `xml_in`, with a 20-second timeout. There is
no sandbox: a test account is one TNT marks as such.

## Installation

```sh
composer require omnibus/tnt
```

Requires PHP 8.2, `omnisense/omnibus` (2.x) and `symfony/http-client` (6.4, 7 or 8); given no
HTTP client, the factory makes one with `HttpClient::create()`. In a Symfony application with
`Omnibus\Bridge\Symfony\OmnibusBundle`, the factory is registered as soon as the package is
installed.

## Credentials

- An ExpressConnect login, **username** and **password**, and the shop's **TNT account number**,
  from the TNT account manager.

Pricing and tracking send the login as HTTP basic authentication; shipping sends it inside the
document (`<LOGIN><COMPANY>` and `<PASSWORD>`).

## Options

| Option | Type | Default | Required | |
|---|---|---|---|---|
| `username` | string | - | yes | the ExpressConnect login |
| `password` | string | - | yes | its password |
| `account_number` | string | - | yes | the TNT account |
| `sender` | ?array | `null` | no | the account's collection address, when it is not the shipment's sender (below) |
| `rates` | list | - | no | prices from configuration instead of ExpressConnect Pricing ([the rates option](https://github.com/glitchr-omnisense/omnibus/blob/2.x/docs/configuration.md#the-rates-option)): with it, Pricing is not called |

The `sender` option is the account's collection address when it is not the shipment's sender:
an `Address`, or its fields (`name`, `street` a list or one line, `postcode`, `city`, `country`,
optionally `company`, `phone`, `email`); a missing field is an `InvalidConfigException` when the
gateway is built.

Shipment options (`Shipment::$options`):

| Option | Read by | Default | |
|---|---|---|---|
| `documents` | rate, ship | `false` | `true`: a documents consignment (type `D`), else non-documents (`N`) |
| `description` | ship | `Parcel` | the content's description, on the consignment and each package |
| `instructions` | ship | `''` | delivery instructions, cut to 60 characters |
| `option` | ship | `''` | a TNT option code |
| `sender_vat` | ship | `''` | the sender's VAT number |

## Capabilities

| Request | Supported |
|---|---|
| `Rating` | yes (ExpressConnect Pricing), or configured rates with `rates` |
| `Shipping` | yes |
| `Tracking` | yes |
| `Pickup` | no: ExpressConnect has no pickup points (TNT depots are reached by phone) |
| `GetSlip` | no |
| `Cancel` | no: ExpressConnect offers no cancellation |
| `Paperless` | no |

## Behaviour

**Services.** The service is TNT's product code; labels come from
`Omnibus\Tnt\Mapping::SERVICES` when TNT gives none:

| Code | Service |
|---|---|
| `15N` | Express (the default when booking) |
| `09N` | 9:00 Express |
| `10N` | 10:00 Express |
| `12N` | 12:00 Express |
| `48N` | Economy Express |
| `15D` | Express Documents |
| `12D` | 12:00 Express Documents |

**Parcels.** Weights in kilograms with two decimals (at least 0.1), dimensions converted to metres
when all three are given, the total volume in cubic metres (parcels without dimensions count for
0).

**Rate.** One `priceCheck` from the sender's country, town and postcode to the recipient's, the
collection time `Shipment::$shippingDate` (else tomorrow at 10:00), the product type (`documents`),
the account with the sender's country as account country, currency `EUR`, each parcel as a piece
line. Every rated service is a rate: TNT's product id as service, its `productDesc` (else the
table, else `TNT <code>`) as label, `totalPrice` in TNT's currency (`EUR` when none). `days` is
`null` and `toPickupPoint` `false`; `Shipment::$service` does not filter the services. Rates come
cheapest first.

**Ship.** In three posts. The first is the consignment document (`ESHIPPER`, application `EC`
3.1):

- the sender, with the account and `sender_vat`; the collection address is the `sender` option,
  else the shipment's sender; the ship date is
  `Shipment::$shippingDate` (else tomorrow) as `dd/mm/yyyy`, the preferred collection window
  10:00 to 16:00;
- each party is its company (else name), two street lines, city (30 characters each), postcode,
  country, contact name; a phone is split into its first three digits as dial code and the rest as
  number; the email when there is one;
- the consignment reference is the shipment's `reference` (else `omnibus`), cut to 24 characters,
  also the customer reference; payment by the sender (`PAYMENTIND S`);
- the goods value is the sum of the parcels' `value` in the first parcel's currency; no insurance;
- the service `Shipment::$service` or `15N`, the `option`, `description` and `instructions`;
  `CUSTOMCONTROLIN N` across borders;
- the activities: create, book (with its booking reference), ship, print the label.

TNT answers an access code (plain text). The second post, `GET_RESULT:<code>`, gives the
consignment number (`CREATE/CONNUMBER`); the third, `GET_LABEL:<code>`, the label. The label is
TNT's XML document, rendered by TNT's stylesheet: `Label::$content` is that XML and `format` is
`application/xml`, not a PDF. When the label cannot be fetched, the label has no content and the
booking still succeeds. `trackingUrl` is
`https://www.tnt.com/express/en_gb/site/shipping-tools/tracking.html?searchType=con&cons=<number>`.
`pickupPoint` is not sent.

**Track.** A `TrackRequest` 3.1 with the complete level of detail, locale `fr_FR` when the
request's locale starts with `fr`, else `en_GB`, and the consignment number alone: neither market
type nor origin country, the optional filters a tracking number cannot tell. The events of the
first consignment are read from
`LocalEventDate` and `LocalEventTime` (`20261002` `1130`, in PHP's default time zone: TNT gives
local times without an offset), with TNT's status code as `code` and the depot (else its name) as
`location`, sorted oldest first. The status is the latest event's, or `DELIVERED` when the
consignment's `SummaryCode` is `DEL`. An event's status is read from its code, else from words of
its description, in this order:

| Code | Or the description contains | `TrackingStatus` |
|---|---|---|
| `OK` | `delivered` | `DELIVERED` |
| `WC` | `out for delivery` | `OUT_FOR_DELIVERY` |
| | `held`, or `collect` and `depot` | `AVAILABLE_FOR_PICKUP` |
| `RT` | `return` | `RETURNED` |
| `NH`, `CA`, `MD`, `UA` | `attempt`, `exception`, `incorrect` | `EXCEPTION` |
| `CI` | `consignment information`, `shipment created` | `PENDING` |
| any other code | | `IN_TRANSIT` |
| no code | | `UNKNOWN` |

## Example

```php
<?php

require __DIR__.'/vendor/autoload.php';

use Omnibus\Model\Address;
use Omnibus\Model\Parcel;
use Omnibus\Model\Shipment;
use Omnibus\Tnt\TntGatewayFactory;
use Symfony\Component\HttpClient\HttpClient;

$tnt = (new TntGatewayFactory(HttpClient::create()))->create([
    'username' => getenv('TNT_USERNAME'),
    'password' => getenv('TNT_PASSWORD'),
    'account_number' => getenv('TNT_ACCOUNT'),
]);

$shipment = new Shipment(
    new Address('Glitch Art', ['12 rue de la Paix'], '75002', 'Paris', 'FR', company: 'Glitch Art', phone: '+33102030405'),
    new Address('Ada Lovelace', ['Unter den Linden 1'], '10117', 'Berlin', 'DE', email: 'ada@example.org'),
    [new Parcel(800, 30, 20, 10, value: 4500)],
    service: '15N',
    reference: 'CMD-1042',
    options: ['description' => 'Prints', 'instructions' => 'Reception desk'],
);

foreach ($tnt->rate($shipment) as $rate) {
    printf("%s %-16s %6.2f %s\n", $rate->service, $rate->label, $rate->amount / 100, $rate->currency);
}

$label = $tnt->ship($shipment);
file_put_contents($label->trackingNumber.'.xml', (string) $label->content);   // TNT's XML label
echo $label->trackingNumber, ' ', $label->trackingUrl, "\n";

$tracking = $tnt->track($label->trackingNumber, 'en');
echo $tracking->status->value, "\n";
foreach ($tracking->events as $event) {
    echo $event->at->format('Y-m-d H:i'), ' ', $event->code, ' ', $event->description, "\n";
}
```

In Symfony:

```yaml
omnibus:
    gateways:
        tnt:
            factory: tnt
            options:
                username: '%env(TNT_USERNAME)%'
                password: '%env(TNT_PASSWORD)%'
                account_number: '%env(TNT_ACCOUNT)%'
```

## Errors

Every failure is an `Omnibus\Exception\CarrierException` whose `carrier` is `tnt`:

- an error in TNT's answer (`errors/brokenRule` from Pricing, `ERROR` from Shipping): its
  description, its code in `carrierCode`;
- an HTTP status of 400 or more: `TNT answered HTTP <status>.`;
- a network error: `TNT request failed: ...`;
- an HTML page: `TNT answered with a page, not XML.`; a body that is neither XML nor an access
  code: `TNT answered with a body that is not XML.`;
- when booking: `TNT gave no access code for the consignment.`, or the result's
  `CREATE/ERROR/DESCRIPTION` (else `TNT created no consignment.`) when no number comes back.

A missing `username`, `password` or `account_number` is an `InvalidConfigException` when the
gateway is built; `Pickup`, `GetSlip`, `Cancel` and `Paperless` raise
`RequestNotSupportedException` before any call.

## Verified, and not

Tested offline: `Tests/TntGatewayTest.php` (prices cheapest first, the account and the total
weight in the request, basic authentication; a consignment created through its access code, then
its result and label fetched; tracking sorted with `SummaryCode DEL` giving `DELIVERED`, the
`en_GB` locale; no pickup points, no cancellation) and `Tests/TntContractTest.php`, the family's
conformance suite on recorded answers. The example above was run in the core's harness on those
recorded answers.

Not verified: the README says the package is built from TNT's published ExpressConnect
documentation and was not yet run against the service. Tracking by the consignment number alone,
without market type and origin country, is unchecked against a real answer.

## Responsibility

An independent, unofficial client, not affiliated with TNT (Federal Express Corporation). Whoever
runs it complies with TNT's terms ([tnt.com terms and
conditions](https://www.tnt.com/express/en_gb/site/terms-conditions.html)): see the README's
Responsibility section.
