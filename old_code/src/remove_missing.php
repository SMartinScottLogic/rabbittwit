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
$users = $m->selectCollection('twitter','users');

function process_friends_list($reply) {
  global $users;
  if(!isset($reply->users)) return 0;

  foreach($reply->users as $friend) {
    print $friend->screen_name . "\n";
    $friend->active=true;
    $users->update(
      array('_id'=>$friend->id_str),
      array('$set'=>$friend),
      array('upsert'=>true, 'multiple'=>false)
    );
  }
  return count($reply->users);
}

function rateSafeFriends($params) {
  global $cb;

  while(true) {
    try {
      $reply = $cb->friends_list($params);
      if(isset($reply->errors)) {
print_r($params);
print_r($reply);
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

function update_friends() {
  global $users, $cb;
  $users->update(
    array(),
    array('$set'=>array('active'=>false)),
    array('upsert'=>false, 'multiple'=>true)
  );

  $result = rateSafeFriends(array());

  $next_cursor = $result->next_cursor;
  $count = process_friends_list($result);

  while( $next_cursor>0 ) {
    $result = rateSafeFriends('cursor=' . $result->next_cursor_str);
    $next_cursor = $result->next_cursor;
    $count += process_friends_list($result);
  }
  print "Found $count friends.\n";
}

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

function get_drop_users($ids) {
  printf("Getting drop list...");
  $m=new MongoClient();

  $keep = array();
  $collection = $m->selectCollection('twitter','images');
  $cursor = $collection->find();
  foreach($cursor as $entry) {
    if( isset($ids[$entry['_id']]) ) {
      foreach($entry['users'] as $user) {
        $keep[$user]=1;
      }
    }
  }

  $known = 0;
  $total = 0;
  $drop = array();
  $collection = $m->selectCollection('twitter','users');
  $cursor = $collection->find(array('active'=>true));
  foreach($cursor as $entry) {
    if(isset($keep[$entry['_id']])) {
      $known ++;
    } else {
      $drop[$entry['_id']]=$entry;
    }
    $total++;
  }
  printf( "done (%d / %d).\n", $known, $total);
  return $drop;
}

update_friends();
$known_ids = get_present_ids();
$drop = get_drop_users($known_ids);

$i = 0;
print "Drop: " . count($drop) . " users.\n";
foreach($drop as $id=>$entry) {
  $i++;
  print "{$i}/" . count($drop) . "\n";
  print "id_str        : {$entry['id_str']}\n";
  print "screen_name   : {$entry['screen_name']}\n";
  print "  description : {$entry['description']}\n";
  print "  name        : {$entry['name']}\n";
  print "  status      : {$entry['status']['text']}\n";
  print "\n(A)lways friend";
  print "\n(K)eep";
  print "\n(U)nfriend";
  print "\n\n";
  $a = fgets(STDIN);
  $answer=strtolower($a[0]);
  switch($answer) {
    case 'a':
      print "Always friend.";break;
    case 'u':
      print "Unfriend.";break;
    case 'k':
    default: 
      print "Keep.";break;
  }
  print "\n";
}

?>
