<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
    exit;
}

// Get JSON input
$json = file_get_contents('php://input');
$data = json_decode($json, true);

// Check if JSON was parsed correctly
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON data']);
    exit;
}

// Validate required fields
if (empty($data['name']) || empty($data['email']) || empty($data['phone'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

// Prepare data
$name = htmlspecialchars($data['name']);
$email = htmlspecialchars($data['email']);
$phone = htmlspecialchars($data['phone']);
$comment = !empty($data['comment']) ? htmlspecialchars($data['comment']) : 'Не указано';
$cartItems = $data['cart'];

// Prepare email message
$message = "Новый заказ с сайта ПромТехСнаб\n\n";
$message .= "Имя: $name\n";
$message .= "Email: $email\n";
$message .= "Телефон: $phone\n";
$message .= "Комментарий: $comment\n\n";
$message .= "Заказанные товары:\n";

$total = 0;
foreach ($cartItems as $item) {
    $itemTotal = $item['price'] * $item['quantity'];
    $total += $itemTotal;
    $message .= "- {$item['name']}: {$item['quantity']} {$item['unit']} × {$item['price']} руб. = $itemTotal руб.\n";
}

$message .= "\nИтого: $total руб.";

// Email settings
$to = 'snabpromgroup@mail.ru';
$subject = 'Новый заказ с сайта ПромТехСнаб';
$headers = "From: $email\r\n";
$headers .= "Reply-To: $email\r\n";

// Send email
if (mail($to, $subject, $message, $headers)) {
    echo json_encode(['success' => true, 'message' => 'Заказ успешно отправлен!']);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Ошибка при отправке заказа']);
}
?>