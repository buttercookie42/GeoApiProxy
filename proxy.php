<?php
/* GeoApiProxy - Switch GeoSetter to a different reverse geocoding API
 * Copyright (C) 2020 – 2021, Jan Henning
 *
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/.
 *
 * Web: https://github.com/buttercookie42/GeoApiProxy */

require 'OpenCage/AbstractGeocoder.php';
require 'OpenCage/Geocoder.php';
require 'GeoNames.php';
require 'GreatCircle.php';
require 'StateService.php';

use OpenCage\Geocoder\Geocoder;

const PASSTHROUGH_APIS = ['timezone', 'srtm3', 'gtopo30'];
const NON_XML_APIS = ['srtm3', 'gtopo30', 'debug'];
const OPENCAGE_KEY = NULL; // <-- Insert your own API key here
const CACHE_FILE = 'rgcCache';
/* Official limit for free tier is 1 req/sec.
   The rate limiting currently doesn't account for the fact that serving up a
   cached response (due to the way the GeoNames API works, GeoSetter makes two
   requests per set of coordinates being queried – we cache the response and
   therefore query OpenCage only once) takes a little bit of time, too.
   Therefore we can set the value a little higher in order to achieve an
   *actual* effective request rate of ~1 req/sec. */
const REQUEST_PER_SEC_LIMIT = 1.25; 

const COORDINATE_ACCURACY = 5;
const LANGUAGE_MAPPING = ['us' => 'en',
                          'ca' => 'en',
                          'de' => 'de',
                          'ch' => 'de',
                          'at' => 'de',
                          'be' => 'native',
                          'fr' => 'fr',
                          'lu' => 'fr',
                          'gb' => 'en',
                          'ie' => 'en',
                          'ro' => 'ro',
                          'hu' => 'hu',
                          'default' => 'de,en',
                         ];
// Ignore states and use counties instead.
const IGNORE_STATE =              ['gb',
                                  ];
// Prefer the municipality name as the city name.
const MUNICPALITY_OVERRIDE =      ['Stutensee',
                                  ];
// Prefer the city district name as the city name.
const CITY_DISTRICT_OVERRIDE =    ['London',
                                  ];
// Prefer suburb name as the city name.
const SUBURB_CITY_NAME_OVERRIDE = ['New York',
                                  ];
// Prefer neighbourhood name over suburbs for the sublocation.
const NEIGHBOURHOOD_OVERRIDE =    ['New York',
                                  ];


$stateService = new StateService(sys_get_temp_dir());
$streamContext = setupContext();

$api = ''; $geonamesQuery = '';
parseRequestUri($api, $geonamesQuery);

handleHeader($api);

if (in_array($api, PASSTHROUGH_APIS))
{
  $api_url = GeoNames::formatQueryUrl($api, $geonamesQuery);
  $response = file_get_contents($api_url, false, $streamContext);
  echo $response;

  return;
}

$lang = getPreferredLanguage($streamContext, $geonamesQuery);
proxyReverseGeocoding($api, $_REQUEST['lat'], $_REQUEST['lng'], $lang);

return;

/*----------------------------------------------------------------------------*/

function handleHeader(string $api): void
{
  if (!in_array($api, NON_XML_APIS))
  {
    header('Content-Type: text/xml;charset=UTF-8');
  }
}

function proxyReverseGeocoding(string $api, float $lat, float $lon, string $lang): void
{
  $lat = round($lat, COORDINATE_ACCURACY);
  $lon = round($lon, COORDINATE_ACCURACY);
  $data = getGeoData($lat, $lon, $lang);

  switch($api)
  {
    case 'findNearbyPlaceName':
      $xml = GeoNames::makePlaceNameXml($data->place, $data->lat, $data->lon,
        $data->country_code, $data->country, $data->distance);
      addRequestsRemaining($xml, $data);
      prettyPrintXml($xml);
      break;
    case 'findNearbyPostalCodes':
      $xml = GeoNames::makePostalCodeXml($data->state, $data->city,
        $data->lat, $data->lon, $data->country_code, $data->distance);
      addRequestsRemaining($xml, $data);
      prettyPrintXml($xml);
      break;
    case 'debug':
      $elapsed = microtime(true) - $_SERVER["REQUEST_TIME_FLOAT"];
      debug(getRawGeoDataCached($lat, $lon, $lang));
      debug($data);
      debug('Request took ' . $elapsed . ' s.');
      break;
  }
  handleRateLimiting($data);
}

