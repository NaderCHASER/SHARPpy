<?php
$model = escapeshellcmd($_GET['m']);
$run = escapeshellcmd($_GET['r']);
$fh = escapeshellcmd($_GET['fh']);
$id = escapeshellcmd($_GET['id']);


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
