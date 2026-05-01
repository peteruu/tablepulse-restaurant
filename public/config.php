<?php
require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'api' . DIRECTORY_SEPARATOR . 'bootstrap.php';
$config = tp_config();
tp_require_page_auth($config);
readfile(__DIR__ . DIRECTORY_SEPARATOR . 'config.html');
