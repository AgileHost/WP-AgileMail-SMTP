<?php
/**
 * Funções de administração do plugin
 */

// Adicionar menu na administração
function custom_smtp_add_admin_menu() {
    add_options_page(
        'Configurações SMTP', 
        'SMTP Personalizado', 
        'manage_options', 
        'custom-smtp', 
        'custom_smtp_options_page'
    );
}

// Conteúdo da página de opções
function custom_smtp_options_page() {
    // Verificar permissões
    if (!current_user_can('manage_options')) {
        return;
    }
    
    // Salvar configurações?
    if (isset($_POST['custom_smtp_save'])) {
        check_admin_referer('custom_smtp_save_settings');
        
        $options = array(
            'smtp_host'         => sanitize_text_field($_POST['smtp_host']),
            'smtp_port'         => intval($_POST['smtp_port']),
            'smtp_auth'         => sanitize_text_field($_POST['smtp_auth']),
            'smtp_user'         => sanitize_text_field($_POST['smtp_user']),
            'smtp_pass'         => $_POST['smtp_pass'], // Senha não é sanitizada para evitar corromper caracteres especiais
            'smtp_secure'       => sanitize_text_field($_POST['smtp_secure']),
            'webhook_enabled' => isset($_POST['webhook_enabled']) ? 'true' : 'false',
            'webhook_url'     => esc_url_raw($_POST['webhook_url']),
        );
        
        update_option('custom_smtp_options', $options);
        echo '<div class="updated"><p>Configurações salvas com sucesso!</p></div>';
    }
    
    // Obter configurações atuais
    $options = get_option('custom_smtp_options', array(
        'smtp_host'   => '',
        'smtp_port'   => '25',
        'smtp_auth'   => 'true',
        'smtp_user'   => '',
        'smtp_pass'   => '',
        'smtp_secure' => 'none',
        'webhook_enabled' => 'false',
        'webhook_url'     => '',
    ));
    
    // Exibir formulário de configurações
    ?>
    <div class="wrap">
        <h1>Configurações de SMTP</h1>
        
        <form method="post" action="">
            <?php wp_nonce_field('custom_smtp_save_settings'); ?>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="smtp_host">Servidor SMTP</label>
                    </th>
                    <td>
                        <input type="text" id="smtp_host" name="smtp_host" 
                               value="<?php echo esc_attr($options['smtp_host']); ?>" 
                               class="regular-text" />
                        <p class="description">
                            Digite o endereço do servidor SMTP. Ex: smtp.gmail.com
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="smtp_port">Porta</label>
                    </th>
                    <td>
                        <input type="number" id="smtp_port" name="smtp_port" 
                               value="<?php echo esc_attr($options['smtp_port']); ?>" 
                               class="small-text" />
                        <p class="description">
                            Digite a porta do servidor SMTP. Normalmente 25, 465 ou 587.
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="smtp_auth">Tipo de Autenticação</label>
                    </th>
                    <td>
                        <select id="smtp_auth" name="smtp_auth">
                            <option value="true" <?php selected($options['smtp_auth'], 'true'); ?>>
                                Com autenticação
                            </option>
                            <option value="false" <?php selected($options['smtp_auth'], 'false'); ?>>
                                Nenhuma
                            </option>
                        </select>
                        <p class="description">
                            O servidor SMTP requer autenticação?
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="smtp_user">Conta</label>
                    </th>
                    <td>
                        <input type="text" id="smtp_user" name="smtp_user" 
                               value="<?php echo esc_attr($options['smtp_user']); ?>" 
                               class="regular-text" />
                        <p class="description">
                            Digite o nome de usuário para autenticação no servidor SMTP.
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="smtp_pass">Senha</label>
                    </th>
                    <td>
                        <input type="password" id="smtp_pass" name="smtp_pass" 
                               value="<?php echo esc_attr($options['smtp_pass']); ?>" 
                               class="regular-text" />
                        <p class="description">
                            Digite a senha para autenticação no servidor SMTP.
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="smtp_secure">Tipo de Segurança</label>
                    </th>
                    <td>
                        <select id="smtp_secure" name="smtp_secure">
                            <option value="none" <?php selected($options['smtp_secure'], 'none'); ?>>
                                Nenhuma
                            </option>
                            <option value="ssl" <?php selected($options['smtp_secure'], 'ssl'); ?>>
                                SSL
                            </option>
                            <option value="tls" <?php selected($options['smtp_secure'], 'tls'); ?>>
                                TLS
                            </option>
                        </select>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="webhook_enabled">Ativar Webhook</label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox" id="webhook_enabled" name="webhook_enabled" 
                                   <?php checked($options['webhook_enabled'], 'true'); ?> />
                            Notificar webhook quando emails forem enviados
                        </label>
                        <p class="description">
                            Quando ativado, cada vez que um email for enviado, o sistema notificará o webhook configurado.
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="webhook_url">URL do Webhook</label>
                    </th>
                    <td>
                        <input type="url" id="webhook_url" name="webhook_url" 
                               value="<?php echo esc_attr($options['webhook_url']); ?>" 
                               class="regular-text" />
                        <p class="description">
                            Digite a URL completa que será chamada quando um email for enviado.
                            Ex: https://seu-site.com/api/email-notification
                        </p>
                    </td>
                </tr>

            </table>
            
            <p class="submit">
                <input type="submit" name="custom_smtp_save" class="button-primary" 
                       value="Salvar Configurações" />
                <input type="submit" name="custom_smtp_test" class="button-secondary" 
                       value="Enviar E-mail de Teste" />
            </p>
        </form>
    </div>
    <?php
    
    // Enviar e-mail de teste?
    if (isset($_POST['custom_smtp_test'])) {
        check_admin_referer('custom_smtp_save_settings');
        
        $to = get_option('admin_email');
        $subject = 'Teste de SMTP - ' . get_bloginfo('name');
        $message = 'Este é um e-mail de teste enviado pelo plugin Custom SMTP.';
        $headers = array('Content-Type: text/html; charset=UTF-8');
        
        $result = wp_mail($to, $subject, $message, $headers);
        
        if ($result) {
            echo '<div class="updated"><p>E-mail de teste enviado com sucesso para ' . esc_html($to) . '!</p>';
            
            // Mencionar sobre o webhook se estiver ativado
            $options = get_option('custom_smtp_options');
            if ($options['webhook_enabled'] === 'true' && !empty($options['webhook_url'])) {
                echo '<p>O webhook também foi acionado para a URL: ' . esc_html($options['webhook_url']) . '</p>';
            }
            
            echo '</div>';
        } else {
            echo '<div class="error"><p>Falha ao enviar e-mail de teste. Verifique suas configurações.</p></div>';
        }
    }
}