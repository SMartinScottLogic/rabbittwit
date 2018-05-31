<?php
$out_dir = "/";

require_once __DIR__ . '/vendor/autoload.php';
use PhpAmqpLib\Connection\AMQPStreamConnection;

$connection = new AMQPStreamConnection('twitter_rabbitmq_1', 5672, 'guest', 'guest');
$channel = $connection->channel();
$channel->basic_qos(null, 1, null);

$channel->queue_declare('hello', false, true, false, false);

echo ' [*] Waiting for messages. To exit press CTRL+C', "\n";

$callback = function($msg) {
  global $out_dir;

  $body = json_decode($msg->body, TRUE);
  $url = $body['url'];
  $name = basename($url);
  echo " [x] Received ${body['url']} -> ${name}\n";

  $fp = fopen("${out_dir}/${name}", 'w+');

  $ch = curl_init(str_replace(" ", "%20", $url));
  curl_setopt($ch, CURLOPT_TIMEOUT, 50);
  curl_setopt($ch, CURLOPT_FILE, $fp);
  curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

  curl_exec($ch);
  curl_close($ch);
  fclose($fp);
};

$channel->basic_consume('hello', '', false, true, false, false, $callback);

while(count($channel->callbacks)) {
    $channel->wait();
}

$channel->close();
$connection->close();

?>
