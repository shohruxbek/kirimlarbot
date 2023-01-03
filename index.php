<? 

// *** - bu to'ldirilishi shart bo'lgan qismlar!!!

//Telegram bot TOKEN
define('API_KEY', '<TOKEN>');                   // ***

$dbServername = "localhost"; //DB server nomi
$dbUsername = "<db_username>";  //DB user nomi  // ***
$dbPassword = "<db_password>";  //DB parol      // ***
$dbName = "<db_name>";  //DB nomi               // ***


//Baza bilan bog'lanish
$db = mysqli_connect($dbServername, $dbUsername, $dbPassword, $dbName) or die ("ulanishda xatolik");


//Botni webhook qilish uchun url export qilish, webhook qilgandan keyin yana kommentga olib qo'yish esdan chiqmasin
//echo "https://api.telegram.org/bot" . API_KEY . "/setwebhook?url=" . $_SERVER['SERVER_NAME'] . "" . $_SERVER['SCRIPT_NAME'];


//Bot telegram serveri bilan aloqada bo'lishi uchun funksiya
function bot($method, $datas = []) 
{
    $url = "https://api.telegram.org/bot" . API_KEY . "/" . $method;
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $datas);
    $res = curl_exec($ch);
    if (curl_error($ch))
    {  
        var_dump(curl_error($ch)); 
    }
    else
    {
        return json_decode($res);
    }
}


//Botga yuborilgan xabarlarni ushlab olish
$update = json_decode(file_get_contents('php://input'));
$message = $update->message;
$message_id = $message->message_id;
$chat_id = $message->chat->id;
$text = $message->text;
$username = $message->from->username;


//Botga start bosganga javob xabari yuborish
if($text == "/start"){
    bot("sendmessage", [
        'chat_id'=>$chat_id,
        'text' => "⚠️ <b>Buyruqlar:</b>

        🔷 Ixtiyoriy raqam yuborish kirimning narxi bo'ladi + bitta probeldan keyingi gap comment bo'ladi:

        🔊 <b>Masalan:</b> <code>120 podarka</code>


        🟡 /stat - shu vaqtgacha qancha kirim bo'lganini ko'rsatadi
        🟠 /stat_10 - shu vaqtgacha bo'lgan 10ta summani batafsil ko'rsatadi
        ",
        'parse_mode'=>"HTML"
    ]);
}


//Botga summa va unga comment yuborganda qabbul qilib olish
if($text and strpos($text, "/cancel") === false and strpos($text, "/stat") === false and $text != "/start"){

    $sum = explode(" ", $text)[0];
    $text = str_replace("$sum ", "", $text);

    $comment = mysqli_real_escape_string($db, $text);
    $summa = mysqli_real_escape_string($db, $sum);

    $time = time()+7200;
    $date = date("Y-m-d H:i:s", $time);
    $month = date("m", $time);

    $sql = "INSERT INTO `kirim` (`id`, `chat_id`, `summa`, `sana`, `oy`, `comment`, `created_date`, `status`) VALUES (NULL, '$chat_id', '$summa', '$date', '$month', '$comment', '$time', '1')";

    $res= mysqli_query($db,$sql);
    $count = mysqli_insert_id($db);
    $row = mysqli_fetch_assoc($res);

    if($row == NULL){
        bot("sendmessage", [
            'chat_id'=>$chat_id,
            'text' => '<b>📅 Sana:</b> '.$date.'
            <b>💰 Summa:</b> '.$sum.'.000 /cancel'.$count.'
            <b>📌 Comment:</b> <span class="tg-spoiler">'.$text."</span>",
            'parse_mode'=>"HTML"
        ]);

        sleep(3);

        bot("deletemessage", [
            'chat_id'=>$chat_id,
            'message_id' => $message_id
        ]);

    }else{
        bot("sendmessage", [
            'chat_id'=>$chat_id,
            'text' => "Xatolik bo'ldi"
        ]);
    }
}



//Botga yuborilgan summani bekor qilishni kutib olish
if(strpos($text, "/cancel") !== false){
    $id = str_replace("/cancel", "", $text);

    $sql = "DELETE FROM `kirim` WHERE `id` = $id AND `chat_id` = '$chat_id'";
    $res= mysqli_query($db,$sql);
    $row = mysqli_fetch_assoc($res);

    if($row == NULL){
        $message_id_chat = bot("sendmessage", [
            'chat_id'=>$chat_id,
            'text' => "Ma'lumot o'chirildi!"
        ])->result->message_id;

        sleep(3);

        bot("deletemessage", [
            'chat_id'=>$chat_id,
            'message_id' => $message_id_chat
        ]);
        bot("deletemessage", [
            'chat_id'=>$chat_id,
            'message_id' => $message_id
        ]);

    }else{
        bot("sendmessage", [
            'chat_id'=>$chat_id,
            'text' => "Xatolik bo'ldi"
        ]);
    }

}



//Botda umumiy statistikani ko'rish buyrug'iga javob yuborish
if($text == "/stat"){

    $time = time()+7200;

    $date = date("Y-m-d H:i:s", $time);
    $month = date("m", $time);


    $sql1 = "SELECT SUM(`summa`) FROM `kirim` WHERE `oy` = $month AND `chat_id` = '$chat_id' "; 
    $res1= mysqli_query($db,$sql1);
    $row1 = mysqli_fetch_assoc($res1);

    $month_money = $row1['SUM(`summa`)'] ?? 0;

    $sql2 = "SELECT SUM(`summa`) FROM `kirim`  WHERE `chat_id` = '$chat_id' "; 
    $res2= mysqli_query($db,$sql2);
    $row2 = mysqli_fetch_assoc($res2);

    $all_money = $row2['SUM(`summa`)'] ?? 0;

    bot("sendmessage", [
        'chat_id'=>$chat_id,
        'text' => "💰\n💰   <b>💎 Statistika:</b>
        💰   <b>📅 Ushbu oydagi:</b> $month_money.000
        💰   <b>🎁 Jami:</b> $all_money.000
        💰",
        'parse_mode'=>"HTML"
    ]);
}



//Botda ko'rsatilgan son bo'yicha batafsil statistika buyrug'iga javob qaytarish
if(strpos($text, "/stat_") !== false){
    $tg = str_replace("/stat_", "", $text);

    $sql = "SELECT * FROM `kirim` WHERE `chat_id` = '$chat_id' ORDER BY `kirim`.`id` DESC limit $tg"; 
    $res= mysqli_query($db,$sql);

    $message = "<b>Statistika:</b>\n\n";
    $k = 1;
    $j = 0;

    while($row = mysqli_fetch_assoc($res)){
        $message .= $k.") <b>📅 Sana:</b> ".$row['sana']."
        <b>💰 Summa:</b> ".$row['summa'].".000 /cancel".$row['id'].'
        <b>📌 Comment:</b> <span class="tg-spoiler">'.$row['comment']."</span>\n\n";
        $j+=$row['summa'];
        $k++;
    }

    $message.="<b>🎁 Jami summa:</b> ".$j.".000 so'm";

    bot("sendmessage", [
        'chat_id'=>$chat_id,
        'text' => $message,
        'parse_mode'=>"HTML"
    ]);
}