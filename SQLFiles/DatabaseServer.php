#!/usr/bin/php
<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');

require('DMZPublish.php');

//SQL Connection Parameters
$hostSQL = 'localhost';
$userSQL = 'dran';
$passSQL = 'pharmacy';
$dbSQL = 'animeDatabase';

function changeAnimeRating($array){
  global $hostSQL, $userSQL, $passSQL, $dbSQL;
  $mysql = new mysqli($hostSQL, $userSQL, $passSQL, $dbSQL);
  $stmt = $mysql->prepare("UPDATE anime SET userRatings = "  . $array['value'] . " WHERE mal_id = " . $array['mal_id'] . ";");
  $stmt->execute();
  $mysql->close();
  return 1;
}

function searchAnime($array){
  global $hostSQL, $userSQL, $passSQL, $dbSQL;
  $mysql = new mysqli($hostSQL, $userSQL, $passSQL, $dbSQL);
  if ($mysql -> connect_errno){
      return "Could not connect to mysql: ". $mysql->connect_error;
      exit();
  }
  $query = "SELECT * FROM anime WHERE title LIKE '%" . $array['title'] . "%' LIMIT 50;";
  $result = $mysql->query($query);
  $mysql->close();
  $anime = array();
  foreach ($result as $row){
    echo "Found: " . PHP_EOL;
    print_r($row);
    array_push($anime, $row);
  }
  return $anime;
}

//Fetching anime for template.php
function fetchAnime($array){
  global $hostSQL, $userSQL, $passSQL, $dbSQL;
  //Establishing connection
  $mysql = new mysqli($hostSQL, $userSQL, $passSQL, $dbSQL);
  if ($mysql -> connect_errno){
      return "Could not connect to mysql: ". $mysql->connect_error;
      exit();
  }
  //Select anime by mal_id
  $query = "SELECT * FROM anime WHERE mal_id = " . $array['mal_id']. ";";
  $result = $mysql->query($query);
  $anime = $result->fetch_row();
  $mysql->close();
  return $anime;
}

//For SQL to ask for anime from API
function insertAnime($array){
  global $hostSQL, $userSQL, $passSQL, $dbSQL;
  //Establish Connection
  $mysql = new mysqli($hostSQL, $userSQL, $passSQL, $dbSQL);
  if ($mysql -> connect_errno){
      return "Could not connect to mysql: ". $mysql->connect_error;
      exit();
  }
  
 //Preparing statement and binding the parameters
  $stmt = $mysql->prepare("INSERT INTO anime (mal_id, title, img, rating, genre, trailer, synopsis) VALUES (?, ?, ?, ?, ?, ?, ?)");
  $stmt->bind_param('sssssss' ,$array['mal_id'], $array['title'], $array['img'], $array['rating'], $array['genre'], $array['trailer'], $array['synopsis']);
  //Returns 1 if the anime is put into databse. Otherwise, return error
  if($stmt->execute()){
    $mysql->close();
    return 1;
  }else{
    $error = $stmt->error;
    $mysql->close();
    return $error;
  }
}

//For Logging in to website by queuering User Table
function sqlLogIn($array){
  global $hostSQL, $userSQL, $passSQL, $dbSQL;
  //Establishing connection
  $mysql = new mysqli($hostSQL, $userSQL, $passSQL, $dbSQL);
  if ($mysql -> connect_errno){
      return "Could not connect to mysql: ". $mysql->connect_error;
      exit();
  }
  //Select Query
  $query = "SELECT username, password FROM Users WHERE username = '" . $array['username'] . "';";
  //Executing Query
  $result = $mysql->query($query);
  //Retrieving Row
  $logInInfo = $result->fetch_row();
  $mysql->close();
  return $logInInfo;
}

