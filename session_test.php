<?php

session_start();

header("Content-Type: application/json; charset=UTF-8");

echo json_encode([
    "session_id" => session_id(),
    "session_data" => $_SESSION
], JSON_PRETTY_PRINT);

?>