<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Загрузка PHPMailer
require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

// Настройка заголовков для JSON и CORS
header('Content-Type: application/json; charset=utf-8');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");
mb_internal_encoding('UTF-8');

// Логирование
$logFile = __DIR__ . '/email_logs/' . date('Y-m-d') . '.log';
if (!file_exists(__DIR__ . '/email_logs')) {
    mkdir(__DIR__ . '/email_logs', 0755, true);
}

function logMessage($message, $logFile) {
    $timestamp = date('[Y-m-d H:i:s]');
    file_put_contents($logFile, $timestamp . ' ' . $message . PHP_EOL, FILE_APPEND);
}

try {
    // Разрешаем только POST-запросы
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        throw new Exception('Разрешен только метод POST');
    }

    // Получаем JSON данные
    $json = file_get_contents('php://input');
    $postData = json_decode($json, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Неверный формат JSON данных');
    }

    // Валидация обязательных полей
    if (empty($postData['name']) || empty($postData['phone'])) {
        throw new Exception('Имя и телефон обязательны для заполнения');
    }

    // Подготовка данных
    $name = htmlspecialchars($postData['name']);
    $email = filter_var($postData['email'] ?? '', FILTER_SANITIZE_EMAIL);
    $phone = htmlspecialchars($postData['phone']);
    $comment = htmlspecialchars($postData['comment'] ?? '');

    // Создание PHPMailer
    $mail = new PHPMailer(true);
    $mail->CharSet = 'UTF-8';
    
    // Настройка SMTP
    $mail->isSMTP();
    $mail->Host = 'smtp.mail.ru';
    $mail->SMTPAuth = true;
    $mail->Username = 'snabpromgroup@mail.ru';
    $mail->Password = 'lorA6fT4VqIzBlCPgn4J';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    $mail->Port = 465;

    // Настройка письма
    $mail->setFrom('snabpromgroup@mail.ru', 'ПромТехСнаб');
    $mail->addAddress('snabpromgroup@mail.ru');
    if (!empty($email)) {
        $mail->addReplyTo($email, $name);
    }

    // Формирование содержимого письма
    $message = "<h2>Новый заказ с сайта ПромТехСнаб</h2>";
    $message .= "<p><strong>Имя:</strong> {$name}</p>";
    $message .= "<p><strong>Email:</strong> {$email}</p>";
    $message .= "<p><strong>Телефон:</strong> {$phone}</p>";
    $message .= "<p><strong>Комментарий:</strong> {$comment}</p>";

    // Добавление товаров
    if (!empty($postData['cart'])) {
        $message .= "<h3>Заказанные товары:</h3>";
        $total = 0;
        
        foreach ($postData['cart'] as $item) {
            $itemTotal = $item['price'] * $item['quantity'];
            $total += $itemTotal;
            $message .= "<p>- {$item['name']}: {$item['quantity']} {$item['unit']} × {$item['price']} руб. = {$itemTotal} руб.</p>";
        }
        
        $message .= "<p><strong>Итого: {$total} руб.</strong></p>";
    }

    $mail->isHTML(true);
    $mail->Subject = 'Новый заказ от ' . $name;
    $mail->Body = $message;
    $mail->AltBody = strip_tags($message);

    // Отправка письма
    $mail->send();
    
    logMessage("Письмо успешно отправлено для $name ($phone)", $logFile);
    echo json_encode(['success' => true, 'message' => 'Ваш заказ успешно отправлен!']);

} catch (Exception $e) {
    logMessage("ОШИБКА: " . $e->getMessage(), $logFile);
    http_response_code(400);
    echo json_encode([
        'success' => false, 
        'message' => 'Произошла ошибка: ' . $e->getMessage()
    ]);
}
?>
