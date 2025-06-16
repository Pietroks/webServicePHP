<?php
defined('MOODLE_INTERNAL') || die();

// 1. Cria o link para a página de configurações.
// Esta parte fica fora do 'if' para que o Moodle sempre encontre o link no menu "Plugins locais".
$settings = new admin_settingpage('local_avaead_settings', get_string('pluginname', 'local_avaead'));
$ADMIN->add('localplugins', $settings);

// 2. Adiciona os campos de configuração APENAS quando o Moodle está construindo a página inteira.
// Esta é a melhor prática, que aprendemos com o plugin 'kopere_dashboard'.
if ($ADMIN->fulltree) {

    // Com a verificação acima, a linha 'require_once' não é mais estritamente necessária,
    // mas não há problema em deixá-la por segurança.
    require_once($CFG->libdir . '/adminlib.php');

    // Adiciona um campo de texto para o "API HTTP User"
    $settings->add(new admin_setting_config_text(
        'local_avaead/api_http_user',
        get_string('api_http_user', 'local_avaead'),
        get_string('api_http_user_desc', 'local_avaead'),
        '',
        PARAM_TEXT
    ));

    // Adiciona um campo de senha para o "API HTTP Pass"
    $settings->add(new admin_setting_config_password(
        'local_avaead/api_http_pass',
        get_string('api_http_pass', 'local_avaead'),
        get_string('api_http_pass_desc', 'local_avaead'),
        '',
        PARAM_TEXT
    ));

    // Adiciona um campo de texto para a "Chave de Acesso"
    $settings->add(new admin_setting_config_text(
        'local_avaead/chave_acesso',
        get_string('chave_acesso', 'local_avaead'),
        get_string('chave_acesso_desc', 'local_avaead'),
        '',
        PARAM_TEXT
    ));
}