<?php
/**
 * Plugin Name: WP AgileMail SMTP
 * Plugin URI: https://www.agilehost.com.br
 * Description: Configura o WordPress para enviar e-mails através de um servidor SMTP da AgileMail (poderá configurar o seu próprio também).
 * Version: 1.0
 * Author: Marcos V Bohrer
 * Text Domain: WP-AgileMail-SMTP
 */

// Se este arquivo for chamado diretamente, abortar.
if (!defined('WPINC')) {
    die;
}

// Definir constantes
define('CUSTOM_SMTP_VERSION', '1.0');
define('CUSTOM_SMTP_PLUGIN_DIR', plugin_dir_path(__FILE__));

// Incluir arquivos necessários
require_once CUSTOM_SMTP_PLUGIN_DIR . 'admin/settings-page.php';
require_once CUSTOM_SMTP_PLUGIN_DIR . 'includes/class-smtp-mailer.php';

// Inicializar o plugin
function custom_smtp_init() {
    // Registrar configurações
    register_setting('custom_smtp_options', 'custom_smtp_options');
    
    // Adicionar página de menu na administração
    add_action('admin_menu', 'custom_smtp_add_admin_menu');
    
    // Substituir a função wp_mail
    add_action('phpmailer_init', 'custom_smtp_configure_phpmailer');

    // Monitorar envio de emails para acionar webhook
    add_action('wp_mail_succeeded', 'custom_smtp_trigger_webhook');
}
add_action('plugins_loaded', 'custom_smtp_init');

// Função de ativação
function custom_smtp_activate() {
    // Configurações padrão
    $default_options = array(
        'smtp_host'   => '',
        'smtp_port'   => '25',
        'smtp_auth'   => 'true',
        'smtp_user'   => '',
        'smtp_pass'   => '',
        'smtp_secure' => 'none',
        'webhook_enabled' => 'false',
        'webhook_url'     => '',
    );
    
    add_option('custom_smtp_options', $default_options);
}
register_activation_hook(__FILE__, 'custom_smtp_activate');

// Função de desativação
function custom_smtp_deactivate() {
    // Nada a fazer por enquanto
}
register_deactivation_hook(__FILE__, 'custom_smtp_deactivate');

// Webhook
function custom_smtp_monitor_email($args) {
    // Armazenar os argumentos originais para uso posterior
    add_filter('wp_mail_succeeded', function($result) use ($args) {
        custom_smtp_trigger_webhook($args);
        return $result;
    });
    
    return $args;
}
add_filter('wp_mail', 'custom_smtp_monitor_email');