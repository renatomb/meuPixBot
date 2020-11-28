<?php
require('config.php');
require('class-http-request.php');
require('functions.php');
require('mensagens.php');
require('validacao.php');
$dateformat = "d-m-Y H:i:s";
$dateformatnosec = "d-m-Y H:i";
$timezone = "America/Fortaleza";
date_default_timezone_set($timezone);

$content = file_get_contents('php://input');
$update = json_decode($content, true);
$logtext=print_r($update, true);
$logfile=fopen("../log.log","a");
fwrite($logfile,"$logtext\n");
fwrite($logfile,"---\n");


if ($update["message"]) {
    $chatID = $update["message"]["chat"]["id"];
    $userID = $update["message"]["from"]["id"];
    $entidades = $update["message"]["entities"];
    $msg = $update["message"]["text"];
    $hashtags=array();
    $cmd=false;
    if (is_array($entidades)) {
        fwrite($logfile,"Entidades é um array\n");
        for ($i=0;$i<count($entidades);$i++){
            fwrite($logfile,"Iterando $i\n");
            $elemento=substr($update["message"]["text"],$entidades[$i]["offset"],$entidades[$i]["length"]);
            fwrite($logfile,"Elemento: $elemento\n");
            switch($entidades[$i]["type"]) {
                case "bot_command":
                    $cmd=$elemento;
                    break;
                case "hashtag":
                    array_push($hashtags,$elemento);
                    break;
                case "phone_number":
                    $telefone=$elemento;
                    break;
                case "email":
                    $email=$elemento;
                    break;
                case "code":
                    $uuid=str_replace("-","",$elemento);
                    break;
                default:
                fwrite($logfile,"Tipo nao reconhecido: " . $entidades[$i]["type"] . "$elemento\n");
            }
            $msg=str_replace("$elemento","",$msg);
            fwrite($logfile,"MSG $msg\n");
        }
    }
    $username = $update["message"]["chat"]["username"];
    $name = $update["message"]["chat"]["first_name"];
} 

$logtext=print_r($hashtags, true);
fwrite($logfile,"Hashtags: $logtext\n");
fwrite($logfile,"COMANDO $cmd\n");
fwrite($logfile,"--------------------------\n");
fclose($logfile);
/*else if($update["callback_query"]["data"]){
    $chatID = $update["callback_query"]["message"]["chat"]["id"];
    $userID = $update["callback_query"]["from"]["id"];
    $msgid = $update["callback_query"]["message"]["message_id"];
} else if($update["inline_query"]["id"]){
    $msg = $update["inline_query"]["query"];
    $userID = $update["inline_query"]["from"]["id"];
    $username = $update["inline_query"]["from"]["username"];
    $name = $update["inline_query"]["from"]["first_name"];
}*/

$result = $dbuser->query("SELECT * FROM BNoteBot_user WHERE userID = '" . $userID . "'") or die("0");
$numrows = mysqli_num_rows($result);
if($numrows == 0 && $update["inline_query"]["id"] == false){
    $query = "INSERT INTO BNoteBot_user (userID, username, name) VALUES ('$userID', '$username', '" . $dbuser->real_escape_string($name) . "')";
    $result = $dbuser->query($query) or die("0");
} else {
    $row = $result->fetch_array(MYSQLI_ASSOC);
    $status = $row['status'];
    $language = $lang = $row['lang'];
    $invertmemodata = $row['invertmemodata'];
    $justwritemode = $row['justwritemode'];
}

switch($cmd) {
    case "/start":
        sm($chatID,BEMVINDO);
    break;
    case "/incluir";
        if (count($hashtags) > 0) {
            if (strlen($msg) == 11) {
                if (valida_cpf($msg)) {
                    sm($chatID,"Entendi, cadastramento de chave de CPF **$msg**");
                }
                else {
                    sm($chatID,"Desculpe mas o CPF utilizado como chave **$msg** não é um CPF válido, verifique os dados informados.");
                }
            }
            elseif (strlen($msg) == 14) {
                if (valida_cpf($cnpj)) {
                    sm($chatID,"Entendi, cadastramento de chave de CNPJ **$msg**");
                }
                else {
                    sm($chatID,"Desculpe mas o CNPJ utilizado como chave **$msg** não é um CNPJ válido, verifique os dados informados.");
                }
            }
            elseif (strlen($msg) == 36){
                if (valida_uuid($uuid)) {
                    sm($chatID,"Entendi, cadastramento de chave EVP **$uuid**");
                }
                else {
                    sm($chatID,"Desculpe mas o EVP utilizado como chave **$msg** não é uma chave aleatória válida, verifique os dados informados.");
                }
            }
            elseif (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                sm($chatID,"Entendi, cadastramento de chave e-mail **$email**");
            }
            elseif (valida_telefone($telefone)){
                sm($chatID,"Entendi, cadastramento da chave telefone **$telefone**");

            }
            else {
                sm($chatID,"Não foi possível reconhecer o tipo de chave, verifique a chave digitada **$msg**.");
            }

        }
        else {
            sm($chatID,"Utilize /incluir [chave] #banco para cadastrar uma chave do pix. É necessário utilizar pelo menos uma hashtag para associar a cada chave do pix.");
        }
    break;
    default:
        sm($chatID,"Não entendi o que você deseja com **" . $update["message"]["text"] . "** por favor digite / para obter a lista de comandos do bot");
        break;
}

