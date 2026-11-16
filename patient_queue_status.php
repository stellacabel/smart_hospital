<?php

session_start();

require_once "db.php";

header("Content-Type: application/json");

if (!isset($_SESSION["user_id"])) {
    echo json_encode([
        "success" => false,
        "message" => "Not authenticated"
    ]);
    exit;
}

if (
    !isset($_SESSION["role"]) ||
    $_SESSION["role"] !== "patient"
) {
    echo json_encode([
        "success" => false,
        "message" => "Unauthorized"
    ]);
    exit;
}

$user_id = (int) $_SESSION["user_id"];


/* =========================================================
   GET USER
========================================================= */

$stmt = $conn->prepare("
    SELECT email
    FROM users
    WHERE id = ?
    LIMIT 1
");

if (!$stmt) {
    echo json_encode([
        "success" => false,
        "message" => "User query failed"
    ]);
    exit;
}

$stmt->bind_param("i", $user_id);
$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode([
        "success" => false,
        "message" => "User not found"
    ]);
    exit;
}

$user = $result->fetch_assoc();

$email = $user["email"];

$stmt->close();


/* =========================================================
   GET PATIENT
========================================================= */

$stmt = $conn->prepare("
    SELECT id
    FROM patients
    WHERE email = ?
    LIMIT 1
");

if (!$stmt) {
    echo json_encode([
        "success" => false,
        "message" => "Patient query failed"
    ]);
    exit;
}

$stmt->bind_param("s", $email);
$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode([
        "success" => false,
        "message" => "Patient not found"
    ]);
    exit;
}

$patient = $result->fetch_assoc();

$patient_id = (int) $patient["id"];

$stmt->close();


/* =========================================================
   GET CURRENT QUEUE
========================================================= */

$stmt = $conn->prepare("
    SELECT
        id,
        queue_number,
        status,
        called_time
    FROM queue
    WHERE patient_id = ?
      AND status IN ('waiting', 'called', 'serving')
    ORDER BY check_in_time DESC
    LIMIT 1
");

if (!$stmt) {
    echo json_encode([
        "success" => false,
        "message" => "Queue query failed"
    ]);
    exit;
}

$stmt->bind_param("i", $patient_id);
$stmt->execute();

$result = $stmt->get_result();


/* =========================================================
   NO ACTIVE QUEUE
========================================================= */

if ($result->num_rows === 0) {

    echo json_encode([
        "success" => true,
        "queue_id" => null,
        "queue_number" => null,
        "status" => null,
        "called_time" => null
    ]);

    exit;
}


/* =========================================================
   RETURN QUEUE
========================================================= */

$queue = $result->fetch_assoc();

echo json_encode([
    "success" => true,
    "queue_id" => (int) $queue["id"],
    "queue_number" => $queue["queue_number"],
    "status" => $queue["status"],
    "called_time" => $queue["called_time"]
]);

exit;
?>