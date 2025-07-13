<?php
header('Content-Type: application/json');

// Проверка, что запрос отправлен методом POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Метод не поддерживается']);
    exit;
}

// Получение данных из формы
$data = json_decode(file_get_contents('php://input'), true);

// Проверка обязательных полей
if (empty($data['name']) || empty($data['email']) || empty($data['phone'])) {
    echo json_encode(['success' => false, 'message' => 'Заполните обязательные поля']);
    exit;
}

// Подготовка данных
$name = htmlspecialchars($data['name']);
$email = htmlspecialchars($data['email']);
$phone = htmlspecialchars($data['phone']);
$comment = !empty($data['comment']) ? htmlspecialchars($data['comment']) : 'Не указано';
$cartItems = $data['cart'];

// Формирование текста письма
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

// Настройки почты
$to = 'snabpromgroup@mail.ru'; // Ваш email
$subject = 'Новый заказ с сайта ПромТехСнаб';
$headers = "From: $email\r\n";
$headers .= "Reply-To: $email\r\n";

// Отправка письма
$mailSent = mail($to, $subject, $message, $headers);

if ($mailSent) {
    echo json_encode(['success' => true, 'message' => 'Заказ успешно отправлен!']);
} else {
    echo json_encode(['success' => false, 'message' => 'Ошибка при отправке заказа']);
}
?>