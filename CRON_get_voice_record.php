<?php
header("Content-Type: text/html; charset=utf-8");
set_time_limit(600);
include_once 'config_engine.php';
$hostname = '{imap.yandex.ru:993/imap/ssl}';
$username = 'yandex.ru';
$password = '123'; 

// Лимит на количество обрабатываемых писем
$limit = 50;

$two_days_ago = date("d-M-Y", strtotime("-2 day"));
$tomorrow = date('d-M-Y', strtotime('+2 day'));


$inbox = imap_open($hostname,$username,$password) or die('Cannot connect to mail: ' . imap_last_error());

// if (function_exists('imap_gc')) {
//     echo "Функция imap_gc доступна.\n";
// } else {
//     echo "Функция imap_gc не найдена. Убедитесь, что вы используете PHP 8.3 или выше и IMAP-расширение установлено.\n";
// }

// Выполняем сборку мусора для очистки кэша
//$flags = 'IMAP_GC_ELT | IMAP_GC_ENV | IMAP_GC_TEXT';
// if (imap_gc($inbox, IMAP_GC_ELT)) {
//     echo "Сборка мусора выполнена успешно.\n";
// } else {
//     echo "Ошибка при выполнении сборки мусора: " . imap_last_error() . "\n";
// }
// if (imap_gc($inbox, IMAP_GC_ENV)) {
//     echo "Сборка мусора выполнена успешно.\n";
// } else {
//     echo "Ошибка при выполнении сборки мусора: " . imap_last_error() . "\n";
// }
// if (imap_gc($inbox, IMAP_GC_TEXTS)) {
//     echo "Сборка мусора выполнена успешно.\n";
// } else {
//     echo "Ошибка при выполнении сборки мусора: " . imap_last_error() . "\n";
// }

// Функция для проверки наличия файла в базе данных
function isFileDownloaded($conn, $filename) {
    $q_search_file = mysqli_query($conn, "SELECT * FROM tb_audio_record_download WHERE file_name = '".$filename."';");
    if (mysqli_num_rows($q_search_file) > 0) return true;
    else return false;
}

// Функция для записи имени файла в базу данных
function saveFileToDatabase($conn, $filename, $uid) {
    mysqli_query($conn, "INSERT INTO tb_audio_record_download (file_name, date_download, UID) VALUES ('".$filename."', NOW(), '".$uid."');");
}

function imap_utf7_decode_custom($text) {
    return mb_convert_encoding($text, 'UTF-8', 'UTF7-IMAP');
}

// Получаем список всех папок
$folders = imap_list($inbox, $hostname, '*');

if ($folders === false) {
    die('Не удается получить список папок: ' . imap_last_error());
}


foreach ($folders as $folder) {
    // Убедимся, что обе строки закодированы в UTF-8
    $decoded_folder = imap_utf7_decode_custom($folder);
    if (mb_detect_encoding('НЕ РАБОТАЮТ', 'UTF-8', true) && mb_detect_encoding($decoded_folder, 'UTF-8', true)) {
        // Поиск подстроки
        if (stripos($decoded_folder, 'НЕ РАБОТАЮТ') !== false || 
            stripos($decoded_folder, 'Удаленные') !== false ||
            stripos($decoded_folder, 'Черновики') !== false ||
            stripos($decoded_folder, 'Отправленные') !== false ||
            stripos($decoded_folder, 'Исходящие') !== false ||
            stripos($decoded_folder, 'Спам') !== false) {
            continue; // Пропускаем папку
        }
    } else {
        echo "Ошибка с кодировкой строк.";
        exit;
    }

    // Открываем папку
    $folder = str_replace($hostname, '', $folder);
    imap_reopen($inbox, $hostname . $folder) or die('Не удается открыть папку: ' . imap_last_error());

    //СНАЧАЛА ИЩЕМ НЕПРОЧИТАННЫЕ, ПОТОМ ПЕРЕСТРАХОВЫВАЕМСЯ ВДРУГ ЧЕЛОВЕК ПРОЧИТАЛ НЕ СКАЧАННОЕ АУДИО
    //SUBJECT "Запись разговора"
    // Ищем письма от отправителя за вчера и сегодня
    $emails = imap_search($inbox, 'UNSEEN SINCE "' . $two_days_ago . '" BEFORE "' . $tomorrow . '"',  SE_FREE, "UTF-8");

    if (!$emails) {
        $emails = imap_search($inbox, 'ALL SINCE "' . $two_days_ago . '" BEFORE "' . $tomorrow . '"',  SE_FREE, "UTF-8");
    }

    print_r('<pre>');
    print_r(imap_utf7_decode_custom($folder));
    print_r('</pre>');
  
    if ($emails) {

    print_r('<pre>');
    print_r($emails);
    print_r('</pre>');    
    
        foreach ($emails as $email_number) {
            $overview = imap_fetch_overview($inbox, $email_number, 0);
            $message = imap_fetchbody($inbox, $email_number, 1.1);
            // Получаем UID письма
            $uid = imap_uid($inbox, $email_number);

            // Проверяем наличие вложений
            $structure = imap_fetchstructure($inbox, $email_number);
            if (isset($structure->parts) && count($structure->parts)) {
                for ($i = 0; $i < count($structure->parts); $i++) {
                    if ($structure->parts[$i]->ifdparameters) {
                        foreach ($structure->parts[$i]->dparameters as $object) {
                            if (strtolower($object->attribute) == 'filename') {

                                $filename = $object->value;
                                
                                // Заменяем символы, недопустимые в именах файлов
                                $filename = preg_replace('/[<>:"\/\\\|\?\*]/', '_', $filename);

                                // Добавляем проверку на расширение файла
                                if (pathinfo($filename, PATHINFO_EXTENSION) !== 'mp3') {
                                    echo "Файл $filename не имеет расширение mp3.<br>";
                                    continue;
                                }

                                // Проверяем, был ли файл уже загружен
                                if (isFileDownloaded($conn, $filename)) {
                                    echo "Файл $filename уже загружен, пропускаем.<br>";
                                    $limit++;
                                    continue;
                                }


                                $attachment = imap_fetchbody($inbox, $email_number, $i + 1);

                                // Декодируем вложение в зависимости от кодировки
                                switch ($structure->parts[$i]->encoding) {
                                    case 3: // base64
                                        $attachment = base64_decode($attachment);
                                        break;
                                    case 4: // quoted-printable
                                        $attachment = quoted_printable_decode($attachment);
                                        break;
                                }

                                // Сохраняем вложение на диск
                                file_put_contents(__DIR__.'/audio_record/'.$filename, $attachment);
                                echo "Сохранен файл: $filename <br>";

                                // Записываем имя файла в базу данных
                                saveFileToDatabase($conn, $filename, $uid);

                            }
                        }
                    }
                }
            }

            // Прерываем цикл, если достигнут лимит
            if (--$limit <= 0) {
                break 2;
            }
        }
    } else echo 'Не найдено писем. Ошибка: ' . imap_last_error();
}

// Закрываем соединение
imap_close($inbox);