/*
if ($update["chosen_inline_result"]) {
    $result = $dbuser->query("SELECT * FROM BNoteBot_memo WHERE id = " . $update["chosen_inline_result"]["result_id"]);
    $row = $result->fetch_assoc();
    $args = array(
        'inline_message_id' => $update["chosen_inline_result"]["inline_message_id"],
        'text' => $row["memo"],
        'parse_mode' => 'HTML'
    );
    new HttpRequest("post", "https://api.telegram.org/$api/editmessagetext", $args);
    $dbuser->query("INSERT INTO BNoteBot_sentinline (memo_id, msg_id) VALUES (". $update["chosen_inline_result"]["result_id"] .", '". $update["chosen_inline_result"]["inline_message_id"] ."')");
}
elseif($update["inline_query"]){
    $result = $dbuser->query("SELECT * FROM BNoteBot_memo WHERE userID = '" . $userID . "' ORDER BY timestamp DESC");
    if($result->num_rows == 0){
        $json[] = array(
            'type' => 'article',
            'id' => "0",
            'title' => "Inline Memo",
            'description' => $lang["nomemo"],
            'message_text' => $lang["nomemo"],
            'parse_mode' => 'HTML'
        );
    } else {
        $rm = array('inline_keyboard' => array(array(array("text" => "Auto-Update ON", "callback_data" => "nothing"))));
        while($row = $result->fetch_assoc()) {
            if(($msg && strpos($row["memo"], $msg) !== false) || $msg == false){
              switch ($row["type"]) {
                case 'text':
                  $json[] = array(
                      'type' => 'article',
                      'id' => $row["id"],
                      'title' => ($invertmemodata == 1) ? $lang['datememo'] . date($dateformat, $row['timestamp']) . " 📅" : $row["memo"],
                      'description' => ($invertmemodata == 1) ? $row["memo"] : $lang['datememo'] . date($dateformat, $row['timestamp']) . " 📅",
                      'message_text' => $row["memo"],
                      'parse_mode' => 'HTML',
                      'reply_markup' => $rm
                  );
                  break;

                case 'voice':
                  $json[] = array(
                      'type' => 'voice',
                      'id' => $row["id"],
                      'voice_file_id' => $row["file_id"],
                      'title' => $lang['datememo'] . date($dateformat, $row['timestamp']) . " 📅"
                  );
                  break;
              }
            }
        }
    }
    $json = json_encode($json);
    $args = array(
        'inline_query_id' => $update["inline_query"]["id"],
        'results' => $json,
        'cache_time' => 5,
        'is_personal' => true,
        'switch_pm_text' => $lang['settingsinline'],
        'switch_pm_parameter' => "settingsinline"
    );
    new HttpRequest("post", "https://api.telegram.org/$api/answerInlineQuery", $args);
}
else if($update["callback_query"]["data"]){
    $textalert = "";
    $alert = false;
    $data = explode("-", $update["callback_query"]["data"]);
    if($data[0] == "deleterem"){
        $dbuser->query("DELETE FROM BNoteBot_memo WHERE id = '" . $data[1] . "'");
        sm($userID, $lang["deleted"]);
        acq($update["callback_query"]["id"], $textalert, $alert);
        if ($update["callback_query"]["message"]["voice"]) {
          dm($chatID, $msgid);
        } else {
          em($userID, $msgid, $update["callback_query"]["message"]["text"]);
        }
        exit();
    } elseif ($data[0] == "reply" AND $status == NULL AND $userID == $owner) {
        $dbuser->query("UPDATE BNoteBot_user SET status='reply-" . $data[1] . "' WHERE userID='$userID'");
        sm($userID, "*Send the response message:*", false, "Markdown");
    }
    $result = $dbuser->query("SELECT * FROM BNoteBot_memo WHERE userID = '" . $userID . "' ORDER BY timestamp DESC") or die("0");
    for ($set = array(); $row = $result->fetch_assoc(); $set[] = $row);
    if($data[0] == "next"){
        $i = $data[2] + 1;
        $query = "SELECT * FROM BNoteBot_reminder WHERE memoid = '" . $set[$i]['id'] . "' ORDER by timestamp DESC";
        if($result = $dbuser->query($query)){
        	if($result->num_rows > 0){
                $counter = 1;
                while ($row = $result->fetch_assoc()) {
                    $reminders = $reminders . $counter . ". " . date($dateformatnosec, $row["timestamp"]) . "\n";
                    $counter++;
                }
                $reminders = "\n\n" . $lang['reminders'] . "\n" . $reminders;
            }
        }
        if($set[$i] == null){
            $text = $lang['end'];
            $menu[] = array(array(
                "text" => $lang['back'],
                "callback_data" => "back-0-" . $i));
            if ($update["callback_query"]["message"]["voice"]) {
              dm($chatID, $msgid);
              sm($chatID, $text, $menu, false, false, false, false, true);
            } else {
              em($userID, $msgid, $text, $menu, true);
            }
        } else {
            $menu[] = array(array(
                "text" => $lang['back'],
                "callback_data" => "back-0-" . $i), array(
                "text" => $lang['next'],
                "callback_data" => "next-0-" . $i));
            $menu[] = array(array(
                "text" => $lang['delete'],
                "callback_data" => "delete-0-" . $i), array(
                "text" => $lang['remindme'],
                "callback_data" => "reminder-0-" . $i));
            $menu[] = array(array(
                "text" => $lang['showmore'],
                "callback_data" => "showmore-0-" . $i));

            switch ($set[$i]['type']) {
              case 'text':
                $text = $set[$i]['memo'] . "\n\n" . $lang['datememo'] . date($dateformat, $set[$i]['timestamp']) . "📅" . $reminders;
                if ($update["callback_query"]["message"]["voice"]) {
                  dm($chatID, $msgid);
                  sm($chatID, $text, $menu, false, false, false, false, true);
                } else {
                  em($userID, $msgid, $text, $menu, true);
                }
                break;

              case 'voice':
                $text = $lang['duration'] . $set[$i]['duration'] . "s\n" . $lang['datememo'] . date($dateformat, $set[$i]['timestamp']) . "📅" . $reminders;
                dm($chatID, $msgid);
                sv($chatID, $set[$i]['file_id'], $text, $menu, false, false, false, true);
                break;
            }
        }
    } else if($data[0] == "back"){
        $i = $data[2] - 1;
        $query = "SELECT * FROM BNoteBot_reminder WHERE memoid = '" . $set[$i]['id'] . "' ORDER by timestamp DESC";
        if($result = $dbuser->query($query)){
        	if($result->num_rows > 0){
                $counter = 1;
                while ($row = $result->fetch_assoc()) {
                    $reminders = $reminders . $counter . ". " . date($dateformatnosec, $row["timestamp"]) . "\n";
                    $counter++;
                }
                $reminders = "\n\n" . $lang['reminders'] . "\n" . $reminders;
            }
        }
        if($i == 0){
            $menu[] = array(array(
                "text" => $lang['next'],
                "callback_data" => "next-0-" . $i));
        } else {
            $menu[] = array(array(
                "text" => $lang['back'],
                "callback_data" => "back-0-" . $i), array(
                "text" => $lang['next'],
                "callback_data" => "next-0-" . $i));
        }
        $menu[] = array(array(
            "text" => $lang['delete'],
            "callback_data" => "delete-0-" . $i), array(
            "text" => $lang['remindme'],
            "callback_data" => "reminder-0-" . $i));
        $menu[] = array(array(
            "text" => $lang['showmore'],
            "callback_data" => "showmore-0-" . $i));

        switch ($set[$i]['type']) {
          case 'text':
            $text = $set[$i]['memo'] . "\n\n" . $lang['datememo'] . date($dateformat, $set[$i]['timestamp']) . "📅" . $reminders;
            if ($update["callback_query"]["message"]["voice"]) {
              dm($chatID, $msgid);
              sm($chatID, $text, $menu, false, false, false, false, true);
            } else {
              em($userID, $msgid, $text, $menu, true);
            }
            break;

          case 'voice':
            $text = $lang['duration'] . $set[$i]['duration'] . "s\n" . $lang['datememo'] . date($dateformat, $set[$i]['timestamp']) . "📅" . $reminders;
            dm($chatID, $msgid);
            sv($chatID, $set[$i]['file_id'], $text, $menu, false, false, false, true);
            break;
        }
    } else if($data[0] == "delete"){
        $menu[] = array(array(
            "text" => $lang['delete'],
            "callback_data" => "confdelete-0-" . $data[2] . "-" .  $set[$data[2]]['id']), array(
            "text" => $lang['no'],
            "callback_data" => "back-0-" . ($data[2]+1)));
        switch ($set[$data[2]]['type']) {
          case 'text':
            $text = $set[$data[2]]['memo'] . "\n\n" . $lang['confdelete'];
            em($userID, $msgid, $text, $menu, true);
            break;

          default:
            emc($userID, $msgid, $lang['confdelete'], $menu);
            break;
        }
    } else if($data[0] == "confdelete"){
        $dbuser->query("DELETE FROM BNoteBot_memo WHERE id = '" . $data[3] . "'");
        if ($update["callback_query"]["message"]["voice"]) {
          dm($chatID, $msgid);
          sm($chatID, $lang['deleted']);
        } else {
          em($userID, $msgid, $lang['deleted']);
        }
    } else if($data[0] == "toggle"){
        if($data[2] == "invertmemodata"){
            if($invertmemodata == 0){ $toset = 1; $textalert = $lang['enabled']; } else { $toset = 0; $textalert = $lang['disabled']; }
            $dbuser->query("UPDATE BNoteBot_user SET invertmemodata = '" . $toset . "' WHERE userID = '" . $userID . "'");
            $menu[] = array(array(
                "text" => $lang['invertmemodata'] . $textalert,
                "callback_data" => "toggle-0-invertmemodata"));
            $text = $lang['settingstextinline'];
        } else if ($data[2] == "justwritemode") {
            if($justwritemode == 0){ $toset = 1; $textalert = $lang['enabled']; } else { $toset = 0; $textalert = $lang['disabled']; }
            $dbuser->query("UPDATE BNoteBot_user SET justwritemode = '" . $toset . "' WHERE userID = '" . $userID . "'");
            $menu[] = array(array(
                "text" => $textalert,
                "callback_data" => "toggle-0-justwritemode"));
            $text = $lang['justwritemodesettings'];
        }
        em($userID, $msgid, $text, $menu, true);
    } else if($data[0] == "confdeleteall"){
        $dbuser->query("DELETE FROM BNoteBot_memo WHERE userID = '" . $userID ."'");
        $dbuser->query("UPDATE BNoteBot_user SET notes='0' WHERE userID='$userID'");
        $text = $lang['deleted'];
        em($userID, $msgid, $text);
    } else if($data[0] == "confdeleteallno"){
        $text = $lang['cancelled'];
        em($userID, $msgid, $text);
    } else if ($data[0] == "reminder") {
      $menu[] = array(array(
        "text" => $lang['add'],
        "callback_data" => "remindme-0-" . $data[2]
      ));
      $query = "SELECT * FROM BNoteBot_reminder WHERE memoid = '" . $set[$data[2]]['id'] . "' ORDER by timestamp DESC";
      if($result = $dbuser->query($query)){
        if($result->num_rows > 0){
              $counter = 1;
              while ($row = $result->fetch_assoc()) {
                  $reminders = $reminders . $counter . ". " . date($dateformatnosec, $row["timestamp"]) . "\n";
                  $counter++;
              }
              $reminders = $lang['uhareminders'] . "\n" . $reminders . "\n";
              $menu[] = array(array(
                "text" => $lang['delete'],
                "callback_data" => "deletereminder-0-" . $set[$data[2]]['id'] . "-" . $data[2]
              ));
          }
      }
      $menu[] = array(array(
        "text" => $lang['back'],
        "callback_data" => "back-0-" . ($data[2]+1)
      ));
      $text = $lang['reminderman'] . "\n\n" . $reminders;
      if ($update["callback_query"]["message"]["voice"]) {
        dm($chatID, $msgid);
        sm($chatID, $text, $menu, false, false, false, false, true);
      } else {
        em($userID, $msgid, $text, $menu, true);
      }
    } else if($data[0] == "remindme"){
        $dbuser->query("UPDATE BNoteBot_user SET status='addremind-" . $data[2] . "' WHERE userID='$userID'");
        $menur[] = array($lang['remindmetut']);
        $menur[] = array($lang['cancel']);
        if ($set[$data[2]]['type'] == 'voice') {
          dm($chatID, $msgid);
          sv($chatID, $set[$data[2]]['file_id']);
        } else {
          em($userID, $msgid, $set[$data[2]]['memo']);
        }
        sm($userID, $lang['remindmetxt'], $menur);
    } else if($data[0] == "deletereminder"){
      $query = "SELECT * FROM BNoteBot_reminder WHERE memoid = '" . $data[2] . "' ORDER by timestamp DESC";
      if($result = $dbuser->query($query)){
        if($result->num_rows > 0){
          $counter = 1;
          while ($row = $result->fetch_assoc()) {
            $reminders = $reminders . $counter . ". " . date($dateformatnosec, $row["timestamp"]) . "\n";
            $menur[] = array(array(
              "text" => "$counter",
              "callback_data" => "deletenreminder-0-" . $row["id"] . "-" . $data[3] . "-" . $data[2]
            ));
            $counter++;
          }
        }
      }
      $menur[] = array(array(
        "text" => $lang['deleteall'],
        "callback_data" => "deleteallreminders-0-" . $data[2]
      ));
      $menur[] = array(array(
        "text" => $lang['back'],
        "callback_data" => "reminder-0-" . $data[3]
      ));
      em($userID, $msgid, $lang['deletereminder'] . "\n\n" . $reminders, $menur, true);
    } elseif ($data[0] == "deleteallreminders") {
      $dbuser->query("DELETE FROM BNoteBot_reminder WHERE memoid = " . $data[2]);
      $menur[] = array(array(
        "text" => $lang['back'],
        "callback_data" => "reminder-0-" . $data[2]
      ));
      em($userID, $msgid, $lang['noreminder'], $menur, true);
    } elseif ($data[0] == "deletenreminder") {
      $dbuser->query("DELETE FROM BNoteBot_reminder WHERE id = " . $data[2]);
      $textalert = $lang['deletedreminder'];
      $alert = true;
      $query = "SELECT * FROM BNoteBot_reminder WHERE memoid = '" . $data[4] . "' ORDER by timestamp DESC";
      if($result = $dbuser->query($query)){
        if($result->num_rows > 0){
          $counter = 1;
          while ($row = $result->fetch_assoc()) {
            $reminders = $reminders . $counter . ". " . date($dateformatnosec, $row["timestamp"]) . "\n";
            $menur[] = array(array(
              "text" => "$counter",
              "callback_data" => "deletenreminder-0-" . $row["id"]
            ));
            $counter++;
          }
          $menur[] = array(array(
            "text" => $lang['back'],
            "callback_data" => "reminder-0-" . $data[3]
          ));
          em($userID, $msgid, $lang['deletereminder'] . "\n\n" . $reminders, $menur, true);
        } else {
          $menur[] = array(array(
            "text" => $lang['back'],
            "callback_data" => "reminder-0-" . $data[3]
          ));
          em($userID, $msgid, $lang['noreminder'], $menur, true);
        }
      }
    } else if($data[0] == "retrodate"){
        $dbuser->query("UPDATE BNoteBot_user SET status='retrodate-" . $data[2] . "' WHERE userID='$userID'");
        $menur[] = array($lang['remindmetut']);
        $menur[] = array($lang['cancel']);
        if ($set[$data[2]]['type'] == 'voice') {
          dm($chatID, $msgid);
          sv($chatID, $set[$data[2]]['file_id']);
        } else {
          em($userID, $msgid, $set[$data[2]]['memo']);
        }
        sm($userID, $lang['retrodatetxt'], $menur);
    } else if($data[0] == "edit"){
        $text = $set[$data[2]]['memo'];
        $dbuser->query("UPDATE BNoteBot_user SET status='edit-" . $data[2] . "' WHERE userID='$userID'");
        $menur[] = array($lang['cancel']);
        em($userID, $msgid, $text);
        sm($userID, $lang['edittxt'], $menur);
    } else if ($data[0] == "showmore") {
        if($data[2] == 0){
            $menu[] = array(array(
                "text" => $lang['next'],
                "callback_data" => "next-0-" . $data[2]));
        } else {
            $menu[] = array(array(
                "text" => $lang['back'],
                "callback_data" => "back-0-" . $data[2]), array(
                "text" => $lang['next'],
                "callback_data" => "next-0-" . $data[2]));
        }
        $menu[] = array(array(
            "text" => $lang['delete'],
            "callback_data" => "delete-0-" . $data[2]), array(
            "text" => $lang['remindme'],
            "callback_data" => "reminder-0-" . $data[2]));
        if ($set[$data[2]]['type'] == 'voice') {
          $menu[] = array(array(
              "text" => $lang['date'],
              "callback_data" => "retrodate-0-" . $data[2]));
        } else {
          $menu[] = array(array(
              "text" => $lang['edit'],
              "callback_data" => "edit-0-" . $data[2]), array(
              "text" => $lang['date'],
              "callback_data" => "retrodate-0-" . $data[2]));
        }
        emk($chatID, $msgid, $menu);
    }
    acq($update["callback_query"]["id"], $textalert, $alert);
}

$sexploded = explode("-", $status);

if($status == "select"){
    include('mensagens.php');
    menu(BEMVINDO);
    $dbuser->query("UPDATE BNoteBot_user SET lang='pt' WHERE userID='$userID'");
    $dbuser->query("UPDATE BNoteBot_user SET status='' WHERE userID='$userID'");
} else if($status == "addmemo"){
    if($msg == $lang['cancel']){
        $dbuser->query("UPDATE BNoteBot_user SET status='' WHERE userID='$userID'");
        menu($lang['cancelled']);
    } else {
        if ($msg) {
          $dbuser->query("INSERT INTO BNoteBot_memo (userID, type, memo, timestamp) VALUES ('$userID', 'text', '" . $dbuser->real_escape_string($msg) . "', '" . time() . "')");
          menu($lang['saved']);
        } elseif($update["message"]["voice"]) {
          $dbuser->query("INSERT INTO BNoteBot_memo (userID, type, file_id, duration, timestamp) VALUES ('$userID', 'voice', '" . $update["message"]["voice"]["file_id"] . "', '". $update["message"]["voice"]["duration"] ."', '" . time() . "')");
          menu($lang['saved']);
        } else {
          menu($lang['onlytxt']);
        }
        $dbuser->query("UPDATE BNoteBot_user SET status='' WHERE userID='$userID'");
    }
} 
/*else if($status == "timezone"){
    if($msg == $lang['cancel']){
        $dbuser->query("UPDATE BNoteBot_user SET status='' WHERE userID='$userID'");
        menu($lang['cancelled']);
    } else if($msg == $lang['defaulttimezone']) {
        $dbuser->query("UPDATE BNoteBot_user SET status='' WHERE userID='$userID'");
        menu($lang['savedt']);
    } else {
        $timezone = date_default_timezone_set($msg);
        if($timezone == TRUE){
            $dbuser->query("UPDATE BNoteBot_user SET timezone='$msg' WHERE userID='$userID'");
            $dbuser->query("UPDATE BNoteBot_user SET status='' WHERE userID='$userID'");
            menu($lang['savedt']);
        } else {
            sm($chatID, $lang['invalidtimezone']);
        }
    }
}
else if($status == "feedback"){
    if($msg == $lang['cancel']){
        menu($lang['cancelled']);
    } else {
        $menu[] = array(array(
            "text" => "Reply",
            "callback_data" => "reply-" . $userID));
        $feedback = "New feedback received!\n\nMessage: $msg\nName: $name\nUsername: @$username\nUserID: $userID\nLanguage: ".$language."\nDate: " . date($dateformat, time());
        sm($owner, $feedback, $menu, false, false, false, false, true);
        $var=fopen("feedback.txt","a+");
        fwrite($var, "\n\n" . $feedback);
        fclose($var);
        menu($lang['thanksfeedback']);
    }
    $dbuser->query("UPDATE BNoteBot_user SET status='' WHERE userID='$userID'");
} else if($sexploded[0] == "addremind"){
    if($msg == $lang['cancel']){
        $dbuser->query("UPDATE BNoteBot_user SET status='' WHERE userID='$userID'");
        menu($lang['cancelled']);
    } else if($msg == $lang['remindmetut']){
        sm($chatID, $lang['remindmeformat']);
    } else {
        $timemsg = strtotime(str_replace(".", "-", toendate($msg)));
        if($timemsg == true){
            $result = $dbuser->query("SELECT * FROM BNoteBot_memo WHERE userID = '" . $userID . "' ORDER BY timestamp DESC") or die("0");
            for ($set = array(); $row = $result->fetch_assoc(); $set[] = $row);
            $dbuser->query("INSERT INTO BNoteBot_reminder (userID, memoid, timestamp) VALUES ('$userID', '".$set[$sexploded[1]]['id']."', '$timemsg')");
            menu($lang['remindersaved'] . "\n" . date($dateformatnosec, $timemsg));
            $dbuser->query("UPDATE BNoteBot_user SET status='' WHERE userID='$userID'");
        } else {
            sm($chatID, $lang['invaliddate']);
        }
    }
} else if($sexploded[0] == "retrodate") {
    if ($msg == $lang['cancel']) {
        $dbuser->query("UPDATE BNoteBot_user SET status='' WHERE userID='$userID'");
        menu($lang['cancelled']);
    } else if ($msg == $lang['remindmetut']) {
        sm($chatID, $lang['remindmeformat']);
    } else {
        $timemsg = strtotime(str_replace(".", "-", toendate($msg)));
        if ($timemsg == true) {
            $result = $dbuser->query("SELECT * FROM BNoteBot_memo WHERE userID = '" . $userID . "' ORDER BY timestamp DESC") or die("0");
            for ($set = array(); $row = $result->fetch_assoc(); $set[] = $row) ;
            $dbuser->query("UPDATE BNoteBot_memo SET timestamp='" . $timemsg . "' WHERE id='" . $set[$sexploded[1]]['id'] . "'");
            menu($lang['datesaved'] . "\n" . date($dateformatnosec, $timemsg));
            $dbuser->query("UPDATE BNoteBot_user SET status='' WHERE userID='$userID'");
        } else {
            sm($chatID, $lang['invaliddate']);
        }
    }
} else if($sexploded[0] == "edit"){
    if($msg == $lang['cancel']){
        $dbuser->query("UPDATE BNoteBot_user SET status='' WHERE userID='$userID'");
        menu($lang['cancelled']);
    } else {
        $result = $dbuser->query("SELECT * FROM BNoteBot_memo WHERE userID = '" . $userID . "' ORDER BY timestamp DESC") or die("0");
        for ($set = array(); $row = $result->fetch_assoc(); $set[] = $row);
        $dbuser->query("UPDATE BNoteBot_memo SET memo='$msg' WHERE id='" . $set[$sexploded[1]]['id'] . "'");
        //$dbuser->query("INSERT INTO BNoteBot_reminder (userID, memoid, timestamp) VALUES ('$userID', '".$set[$sexploded[1]]['id']."', '$timemsg')");
        menu($lang['saved']);
        $dbuser->query("UPDATE BNoteBot_user SET status='' WHERE userID='$userID'");
        $result = $dbuser->query("SELECT * FROM BNoteBot_sentinline WHERE memo_id = " . $set[$sexploded[1]]['id']);
        while($row = $result->fetch_assoc()){
            $args = array(
                'inline_message_id' => $row["msg_id"],
                'text' => $msg,
                'parse_mode' => 'HTML'
            );
            $r = new HttpRequest("post", "https://api.telegram.org/$api/editmessagetext", $args);
            $r = json_decode($r->getResponse(), true);
            if (!$r["ok"]) $dbuser->query("DELETE FROM BNoteBot_sentinline WHERE id = " . $row["id"]);
        }
    }
} else if($sexploded[0] == "reply" AND $userID == $owner){
    $dbuser->query("UPDATE BNoteBot_user SET status='' WHERE userID='$userID'");
    sm($userID, "*Sent.*", false, "Markdown");
    sm($sexploded[1], $msg);
} else {
    if($msg == ADICIONAR){
        $dbuser->query("UPDATE BNoteBot_user SET status='addmemo' WHERE userID='$userID'");
        $menu[] = array($lang['cancel']);
        sm($chatID, $lang['addmemotext_v2'], $menu, 'HTML', false, false, true);
    } else if($msg == $lang['settings']){
        setmenu($lang['settings']);
    } else if($msg == $lang['feedback']){
        $dbuser->query("UPDATE BNoteBot_user SET status='feedback' WHERE userID='$userID'");
        $menu[] = array($lang['cancel']);
        sm($chatID, $lang['feedbacktext'], $menu, 'HTML', false, false, true);
    } else if($msg == $lang['savedmemo']){
        $result = $dbuser->query("SELECT * FROM BNoteBot_memo WHERE userID = '" . $userID . "' ORDER BY timestamp DESC") or die("0");
        if($result->num_rows > 0){
            for ($set = array(); $row = $result->fetch_assoc(); $set[] = $row);
            $query = "SELECT * FROM BNoteBot_reminder WHERE memoid = '" . $set['0']['id'] . "' ORDER by timestamp DESC";
            if($result = $dbuser->query($query)){
                if($result->num_rows > 0){
                    $counter = 1;
                    while ($row = $result->fetch_assoc()) {
                        $reminders = $reminders . $counter . ". " . date($dateformatnosec, $row["timestamp"]) . "\n";
                        $counter++;
                    }
                    $reminders = "\n\n" . $lang['reminders'] . "\n" . $reminders;
                }
            }

            $menu[] = array(array(
                "text" => $lang['next'],
                "callback_data" => "next-0-0"));
            $menu[] = array(array(
                "text" => $lang['delete'],
                "callback_data" => "delete-0-0"), array(
                "text" => $lang['remindme'],
                "callback_data" => "reminder-0-0"));
            $menu[] = array(array(
                "text" => $lang['showmore'],
                "callback_data" => "showmore-0-0"));

            switch ($set['0']['type']) {
              case 'text':
                $text = $set['0']['memo'] . "\n\n" . $lang['datememo'] . date($dateformat, $set['0']['timestamp']) . "📅" . $reminders;
                sm($chatID, $text, $menu, false, false, false, false, true);
                break;

              case 'voice':
                $text = $lang['duration'] . $set['0']['duration'] . "s\n" . $lang['datememo'] . date($dateformat, $set['0']['timestamp']) . "📅" . $reminders;
                sv($chatID, $set['0']['file_id'], $text, $menu, false, false, false, true);
                break;
            }
        } else {
            sm($chatID, $lang['nomemo']);
        }
    } else if($msg == $lang['info']){
        $menu[] = array(array(
            "text" => $lang['subchannel'],
            "url" => "https://telegram.me/r3n4t0"));
        sm($chatID, $lang['infomsg'], $menu, 'HTML', false, false, false, true);
    } else if($msg == GITHUB){
      $menu[] = M_GITHUB;
      sm($chatID, GITHUB, $menu, 'HTML', false, false, false, true);
    } else if($msg == CONTRIBUA){
        $menu[] = M_CONTRIBUA;
        sm($chatID, COMOCOLABORAR, $menu, 'HTML', false, false, false, true);
    } else if($msg == $lang['inlinemode']){
        inlinemodeset($invertmemodata);
    } else if($msg == $lang['deleteallnote']){
        $menu[] = array(array(
            "text" => $lang['delete'],
            "callback_data" => "confdeleteall"), array(
            "text" => $lang['no'],
            "callback_data" => "confdeleteallno"));
        sm($chatID, $lang['askdeleteallnote'], $menu, 'HTML', false, false, false, true);
    } 
    else if($msg == $lang['justwritemode']){
        if($justwritemode){ $justwritemodetxt = $lang['enabled']; } else { $justwritemodetxt = $lang['disabled']; }
        $menu[] = array(array(
            "text" => $justwritemodetxt,
            "callback_data" => "toggle-0-justwritemode"));
        sm($chatID, $lang['justwritemodesettings'], $menu, 'HTML', false, false, false, true);
    } else if($msg == $lang['cancel']){
        menu($lang['cancelled']);
    } else {
        switch ($msg){
            case '/start':
                menu(BEMVINDO);
                break;
            case '/start settingsinline':
                inlinemodeset($invertmemodata);
                break;
            default:
                if ($update["message"]["text"]) {
                  if ($justwritemode) {
                    if ($update["message"]["text"]) {
                        $dbuser->query("INSERT INTO BNoteBot_memo (userID, type, memo, timestamp) VALUES ('$userID', 'text', '" . $dbuser->real_escape_string($msg) . "', '" . time() . "')");
                    } elseif ($update["message"]["voice"]) {
                        $dbuser->query("INSERT INTO BNoteBot_memo (userID, type, file_id, duration, timestamp) VALUES ('$userID', 'voice', '" . $update["message"]["voice"]["file_id"] . "', '". $update["message"]["voice"]["duration"] ."', '" . time() . "')");
                    }
                    $menu[] = array(array(
                        "text" => $lang['delete'],
                        "callback_data" => "confdelete-0-0-" .$dbuser->insert_id));
                    sm($chatID, $lang['saved'], $menu, 'HTML', false, false, false, true);
                  } else {
                    sm($chatID, $lang['messagenovalid']);
                  }
                }
                break;
        }
    }
} */

