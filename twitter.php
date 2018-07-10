<?php

$max_pages = 15;
$page_len = 200;

require_once( dirname(__FILE__) . '/codebird-php/src/codebird.php');
require_once __DIR__ . '/vendor/autoload.php';
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

$connection = new AMQPStreamConnection('twitter_rabbitmq_1', 5672, 'guest', 'guest');
$channel = $connection->channel();
$channel->basic_qos(null, 1, null);

$channel->queue_declare('hello', false, true, false, false);

$Consumer_Key = "siXDhVB0M7zrtRmT0Qc3bQ";
$Consumer_Secret = "2l6QoKPHDedi5ytK7aioLV3sZhPk7xcmQc2QaJUN8";

\Codebird\Codebird::setConsumerKey($Consumer_Key, $Consumer_Secret);
$cb = \Codebird\Codebird::getInstance();
$cb->setUseCurl(false);

//// Public 'Havvoric' Account:
// $User_Token = '86919953-qivtMGvPsOECHQXZaQqDGK0NXHpTihwboWc42Lb9s';
// $User_Secret = '1DEBXSLownX73DbFOzNCZWdTakDqXigAFJEYpZhCGM';

// Private account
$User_Token = '720693365503823872-zlN8e1M3SPTIoD7gteCab12JLQV9rqU';
$User_Secret = 'eRpALhdwA0YRmAIpXXK9B0RDGnu0aguveDTuUwwePQEWi';

$cb->setToken($User_Token, $User_Secret);
$cb->setTimeout(10000);
$cb->setConnectionTimeout(3000);

$since_id = file_get_contents( dirname(__FILE__) . '/highest_id.txt');
if(!$since_id) {
  unset($since_id);
  if(isset($argv[1])) {
    $since_id = $argv[1];
  }
}
if(isset($since_id)) {
  $since_id = trim($since_id);
  fprintf(STDERR, "since_id = '%s'\n", $since_id);
}

function some_callback($tweet)
{
  // gets called for every new streamed message
  // gets called with $message = NULL once per second

  if ($tweet !== null) {
    //print_r($tweet);

    $images = array();
    analyseTweet($images, $tweet);
    foreach($images as $image) {
      upsert_image($image);
    }
    flush();
  }

  // return false to continue streaming
  // return true to close the stream

  // close streaming after 1 minute for this simple sample
  // don't rely on globals in your code!
  if (time() - $GLOBALS['time_start'] >= 60) {
    return true;
  }

  return false;
}

// set the streaming callback in Codebird
$cb->setStreamingCallback('some_callback');

