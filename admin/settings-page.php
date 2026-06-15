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

        // Configurações existentes (para preservar a senha quando o campo vier vazio).
        $existing = get_option('custom_smtp_options', array());
        if (!is_array($existing)) {
            $existing = array();
        }

        // A senha não é reexibida no formulário; um campo em branco significa
        // "manter a senha atual". Não é sanitizada para não corromper caracteres especiais.
        $posted_pass = isset($_POST['smtp_pass']) ? $_POST['smtp_pass'] : '';
        $smtp_pass   = ($posted_pass !== '') ? $posted_pass : (isset($existing['smtp_pass']) ? $existing['smtp_pass'] : '');

        // O segredo do webhook segue o mesmo tratamento da senha: não é reexibido e um
        // campo vazio mantém o valor atual. Também não é sanitizado.
        $posted_secret  = isset($_POST['webhook_secret']) ? $_POST['webhook_secret'] : '';
        $webhook_secret = ($posted_secret !== '') ? $posted_secret : (isset($existing['webhook_secret']) ? $existing['webhook_secret'] : '');

        $options = array(
            'smtp_host'         => sanitize_text_field($_POST['smtp_host']),
            'smtp_port'         => intval($_POST['smtp_port']),
            'smtp_auth'         => sanitize_text_field($_POST['smtp_auth']),
            'smtp_user'         => sanitize_text_field($_POST['smtp_user']),
            'smtp_pass'         => $smtp_pass,
            'smtp_secure'       => sanitize_text_field($_POST['smtp_secure']),
            'from_name'         => sanitize_text_field($_POST['from_name']),
            'from_email'        => sanitize_email($_POST['from_email']),
            'webhook_enabled'    => isset($_POST['webhook_enabled']) ? 'true' : 'false',
            'webhook_url'        => esc_url_raw($_POST['webhook_url']),
            'webhook_secret'     => $webhook_secret,
            'webhook_on_failure' => isset($_POST['webhook_on_failure']) ? 'true' : 'false',
            'log_enabled'         => isset($_POST['log_enabled']) ? 'true' : 'false',
            'delete_on_uninstall' => isset($_POST['delete_on_uninstall']) ? 'true' : 'false',
        );

        update_option('custom_smtp_options', $options);
        echo '<div class="updated"><p>Configurações salvas com sucesso!</p></div>';
    }

    // Limpar o log de e-mails?
    if (isset($_POST['custom_smtp_clear_log'])) {
        check_admin_referer('custom_smtp_save_settings');
        delete_option('custom_smtp_log');
        echo '<div class="updated"><p>Log de e-mails limpo.</p></div>';
    }

    // Obter configurações atuais
    $options = get_option('custom_smtp_options', array(
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
                               value="" autocomplete="new-password"
                               placeholder="<?php echo !empty($options['smtp_pass']) ? '••••••••' : ''; ?>"
                               class="regular-text" />
                        <p class="description">
                            Digite a senha para autenticação no servidor SMTP.
                            <?php if (!empty($options['smtp_pass'])) echo 'Deixe em branco para manter a senha atual.'; ?>
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
                        <label for="from_name">Nome do Remetente</label>
                    </th>
                    <td>
                        <input type="text" id="from_name" name="from_name"
                               value="<?php echo esc_attr($options['from_name'] ?? ''); ?>"
                               class="regular-text" />
                        <p class="description">
                            Opcional. Nome exibido como remetente dos e-mails. Ex: Equipe do Site
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="from_email">E-mail do Remetente</label>
                    </th>
                    <td>
                        <input type="email" id="from_email" name="from_email"
                               value="<?php echo esc_attr($options['from_email'] ?? ''); ?>"
                               class="regular-text" />
                        <p class="description">
                            Opcional. Quando preenchido, força este endereço como remetente de todos
                            os e-mails (sobrescreve o padrão do WordPress). Ex: contato@seu-site.com
                        </p>
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

                <tr>
                    <th scope="row">
                        <label for="webhook_secret">Segredo do Webhook</label>
                    </th>
                    <td>
                        <input type="password" id="webhook_secret" name="webhook_secret"
                               value="" autocomplete="new-password"
                               placeholder="<?php echo !empty($options['webhook_secret']) ? '••••••••' : ''; ?>"
                               class="regular-text" />
                        <p class="description">
                            Opcional. Quando preenchido, cada requisição leva o header
                            <code>X-AgileMail-Signature: sha256=&lt;hmac&gt;</code> para o receptor
                            validar a autenticidade do payload.
                            <?php if (!empty($options['webhook_secret'])) echo 'Deixe em branco para manter o segredo atual.'; ?>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="webhook_on_failure">Webhook em falha</label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox" id="webhook_on_failure" name="webhook_on_failure"
                                   <?php checked($options['webhook_on_failure'] ?? 'false', 'true'); ?> />
                            Notificar o webhook também quando um envio falhar
                        </label>
                        <p class="description">
                            Quando ativado, falhas de envio disparam o webhook com o evento
                            <code>email_failed</code>.
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="log_enabled">Registrar e-mails</label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox" id="log_enabled" name="log_enabled"
                                   <?php checked($options['log_enabled'] ?? 'false', 'true'); ?> />
                            Manter um log dos últimos e-mails enviados
                        </label>
                        <p class="description">
                            Quando ativado, guarda os 50 e-mails mais recentes (data, destinatário,
                            assunto e status) na tabela exibida abaixo do formulário.
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="delete_on_uninstall">Limpar ao remover</label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox" id="delete_on_uninstall" name="delete_on_uninstall"
                                   <?php checked($options['delete_on_uninstall'], 'true'); ?> />
                            Apagar todas as configurações ao excluir o plugin
                        </label>
                        <p class="description">
                            Quando ativado, todas as configurações salvas serão removidas do banco de dados
                            ao excluir o plugin, deixando tudo limpo. Desativações temporárias preservam os
                            dados. Caso contrário, as configurações são sempre preservadas.
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

        <?php
        // Log de e-mails (exibido apenas quando o log está ativado).
        if (($options['log_enabled'] ?? 'false') === 'true') {
            $log = get_option('custom_smtp_log', array());
            if (!is_array($log)) {
                $log = array();
            }
            ?>
            <h2>Últimos e-mails</h2>
            <?php if (empty($log)) : ?>
                <p>Nenhum e-mail registrado ainda.</p>
            <?php else : ?>
                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th>Data</th>
                            <th>Para</th>
                            <th>Assunto</th>
                            <th>Status</th>
                            <th>Erro</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (array_reverse($log) as $entry) : ?>
                            <tr>
                                <td><?php echo esc_html(wp_date('d/m/Y H:i', $entry['time'] ?? time())); ?></td>
                                <td><?php echo esc_html($entry['to'] ?? ''); ?></td>
                                <td><?php echo esc_html($entry['subject'] ?? ''); ?></td>
                                <td><?php echo esc_html($entry['status'] ?? ''); ?></td>
                                <td><?php echo esc_html($entry['error'] ?? ''); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <form method="post" action="">
                    <?php wp_nonce_field('custom_smtp_save_settings'); ?>
                    <p class="submit">
                        <input type="submit" name="custom_smtp_clear_log" class="button-secondary"
                               value="Limpar log" />
                    </p>
                </form>
            <?php endif; ?>
            <?php
        }
        ?>
    </div>
    <?php
    
    // Enviar e-mail de teste?
    if (isset($_POST['custom_smtp_test'])) {
        check_admin_referer('custom_smtp_save_settings');

        $to = get_option('admin_email');
        $subject = 'Teste de SMTP - ' . get_bloginfo('name');
        $message = 'Este é um e-mail de teste enviado pelo plugin Custom SMTP.';
        $headers = array('Content-Type: text/html; charset=UTF-8');

        // Capturar o motivo real da falha (auth, TLS, conexão recusada, etc.) para
        // exibir um diagnóstico útil em vez de uma mensagem genérica.
        $smtp_error = '';
        add_action('wp_mail_failed', function ($wp_error) use (&$smtp_error) {
            $smtp_error = $wp_error->get_error_message();
        });

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
            echo '<div class="error"><p>Falha ao enviar e-mail de teste. Verifique suas configurações.';
            if ($smtp_error !== '') {
                echo '<br><strong>Motivo:</strong> ' . esc_html($smtp_error);
            }
            echo '</p></div>';
        }
    }
}