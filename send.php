<?php

require_once __DIR__ . '/vendor/autoload.php';
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

$connection = new AMQPStreamConnection('twitter_rabbitmq_1', 5672, 'guest', 'guest');
$channel = $connection->channel();
$channel->basic_qos(null, 1, null);

$channel->queue_declare('hello', false, true, false, false);

for($i = 0; $i < 10; $i ++) {
	$s = 'Hello World #'.$i;
	$msg = new AMQPMessage($s, array('delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT));
	$channel->basic_publish($msg, '', 'hello');

	echo " [x] Sent '${s}'\n";
	sleep(3);
}

$channel->close();
$connection->close();

?>
