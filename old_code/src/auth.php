<?php

require_once( dirname(__FILE__) . '/codebird-php/src/codebird.php');

$Consumer_Key = "siXDhVB0M7zrtRmT0Qc3bQ";
$Consumer_Secret = "2l6QoKPHDedi5ytK7aioLV3sZhPk7xcmQc2QaJUN8";

\Codebird\Codebird::setConsumerKey($Consumer_Key, $Consumer_Secret);
$cb = \Codebird\Codebird::getInstance();
$cb->setUseCurl(false);

$cb->setTimeout(10000);
$cb->setConnectionTimeout(3000);

session_start();
$reply = $cb->oauth_requestToken(array(
   'oauth_callback' => 'oob'
));
// store the token
$cb->setToken($reply->oauth_token, $reply->oauth_token_secret);
$_SESSION['oauth_token'] = $reply->oauth_token;
$_SESSION['oauth_token_secret'] = $reply->oauth_token_secret;
$_SESSION['oauth_verify'] = true;

// redirect to auth website
$auth_url = $cb->oauth_authorize();
print $auth_url . "\n";

print "Enter PIN: ";
$pin = trim(fgets(STDIN));

// get the access token
$reply = $cb->oauth_accessToken([
  'oauth_verifier' => $pin
]);

print_r($reply);
