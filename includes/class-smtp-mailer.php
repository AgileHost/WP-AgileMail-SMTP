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

    // Remetente fixo (opcional). phpmailer_init dispara depois de o WordPress definir o
    // From padrão, então isto sobrescreve para garantir um remetente consistente. O
    // terceiro argumento (false) evita reescrever o cabeçalho From a cada chamada e o
    // próprio setFrom() também ajusta o envelope (Sender).
    if (!empty($options['from_email']) && is_email($options['from_email'])) {
        $phpmailer->setFrom($options['from_email'], $options['from_name'] ?? '', false);
    }

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
 * Aciona o webhook quando um email é enviado com sucesso.
 *
 * @param array $mail_data Os dados do email enviado
 */
function custom_smtp_trigger_webhook($mail_data) {
    if (!is_array($mail_data)) {
        $mail_data = array();
    }

    custom_smtp_send_webhook('email_sent', array(
        'to' => $mail_data['to'] ?? '',
        'subject' => $mail_data['subject'] ?? '',
        'message_length' => isset($mail_data['message']) ? strlen($mail_data['message']) : 0,
    ));
}

/**
 * Aciona o webhook quando o envio de um email falha (se "webhook em falha" estiver ativo).
 *
 * @param WP_Error $wp_error Erro retornado pelo WordPress, cujos dados carregam to/subject/message
 */
function custom_smtp_trigger_webhook_failed($wp_error) {
    $options = get_option('custom_smtp_options');
    if (!is_array($options) || ($options['webhook_on_failure'] ?? 'false') !== 'true') {
        return;
    }

    $error_data = is_wp_error($wp_error) ? $wp_error->get_error_data() : array();
    if (!is_array($error_data)) {
        $error_data = array();
    }

    // 'to' pode vir como array no WP_Error; normaliza para string.
    $to = $error_data['to'] ?? '';
    if (is_array($to)) {
        $to = implode(', ', $to);
    }

    custom_smtp_send_webhook('email_failed', array(
        'to' => $to,
        'subject' => $error_data['subject'] ?? '',
        'message_length' => isset($error_data['message']) ? strlen($error_data['message']) : 0,
        'error' => is_wp_error($wp_error) ? $wp_error->get_error_message() : '',
    ));
}

/**
 * Envia uma requisição ao webhook configurado. Caminho único de entrega usado tanto
 * pelo evento de sucesso quanto pelo de falha.
 *
 * @param string $event       Nome do evento ('email_sent' | 'email_failed')
 * @param array  $email_data  Metadados mínimos do email (sem cabeçalhos brutos)
 */
function custom_smtp_send_webhook($event, $email_data) {
    $options = get_option('custom_smtp_options');

    // Verificar se o webhook está ativado e se há uma URL configurada.
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

    // Preparar os dados a serem enviados para o webhook.
    // Os cabeçalhos brutos NÃO são enviados: poderiam vazar Bcc/Reply-To/From a
    // terceiros a cada e-mail. Enviamos apenas metadados mínimos do evento.
    $data = array(
        'event' => $event,
        'timestamp' => time(),
        'site_url' => site_url(),
        'site_name' => get_bloginfo('name'),
        'email_data' => $email_data,
    );

    $body = json_encode($data);

    $headers = array('Content-Type' => 'application/json');

    // Assinatura HMAC opcional: permite que o receptor verifique a autenticidade do
    // payload comparando o header com hash_hmac('sha256', corpo_recebido, segredo).
    if (!empty($options['webhook_secret'])) {
        $headers['X-AgileMail-Signature'] = 'sha256=' . hash_hmac('sha256', $body, $options['webhook_secret']);
    }

    // Enviar requisição para o webhook.
    $response = wp_remote_post($webhook_url, array(
        'method' => 'POST',
        'timeout' => 5,
        'redirection' => 0, // Não seguir redirecionamentos (evita bypass de SSRF via redirect)
        'httpversion' => '1.0',
        'blocking' => false, // Não bloquear para não atrasar o processo
        'headers' => $headers,
        'body' => $body,
        'cookies' => array()
    ));

    // Registrar no log se houver erro (opcional).
    if (is_wp_error($response)) {
        error_log('Erro ao acionar webhook de email: ' . $response->get_error_message());
    }
}

/**
 * Registra uma entrada no log de e-mails (option 'custom_smtp_log'), se o log estiver
 * ativado. O log é circular: guarda apenas as últimas 50 entradas.
 *
 * @param string $status     'enviado' | 'falhou'
 * @param array  $email_data Metadados do email (to/subject)
 * @param string $error      Mensagem de erro, quando status = 'falhou'
 */
function custom_smtp_record_log($status, $email_data, $error = '') {
    $options = get_option('custom_smtp_options');
    if (!is_array($options) || ($options['log_enabled'] ?? 'false') !== 'true') {
        return;
    }

    $to = $email_data['to'] ?? '';
    if (is_array($to)) {
        $to = implode(', ', $to);
    }

    $log = get_option('custom_smtp_log', array());
    if (!is_array($log)) {
        $log = array();
    }

    $log[] = array(
        'time' => time(),
        'to' => $to,
        'subject' => $email_data['subject'] ?? '',
        'status' => $status,
        'error' => $error,
    );

    // Mantém apenas as últimas 50 entradas (log circular).
    $log = array_slice($log, -50);

    update_option('custom_smtp_log', $log, false);
}

/**
 * Handler de sucesso: registra o e-mail enviado no log.
 *
 * @param array $mail_data Os dados do email enviado
 */
function custom_smtp_log_succeeded($mail_data) {
    if (!is_array($mail_data)) {
        $mail_data = array();
    }
    custom_smtp_record_log('enviado', array(
        'to' => $mail_data['to'] ?? '',
        'subject' => $mail_data['subject'] ?? '',
    ));
}

/**
 * Handler de falha: registra a tentativa de envio que falhou no log.
 *
 * @param WP_Error $wp_error Erro retornado pelo WordPress
 */
function custom_smtp_log_failed($wp_error) {
    $error_data = is_wp_error($wp_error) ? $wp_error->get_error_data() : array();
    if (!is_array($error_data)) {
        $error_data = array();
    }
    custom_smtp_record_log('falhou', array(
        'to' => $error_data['to'] ?? '',
        'subject' => $error_data['subject'] ?? '',
    ), is_wp_error($wp_error) ? $wp_error->get_error_message() : '');
}