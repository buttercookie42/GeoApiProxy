<?php
class GreatCircle
{
  /* Vincenty great circle distance by martinstoeckli,
     from https://www.martinstoeckli.ch/php/php.html#great_circle_dist */
  public static function vincentyGCD(
    float $latitudeFrom, float $longitudeFrom,
    float $latitudeTo, float $longitudeTo, float $earthRadius = 6371000): float
  {
    // convert from degrees to radians
    $latFrom = deg2rad($latitudeFrom);
    $lonFrom = deg2rad($longitudeFrom);
    $latTo = deg2rad($latitudeTo);
    $lonTo = deg2rad($longitudeTo);

    $lonDelta = $lonTo - $lonFrom;
    $a = pow(cos($latTo) * sin($lonDelta), 2) +
      pow(cos($latFrom) * sin($latTo) - sin($latFrom) * cos($latTo) * cos($lonDelta), 2);
    $b = sin($latFrom) * sin($latTo) + cos($latFrom) * cos($latTo) * cos($lonDelta);

    $angle = atan2(sqrt($a), $b);
    return $angle * $earthRadius;
  }
}
?>
