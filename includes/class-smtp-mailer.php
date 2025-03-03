<?php
/**
 * Configurar o PHPMailer para usar as configurações SMTP personalizadas
 */

function custom_smtp_configure_phpmailer($phpmailer) {
    // Obter configurações SMTP
    $options = get_option('custom_smtp_options');
    
    // Verificar se existem configurações
    if (empty($options['smtp_host'])) {
        return;
    }
    
    // Configurar o PHPMailer para usar SMTP
    $phpmailer->isSMTP();
    
    // Configurações do servidor SMTP
    $phpmailer->Host = $options['smtp_host'];
    $phpmailer->Port = $options['smtp_port'];
    
    // Autenticação
    if ($options['smtp_auth'] === 'true') {
        $phpmailer->SMTPAuth = true;
        $phpmailer->Username = $options['smtp_user'];
        $phpmailer->Password = $options['smtp_pass'];
    } else {
        $phpmailer->SMTPAuth = false;
    }
    
    // Segurança
    if ($options['smtp_secure'] !== 'none') {
        $phpmailer->SMTPSecure = $options['smtp_secure'];
    }
    
    // Configurações adicionais
    $phpmailer->SMTPAutoTLS = false; // Desativar TLS automático para evitar conflitos
}

/**
 * Aciona o webhook quando um email é enviado com sucesso
 * 
 * @param array $mail_data Os dados do email enviado
 */
function custom_smtp_trigger_webhook($mail_data) {
    // Obter configurações
    $options = get_option('custom_smtp_options');
    
    // Verificar se o webhook está ativado e se há uma URL configurada
    if ($options['webhook_enabled'] !== 'true' || empty($options['webhook_url'])) {
        return;
    }
    
    // Preparar os dados a serem enviados para o webhook
    $data = array(
        'event' => 'email_sent',
        'timestamp' => current_time('timestamp'),
        'site_url' => site_url(),
        'site_name' => get_bloginfo('name'),
        'email_data' => array(
            'to' => $mail_data['to'],
            'subject' => $mail_data['subject'],
            'message_length' => strlen($mail_data['message']),
            'headers' => $mail_data['headers'],
        )
    );
    
    // Enviar requisição para o webhook
    $response = wp_remote_post($options['webhook_url'], array(
        'method' => 'POST',
        'timeout' => 5,
        'redirection' => 5,
        'httpversion' => '1.0',
        'blocking' => false, // Não bloquear para não atrasar o processo
        'headers' => array('Content-Type' => 'application/json'),
        'body' => json_encode($data),
        'cookies' => array()
    ));
    
    // Registrar no log se houver erro (opcional)
    if (is_wp_error($response)) {
        error_log('Erro ao acionar webhook de email: ' . $response->get_error_message());
    }
}