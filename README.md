# WP-AgileMail-SMTP

Plugin para WordPress que substitui o envio padrão de emails por configurações de SMTP.

Também tem o recurso que sempre que um email for enviado pelo WordPress (usando o wp_mail), o webhook será acionado com informações sobre o email enviado. O serviço que receberá este webhook deverá estar preparado para processar os dados no formato JSON com as informações sobre o email.