function getGeoData(float $lat, float $lon, string $lang)
{
  $rawData = getRawGeoDataCached($lat, $lon, $lang);
  $geoData = mapOpenCageToGeoNames($rawData, $lat, $lon);

  return $geoData;
}

function mapOpenCageToGeoNames($rawData, float $origLat, float $origLon)
{
  $data = new stdClass();
  $data->country_code = $rawData->{'ISO_3166-1_alpha-2'};
  $data->country = $rawData->country;
  $data->state = getState($rawData);
  $data->city = getCity($rawData);
  $data->place = getPlace($rawData);
  $data->lat = $rawData->lat;
  $data->lon = $rawData->lng;
  $data->distance = round(GreatCircle::vincentyGCD($origLat, $origLon, $rawData->lat, $rawData->lng) / 1000,4);
  if (isset($rawData->cached))
  {
    $data->cached = $rawData->cached;
  }
  if (isset($rawData->requests_remaining))
  {
    $data->requests_remaining = $rawData->requests_remaining;
  }
  return $data;
}

function getState($rawData): string
{
  $state = '';
  if (isset($rawData->state) && !in_array($rawData->country_code, IGNORE_STATE))
  {
    $state = $rawData->state;
  } else if (isset($rawData->county))
  {
    $state = $rawData->county;
  } else if (isset($rawData->state_district))
  {
    $state = $rawData->state_district;
  } else if (isset($rawData->city))
  {
    $state = $rawData->city;
  }

  $state = preg_replace('/^Komitat /', '', $state);
  $state = preg_replace('/megye$/', '', $state);

  return $state;
}

function getCity($rawData): string
{
  $city = '';
  if (isset($rawData->municipality) &&
      in_array($rawData->municipality, MUNICPALITY_OVERRIDE))
  {
    $city = $rawData->municipality;
  } else if (isset($rawData->city_district) && isset($rawData->city) &&
      in_array($rawData->city, CITY_DISTRICT_OVERRIDE))
  {
    $city = $rawData->city_district;
  } else if (isset($rawData->suburb) && isset($rawData->city) &&
      in_array($rawData->city, SUBURB_CITY_NAME_OVERRIDE))
  {
    $city = $rawData->suburb;
  } else if (isset($rawData->town))
  {
    $city = $rawData->town;
  } else if (isset($rawData->city))
  {
    $city = $rawData->city;
  } else if (isset($rawData->village))
  {
    $city = $rawData->village;
  } else if (isset($rawData->district))
  {
    $city = $rawData->district;
  } else if (isset($rawData->municipality))
  {
    $city = $rawData->municipality;
  } else if (isset($rawData->county))
  {
    $city = $rawData->county;
  } else {
    $city = getState($rawData);
  }

  $city = preg_replace('/^Straß-Sommerein$/', 'Hegyeshalom', $city);

  return $city;
}

function getPlace($rawData): string
{
  $place = '';
  if (isset($rawData->neighbourhood) && isset($rawData->city) &&
      in_array($rawData->city, NEIGHBOURHOOD_OVERRIDE))
  {
    $place = $rawData->neighbourhood;
  } else if (isset($rawData->suburb))
  {
    $place = $rawData->suburb;
  } else if (isset($rawData->neighbourhood))
  {
    $place = $rawData->neighbourhood;
  } else if (isset($rawData->hamlet))
  {
    $place = $rawData->hamlet;
  } else if (isset($rawData->village))
  {
    $place = $rawData->village;
  } else if (isset($rawData->city_district))
  {
    $place = $rawData->city_district;
  } else if (isset($rawData->district))
  {
    $place = $rawData->district;
  } else if (isset($rawData->town))
  {
    $place = $rawData->town;
  } else if (isset($rawData->city))
  {
    $place = $rawData->city;
  } else {
    $place = getCity($rawData);
  }

  $place = preg_replace('/^KG /', '', $place);
  $place = preg_replace('/^Katastralgemeinde /', '', $place);
  $place = preg_replace('/^Bezirksteil /', '', $place);

  $place = preg_replace('/^Krenfeld$/', 'Kelenföld', $place);

  return $place;
}

