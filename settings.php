<?php
defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) { // Garante que apenas usuários com permissão vejam a página.

    // Cria a página de configurações do plugin
    $settings = new admin_settingpage('local_integracao_ava', get_string('pluginname', 'local_integracao_ava'));

    // Cabeçalho e descrição
    $settings->add(new admin_setting_heading(
        'local_integracao_ava_auth_header',
        get_string('auth_header', 'local_integracao_ava'),
        get_string('auth_description', 'local_integracao_ava')
    ));

    // Campo para 'api_http_user'
    $settings->add(new admin_setting_configtext(
        'local_integracao_ava/api_http_user',
        get_string('api_http_user', 'local_integracao_ava'),
        get_string('api_http_user_desc', 'local_integracao_ava'),
        '',
        PARAM_TEXT
    ));

    // Campo para 'api_http_pass'
    $settings->add(new admin_setting_configpasswordunmask(
        'local_integracao_ava/api_http_pass',
        get_string('api_http_pass', 'local_integracao_ava'),
        get_string('api_http_pass_desc', 'local_integracao_ava'),
        '',
        PARAM_TEXT
    ));

    // Campo para 'chave_acesso'
    $settings->add(new admin_setting_configtext(
        'local_integracao_ava/chave_acesso',
        get_string('chave_acesso', 'local_integracao_ava'),
        get_string('chave_acesso_desc', 'local_integracao_ava'),
        '',
        PARAM_TEXT
    ));

    // Campo para 'chave_name'
    $settings->add(new admin_setting_configtext(
        'local_integracao_ava/chave_name',
        get_string('chave_name', 'local_integracao_ava'),
        get_string('chave_name_desc', 'local_integracao_ava'),
        'EAD-API-KEY', // Valor padrão, conforme o manual
        PARAM_TEXT
    ));

    $ADMIN->add('localplugins', $settings);
}