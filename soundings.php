<?php
$model = escapeshellcmd($_GET['m']);
$run = escapeshellcmd($_GET['r']);
$fh = escapeshellcmd($_GET['fh']);

function getClosest($searchLat, $searchLon, $arr) {
    $closestLat = null;
    $closestLon = null;
    $closestItem = null;
    foreach($arr as $item) {
            if($closestLat == null || abs((float) $searchLat - (float) $closestLat) > abs((float) $item['lat'] - (float) $searchLat) && abs((float) $searchLon - (float) $closestLon) > abs((float) $item['lon'] - (float) $searchLon)) {
                $closestLat = $item['lat'];
                $closestLon = $item['lon'];
                $closestItem = $item;
            }
    }
    return $closestItem;
}

if(isset($_GET['id']) && !empty($_GET['id'])) {
        $id = escapeshellcmd($_GET['id']);
} else {
        $lat = escapeshellcmd($_GET['lat']);
        $lon = escapeshellcmd($_GET['lon']);
        $sites = [];

        $stationFile = 'spc_ua';
        switch(strtolower($model)) {
                case '3km nam':
                        $stationFile = 'nam3km';
                        break;

                case 'observed':
                        $stationFile = 'spc_ua';
                        break;

                default:
                        $stationFile = strtolower($model);
                        break;
        }

        $handle = fopen('/sharppy/datasources/' . $stationFile . '.csv', 'r') or die('# Could not figure out lat/lon');

        while (!feof($handle)) {
            list ($icao,$iata,$synop,$name,$state,$country,$slat,$slon,$elev,$priority,$srcid) = fgetcsv($handle);
            $sites[] = array(
                'icao' => $icao,
                'srcid' => $srcid,
                'lat' => $slat,
                'lon' => $slon
            );
        }

        $closest = getClosest($lat, $lon, $sites);

        # var_dump($closest);
        # exit;

        $id = $closest['icao'];
}

$filePath = "/var/www/data/${model}_${id}_${run}_${fh}.png";

if(!file_exists($filePath)) {
	putenv('DISPLAY=:99');
	shell_exec("python /sharppy/runsharp/no_gui.py \"$model\" $run $fh $id");
}

if(file_exists($filePath)) {
        $fp = fopen($filePath, 'rb');

        // send the right headers
        header("Content-Type: image/png");
        header("Content-Length: " . filesize($filePath));

        fpassthru($fp);
} else {
        echo $filePath . "<br />";
        echo "There was an error generating your sounding.";
}
?>