function getRawGeoDataCached(float $lat, float $lon, string $lang)
{
  global $stateService;

  $cachedData = $stateService->getState(CACHE_FILE);
  if (!empty($cachedData) && $cachedData['lat'] === $lat &&
      $cachedData['lon'] === $lon && $cachedData['lang'] === $lang)
  {
    $rawData = (object) $cachedData['data'];
    $rawData->cached = true;
  } else {
    $rawData = getRawGeoData($lat, $lon, $lang);
    $toCache = array('data' => $rawData, 'lat' => $lat, 'lon' => $lon, 'lang' => $lang);
    $stateService->saveState(CACHE_FILE, $toCache);
  }
  return $rawData;
}

function getRawGeoData(float $lat, float $lon, string $lang)
{
  $query = $lat . ',' . $lon;
  $geocoder = new Geocoder(OPENCAGE_KEY);
  $opts = ['language' => $lang,
           'no_annotations' => 1,
          ];
  $result = $geocoder->geocode($query, $opts);
  $data = (object) $result['results'][0]['components'];
  $data->lat = $result['results'][0]['geometry']['lat'];
  $data->lng = $result['results'][0]['geometry']['lng'];
  if (array_key_exists('rate', $result))
  {
    $data->requests_remaining = $result['rate']['remaining'];
  }
  return $data;
}

function handleRateLimiting($data = NULL): void
{
  if (isset($data) && isset($data->cached) && $data->cached)
  {
    return;
  }
  $elapsed = microtime(true) - $_SERVER["REQUEST_TIME_FLOAT"];
  $minDuration = 1 / REQUEST_PER_SEC_LIMIT;
  $toSleep = ($minDuration - $elapsed) * 10**6;
  if ($toSleep > 0)
  {
    usleep($toSleep);
  }
}

function getPreferredLanguage($streamContext, string $geonamesQuery): string
{
  $country = GeoNames::getCountry($streamContext, $geonamesQuery);
  if (array_key_exists($country, LANGUAGE_MAPPING))
  {
    return LANGUAGE_MAPPING[$country];
  }
  return LANGUAGE_MAPPING['default'];
}

function parseRequestUri(string &$api, string &$query): void
{
  $uri = parse_url($_SERVER['REQUEST_URI']);
  $path = $uri['path'];
  $api = substr($path, strrpos($path, '/') + 1);
  $query = $uri['query'];
}

function setupContext()
{
  $userAgent = $_SERVER['HTTP_USER_AGENT'];
  $opts = [
      'http' => [
          'method' => 'GET',
          'header' => 'User-Agent: ' . $userAgent
      ],
      'https' => [
          'method' => 'GET',
          'header' => 'User-Agent: ' . $userAgent
      ]
  ];
  return stream_context_create($opts);
}

function addRequestsRemaining(SimpleXMLElement $xml, $geoData): void
{
  if (isset($geoData->requests_remaining))
  {
    $xml->addChild('remaining', $geoData->requests_remaining);
  }
}

function prettyPrintXml(SimpleXMLElement $xml): void
{
  $dom = new DOMDocument("1.0");
  $dom->preserveWhiteSpace = false;
  $dom->formatOutput = true;
  $dom->loadXML($xml->asXML());
  echo $dom->saveXML();
}

function debug($output): void
{
  echo '<pre>'; print_r($output); echo '</pre>';
}
?>
