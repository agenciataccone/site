<?php
header('Content-Type: application/json');

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode(['success' => false]);
    exit;
}

$smtp_host  = 'smtp.titan.email';
$smtp_user  = 'atendimento@taccone.com.br';
$smtp_pass  = '3jxmp!oH';
$smtp_port  = 587;
$para       = 'atendimento@taccone.com.br';

$nome  = strip_tags(trim($_POST['name']    ?? 'Não informado'));
$email = strip_tags(trim($_POST['email']   ?? 'Não informado'));
$tel   = strip_tags(trim($_POST['text']    ?? 'Não informado'));
$msg   = strip_tags(trim($_POST['message'] ?? 'Sem mensagem'));

$assunto = "Site TACCONE® - Contato de $nome";
$corpo   = "Nome: $nome\nE-mail: $email\nTelefone: $tel\n\nMensagem:\n$msg";

function smtp_send($host, $port, $user, $pass, $from, $to, $subject, $body) {
    $conn = @fsockopen($host, $port, $errno, $errstr, 15);
    if (!$conn) return "Falha na conexão: $errstr ($errno)";

    $resp = fgets($conn, 512);
    if (substr($resp, 0, 3) !== '220') { fclose($conn); return "Sem 220: $resp"; }

    fputs($conn, "EHLO smtp.titan.email\r\n");
    while ($line = fgets($conn, 512)) {
        if (substr($line, 3, 1) === ' ') break;
    }

    fputs($conn, "STARTTLS\r\n");
    $resp = fgets($conn, 512);
    if (substr($resp, 0, 3) !== '220') { fclose($conn); return "STARTTLS falhou: $resp"; }

    stream_socket_enable_crypto($conn, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);

    fputs($conn, "EHLO smtp.titan.email\r\n");
    while ($line = fgets($conn, 512)) {
        if (substr($line, 3, 1) === ' ') break;
    }

    // AUTH PLAIN (uma única string base64: \0usuario\0senha)
    $auth = base64_encode("\0" . $user . "\0" . $pass);
    fputs($conn, "AUTH PLAIN $auth\r\n");
    $resp = fgets($conn, 512);
    if (substr($resp, 0, 3) !== '235') { fclose($conn); return "AUTH PLAIN falhou: $resp"; }

    fputs($conn, "MAIL FROM:<$from>\r\n");
    $resp = fgets($conn, 512);
    if (substr($resp, 0, 3) !== '250') { fclose($conn); return "MAIL FROM falhou: $resp"; }

    fputs($conn, "RCPT TO:<$to>\r\n");
    $resp = fgets($conn, 512);
    if (substr($resp, 0, 3) !== '250') { fclose($conn); return "RCPT TO falhou: $resp"; }

    fputs($conn, "DATA\r\n");
    $resp = fgets($conn, 512);
    if (substr($resp, 0, 3) !== '354') { fclose($conn); return "DATA falhou: $resp"; }

    $headers  = "From: TACCONE Site <$from>\r\n";
    $headers .= "To: $to\r\n";
    $headers .= "Reply-To: $from\r\n";
    $headers .= "Subject: $subject\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $headers .= "Date: " . date('r') . "\r\n";

    fputs($conn, $headers . "\r\n" . $body . "\r\n.\r\n");
    $resp = fgets($conn, 512);
    if (substr($resp, 0, 3) !== '250') { fclose($conn); return "Envio falhou: $resp"; }

    fputs($conn, "QUIT\r\n");
    fclose($conn);
    return true;
}

$resultado = smtp_send($smtp_host, $smtp_port, $smtp_user, $smtp_pass, $smtp_user, $para, $assunto, $corpo);

if ($resultado === true) {
    echo json_encode(['success' => true]);
} else {
    error_log("Erro SMTP TACCONE: $resultado");
    echo json_encode(['success' => false, 'debug' => $resultado]);
}
exit;
?>