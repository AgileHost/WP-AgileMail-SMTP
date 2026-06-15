<?php
/**
 * Configurar o PHPMailer para usar as configurações SMTP personalizadas
 */

function custom_smtp_configure_phpmailer($phpmailer) {
    // Obter configurações SMTP
    $options = get_option('custom_smtp_options');

    // Verificar se existem configurações
    if (!is_array($options) || empty($options['smtp_host'])) {
        return;
    }

    // Configurar o PHPMailer para usar SMTP
    $phpmailer->isSMTP();

    // Configurações do servidor SMTP
    $phpmailer->Host = $options['smtp_host'];
    $phpmailer->Port = isset($options['smtp_port']) ? $options['smtp_port'] : 25;

    // Autenticação
    if (($options['smtp_auth'] ?? 'false') === 'true') {
        $phpmailer->SMTPAuth = true;
        $phpmailer->Username = $options['smtp_user'] ?? '';
        $phpmailer->Password = $options['smtp_pass'] ?? '';
    } else {
        $phpmailer->SMTPAuth = false;
    }

    // Segurança
    if (($options['smtp_secure'] ?? 'none') !== 'none') {
        // Segurança explícita escolhida (ssl/tls): desativa o AutoTLS para evitar
        // conflito de TLS duplo.
        $phpmailer->SMTPSecure = $options['smtp_secure'];
        $phpmailer->SMTPAutoTLS = false;
    }
    // Quando nenhuma segurança é escolhida, mantém o SMTPAutoTLS padrão (true) para
    // que a conexão seja promovida a TLS automaticamente caso o servidor suporte
    // STARTTLS, evitando tráfego de credenciais/e-mail em texto puro.
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
    if (!is_array($options) || ($options['webhook_enabled'] ?? 'false') !== 'true' || empty($options['webhook_url'])) {
        return;
    }

    // Proteção contra SSRF: wp_http_validate_url() rejeita esquemas não-HTTP e
    // endereços internos/privados (localhost, 127.0.0.1, 169.254.x, redes privadas),
    // impedindo que o webhook seja usado para sondar a rede interna do servidor.
    $webhook_url = wp_http_validate_url($options['webhook_url']);
    if (!$webhook_url) {
        error_log('Webhook de email ignorado: URL inválida ou apontando para endereço interno: ' . $options['webhook_url']);
        return;
    }

    if (!is_array($mail_data)) {
        $mail_data = array();
    }

    // Preparar os dados a serem enviados para o webhook.
    // Os cabeçalhos brutos NÃO são enviados: poderiam vazar Bcc/Reply-To/From a
    // terceiros a cada e-mail. Enviamos apenas metadados mínimos do evento.
    $data = array(
        'event' => 'email_sent',
        'timestamp' => time(),
        'site_url' => site_url(),
        'site_name' => get_bloginfo('name'),
        'email_data' => array(
            'to' => $mail_data['to'] ?? '',
            'subject' => $mail_data['subject'] ?? '',
            'message_length' => isset($mail_data['message']) ? strlen($mail_data['message']) : 0,
        )
    );

    // Enviar requisição para o webhook
    $response = wp_remote_post($webhook_url, array(
        'method' => 'POST',
        'timeout' => 5,
        'redirection' => 0, // Não seguir redirecionamentos (evita bypass de SSRF via redirect)
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