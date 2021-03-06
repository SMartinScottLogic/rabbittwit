<?php

$max_pages = 15;
$page_len = 200;
$mongo_host = '192.168.1.10';

$m=new MongoClient("mongodb://{$mongo_host}:27017");
$collection = $m->selectCollection('twitter','tweets');
$images = $m->selectCollection('twitter','images');
$users = $m->selectCollection('twitter','users');

require_once( dirname(__FILE__) . '/codebird-php/src/codebird.php');

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

function get_image_id($url) {
  $parts = explode("/", $url);
  $filename = $parts[count($parts)-1];
  $parts = explode(".", $filename);
  $filename = $parts[0];
  $parts = explode(":", $filename);
  $id = $parts[0];
  return $id;
}

function upsert_image($url, $user) {
  global $images, $users;

  $image_id = get_image_id($url);

  $images->update(
    array('_id'=>$image_id ), 
    array(
      '$set'=>array('url'=>$url), 
      '$addToSet'=>array('users'=>$user) 
    ), 
    array('upsert'=>true, 'multiple'=>false)
  );
  $users->update(
    array('_id'=>$user),
    array('$set'=>array('active'=>true)),
    array('upsert'=>true, 'multiple'=>false)
  );
}

/* gets the data from a URL */
function produce_fetchlist() {
  global $images;

  $query = array('fetched'=>array('$exists'=>false));
  $cursor = $images->find($query);
  $cursor->timeout(120000);
  foreach($cursor as $entry) {
    print $entry['url'] . "\n";
  }
  $images->update($query, array('$set'=>array('fetched'=>true)), array('upsert'=>false, 'multiple'=>true, 'wTimeoutMS'=>120000, 'timeout'=>120000));
  return;
  $ch = curl_init();
  $timeout = 5;
  $userAgent = 'Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; .NET CLR 1.1.4322)';

  curl_setopt($ch, CURLOPT_URL, $url);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
  curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
  curl_setopt($ch, CURLOPT_USERAGENT, $userAgent);
  curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
  curl_setopt($ch, CURLOPT_AUTOREFERER, true);

  $data = curl_exec($ch);
  curl_close($ch);
  return $data;
}

function analyseTweets(&$reply) {
  global $highest_id, $lowest_id;
  global $collection;

  foreach($reply as $tweet) {
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
      try {
        $collection->insert($tweet, array("w" => 1));
      } catch(Exception $e) {
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
            upsert_image($url.":large", $tweet->user->id_str);
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
            upsert_image($url.":large", $tweet->user->id_str);
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
                upsert_image($url, $tweet->user->id_str);
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
          upsert_image($url.":large", $tweet->retweeted_status->user->id_str);
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
          upsert_image($url.":large", $tweet->retweeted_status->user->id_str);
        }
      }
    }
    if( $num_media+$num_extended_media+$num_retweeted_media+$num_retweeted_extended_media+$num_extended_media_videos>0) {
      fprintf(STDERR, "%s\t%s\t%s\t%s\t%s\t%s\n", $id, $num_media, $num_extended_media, $num_retweeted_media, $num_retweeted_extended_media, $num_extended_media_videos);
    }
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
$fp = fopen(dirname(__FILE__) . '/highest_id.txt', "w");
if($fp) {
  fprintf($fp, "%s\n", $highest_id);
  fclose($fp);
}
produce_fetchlist();
//print_r($reply);
?>