function langmenu($chatID){
    $text = "🇬🇧 - Welcome! Select a language:
🇮🇹 - Benvenuto! Seleziona una lingua:
🇩🇪 - Herzlich willkommen! Wähle eine Sprache:
🇧🇷 - Bem-vindo! Escolha um idioma:
🇷🇺 - Добро пожаловать! Выберите язык:";
    $menu[] = array("English 🇬🇧");
    $menu[] = array("Italiano 🇮🇹");
    $menu[] = array("Deutsch 🇩🇪");
    $menu[] = array("Português 🇧🇷");
    $menu[] = array("Russian 🇷🇺");
    sm($chatID, $text, $menu, 'HTML', false, false, true);
}

function menu($text){
    global $lang;
    global $chatID;
    $menu[] = array(ADICIONAR);
    $menu[] = array(SALVO);
    $menu[] = array(INFORMACOES, CONTRIBUA);
    $menu[] = array(FEEDBACK);
    $menu[] = array(CONFIGURAR, GITHUB);
    sm($chatID, $text, $menu, 'HTML', false, false, true);
}

function setmenu($text){
    global $lang;
    global $chatID;
    $menu[] = array($lang['inlinemode']);
    $menu[] = array($lang['justwritemode']);
    $menu[] = array($lang['deleteallnote']);
  //  $menu[] = array($lang['settimezone']);
    $menu[] = array($lang['cancel']);
    sm($chatID, $text, $menu, 'HTML', false, false, true);
}

function inlinemodeset($invertmemodata){
    global $lang;
    global $chatID;
    if($invertmemodata == 1){ $invertmemodatatxt = $lang['enabled']; } else { $invertmemodatatxt = $lang['disabled']; }
    $menu[] = array(array(
        "text" => $lang['invertmemodata'] . $invertmemodatatxt,
        "callback_data" => "toggle-0-invertmemodata"));
    sm($chatID, $lang['settingstextinline'], $menu, 'HTML', false, false, false, true);
}

function toendate($date){
    $date = str_ireplace("oggi","today", $date);
    $date = str_ireplace("ieri","yesterday", $date);
    $date = str_ireplace("domani","tomorrow", $date);
    $date = str_ireplace("alle","", $date);
    $date = str_ireplace("at","", $date);
    return $date;
}


?>