function upsert_image($image) {
  global $channel;

  $s = json_encode($image);
  $msg = new AMQPMessage($s, array('delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT));
  $channel->basic_publish($msg, '', 'hello');
  print "TODO: enqueue: " . print_r($image, TRUE) . "\n";
}

function analyseTweet(&$images, &$tweet) {
  global $highest_id, $lowest_id;
  if(isset($tweet->id)) {
    $id = $tweet->id;
    // print "ID: " . $id . "\n";

    if(!isset($highest_id)) {
      $highest_id = $id;
    } else {
      $highest_id = max($highest_id, $id);
    }
    if(!isset($lowest_id)) {
      $lowest_id = $id;
    } else {
      $lowest_id = min($lowest_id, $id);
    }
  }

  //print $tweet->text . "\n";
  $num_media = 0;
  $num_extended_media=0;
  $num_extended_media_videos = 0;
  $num_retweeted_media=0;
  $num_retweeted_extended_media=0;
  if(isset($tweet->entities) ) {
    if( isset($tweet->entities->media)) {
      $num_media = count($tweet->entities->media);
      foreach($tweet->entities->media as $media) {
        //print_r($media);
        unset($url);
        if(isset($media->media_url)) {
          $url = $media->media_url;
        } else if(isset($media->media_url_https)) {
          $url = $media->media_url_https;
        }
        if(isset($url)) {
          $large_url = $url.':large';
	  $images[$large_url] = array('url' => $large_url, 'user' => $tweet->user->id_str);
          //upsert_image($url.":large", $tweet->user->id_str);
        }
      }
    }
  }
  if(isset($tweet->extended_entities) ) {
    if( isset($tweet->extended_entities->media)) {
      $num_extended_media = count($tweet->extended_entities->media);
      foreach($tweet->extended_entities->media as $media) {
        //print_r($media);
        unset($url);
        if(isset($media->media_url)) {
          $url = $media->media_url;
        } else if(isset($media->media_url_https)) {
          $url = $media->media_url_https;
        }
        if(isset($url)) {
          $large_url = $url.':large';
          $images[$large_url] = array('url' => $large_url, 'user' => $tweet->user->id_str);
          //upsert_image($url.":large", $tweet->user->id_str);
        }
      }
    }
  }

  if(isset($tweet->extended_entities) ) {
    if( isset($tweet->extended_entities->media)) {
      $num_extended_media_videos = 0;
      foreach($tweet->extended_entities->media as $media) {
        //print_r($media);
        if(isset($media->video_info) && isset($media->video_info->variants)) {
          foreach($media->video_info->variants as $variant) {
            unset($url);
            if(isset($variant->url)) {
              $url = $variant->url;
            }
            if(isset($url)) {
              $num_extended_media_videos ++;
              $large_url = $url;
              $images[$large_url] = array('url' => $large_url, 'user' => $tweet->user->id_str);
              //upsert_image($url, $tweet->user->id_str);
            }
          }
        }
      }
    }
  }
  if(isset($tweet->retweeted_status) && isset($tweet->retweeted_status->entities) && isset($tweet->retweeted_status->entities->media)) {
    $num_retweeted_media = count($tweet->retweeted_status->entities->media);
    foreach($tweet->retweeted_status->entities->media as $media) {
      //print_r($media);
      unset($url);
      if(isset($media->media_url)) {
        $url = $media->media_url;
      } else if(isset($media->media_url_https)) {
        $url = $media->media_url_https;
      }
      if(isset($url)) {
        $large_url = $url . ':large';
        $images[$large_url] = array('url' => $large_url, 'user' => $tweet->retweeted_status->user->id_str);
        //upsert_image($url.":large", $tweet->retweeted_status->user->id_str);
      }
    }
  }
  if(isset($tweet->retweeted_status) && isset($tweet->retweeted_status->extended_entities) && isset($tweet->retweeted_status->extended_entities->media)) {
    $num_retweeted_media = count($tweet->retweeted_status->extended_entities->media);
    foreach($tweet->retweeted_status->extended_entities->media as $media) {
      //print_r($media);
      unset($url);
      if(isset($media->media_url)) {
        $url = $media->media_url;
      } else if(isset($media->media_url_https)) {
        $url = $media->media_url_https;
      }
      if(isset($url)) {
        $large_url = $url . ':large';
        $images[$large_url] = array('url' => $large_url, 'user' => $tweet->retweeted_status->user->id_str);
        //upsert_image($url.":large", $tweet->retweeted_status->user->id_str);
      }
    }
  }
  if( $num_media+$num_extended_media+$num_retweeted_media+$num_retweeted_extended_media+$num_extended_media_videos>0) {
    fprintf(STDERR, "%s\t%s\t%s\t%s\t%s\t%s\n", $id, $num_media, $num_extended_media, $num_retweeted_media, $num_retweeted_extended_media, $num_extended_media_videos);
  }
}

function analyseTweets(&$reply) {
  $images = array();
  foreach($reply as $tweet) {
    analyseTweet($images, $tweet);
  }

  foreach($images as $image) {
    upsert_image($image);
  }
}

function rateSafeTimeline(&$params) {
  global $cb;

  while(true) {
    try {
fprintf( STDERR, "Requesting @ %s\n", date('Y-m-d H:i:s O') );
      $reply = (array) $cb->statuses_homeTimeline($params);
fprintf( STDERR, "Request complete @ %s\n", date('Y-m-d H:i:s O') );
      if(isset($reply['errors'])) {
        $message = "";
        foreach($reply['errors'] as $error) {
          $message .= $error->message . " ";
        }
        $message = substr($message, 0, -1);
        throw new Exception($message);
      }
      return $reply;
    } catch(Exception $e) {
      fprintf(STDERR, "%s. Sleeping ... ", $e->getMessage() );
      for($s=0; $s<12; $s++) {
        sleep(5);
        fprintf(STDERR, "+");
      }
      fprintf(STDERR, " done. Trying again.\n");
    }
  }
}

for($page=0; $page<$max_pages; $page++) {
  fprintf(STDERR, "=========== PAGE %d ==========\n", $page+1 );
  $params = array('count'=>$page_len);
  if(isset($lowest_id)) {
    $params['max_id'] = $lowest_id;
    $last_lowest_id = $lowest_id;
  }
  if(isset($since_id)) {
    $params['since_id'] = $since_id;
  }
  //$reply = (array) $cb->statuses_homeTimeline($params);
  $reply = rateSafeTimeline($params);
//print_r($params);
//print_r($reply);
  analyseTweets($reply);
  if(isset($last_lowest_id) && $last_lowest_id==$lowest_id) {
    break;
  }
}

// for canceling, see callback function body
// not considered good practice in real world!
$GLOBALS['time_start'] = time();

// Second, start consuming the stream:
$reply = $cb->user();

$fp = fopen(dirname(__FILE__) . '/highest_id.txt', "w");
if($fp) {
  fprintf($fp, "%s\n", $highest_id);
  fclose($fp);
}
?>
