<?php
// Start user session
session_name('user_session');
session_start();

// Load database connection
require_once __DIR__ . "/../config/db.php";

// Set JSON response type
header('Content-Type: application/json; charset=utf-8');

// Allow only logged-in users
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'user') {
  http_response_code(401);
  echo json_encode([
    "ok" => false,
    "error" => "Unauthorized"
  ]);
  exit;
}

// Read JSON data from request
$raw = file_get_contents("php://input");
$body = json_decode($raw, true);

// If request body is not valid JSON, use empty array
if (!is_array($body)) {
  $body = [];
}

// Get event type and payload
$eventType = trim((string)($body['event_type'] ?? ''));
$payload   = $body['payload'] ?? [];

// Check if event type is missing
if ($eventType === '') {
  http_response_code(400);
  echo json_encode([
    "ok" => false,
    "error" => "Missing event_type"
  ]);
  exit;
}

// Get current user ID
$userId = (int) $_SESSION['user_id'];

// Convert payload into JSON text
$payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE);

// Save chatbot event to database
$stmt = $conn->prepare("
  INSERT INTO chatbot_events (user_id, event_type, payload_json, created_at)
  VALUES (?, ?, ?, NOW())
");
$stmt->bind_param("iss", $userId, $eventType, $payloadJson);
$stmt->execute();

// Return success response
echo json_encode([
  "ok" => true
], JSON_UNESCAPED_UNICODE);
exit;
