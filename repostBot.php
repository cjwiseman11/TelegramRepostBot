<?php
//php /home/pepperte/public_html/feedify/bot/repostBot.php  
// Load configuration file outside of doc root
//$root = "$_SERVER['DOCUMENT_ROOT']";
$config = parse_ini_file("/home/pepperte/configTelegram.ini");
$bot_id = $config['botid'];
//Connecting to sql db.
$connection = mysqli_connect("localhost",$config['username'],$config['password'],$config['dbname']);
if($connection === false){    
//TODO: Add error
}
$getLastUpdate = mysqli_query($connection,"SELECT * FROM `telegramBotFeed` WHERE `id` = 1") or die(mysqli_error($connection));
if($row = mysqli_fetch_array($getLastUpdate)){
	$last_update = $row["lastupdate"];    
	echo "<br>Before: $last_update";} 
else {    
	echo "Something went wrong";
	}

$website = "https://api.telegram.org/bot".$bot_id;
$update = file_get_contents('php://input');

$result = json_decode($update, TRUE);
//$result = file_get_contents($url);
//$result = json_decode($result, true);
$extLinkArray = array();

function has_dupes($array){
 $dupe_array = array();
 foreach($array as $val){
  if(++$dupe_array[$val] > 1){
   return true;
  }
 }
 return false;
}

//Check each message
$receivedMessage = $result["message"]["text"];
preg_match_all('!https?://\S+!', $receivedMessage, $matches);
$extractedLink = $matches[0][0];
//Check if message is newer than last_update
if ($last_update<$result["update_id"]){            
    $chat_id = $result["message"]["chat"]["id"];
    //TODO: Get URL object from JSON, how to get optional URL
    $urlCheck = $result["message"]["entities"][0]["type"];
    //$getURL = $result["message"]["entities"][0]["url"];
    //Stop Bot
    if($receivedMessage == "StopBot"){
        file_get_contents('https://api.telegram.org/bot' . $bot_id . '/sendMessage?text=BotStopping&chat_id='.$chat_id);
        $break = true;
        break;
    } else if($receivedMessage == "/repoststats"){
        $repoststats = mysqli_query($connection,"SELECT * FROM `stats` WHERE `chatid` = $chat_id ORDER BY reposts DESC") or die(mysqli_error($connection));
        $i=1;
        while($row3 = mysqli_fetch_array($repoststats)){
            $message = $message . "%0A" . "$i. " . $row3["firstname"] .": ". $row3["reposts"];
            $i++;
        }
        echo "<br>Showing stats";
        /*foreach($repoststats as $key2 => $result2){
            $message = $message . "\n" . $result2["firstname"] .":". $result2["reposts"];
        }*/
        file_get_contents('https://api.telegram.org/bot' . $bot_id . '/sendMessage?text='.$message.'&chat_id='.$chat_id);
    } else if($urlCheck == "url"){
        $urlCheck = mysqli_query($connection,"SELECT * FROM `urls` WHERE `url` = '$extractedLink' AND `chatid` = $chat_id") or die(mysqli_error($connection));
        $firstname = $result["message"]["from"]["first_name"];
        $messageid = $result["message"]["message_id"];
        if($row2 = mysqli_fetch_array($urlCheck)){
            $message = "wowow repost lul";
            $originalMsgId = $row2["messageid"];
            file_get_contents('https://api.telegram.org/bot' . $bot_id . '/sendMessage?text='.$message.'&chat_id='.$chat_id.'&reply_to_message_id='.$originalMsgId);
            $repostStatCheck = mysqli_query($connection,"SELECT * FROM `stats` WHERE `firstname` = '$firstname' AND `chatid` = $chat_id") or die(mysqli_error($connection));
            if($row3 = mysqli_fetch_array($repostStatCheck)){
                mysqli_query($connection,"UPDATE `stats` SET `reposts`= reposts+1 WHERE `firstname` = '$firstname' AND `chatid` = $chat_id") or die(mysqli_error($connection));
            } else {
                mysqli_query($connection,"INSERT INTO `stats`(`chatid`, `firstname`, `reposts`) VALUES ('$chat_id','$firstname',1)") or die(mysqli_error($connection));
            }

            echo "<br>repost";
        } else {
            //Add URL to list
            echo "<br>not a repost, adding to db";
            mysqli_query($connection,"INSERT INTO `urls` (`url`, `firstname`,`messageid`,`chatid`) VALUES ('$extractedLink', '$firstname','$messageid','$chat_id')");
        }
        /*if(in_array($extractedLink,$extLinkArray)){
            echo "<br>OMG DUPE";
            foreach ($result['result'] as $key2 => $result2){
                if(strpos($result2["message"]["text"],$extractedLink) !== false){
                    $originalPoster = $result2["message"]["from"]["first_name"];
                    $originalMsgId = $result2["message"]["message_id"];
                    break;
                }
            }
            $message = "Wuwuwu wow repost lul, this was posted by " . $originalPoster . " already!!!";
        }
        array_push($extLinkArray, $extractedLink);*/
    }
}
$last_update = $result["update_id"];
mysqli_query($connection,"UPDATE `telegramBotFeed` SET `lastupdate`= '$last_update' WHERE `id` = 1");             
echo "<br>After: $last_update";

//close the db connection
mysqli_close($connection);
?>.