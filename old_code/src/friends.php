<?php

require_once( dirname(__FILE__) . '/codebird-php/codebird.php');

$Consumer_Key = "siXDhVB0M7zrtRmT0Qc3bQ";
$Consumer_Secret = "2l6QoKPHDedi5ytK7aioLV3sZhPk7xcmQc2QaJUN8";

Codebird::setConsumerKey($Consumer_Key, $Consumer_Secret);
$cb = Codebird::getInstance();

$User_Token = '86919953-qivtMGvPsOECHQXZaQqDGK0NXHpTihwboWc42Lb9s';
$User_Secret = '1DEBXSLownX73DbFOzNCZWdTakDqXigAFJEYpZhCGM';

$cb->setToken($User_Token, $User_Secret);

function process_friends(&$reply) {
  if(!isset($reply->users)) return;
  print "================================================\n";
  print_r($reply);
  print "================================================\n";

  foreach($reply->users as $friend) {
    print_r($friend);
  }
}

$result = $cb->friends_list();
process_friends($result);

$nextCursor = $result->next_cursor_str;
while ($nextCursor > 0) {
  $result = $cb->followers_list('cursor=' . $nextCursor);
  process_friends($result);
}
?>
