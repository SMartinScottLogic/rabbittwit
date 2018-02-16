var get_image_id = function(url) {
  var p = url.split('/');
  var f = p[p.length-1].split('.')[0];
  return f;
}

var store_image = function(url, user, mode) {
  var image_id = get_image_id(url);
  print(url + " " + image_id + " " + user['id_str']);

  db.images.insert({'_id':image_id});
  if(mode) {
    db.images.update({'_id':image_id},{'$set':{'url':url},'$unset':{fetched:true},'$addToSet':{'users':user['id_str']}},false,true);
  } else {
    db.images.update({'_id':image_id},{'$set':{'url':url,fetched:true},'$addToSet':{'users':user['id_str']}},false,true);
  }

  user['active'] = true;
  db.users.update({'_id':user['id_str']},{'$set':user},true,false);
}

var store_user = function(user) {
  user['active'] = true;
  db.users.update({'_id':user['id_str']},{'$set':user},true,false);
}

var extract = function(doc) {
  //printjson(doc);
  //printjson(doc['user']['id_str']);
  if(typeof(doc['entities'])!=='undefined' && typeof(doc['entities']['media'])!=='undefined'){
    for(var i in doc['entities']['media']){
      var media=doc['entities']['media'][i];
      if(typeof(media['media_url'])!=='undefined') {
        store_image(media['media_url'], doc['user'], false);
      } else if(typeof(media['media_url_https'])!=='undefined') {
        store_image(media['media_url_https'], doc['user'], false);
      }
    }
  }
  if(typeof(doc['extended_entities'])!=='undefined' && typeof(doc['extended_entities']['media'])!='undefined') {
    for(var i in doc['extended_entities']['media']){
      var media=doc['extended_entities']['media'][i];
      if(typeof(media['media_url'])!=='undefined') {
        store_image(media['media_url'], doc['user'], false);
      } else if(typeof(media['media_url_https'])!=='undefined') {
        store_image(media['media_url_https'], doc['user'], false);
      }
      if(typeof(media['video_info'])!=='undefined' && typeof(media['video_info']['variants'])!=='undefined') {
        for(var j in media['video_info']['variants']) {
          var variant=media['video_info']['variants'][j];
          if(typeof(variant['url'])!=='undefined') {
            store_image(variant['url'], doc['user'], true);
          }
        }
      }
    }
  }
  if(typeof(doc['retweeted_status'])!=='undefined' && typeof(doc['retweeted_status']['entities'])!=='undefined' && typeof(doc['retweeted_status']['entities']['media'])!=='undefined') {
    for(var i in doc['retweeted_status']['entities']['media']){
      var media=doc['retweeted_status']['entities']['media'][i];
      if(typeof(media['media_url'])!=='undefined') {
        store_image(media['media_url'], doc['retweeted_status']['user'], false);
      } else if(typeof(media['media_url_https'])!=='undefined') {
        store_image(media['media_url_https'], doc['retweeted_status']['user'], false);
      }
    }
    store_user(doc['retweeted_status']['user']);
  }
  store_user(doc['user']);
}

db.tweets.find().forEach(extract);
