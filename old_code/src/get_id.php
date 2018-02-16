<?php

$file = fopen("php://stdin", "r");
if(!$file) {
  exit(1);
}

while( ($pathname = fgets($file)) !== false ) {
  $parts = explode("/", $pathname);
  $filename = $parts[count($parts)-1];
  $parts = explode(".", $filename);
  $filename = $parts[0];
  $parts = explode(":", $filename);
  $id = $parts[0];
  print "{$pathname} {$id}\n";
}
?>