//For signing up from website and putting credentials into Database
function sqlSignUp($array){

  global $hostSQL, $userSQL, $passSQL, $dbSQL;
  //Establishing connection
  $mysql = new mysqli($hostSQL, $userSQL, $passSQL, $dbSQL);
    if ($mysql -> connect_errno){
        return "Could not connect to mysql: ". $mysql->connect_error;
        exit();
    }
  //Preparing statement and binding parameters
  $stmt = $mysql->prepare("INSERT INTO Users (username, password, firstName, lastName, email) VALUES (?,?,?,?,?)");
  $stmt->bind_param('sssss', $array['username'], $array['password'], $array['firstName'], $array['lastName'], $array['email']);
  //If executed correctly, return 1. Else, return the statement error
  if($stmt->execute()){
    $mysql->close();
    return 1;
  }else{
    $error = $stmt->error;
    $mysql->close();
    return $error;
  }

}

//add thread to forum
function addThread ($array) {  //Establishing connection
  $mysql = new mysqli('localhost', 'gds25', 'Roseli1975', 'animeDatabase');

    if ($mysql -> connect_errno){
        return "Could not connect to mysql: ". $mysql->connect_error;
        exit();
    }

  $stmt = $mysql->prepare("insert into threads (thread_id, thread_title, thread_time, thread_owner) values (?, ?, NOW(), ?)");
  $id = '';
  $stmt->bind_param("sss", $id, $array['thread_title'], $array['username']);

  //$stmt->bind_param(":thread_id", $id);
  //$stmt->bind_param("thread_title", $array['thread_title']);
  //$stmt->bind_param(":thread_owner", $array['username']);

  if($stmt->execute()){ 
    $stmt->store_result();	  
    $query = "SELECT thread_id FROM threads ORDER BY thread_time desc LIMIT 1;";
    $result = $mysql->query($query);
    $get_result = $result->fetch_row();
    $array['thread_id'] = $get_result[0];	  
    
    $mysql->close();
    return addPost($array);
  }
  else{
    return $stmt->error;
  }

}

//get and list all threads in forum
function showThreads ($array) {

 $mysql = new mysqli('localhost', 'gds25', 'Roseli1975', 'animeDatabase');

    if ($mysql -> connect_errno){
        return "Could not connect to mysql: ". $mysql->connect_error;
        exit();
    }
  $query = "select thread_id, thread_title, date_format(thread_time, '%b %e %Y at %r') as fmt_thread_time, thread_owner from threads order by thread_time desc;";

  //Executing Query
 $result = $mysql->query($query);

 $query_count = "select count(thread_id) from threads;";
 $result_count = $mysql->query($query_count);
 $string_count = $result_count->fetch_row();

 if ($string_count[0]==0) {
	 echo $string_count;
	  //there are no topics
     $mysql->close();
     return "<P><em>No threads exist.</em></p>";
  }

 $html = "
    <table cellpadding=3 cellspacing=1 border=1>
    <tr>
    <th>TOPIC TITLE</th>
    <th># of POSTS</th>
    </tr>";

  foreach ($result as $row) {
        $thread_id = $row['thread_id'];
        $thread_title = stripslashes($row['thread_title']);
        $thread_time = $row['fmt_thread_time'];
        $thread_owner = stripslashes($row['thread_owner']);
 
        //get number of posts
        $posts = "select count(post_id) from posts
             where thread_id = $thread_id";
        $num_posts = $mysql->query($posts);
        $string_posts = $num_posts->fetch_row();
		//add to display
        $html .= "
        <tr>
        <td><a href=\"threads.php?thread_id=$thread_id\">
        <strong>$thread_title</strong></a><br>
        Created on $thread_time by $thread_owner</td>
        <td align=center>$string_posts[0]</td>
        </tr>";
    }
 $html .= "</table";
 $mysql->close();
 return $html;

}

//adding posts to forum thread
function addPost ($array) {
  $mysql = new mysqli('localhost', 'gds25', 'Roseli1975', 'animeDatabase');

    if ($mysql -> connect_errno){
        return "Could not connect to mysql: ". $mysql->connect_error;
        exit();
    }
    $stmt = $mysql->prepare("insert into posts (post_id, thread_id, post_content, post_time, post_owner) values (?, ?, ?, NOW(), ?)");
    $id = '';
    $stmt->bind_param("ssss", $id, $array['thread_id'], $array['post_content'], $array['username']);
   
    if($stmt->execute()){
	 $mysql->close();
         if($array['type'] == 'addThread'){
            return showThreads($array);
	 } 
	 else if ($array['type'] == 'addPost'){
	    return showPosts($array);
	 }
    }	 
    else{
	$mysql->close();
        return $stmt->error;
    }


}

