<?php
// config/distance_api.php - DistanceMatrix.ai Configuration

// ============================================
// YOUR API KEY (Get from https://distancematrix.ai/dashboard)
// ============================================
define('DISTANCE_API_KEY', 'p4YMCTEDtj0owcyAyzPuI2Z04TSRyqXfvWY9MyUT2XrwAn1cMLoFzuVqPuyI9YEWt');
define('DISTANCE_URL', 'https://api.distancematrix.ai/maps/api/distancematrix/json');

// ============================================
// GET DISTANCE FROM API
// ============================================
function getDistanceFromAPI($originLat, $originLng, $destLat, $destLng) {
    $url = DISTANCE_URL . "?" . http_build_query([
        'origins' => "{$originLat},{$originLng}",
        'destinations' => "{$destLat},{$destLng}",
        'key' => DISTANCE_API_KEY,
        'units' => 'metric'
    ]);
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code == 200 && $response) {
        $data = json_decode($response, true);
        
        if (isset($data['status']) && $data['status'] == 'OK') {
            $element = $data['rows'][0]['elements'][0];
            if ($element['status'] == 'OK') {
                return [
                    'success' => true,
                    'distance_km' => round($element['distance']['value'] / 1000, 1),
                    'distance_text' => $element['distance']['text'],
                    'duration_min' => round($element['duration']['value'] / 60),
                    'duration_text' => $element['duration']['text']
                ];
            }
        }
    }
    
    return ['success' => false];
}

// ============================================
// HAVERSINE FALLBACK (IF API FAILS)
// ============================================
function calculateHaversine($lat1, $lon1, $lat2, $lon2) {
    $R = 6371; // Earth radius in km
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    $a = sin($dLat/2) * sin($dLat/2) + 
         cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * 
         sin($dLon/2) * sin($dLon/2);
    $c = 2 * atan2(sqrt($a), sqrt(1-$a));
    return round($R * $c, 1);
}

// ============================================
// GET DISTANCE WITH FALLBACK
// ============================================
function getDistance($businessLat, $businessLng, $customerLat, $customerLon) {
    $result = getDistanceFromAPI($businessLat, $businessLng, $customerLat, $customerLon);
    
    if ($result['success']) {
        return $result;
    }
    
    // Fallback to Haversine
    $distance_km = calculateHaversine($businessLat, $businessLng, $customerLat, $customerLon);
    $duration_min = round(($distance_km / 30) * 60);
    
    return [
        'success' => true,
        'distance_km' => $distance_km,
        'distance_text' => $distance_km . ' km',
        'duration_min' => $duration_min,
        'duration_text' => $duration_min . ' min',
        'fallback' => true
    ];
}
?>