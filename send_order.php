<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// 1. Загрузка необходимых файлов PHPMailer
require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

// 2. Настройка кодировки и заголовков
header('Content-Type: application/json; charset=utf-8');
mb_internal_encoding('UTF-8');

// 3. Логирование (создаем лог-файл)
$logFile = __DIR__ . '/email_logs/' . date('Y-m-d') . '.log';
if (!file_exists(__DIR__ . '/email_logs')) {
    mkdir(__DIR__ . '/email_logs', 0755, true);
}

function logMessage($message, $logFile) {
    $timestamp = date('[Y-m-d H:i:s]');
    file_put_contents($logFile, $timestamp . ' ' . $message . PHP_EOL, FILE_APPEND);
}

// 4. Обработка входящих данных
try {
    // Проверка метода запроса
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Метод не поддерживается');
    }

    // Получение данных формы
    $postData = array_merge($_POST, json_decode(file_get_contents('php://input'), true) ?: []);

    // Валидация обязательных полей
    if (empty($postData['name']) || empty($postData['phone'])) {
        throw new Exception('Имя и телефон обязательны для заполнения');
    }

    // Подготовка данных
    $name = htmlspecialchars($postData['name']);
    $email = filter_var($postData['email'] ?? '', FILTER_SANITIZE_EMAIL);
    $phone = htmlspecialchars($postData['phone']);
    $comment = htmlspecialchars($postData['comment'] ?? '');

    // 5. Создание PHPMailer
    $mail = new PHPMailer(true);
    $mail->CharSet = 'UTF-8';
    
    // Включение логирования (для отладки)
    $mail->SMTPDebug = 2; // 0 = off, 1 = client, 2 = client and server
    $mail->Debugoutput = function($str, $level) use ($logFile) {
        logMessage("SMTP (level $level): $str", $logFile);
    };

    // 6. Настройка SMTP (замените на свои данные)
    $mail->isSMTP();
    $mail->Host = 'smtp.mail.ru';
    $mail->SMTPAuth = true;
    $mail->Username = 'snabpromgroup@mail.ru';
    $mail->Password = 'lorA6fT4VqIzBlCPgn4J';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    $mail->Port = 465;

    // 7. Настройка письма
    $mail->setFrom('snabpromgroup@mail.ru', 'ПромТехСнаб');
    $mail->addAddress('snabpromgroup@mail.ru');
    if (!empty($email)) {
        $mail->addReplyTo($email, $name);
    }

    // 8. Обработка вложения
    if (!empty($_FILES['file']['tmp_name'])) {
        // Проверка типа файла
        $allowedTypes = [
            'application/pdf', 
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'image/jpeg', 
            'image/png',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        ];
        
        $fileType = mime_content_type($_FILES['file']['tmp_name']);
        
        if (!in_array($fileType, $allowedTypes)) {
            throw new Exception('Недопустимый тип файла. Разрешены: PDF, DOC, DOCX, JPEG, PNG, XLS, XLSX');
        }

        // Проверка размера файла (до 10MB)
        if ($_FILES['file']['size'] > 10 * 1024 * 1024) {
            throw new Exception('Файл слишком большой. Максимальный размер: 10MB');
        }

        // Безопасное имя файла
        $safeFilename = preg_replace('/[^a-z0-9\._-]+/i', '_', $_FILES['file']['name']);
        $mail->addAttachment($_FILES['file']['tmp_name'], $safeFilename);
        
        logMessage("Прикреплен файл: $safeFilename (тип: $fileType)", $logFile);
    }

    // 9. Формирование содержимого письма
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

    // 10. Отправка письма
    $mail->send();
    
    logMessage("Письмо успешно отправлено для $name ($phone)", $logFile);
    echo json_encode(['success' => true, 'message' => 'Ваш заказ успешно отправлен!']);

} catch (Exception $e) {
    logMessage("ОШИБКА: " . $e->getMessage(), $logFile);
    echo json_encode([
        'success' => false, 
        'message' => 'Произошла ошибка: ' . $e->getMessage()
    ]);
}
?>