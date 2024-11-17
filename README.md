# speech_analytics
Speech analytics server on Windows using Whisper


1. Установка whisper
`https://github.com/openai/whisper/discussions/1463`

2. CRON_get_voice_record.php Скачивает по IMAP с почты вложения mp3

3. while_transcrib.php Запускается каждую минуту для транскрибации видео, если в папке есть mp3 файлы, то запускается скрипт get_transcrib.php

