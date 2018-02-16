db.users.find(
  {'$or':[
    {screen_name:'kikidevine'},
    {screen_name:'Lucy_Anne'},
    {screen_name:'Lana_Mode'}
  ]},
  {screen_name:1}
).forEach(
  function(doc) {
    db.images.update({users:doc['_id']},{'$unset':{fetched:true}},false,true);
  });
