<?php

require_once( dirname(__FILE__) . '/codebird-php/codebird.php');

$Consumer_Key = "siXDhVB0M7zrtRmT0Qc3bQ";
$Consumer_Secret = "2l6QoKPHDedi5ytK7aioLV3sZhPk7xcmQc2QaJUN8";

Codebird::setConsumerKey($Consumer_Key, $Consumer_Secret);
$cb = Codebird::getInstance();

$User_Token = '86919953-qivtMGvPsOECHQXZaQqDGK0NXHpTihwboWc42Lb9s';
$User_Secret = '1DEBXSLownX73DbFOzNCZWdTakDqXigAFJEYpZhCGM';

$cb->setToken($User_Token, $User_Secret);

$m=new MongoClient();
$t_users = $m->selectCollection('twitter','users');
$t_images = $m->selectCollection('twitter','images');

function get_present_ids() {
  $file = fopen("php://stdin", "r");
  if(!$file) {
    exit(1);
  }

  $lineno = 0;
  $known_ids = array();
  while( ($pathname = fgets($file)) !== false ) {
    $parts = explode("/", $pathname);
    $filename = $parts[count($parts)-1];
    $parts = explode(".", $filename);
    $filename = $parts[0];
    $parts = explode(":", $filename);
    $id = $parts[0];
//    print "{$id}\n";
    if(strlen($id)>0) {
      $known_ids[$id] = $id;
    }
  }
  return $known_ids;
}

function get_associated_users($images) {
  global $t_images;

  $image_users = array();
  $records = $t_images->find();
  foreach($records as $record) {
    if(isset($images[$record['_id']])) {
      $image_users[$record['_id']] = $record['users'];
    }
  }
  return $image_users;
}

function get_wanted_friends($image_users) {
  // Count involvement of each user
  $occurs = array();
  foreach($image_users as $image=>$users) {
    foreach($users as $user) {
      if(isset($occurs[$user])) {
        $occurs[$user] ++;
      } else {
        $occurs[$user] = 1;
      }
    }
  }

  // Pass 1 - images with one user
  $friends = array();
  foreach($image_users as $image=>$users) {
    if(count($users)==1) {
      $user = $users[0];
      if(!isset($friends[$user])) {
        $friends[$user] = 1;
      } else {
        $friends[$user] ++;
      }
    }
  }

  // Pass 2 - image with multiple users
  foreach($image_users as $image=>$users) {
    $match = false;
    $best = null;
    $best_count = 0;
    foreach($users as $user) {
      if($occurs[$user]>$best_count) {
        $best = array($user);
        $best_count = $occurs[$user];
      } else if($occurs[$user] == $best_count) {
        $best[] = $user;
      }
      if(isset($friends[$user])) {
        $match = true;
        $friends[$user] ++;
      }
    }
    if($match == false) {
      foreach($best as $u) {
        $friends[$u] = 1;
      }
    }
  }
  return $friends;
}

function rateSafeLookup($params) {
  global $cb;

  while(true) {
    try {
      $reply = $cb->users_lookup($params);
      if(isset($reply->errors)) {
//print_r($params);
//print_r($reply);
        $message = "";
        foreach($reply->errors as $error) {
          $message .= $error->message . " ";
        }
        $message = substr($message, 0, -1);
        throw new Exception($message);
      }
      return $reply;
    } catch(Exception $e) {
      fprintf(STDERR, "%s. Sleeping ... ", $e->getMessage() );
      for($s=0; $s<12; $s++) {
        sleep(60);
        fprintf(STDERR, "+");
      }
      fprintf(STDERR, " done. Trying again.\n");
    }
  }
}

function lookup_names($unnamed, &$named) {
//  print "Unnamed: " . count($unnamed) . "\n";

  $all_ids = array_chunk(array_keys($unnamed), 75);
  foreach($all_ids as $ids) {
    $params = array('user_id'=>implode(",", $ids));
    $result = rateSafeLookup($params);
    //print_r($result);
    foreach($result as $person) {
      if(gettype($person)=="object") {
        $named[$person->id_str] = array('count'=>$unnamed[$person->id_str], 'name'=>$person->screen_name, 'befriended'=>0);
      }
    }
  }
}

function get_screen_names($friends) {
  global $t_users;

  $names = array();
  $unnamed = array();

  $records = $t_users->find();
  foreach($records as $record) {
    $id = $record['_id'];
    if( isset($friends[$id]) ) {
      if(isset($record['screen_name'])) {
        $names[$id] = array('count'=>$friends[$id], 'name'=>$record['screen_name'], 'befriended'=>0);
      } else {
        $unnamed[$id] = $friends[$id];
      }
    }
  }
  lookup_names($unnamed, $names);
  return $names;
}

function process_friends_list($reply, &$friends) {
  if(!isset($reply->users)) return 0;

  foreach($reply->users as $friend) {
    if(isset($friends[$friend->id_str])) {
      $friends[$friend->id_str]['befriended']=1;
    }
  }
}

function rateSafeFriends($params) {
  global $cb;

  while(true) {
    try {
      $reply = $cb->friends_list($params);
      if(isset($reply->errors)) {
        $message = "";
        foreach($reply->errors as $error) {
          $message .= $error->message . " ";
        }
        $message = substr($message, 0, -1);
        throw new Exception($message);
      }
      return $reply;
    } catch(Exception $e) {
      fprintf(STDERR, "%s. Sleeping ... ", $e->getMessage() );
      for($s=0; $s<12; $s++) {
        sleep(60);
        fprintf(STDERR, "+");
      }
      fprintf(STDERR, " done. Trying again.\n");
    }
  }
}

function update_friends(&$friends) {
  global $cb;

  $result = rateSafeFriends(array());

  $next_cursor = $result->next_cursor;
  process_friends_list($result, $friends);

  while( $next_cursor>0 ) {
    $result = rateSafeFriends('cursor=' . $result->next_cursor_str);
    $next_cursor = $result->next_cursor;
    process_friends_list($result, $friends);
  }
}

fprintf(STDERR, "Get image ids...");
$images = get_present_ids();
fprintf(STDERR, "done.\n");
fprintf(STDERR, "Get associated users...");
$image_users = get_associated_users($images);
fprintf(STDERR, "done.\n");
fprintf(STDERR, "Get users to befriend...");
$friends = get_wanted_friends($image_users);
fprintf(STDERR, "done.\n");
fprintf(STDERR, "Get screen names...");
$names = get_screen_names($friends);
fprintf(STDERR, "done.\n");
fprintf(STDERR, "Get current friends...");
update_friends($names);
fprintf(STDERR, "done.\n");
//print "To befriend: " . count($names) . "\n";
//print_r($names);
foreach($names as $entry) {
  if( !isset($entry['befriended']) || $entry['befriended']==0 ) {
    print $entry['name'] . "\t" . $entry['count'] . "\t" . $entry['befriended'] . "\n";
  }
}
/*
> db.images.find().limit(1)
{ "_id" : "BDNHSZICEAANGiQ", "fetched" : true, "url" : "http://pbs.twimg.com/media/BDNHSZICEAANGiQ.jpg", "users" : [ "474113563", "436166281", "322283224", "984016106" ] }

print_r($ids);
*/
?>
