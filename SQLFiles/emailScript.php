#!/usr/bin/php
<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

require('./PHPMailer-master/PHPMailer-master/src/PHPMailer.php');
require('./PHPMailer-master/PHPMailer-master/src/Exception.php');
require('./PHPMailer-master/PHPMailer-master/src/SMTP.php');

$servername = "127.0.0.1";
$username = "dran";
$password = "pharmacy";
$dbname = "animeDatabase";
$tablename = "anime";
$conn = mysqli_connect($servername, $username, $password, $dbname);
    
// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
    //error message for logger
  }
  
$sql = "SELECT email FROM Users";
$result = $conn -> query($sql);
$conn -> close();
print_r($sql);



//deal with API Call

$today = date('l');
//basic get top anime query
$data_json = file_get_contents("https://api.jikan.moe/v4/schedules?filter={$today}");
$arr = json_decode($data_json, true);

//exec("php DMZPublish.php https://api.jikan.moe/v4/schedules?filter={$today} email");



$mail = new PHPMailer(true);

$mail->IsSMTP(); // enable SMTP

$mail->SMTPOptions = array(
   'ssl' => array(
       'verify_peer' => false,
       'verify_peer_name' => false,
       'allow_self_signed' => true
   )
);

$mail->SMTPDebug = 1; // debugging: 1 = errors and messages, 2 = messages only
$mail->SMTPAuth = true; // authentication enabled
$mail->SMTPSecure = 'ssl'; // secure transfer enabled REQUIRED for Gmail
$mail->Host = "smtp.gmail.com";
$mail->Port = 465; // or 587
$mail->IsHTML(true);
$mail->Username = "myAnimeDatabaseEmail";
$mail->Password = "Wordpass1";
$mail->SetFrom("myAnimeDatabaseEmail@gmail.com");
$mail->Subject = "Your Daily Anime Schedule!";

$bodyTemp = "<h1>Heres the schedule for today:</h1> <br/>";
//populate message body
foreach ($arr['data'] as $anime)
        {
			// set up each anime's array variables
			$img = $anime['images']['jpg']['image_url'];
			$title = $anime['title']; 
			
			
			$bodyTemp = $bodyTemp . "<br />" . $title;
			
			
			
            //print_r($anime);
			
        }	
$mail->Body = $bodyTemp;
//add me there so i can make sure it works
$mail->AddAddress("db488@njit.edu");
//loop to add all email addresses in db

while($row  = $result->fetch_array(MYSQLI_ASSOC))
    {
      $mail->AddAddress($row["email"]);
    }


 if(!$mail->Send()) {
    echo "Mailer Error: " . $mail->ErrorInfo . PHP_EOL;
    exit();
 } else {
    echo "Message has been sent" . PHP_EOL;
    exit();
 }
 
?>