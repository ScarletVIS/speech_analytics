<?php
header("Content-Type: text/html; charset=utf-8");
set_time_limit(60);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include_once 'config.php';

$script_path = "C:\\ospanel\\domains\\speech.local\\get_transcrib.php";


$start_minute = date('i');  // Минуты начала выполнения скрипта

$current_minute = $start_minute;  // Инициализируем текущую минуту

// Рассчитываем предполагаемое время следующей итерации (это должно быть до цикла)
$estimated_next_iteration = microtime(true) + 4;  // Текущее время + 4 секунд

while ($current_minute == $start_minute && date('i', (int)$estimated_next_iteration) == $start_minute) {

    $q_search_flag_active  = mysqli_query($conn,"SELECT * FROM tb_check_transcrib WHERE id = 1 AND flag_active = 0;");
    if (mysqli_num_rows($q_search_flag_active) > 0) {
        $flag_active = mysqli_fetch_assoc($q_search_flag_active);
        
        mysqli_query($conn, "UPDATE tb_check_transcrib SET flag_active = 1 WHERE id = '".$flag_active['id']."';");


        // Запускаем другой скрипт в фоновом режиме
        exec('php '.$script_path.' > NUL 2>&1');


        sleep(10);
    } else {
        sleep(1);   
    }
    
    
    // Обновляем текущее время (для следующей проверки минуты)
    $current_minute = date('i');

    // Рассчитываем предполагаемое время следующей итерации
    $estimated_next_iteration = microtime(true) + 4;  // Текущее время + 4 секунд

}


?>