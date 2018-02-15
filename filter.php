<?php

require_once __DIR__ . '/vendor/autoload.php';
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

$connection = new AMQPStreamConnection('twitter_rabbitmq_1', 5672, 'guest', 'guest');
$channel = $connection->channel();
$channel->basic_qos(null, 1, null);
$channel->queue_declare('hello', false, true, false, false);

$channel2 = $connection->channel();
$channel2->basic_qos(null, 1, null);
$channel2->queue_declare('hello2', false, true, false, false);

$callback = function($msg) {
	global $channel;

	echo " [x] Received ", $msg->body, "\n";
	$s = 'Forward - '.$msg->body;
	$msg = new AMQPMessage($s, array('delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT));
	$channel->basic_publish($msg, '', 'hello');
      	echo " [x] Sent '${s}'\n";
};

$channel2->basic_consume('hello2', '', false, true, false, false, $callback);

while(count($channel2->callbacks)) {
    $channel2->wait();
}

$channel2->close();
$channel->close();
$connection2->close();
$connection->close();

?>
