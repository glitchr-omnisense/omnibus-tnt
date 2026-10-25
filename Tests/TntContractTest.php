<?php

namespace Omnibus\Tnt\Tests;

use Omnibus\GatewayFactoryInterface;
use Omnibus\Testing\GatewayContractTests;
use Omnibus\Tnt\TntGatewayFactory;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/** The conformance suite on TNT's recorded answers (ExpressConnect pricing, shipping, tracking). */
final class TntContractTest extends TestCase
{
    use GatewayContractTests;

    protected function gatewayFactory(HttpClientInterface $http): GatewayFactoryInterface
    {
        return new TntGatewayFactory($http);
    }

    protected function gatewayOptions(): array
    {
        return ['username' => 'user', 'password' => 'pass', 'account_number' => '000123456'];
    }

    protected function contractTrackingNumber(): string
    {
        return 'GE123456789FR';
    }

    protected function recordedAnswers(): array
    {
        return [
            '/pricing/' => '<?xml version="1.0"?><document><priceResponse><rateId>rate1</rateId><ratedServices><ratedService><product><id>15N</id><productDesc>Express</productDesc></product><totalPrice>42.10</totalPrice><currency>EUR</currency></ratedService><ratedService><product><id>48N</id><productDesc>Economy Express</productDesc></product><totalPrice>25.00</totalPrice><currency>EUR</currency></ratedService></ratedServices></priceResponse></document>',
            'body:ESHIPPER' => 'ABC123XYZ',
            'body:GET_RESULT' => '<?xml version="1.0"?><document><CREATE><CONREF>CMD-1042</CONREF><CONNUMBER>GE123456789FR</CONNUMBER><SUCCESS>Y</SUCCESS></CREATE><BOOK><CONSIGNMENT><CONREF>CMD-1042</CONREF><SUCCESS>Y</SUCCESS></CONSIGNMENT></BOOK></document>',
            'body:GET_LABEL' => '<?xml version="1.0"?><labelResponse><consignment key="1"><consignmentIdentity><consignmentNumber>GE123456789FR</consignmentNumber></consignmentIdentity></consignment></labelResponse>',
            'track.do' => '<?xml version="1.0"?><TrackResponse><Consignment access="FULL"><ConsignmentNumber>GE123456789FR</ConsignmentNumber><SummaryCode>DEL</SummaryCode><StatusData><StatusCode>OK</StatusCode><StatusDescription>Shipment Delivered In Good Condition.</StatusDescription><LocalEventDate>20261002</LocalEventDate><LocalEventTime>1130</LocalEventTime><Depot>PAR</Depot><DepotName>Paris</DepotName></StatusData><StatusData><StatusCode>PU</StatusCode><StatusDescription>Shipment Collected From Sender.</StatusDescription><LocalEventDate>20261001</LocalEventDate><LocalEventTime>1700</LocalEventTime><Depot>PAR</Depot><DepotName>Paris</DepotName></StatusData></Consignment></TrackResponse>',
        ];
    }
}
