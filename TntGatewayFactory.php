<?php

namespace Omnibus\Tnt;

use Omnibus\Config;
use Omnibus\Exception\InvalidConfigException;
use Omnibus\GatewayFactory;
use Omnibus\Model\Address;
use Omnibus\Tnt\Action\RatingAction;
use Omnibus\Tnt\Action\ShippingAction;
use Omnibus\Tnt\Action\TrackingAction;
use Symfony\Component\HttpClient\HttpClient;

/**
 *   options:
 *     username: '%env(TNT_USERNAME)%'        # the ExpressConnect login
 *     password: '%env(TNT_PASSWORD)%'
 *     account_number: '%env(TNT_ACCOUNT)%'
 *     sender: { name, company, street: [], postcode, city, country, phone, email }  # optional: the account's collection address when it is not the shipment's sender
 *     rates: [...]                           # optional: configured prices instead of ExpressConnect Pricing
 *
 * No pickup points, no cancellation: ExpressConnect offers neither; TNT Depots are
 * addressed by phone. ExpressConnect has no sandbox: a test account is one TNT marks as such.
 */
final class TntGatewayFactory extends GatewayFactory
{
    protected function populateConfig(Config $config): void
    {
        $config->defaults([
            'omnibus.factory_name' => 'tnt',
            'omnibus.factory_title' => 'TNT',
            'omnibus.required_options' => ['username', 'password', 'account_number'],
            'omnibus.api' => function (Config $c) {
                $http = $this->http ?? HttpClient::create();

                return new Api($http, (string) $c['username'], (string) $c['password'], (string) $c['account_number']);
            },
            'omnibus.action.rating' => static fn (Config $c) => $c->get('rates') ? null : new RatingAction(),
            'sender' => null,
            'omnibus.action.shipping' => static fn (Config $c) => new ShippingAction(self::collection($c['sender'])),
            'omnibus.action.tracking' => new TrackingAction(),
        ]);
    }

    /** The "sender" option as an Address: an Address, or an array of its fields (street a list or one line). */
    private static function collection(mixed $sender): ?Address
    {
        if (null === $sender || $sender instanceof Address) {
            return $sender;
        }
        if (!\is_array($sender)) {
            throw new InvalidConfigException('TNT\'s "sender" option is an address: name, street, postcode, city, country (company, phone, email optional).');
        }
        foreach (['name', 'street', 'postcode', 'city', 'country'] as $field) {
            if (empty($sender[$field])) {
                throw new InvalidConfigException(sprintf('TNT\'s "sender" option needs its "%s".', $field));
            }
        }

        return new Address((string) $sender['name'], array_values((array) $sender['street']), (string) $sender['postcode'], (string) $sender['city'], (string) $sender['country'], $sender['company'] ?? null, $sender['email'] ?? null, $sender['phone'] ?? null);
    }
}
