<?php
declare(strict_types=1);

function fail_response(string $message, int $statusCode = 400): void
{
  http_response_code($statusCode);
  echo $message;
  exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  fail_response('Method not allowed', 405);
}

$name = trim((string)($_POST['name'] ?? ''));
$email = trim((string)($_POST['email'] ?? ''));
$subject = trim((string)($_POST['subject'] ?? ''));
$message = trim((string)($_POST['message'] ?? ''));

if ($name === '' || $email === '' || $subject === '' || $message === '') {
  fail_response('Please fill in all required fields.');
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
  fail_response('Invalid email address.');
}

$mailjetApiKey = getenv('MAILJET_API_KEY') ?: '';
$mailjetApiSecret = getenv('MAILJET_API_SECRET') ?: '';
$mailjetFromEmail = getenv('MAILJET_FROM_EMAIL') ?: '';
$mailjetFromName = getenv('MAILJET_FROM_NAME') ?: 'Website Contact Form';
$receivingEmail = getenv('MAILJET_TO_EMAIL') ?: 'info@vitaltechmyanmar.com';

if ($mailjetApiKey === '' || $mailjetApiSecret === '' || $mailjetFromEmail === '') {
  fail_response('Mail service is not configured on server.', 500);
}

$payload = array(
  'Messages' => array(
    array(
      'From' => array(
        'Email' => $mailjetFromEmail,
        'Name' => $mailjetFromName
      ),
      'To' => array(
        array('Email' => $receivingEmail)
      ),
      'ReplyTo' => array(
        'Email' => $email,
        'Name' => $name
      ),
      'Subject' => $subject,
      'TextPart' => "From: {$name}\nEmail: {$email}\n\n{$message}"
    )
  )
);

$ch = curl_init('https://api.mailjet.com/v3.1/send');
if ($ch === false) {
  fail_response('Failed to initialize mail client.', 500);
}

curl_setopt_array($ch, array(
  CURLOPT_POST => true,
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_HTTPHEADER => array('Content-Type: application/json'),
  CURLOPT_USERPWD => $mailjetApiKey . ':' . $mailjetApiSecret,
  CURLOPT_POSTFIELDS => json_encode($payload),
  CURLOPT_TIMEOUT => 15
));

$result = curl_exec($ch);
$curlErrNo = curl_errno($ch);
$httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($curlErrNo !== 0 || $result === false) {
  fail_response('Unable to send message right now.', 502);
}

$decoded = json_decode($result, true);
$status = $decoded['Messages'][0]['Status'] ?? '';

if ($httpCode >= 200 && $httpCode < 300 && $status === 'success') {
  echo 'OK';
  exit;
}

fail_response('Mailjet rejected the message.', 502);
?>
