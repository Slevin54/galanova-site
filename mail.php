<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Content-Type: application/json; charset=utf-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$raw = file_get_contents('php://input');
$input = json_decode($raw, true);

if (!$input) {
    echo json_encode(['success' => false, 'error' => 'No data']);
    exit;
}

// Защита от спама (honeypot)
if (!empty($input['company_website_honeypot'])) {
    echo json_encode(['success' => true]);
    exit;
}

$topic   = !empty($input['topic']) ? htmlspecialchars(trim($input['topic'])) : 'Общий вопрос';
$contact = !empty($input['contact']) ? htmlspecialchars(trim($input['contact'])) : '';
$comment = !empty($input['comment']) ? htmlspecialchars(trim($input['comment'])) : 'Не указан';

if (empty($contact)) {
    echo json_encode(['success' => false, 'error' => 'Контакт не указан']);
    exit;
}

// === ВАШИ ДАННЫЕ ДЛЯ ОТПРАВКИ ===
$smtpUser = 'kombinir@mail.ru';
// ВСТАВЬТЕ ВАШ 16-ЗНАЧНЫЙ ПАРОЛЬ ВМЕСТО ВСТАВЬТЕ_СЮДА_ПАРОЛЬ_ПРИЛОЖЕНИЯ:
$smtpPass = 'av4qvtUyv6n8gHL2crfu'; 

$to = 'kombinir@mail.ru';
$subject = "Заявка с сайта: " . $topic;
$messageText = "Поступила новая заявка с сайта:\n\n"
             . "Тема обращения: " . $topic . "\n"
             . "Контакт клиента: " . $contact . "\n"
             . "Комментарий: " . $comment . "\n\n"
             . "Время отправки: " . date('d.m.Y H:i:s');

// Функция прямой отправки через защищенный SMTP Mail.ru
function sendMailRuSmtp($to, $subject, $body, $user, $pass) {
    $socket = fsockopen("ssl://smtp.mail.ru", 465, $errno, $errstr, 15);
    if (!$socket) return false;

    $read = function() use ($socket) {
        $data = "";
        while ($str = fgets($socket, 515)) {
            $data .= $str;
            if (substr($str, 3, 1) == " ") break;
        }
        return $data;
    };

    $write = function($cmd) use ($socket) {
        fputs($socket, $cmd . "\r\n");
    };

    $read();
    $write("EHLO galanova-elena.ru");
    $read();
    $write("AUTH LOGIN");
    $read();
    $write(base64_encode($user));
    $read();
    $write(base64_encode($pass));
    $authRes = $read();
    if (strpos($authRes, '235') === false) {
        fclose($socket);
        return false;
    }

    $write("MAIL FROM: <$user>");
    $read();
    $write("RCPT TO: <$to>");
    $read();
    $write("DATA");
    $read();

    $headers  = "From: =?UTF-8?B?" . base64_encode("Сайт Елена Галанова") . "?= <$user>\r\n";
    $headers .= "To: <$to>\r\n";
    $headers .= "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

    $write($headers . "\r\n" . $body . "\r\n.");
    $sendRes = $read();
    $write("QUIT");
    fclose($socket);

    return (strpos($sendRes, '250') !== false);
}

$sent = sendMailRuSmtp($to, $subject, $messageText, $smtpUser, $smtpPass);

echo json_encode(['success' => $sent]);