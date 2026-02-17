<?php

/**
 * Envio de email via SMTP (Gmail ou outro).
 * Usa PHPMailer instalado via Composer.
 *
 * Uso: enviar_email_smtp('destino@email.com', 'Assunto', 'Corpo em texto');
 * Retorna true se enviou, false em caso de erro.
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function enviar_email_smtp($destino, $assunto, $corpo_texto)
{
    $base = defined('BASE_URL') ? BASE_URL : __DIR__;
    $autoload = $base . '/vendor/autoload.php';
    if (!is_file($autoload)) {
        error_log('SMCPA email: composer install não foi executado (vendor/autoload.php não encontrado).');
        return false;
    }
    $config_file = $base . '/config_email.php';
    if (!is_file($config_file)) {
        error_log('SMCPA email: config_email.php não encontrado. Copie config_email.php.example e configure.');
        return false;
    }
    $conf = include $config_file;
    if (empty($conf['smtp_ativo']) || empty($conf['smtp_usuario']) || empty($conf['smtp_senha'])) {
        error_log('SMCPA email: SMTP não configurado ou credenciais vazias em config_email.php');
        return false;
    }

    require_once $autoload;



    try {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = $conf['smtp_host'] ?? 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = $conf['smtp_usuario'];
        $mail->Password   = $conf['smtp_senha'];
        $mail->SMTPSecure = ($conf['smtp_seguro'] ?? 'tls') === 'ssl'
            ? PHPMailer::ENCRYPTION_SMTPS
            : PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = (int) ($conf['smtp_porta'] ?? 587);
        $mail->CharSet    = 'UTF-8';
        $mail->Encoding   = 'base64';

        $mail->setFrom(
            $conf['remetente'] ?? $conf['smtp_usuario'],
            $conf['nome_remetente'] ?? 'SMCPA'
        );
        $mail->addAddress($destino);
        $mail->Subject = $assunto;
        $mail->Body    = $corpo_texto;
        $mail->isHTML(false);

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log('SMCPA email: ' . $e->getMessage());
        return false;
    }
}
