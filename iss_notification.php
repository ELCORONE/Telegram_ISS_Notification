<?php
// ISS Notification by ELCORONE 2024

// CONFIGURATION API KEY

// SET N2YO API KEY
define('SATELITE_TOKEN', '******-******-******-******');
define('N2YO_API','https://api.n2yo.com/rest/v1/satellite/positions/');

//SET TELEGRAM API KEY
define('TELEGRAM_TOKEN', 'bot**********************************************');
define('TG_API', 'https://api.telegram.org/');

// Number of positions of the ISS in the future.
$check_count		= 60;			// Maximum = 300

// User's setting
$targetLatitude		= 55.7558;		// User's latitude
$targetLongitude	= 37.6177;		// User's longitude
$user_id			= *********;	// User's telegram ID

// Function of sending data to the Telegram server
function RequestAPI($url){
	$ch = curl_init();
	curl_setopt_array ($ch, array(CURLOPT_URL => $url, CURLOPT_RETURNTRANSFER => true)); 
    curl_setopt($ch, CURLOPT_HEADER, false);  
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
	curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
    $response = curl_exec($ch);
	if ($response === FALSE) $response = "cURL Error: " . curl_error($ch);		
	curl_close($ch); 
	return $response;
}
// Data packet generation function for Telegram
function sendMessage($user_id,$msg) {	
	$url = TG_API.TELEGRAM_TOKEN."/sendMessage?chat_id=".$user_id."&text=".urlencode($msg);
	RequestAPI($url);
}

// Receiving a JSON request for positions from the API
$url	= N2YO_API.'25544/43.7976/131.9356/0/'.$check_count.'/&apiKey='.SATELITE_TOKEN;
$json	= file_get_contents($url);
$data	= json_decode($json, true);

// Function for calculating the distance between two points on the earth's surface
function calculateDistance($lat1, $lon1, $lat2, $lon2) {
    $earthRadius = 6371; // Radius of the Earth
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    $a = sin($dLat / 2) * sin($dLat / 2) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) * sin($dLon / 2);
    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
    $distance_result = $earthRadius * $c;
    return $distance_result;
}
// A loop in which future positions are checked alternately with a given position
for($check = 0;$check < $check_count;$check++){
	// ↓ Read the Readme about the need for this. Do not delete!
	$timer = time() - filemtime('mks.txt');
	if($timer < 600) exit;
	// ↑ Read the Readme about the need for this. Do not delete!
	
	// ISS position
	$issPositionData = $data['positions'][$check];
	$currentLongitude = $issPositionData['satlongitude'];
	$currentLatitude = $issPositionData['satlatitude'];
	
	// Calculation of the distance between the current position of the ISS and given coordinates
	$distanceToTarget = calculateDistance($currentLatitude, $currentLongitude, $targetLatitude, $targetLongitude);

	// Results
	$distance = round($distanceToTarget, 2);
	$issDate = date('H:i:s',$issPositionData['timestamp']);
	$issAzimuth = $issPositionData['azimuth'];

	// Generating a proximity notification
	// ISS visibility in a radius of approximately 2300-2500
	if($distance < 2500) {
		$rLat = $targetLatitude - $currentLatitude;
		$rLon = $targetLongitude - $currentLongitude;
		// Protection against false positives. The difference in latitude and longitude is no more than 20.
		// You can play with the values
		if(abs($rLon) < 20 && abs($rLat) < 15) {
			// Write to a log file. Read the Readme about the need for this. Do not delete!
			// You can replace the line written to the file with your own.
			file_put_contents('mks.txt',"The ISS will fly by at  $issDate. Azimuth: $issAzimuth. Debug: $check".PHP_EOL,FILE_APPEND);
			// Telegram notification
			sendMessage($user_id,"🛰The ISS will fly by at  $issDate. Azimuth: $issAzimuth. $currentLongitude/$currentLatitude");
		}
		exit;
	}
}
?>
