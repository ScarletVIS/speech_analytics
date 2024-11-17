<?php

    function db_base() {
        static $db_base;
        return !isset($db_base) ? $db_base = mysqli_connect("localhost", "speech_analytics_user", "123", "speech_analytics") : $db_base; 
    }

?>