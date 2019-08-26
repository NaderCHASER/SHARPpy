<?php
$model = escapeshellcmd($_GET['m']);
$run = escapeshellcmd($_GET['r']);
$fh = escapeshellcmd($_GET['fh']);

function distance($a, $b)
{
    list($lat1, $lon1) = array($a['lat'], $a['lon']);
    list($lat2, $lon2) = $b;

    $theta = $lon1 - $lon2;
    $dist = sin(deg2rad($lat1)) * sin(deg2rad($lat2)) +  cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * cos(deg2rad($theta));
    $dist = acos($dist);
    $dist = rad2deg($dist);
    $miles = $dist * 60 * 1.1515;
    return $miles;
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
	    if ($icao == "icao") {
		continue;
	    }
            $sites[] = array(
                'icao' => $icao,
                'srcid' => $srcid,
                'lat' => $slat,
                'lon' => $slon
            );
        }

	$ref = array($lat, $lon);

	$distances = array_map(function($item) use($ref) {
		$a = array_slice($item, -2);
		return distance($a, $ref);
	}, $sites);

	asort($distances);

        $closest = $sites[key($distances)];

        $id = $closest['icao'];
}

$filePath = "/var/www/data/${model}_${id}_${run}_${fh}.png";

if(!file_exists($filePath)) {
	putenv('DISPLAY=:99');
	$command = "python /sharppy/runsharp/no_gui.py \"$model\" $run $fh $id";
	shell_exec($command);
}

if(file_exists($filePath)) {
        $fp = fopen($filePath, 'rb');

        // send the right headers
        header("Content-Type: image/png");
        header("Content-Length: " . filesize($filePath));

        fpassthru($fp);
} else {
	http_response_code(404);
        echo $filePath . "<br />";
        echo "There was an error generating your sounding.<br />";
}
?>
