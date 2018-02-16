<?php
set_time_limit(0);
$fp = fopen(dirname(__FILE__) . '/localfile.tmp', 'w+');

$ch = curl_init(str_replace(" ", "%20", $url));
curl_setopt($ch, CURLOPT_TIMEOUT, 50);
curl_setopt($ch, CURLOPT_FILE, $fp);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

curl_exec($ch);
curl_close($ch);
fclose($fp);
?>
