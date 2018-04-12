<?php
$model = escapeshellcmd($_GET['m']);
$run = escapeshellcmd($_GET['r']);
$fh = escapeshellcmd($_GET['fh']);

function getClosest($search, $arr) {
    $closest = null;
    $closestItem = null;
    foreach($arr as $item) {
        foreach($item as $key => $value) {
            if($closest == null || abs((float) $search - (float) $closest) > abs((float) $value - (float) $search)) {
                $closest = $value;
                $closestItem = $item;
            }
        }
    }
    return $closestItem;
}

if(isset($_GET['id']) && !empty($_GET['id'])) {
	$id = escapeshellcmd($_GET['id']);
} else {
	$lat = escapeshellcmd($_GET['lat']);
	$lon = escapeshellcmd($_GET['lon']);

	$handle = fopen('/sharppy/datasources/radars.gis', 'r') or die('# Could not figure out lat/lon');

	while (!feof($handle)) {
	    list ($icao,$iata,$synop,$name,$state,$country,$slat,$slon,$elev,$priority,$srcid) = fscanf($handle,"%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s\n");
	    $sites[] = array(
	        'icao' => $icao,
		'srcid' => $srcid,
	        'lat' => $slat,
	        'lon' => $slon
	    );
	}

	$closest = self::getClosest($lat, $sites);

	$close = self::getClosest($lon, $sites);

	if($closest['srcid'] !== $close['srcid']) {
		$closest = $close;
	}
	
	$id = $closest['srcid'];
}

putenv('DISPLAY=:99');
shell_exec("python /sharppy/runsharp/no_gui.py \"$model\" $run $fh $id");

$filePath = "/var/www/data/${model}_${id}_${run}_${fh}.png";

if(file_exists($filePath)) {
        $fp = fopen($filePath, 'rb');

        // send the right headers
        header("Content-Type: image/png");
        header("Content-Length: " . filesize($filePath));

	fpassthru($fp);
} else {
	echo "There was an error generating your sounding.";
}
?>
