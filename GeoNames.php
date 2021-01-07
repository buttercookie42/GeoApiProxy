<?php
/* GeoApiProxy - Switch GeoSetter to a different reverse geocoding API
 * Copyright (C) 2020 – 2021, Jan Henning
 *
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/.
 *
 * Web: https://github.com/buttercookie42/GeoApiProxy */

class GeoNames
{
  private const GEONAMES_API_BASE = 'http://api.geonames.org/';

  public static function getCountry($streamContext, string $query): string
  {
    $api_url = GeoNames::formatQueryUrl('countryCode', $query);
    return strtolower(trim(file_get_contents($api_url, false, $streamContext)));
  }

  public static function formatQueryUrl(string $api, string $query): string
  {
    return self::GEONAMES_API_BASE . $api . '?' . $query;
  }

  public static function makePlaceNameXml(string $place, float $lat, float $lon, string $cc, string $country, float $dist): SimpleXMLElement
  {
    $xml = self::initGeoNamesXml();
    $geoname = $xml->addChild('geoname');
    $geoname->addChild('toponymName', $place);
    $geoname->addChild('name', $place);
    $geoname->addChild('lat', $lat);
    $geoname->addChild('lng', $lon);
    $geoname->addChild('countryCode', $cc);
    $geoname->addChild('countryName', $country);
    $geoname->addChild('distance', $dist);
    return $xml;
  }

  public static function makePostalCodeXml(string $state, string $city, float $lat, float $lon, string $cc, float $dist): SimpleXMLElement
  {
    $xml = self::initGeoNamesXml();
    $code = $xml->addChild('code');
    $code->addChild('name', $city);
    $code->addChild('countryCode', $cc);
    $code->addChild('lat', $lat);
    $code->addChild('lng', $lon);
    $code->addChild('adminName1', $state);
    $code->addChild('distance', $dist);
    return $xml;
  }

  private static function initGeoNamesXml(): SimpleXMLElement
  {
    return new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><geonames></geonames>');
  }
}
?>
