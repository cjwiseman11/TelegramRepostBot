<?php

function connectToDatabase(){
    $root = $_SERVER['DOCUMENT_ROOT'];
    $config = parse_ini_file($root . '/../configTelegram.ini');
    $user = $config['username'];
    $pass = $config['password'];
    $dbname = $config['dbname'];
    $db = new PDO("mysql:host=localhost;dbname=$dbname",$user,$pass);
    $db->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
    return $db;
}

function getLastUpdate(){
	$db = connectToDatabase();
	$statement = $db->prepare("SELECT * FROM `telegramBotFeed` WHERE `id` = 1");
	$statement->execute();
	$row = $statement->fetchAll();
	return $row[0]["lastupdate"];
}

function getRepostStats($chat_id){
	$db = connectToDatabase();
	$statement = $db->prepare("SELECT * FROM `stats` WHERE `chatid` = :chatid ORDER BY reposts DESC");
	$statement->execute(array(':chatid' => $chat_id));
	$row = $statement->fetchAll();
	return $row;
}

function urlRepostCheck($extracted_link, $chat_id){
	$db = connectToDatabase();
	$statement = $db->prepare("SELECT * FROM `urls` WHERE `url` = :extractedlink AND `chatid` = :chatid");
	$statement->execute(array(':extractedlink' => $extracted_link, ':chatid' => $chat_id));
	$row = $statement->fetchAll();
	return $row[0];
}

function checkRepostStats($type, $firstname, $chat_id){
	$db = connectToDatabase();
	if($type == "exist"){
		$statement = $db->prepare("SELECT * FROM `stats` WHERE `firstname` = :firstname AND `chatid` = :chatid");
		$statement->execute(array(':firstname' => $firstname, ':chatid' => $chat_id));
		$row = $statement->fetchAll();
		return $row[0];
	} else if($type == "insert"){
		$statement = $db->prepare("INSERT INTO `stats`(`chatid`, `firstname`, `reposts`) VALUES (:chatid,:firstname,1)");
		$statement->execute(array(':firstname' => $firstname, ':chatid' => $chat_id));
	} else if($type == "update"){
		$statement = $db->prepare("UPDATE `stats` SET `reposts`= reposts+1 WHERE `firstname` = :firstname AND `chatid` = :chatid");
		$statement->execute(array(':firstname' => $firstname, ':chatid' => $chat_id));
	}
}

function addUrlToDB($extracted_link, $firstname, $messageid, $chat_id){
	$db = connectToDatabase();
	$statement = $db->prepare("INSERT INTO `urls` (`url`, `firstname`,`messageid`,`chatid`) VALUES (:extractedlink, :firstname, :messageid, :chatid)");
	$statement->execute(array(':extractedlink' => $extracted_link, ':firstname' => $firstname, ':messageid' => $messageid, ':chatid' => $chat_id));
}

function renewLastUpdate($last_update){
	$db = connectToDatabase();
	$statement = $db->prepare("UPDATE `telegramBotFeed` SET `lastupdate`= :lastupdate WHERE `id` = 1");
	$statement->execute(array(':lastupdate' => $last_update));
}

$root = $_SERVER['DOCUMENT_ROOT'];
$botconfig = parse_ini_file($root . '/../configTelegram.ini');

$bot_id = $botconfig['botid'];
$last_update = getLastUpdate($bot_id);

echo "<br>Before: $last_update";

$website = "https://api.telegram.org/bot".$bot_id;
$update = file_get_contents('php://input');

$result = json_decode($update, TRUE);

//Check each message
$received_message = $result["message"]["text"];

//Check if message is newer than last_update
if ($last_update<$result["update_id"]){

  $chat_id = $result["message"]["chat"]["id"];

	if($received_message == "/repoststats"){
	    $repost_stats = getRepostStats($chat_id);
	    $i=1;
	    foreach($repost_stats as $row3){
	        $message = $message . "%0A" . "$i. " . $row3["firstname"] .": ". $row3["reposts"];
	        $i++;
	    }
			//Send Message
	    file_get_contents('https://api.telegram.org/bot' . $bot_id . '/sendMessage?text='.$message.'&chat_id='.$chat_id);
	}


	$url_check = $result["message"]["entities"][0]["type"];
	if($url_check == "url"){

		//TODO: Utilise url entity when available
		/*$url_exists = $result["message"]["entities"][0]["url"];

		if($url_exists != "" || $url_exists != null){
				$extracted_link = $url_exists;
		}*/

		//preg_match_all('#(www\.|https?:\/\/)?[a-zA-Z0-9]{2,}\.[a-zA-Z0-9]{2,}(\S*)#i', , $matches); Old Match
		preg_match_all('#\bhttps?://[^,\s()<>]+(?:\([\w\d]+\)|([^,[:punct:]\s]|/))#', $received_message, $matches);

		file_get_contents('https://api.telegram.org/bot' . $bot_id . '/sendMessage?text='.$url_exists.'&chat_id='.$chat_id.'&reply_to_message_id='.$original_msg_id);
		$uniquematch = array_unique($matches[0]);
		foreach($uniquematch as $extracted_link){

      $url_repost = urlRepostCheck($extracted_link, $chat_id);

      $firstname = $result["message"]["from"]["first_name"];
      $messageid = $result["message"]["message_id"];

      if($url_repost){
        $message = "wowow repost lul";
        $original_msg_id = $url_repost["messageid"];
        file_get_contents('https://api.telegram.org/bot' . $bot_id . '/sendMessage?text='.$message.'&chat_id='.$chat_id.'&reply_to_message_id='.$original_msg_id);

        $repost_stat_check = checkRepostStats("exist", $firstname, $chat_id);
				if($repost_stat_check){
					checkRepostStats("update", $firstname, $chat_id);
        } else {
            checkRepostStats("insert", $firstname, $chat_id);
        }
        echo "<br>repost";
  		} else {
            //Add URL to list
            echo "<br>not a repost, adding to db";
						addUrlToDB($extracted_link, $firstname, $messageid, $chat_id);
            mysqli_query($connection,"");
      }
    }
	}
}
$last_update = $result["update_id"];
renewLastUpdate($last_update);
echo "<br>After: $last_update";
?>
