<?php
/**
 * Executado pelo WordPress ao excluir o plugin.
 *
 * Apaga todas as configurações salvas somente se o usuário tiver marcado
 * "Limpar ao remover" (delete_on_uninstall) na página de administração.
 */

// Se não for chamado pelo WordPress durante a desinstalação, abortar.
if (!defined('WP_UNINSTALL_PLUGIN')) {
    die;
}

$options = get_option('custom_smtp_options');
if (is_array($options) && isset($options['delete_on_uninstall']) && $options['delete_on_uninstall'] === 'true') {
    delete_option('custom_smtp_options');
}
