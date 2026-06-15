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

    // Disparar o webhook também em caso de falha (se habilitado).
    add_action('wp_mail_failed', 'custom_smtp_trigger_webhook_failed');

    // Registrar no log os e-mails enviados e as falhas (se o log estiver ativado).
    add_action('wp_mail_succeeded', 'custom_smtp_log_succeeded');
    add_action('wp_mail_failed', 'custom_smtp_log_failed');
}
add_action('plugins_loaded', 'custom_smtp_init');

// Adicionar link "Configurações" na linha do plugin (lista de plugins).
function custom_smtp_settings_link($links) {
    $settings_link = '<a href="' . esc_url(admin_url('options-general.php?page=custom-smtp')) . '">Configurações</a>';
    array_unshift($links, $settings_link);
    return $links;
}
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'custom_smtp_settings_link');

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
        'from_name'   => '',
        'from_email'  => '',
        'webhook_enabled'    => 'false',
        'webhook_url'        => '',
        'webhook_secret'     => '',
        'webhook_on_failure' => 'false',
        'log_enabled'         => 'false',
        'delete_on_uninstall' => 'false',
    );

    add_option('custom_smtp_options', $default_options);
}
register_activation_hook(__FILE__, 'custom_smtp_activate');

// Função de desativação
function custom_smtp_deactivate() {
    // Nada a fazer aqui. A limpeza das configurações acontece apenas na
    // exclusão do plugin (ver uninstall.php), quando "Limpar ao remover"
    // estiver marcado. Assim desativações temporárias preservam os dados.
}
register_deactivation_hook(__FILE__, 'custom_smtp_deactivate');

// O webhook é acionado exclusivamente pelo hook 'wp_mail_succeeded' registrado em
// custom_smtp_init(). O antigo caminho via filtro 'wp_mail' foi removido porque
// registrava uma nova closure a cada chamada de wp_mail() na mesma requisição,
// gerando disparos quadráticos (N²) do webhook e reenvio de dados de e-mails anteriores.