//get and list all posts in thread
function showPosts ($array) {
  $mysql = new mysqli('localhost', 'gds25', 'Roseli1975', 'animeDatabase');

    if ($mysql -> connect_errno){
        return "Could not connect to mysql: ". $mysql->connect_error;
        exit();
    }
	
   $query = "select thread_title from threads where thread_id = '" . $array['thread_id'] . "';";

   //Executing Query
  $result = $mysql->query($query);
  if (mysqli_num_rows($result) < 1) {
    //thread doesn't exist
    return "<P><em>Thread does not exist</em></p>";
  }
  $title = $result->fetch_row();
  
    //create the display string
    $thread_name = stripslashes($title[0]);
    $posts_query = "select post_id, post_content, date_format(post_time, '%b %e %Y at %r') as fmt_post_time, post_owner from posts where thread_id = '" . $array['thread_id'] . "' order by post_time desc;";

    $posts_result = $mysql->query($posts_query); // get the mysqli result

    $thread = "<strong>$thread_name</strong> <table width=100% cellpadding=3 cellspacing=1 border=1> 
            <tr><th>AUTHOR</th><th>POST</th></tr>";


    foreach ($posts_result as $row) {
        $post_id = $row['post_id'];
        $post_content = stripslashes($row['post_content']);
        $post_time = $row['fmt_post_time'];
        $post_owner = stripslashes($row['post_owner']);

        //add to display
        $thread .= "
        <tr>
        <td width=35% valign=top>$post_owner<br>[$post_time]</td>
        <td width=65% valign=top>$post_content<br><br>
        </td>
        </tr>";


    }
  $thread .= "</table>";
  $mysql->close();  
  return $thread;
}

//todo: add review (same code as addPosts, just a different database)
function addReview () {

}

//todo: list all reviews (same code as showPosts)
function showReviews () {

}

//Processes request from RabbitMQ Publisher
function requestProcessor($array) {
  if($array['type'] == 'changeRating'){
    echo "Changing rating for " . $array['mal_id'];
    print_r($array);
    return changeAnimeRating($array);
  }

  if($array['type'] == 'searchAnime'){
    echo "Searching for: " . PHP_EOL;
    print_r($array);
    DMZPublish('https://api.jikan.moe/v4/anime?q=' . urlencode($array['title']));
    $anime = searchAnime($array);
    if(!$anime){
      return  "No anime found";
    }else{
      return $anime;
    }
  }

  //Fetching anime for Template.php
  if($array['type'] == 'fetchAnime'){
    echo "Fetching: " . PHP_EOL;
    print_r($array);
    return fetchAnime($array);
  }

  //For login
  if($array['type'] == 'login'){
      print_r($array);
      echo "Logging in" . PHP_EOL;
      return sqlLogIn($array);
  }

  //For signup
  if($array['type'] == 'signup'){
      print_r($array);
      echo "Signing up" . PHP_EOL;
      return sqlSignUp($array);
  }

  //for populating and listing forum/threads
  if($array['type'] == 'addThread'){
    echo "Add thread to forum" . PHP_EOL;
    return addThread($array);
  }

  if($array['type'] == 'showThreads'){
    echo "Show all threads in forum" . PHP_EOL;
    return showThreads($array);
  }

  if($array['type'] == 'addPost'){
    echo "Add post to thread" . PHP_EOL;
    return addPost($array);
  }

  if($array['type'] == 'showPosts'){
    echo "Show all posts in threads" . PHP_EOL;
    return showPosts($array);
  }
  
}

//Establishing rabbitMQ Server
$server = new rabbitMQServer("testRabbitMQ.ini","testServer");

echo "SQL Server BEGIN".PHP_EOL;
$server->process_requests('requestProcessor');
echo "testRabbitMQServer END".PHP_EOL;
exit();

?>

