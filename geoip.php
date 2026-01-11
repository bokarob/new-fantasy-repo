<?php
require_once 'vendor/autoload.php';

use GeoIp2\Database\Reader;

// Path to the GeoLite2 database
$databaseFile = 'vendor/geoip2/geoip2/maxmind-db/GeoLite2-Country.mmdb';

// Create a new GeoIP2 reader instance
$reader = new Reader($databaseFile);

// Get the user's IP address
$userIP = $_SERVER['REMOTE_ADDR'];


// Fallback in case the location is not detected
$language = 'en';

try {
    // Perform the lookup
    $record = $reader->country($userIP);
    $countryCode = $record->country->isoCode;

    // Set language to German if the user is from Germany, Austria, or Switzerland
    if (in_array($countryCode, ['DE', 'AT', 'CH'])) {
        $language = 'de';
    }elseif($countryCode=='HU'){
        $language = 'hu';
    }

} catch (Exception $e) {
    // Handle errors (e.g., IP address not found in the database)
    error_log($e->getMessage());
}

// Now you can load the appropriate language file or redirect the user
switch ($language) {
    case 'de':
        // Load German language resources
        $_SESSION['lang']=3;
        break;
    case 'hu':
        // Load German language resources
        $_SESSION['lang']=1;
        break;
    case 'en':
    default:
        // Load English (default) language resources
        $_SESSION['lang']=2;
        break;
}



