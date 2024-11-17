

 
<?php
header("Content-Type: text/html; charset=utf-8");
set_time_limit(2000);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include_once 'config.php';

$path_to_whisper = "C:\\Users\\Pluton\\AppData\\Local\\Programs\\Python\\Python39\\Scripts\\whisper";
$path_to_ffprobe = "C:\\Users\\Pluton\\AppData\\Local\\Programs\\Python\\Python39\\ffprobe";

$telegram_id = '123123';

// Открываем директорию
$files = scandir(__DIR__.'/audio_record/');

$q_search_hash  = mysqli_query($conn,"SELECT * FROM tb_speech_api_hash WHERE id = 1;");
if (mysqli_num_rows($q_search_hash) > 0) {
    $hash = mysqli_fetch_assoc($q_search_hash);

    
} else {
    //не нашли хэш???
}

// if (count($files) == 0) exit;
// if (count($files) < $limit) $limit = count($files);




if (count($files) == 2) {
    $limit = 2;
} else {
    $limit = 3;
}





for ($i = 0; $i < $limit; $i++) {

    if ($files[$i] === '.' || $files[$i] === '..') {
        continue;
    }

    if (pathinfo($files[$i], PATHINFO_EXTENSION) === 'mp3') {
        $start_time = microtime(true);

        $audio_file_path = "C:\\ospanel\\domains\\speech.local\\audio_record\\" . $files[$i];

        // Команда для получения продолжительности через ffmpeg
        $duration = '';
        $command = escapeshellcmd($path_to_ffprobe) . ' ' . escapeshellarg($audio_file_path) . ' -show_entries format=duration -v quiet ';
        // Выполняем команду и получаем продолжительность
        exec($command, $duration);
 

  
        $output = '';
        // Создаем команду с помощью escapeshellcmd и escapeshellarg
        $command = escapeshellcmd($path_to_whisper) . ' ' . escapeshellarg($audio_file_path) . ' --language Russian --model large --temperature 0.2 --best_of 3 --beam_size 5 --no_speech_threshold 0.5  --logprob_threshold -1.0  --compression_ratio_threshold 3.0 --output_format txt --output_dir /dev/null';

        // Выполняем команду
        exec($command, $output);

        $parts = explode('__', $files[$i]);
        
        $datetime = DateTime::createFromFormat('Y.m.d H-i-s', $parts[0] . ' ' . $parts[1]);
        $formattedDatetime = $datetime->format('Y-m-d H:i:s');

        $inNumber = $parts[2]; // Входящий номер
        $outNumber = $parts[3]; // Исходящий номер
        $anotherNumber = str_replace('.mp3', '', $parts[4]); // 


        // Преобразуем каждую строку в массиве в UTF-8
        foreach ($output as &$value) {
            $value = mb_convert_encoding($value, 'UTF-8', 'windows-1251');
        }

        
        $end_time = microtime(true);


        $q_insert = mysqli_query($conn, "INSERT INTO tb_speech (file_name, date_call, number_in, number_out, number_another, transcrib, time_execute, time_audio) VALUES 
        ('".$files[$i]."','".$formattedDatetime."','".$inNumber."','".$outNumber."','".$anotherNumber."','".mysqli_real_escape_string($conn, json_encode($output))."','".round(($end_time-$start_time), 2)."', '".str_replace('duration=', '', $duration[1])."');");
        
        if ($q_insert) {
            // Удаляем файл
            if (unlink(__DIR__.'/audio_record/'.$files[$i])) {
                //echo "Файл $file успешно удален.<br>";
            } else {
                //echo "Ошибка при удалении файла $file.<br>";
                mysqli_query($conn, "INSERT INTO tb_speech_log_api (date_request, body, request) VALUES 
                (NOW(), 'Прерываю выполнение скрипта, Ошибка при удалении файла {$files[$i]}', 'Файл не найден');");
                $text_comment=str_replace(' ', '_', "SPEECH Прерываю выполнение скрипта, Ошибка при удалении файла {$files[$i]}', 'Файл не найден");
                $url="https:www/telegram_bots/telegram_bot_curator/bot.php?action=add_new_comment_to_chat&user_id=".$telegram_id."&type=&text=".$text_comment."";
                $ch = curl_init();
                    curl_setopt_array($ch, array( 
                    CURLOPT_HEADER => true,
                    CURLOPT_URL => $url,
                    CURLOPT_POST => false,
                    CURLOPT_RETURNTRANSFER => 1
                ));
                $result=curl_exec($ch);
                curl_close($ch);
                exit;
            }
        } else {
            mysqli_query($conn, "INSERT INTO tb_speech_log_api (date_request, body, request) VALUES 
            (NOW(), 'Прерываю выполнение скрипта, транскрибация не записалась в БД', '".mysqli_real_escape_string($conn, "Ошибка записи в бд, после транскрибации: " . mysqli_error($conn))."');");
            
            $text_comment=str_replace(' ', '_', "SPEECH Прерываю выполнение скрипта, транскрибация не записалась в БД', '".mysqli_real_escape_string($conn, "Ошибка записи в бд, после транскрибации: " . mysqli_error($conn)));
            $url="https:www/telegram_bots/telegram_bot_curator/bot.php?action=add_new_comment_to_chat&user_id=".$telegram_id."&type=&text=".$text_comment."";
            $ch = curl_init();
                curl_setopt_array($ch, array( 
                CURLOPT_HEADER => true,
                CURLOPT_URL => $url,
                CURLOPT_POST => false,
                CURLOPT_RETURNTRANSFER => 1
            ));
            $result=curl_exec($ch);
            curl_close($ch);

            exit;
        }


        $q_search_param  = mysqli_query($conn, "SELECT * FROM tb_speech WHERE flag_transfer = 0 AND file_name = '".$files[$i]."'  ;");
        if (mysqli_num_rows($q_search_param)>0) {
            $id_current_row = mysqli_fetch_assoc($q_search_param)['id'];
            $param = [
                'id' => $hash['id'],
                'hash' => $hash['hash'],
                'id_hash' => $id_current_row,
                'file_name' => $files[$i],
                'date_call' => $formattedDatetime,
                'transcrib' => json_encode($output),
                'time_execute' => round(($end_time-$start_time), 2),
                'time_audio' => str_replace('duration=', '', $duration[1]),
                'number_in' => $inNumber,
                'number_out' => $outNumber,
                'number_another' => $anotherNumber,
            ];

  

        $url = "https://www/speech_analitik/api_get.php";

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array("Accept: application/json"));
        //curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $param);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
       
        $result_REQUEST = curl_exec($ch);	

        if ($result_REQUEST == false) {
            echo "Ошибка cURL: " . curl_error($ch);
            $param['transcrib'] = json_decode($param['transcrib']);
            mysqli_query($conn, "INSERT INTO tb_speech_log_api (date_request, body, request) VALUES 
            (NOW(), '".mysqli_real_escape_string($conn, json_encode($param))."', '".mysqli_real_escape_string($conn, "Ошибка cURL: " . curl_error($ch))."');");
    
            $text_comment=str_replace(' ', '_', "SPEECH Ошибка cURL: " . curl_error($ch));
            $url="https://www/telegram_bots/telegram_bot_curator/bot.php?action=add_new_comment_to_chat&user_id=".$telegram_id."&type=&text=".$text_comment."";
            $ch = curl_init();
                curl_setopt_array($ch, array( 
                CURLOPT_HEADER => true,
                CURLOPT_URL => $url,
                CURLOPT_POST => false,
                CURLOPT_RETURNTRANSFER => 1
            ));
            $result=curl_exec($ch);
            curl_close($ch);
      
        } else {
            $result_REQUEST = json_decode($result_REQUEST, TRUE);
        }
        if (isset($result_REQUEST['error']) && $result_REQUEST['error'] != '') {
            $param['transcrib'] = json_decode($param['transcrib']);
            mysqli_query($conn, "INSERT INTO tb_speech_log_api (date_request, body, request) VALUES 
            (NOW(), '".mysqli_real_escape_string($conn, json_encode($param))."', '".mysqli_real_escape_string($conn, json_encode($result_REQUEST))."');");
    
    
        }
        curl_close($ch);

        if (isset($result_REQUEST['success']) && $result_REQUEST['success'] == 'Всё получилось') {
            mysqli_query($conn, "UPDATE tb_speech SET flag_transfer = 1 WHERE id = '".$id_current_row."';");
        }

       

        } else {
            //нет записи в бд?
        }



    } else if (isset($files[$i]) && $files[$i] != '')  {

        $text_comment=str_replace(' ', '_', "SPEECH Удален не mp3 файл, ".$files[$i]);
        $url="https://www/telegram_bots/telegram_bot_curator/bot.php?action=add_new_comment_to_chat&user_id=".$telegram_id."&type=&text=".$text_comment."";
        $ch = curl_init();
            curl_setopt_array($ch, array( 
            CURLOPT_HEADER => true,
            CURLOPT_URL => $url,
            CURLOPT_POST => false,
            CURLOPT_RETURNTRANSFER => 1
        ));
        $result=curl_exec($ch);
        curl_close($ch);
        unlink(__DIR__.'/audio_record/'.$files[$i]);
        //пропускаем не mp3 файл
    }
}
mysqli_query($conn, "UPDATE tb_check_transcrib SET flag_active = 0 WHERE id = 1;");

//ПОМИМО ТРАНСКРИБАЦИИ НАМ НУЖНО ПРОВЕРИТЬ, ВДРУГ БЫЛИ ОШИБКИ ПРИ ОТПРАВКЕ И FLAG_TRANSFER РАВЕН 0, ТО ОТПРАВОЯЕМ ПОВТОРНО
$q_search_flag_zero  = mysqli_query($conn, "SELECT * FROM tb_speech WHERE flag_transfer = 0 LIMIT 10;");
if (mysqli_num_rows($q_search_flag_zero)>0) {
    while ($r_flag_zero = mysqli_fetch_assoc($q_search_flag_zero)) {
        $param = [
            'id' => $hash['id'],
            'hash' => $hash['hash'],
            'id_hash' => $r_flag_zero['id'],
            'file_name' => $r_flag_zero['file_name'],
            'date_call' => $r_flag_zero['date_call'],
            'transcrib' => $r_flag_zero['transcrib'],
            'time_execute' => $r_flag_zero['time_execute'],
            'time_audio' => $r_flag_zero['time_audio'],
            'number_in' => $r_flag_zero['number_in'],
            'number_out' => $r_flag_zero['number_out'],
            'number_another' => $r_flag_zero['number_another'],
        ];

        $url = "https://www/speech_analitik/api_get.php";

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array("Accept: application/json"));
        //curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $param);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
        $result_REQUEST = curl_exec($ch);	

        if ($result_REQUEST == false) {
            echo "Ошибка cURL: " . curl_error($ch);
            $param['transcrib'] = json_decode($param['transcrib']);
            mysqli_query($conn, "INSERT INTO tb_speech_log_api (date_request, body, request) VALUES 
            (NOW(), '".mysqli_real_escape_string($conn, json_encode($param))."', '".mysqli_real_escape_string($conn, "Ошибка cURL: " . curl_error($ch))."');");

            $text_comment=str_replace(' ', '_', "SPEECH Ошибка cURL: " . curl_error($ch));
            $url="https://www/telegram_bots/telegram_bot_curator/bot.php?action=add_new_comment_to_chat&user_id=".$telegram_id."&type=&text=".$text_comment."";
            $ch = curl_init();
                curl_setopt_array($ch, array( 
                CURLOPT_HEADER => true,
                CURLOPT_URL => $url,
                CURLOPT_POST => false,
                CURLOPT_RETURNTRANSFER => 1
            ));
            $result=curl_exec($ch);
            curl_close($ch);

            exit;
        } else {
            $result_REQUEST = json_decode($result_REQUEST, TRUE);
        }
        if (isset($result_REQUEST['error']) && $result_REQUEST['error'] != '') {
            $param['transcrib'] = json_decode($param['transcrib']);
            mysqli_query($conn, "INSERT INTO tb_speech_log_api (date_request, body, request) VALUES 
            (NOW(), '".mysqli_real_escape_string($conn, json_encode($param))."', '".mysqli_real_escape_string($conn, json_encode($result_REQUEST))."');");

            exit;
        }
        curl_close($ch);

        if (isset($result_REQUEST['success']) && $result_REQUEST['success'] == 'Всё получилось') {
            mysqli_query($conn, "UPDATE tb_speech SET flag_transfer = 1 WHERE id = '".$r_flag_zero['id']."';");
        }
    }
}





