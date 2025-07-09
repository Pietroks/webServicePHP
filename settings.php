<?php
defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_ead_integration_settings', get_string('pluginname', 'local_ead_integration'));

    // Adiciona o campo para a URL Base
    $settings->add(new admin_setting_configtext(
        'local_ead_integration/baseurl',
        get_string('setting_baseurl', 'local_ead_integration'),
        get_string('setting_baseurl_desc', 'local_ead_integration'),
        'https://ead.portalava.com.br.homologacao.iesde.com.br', // Valor padrão do ambiente
        PARAM_URL
    ));

    // Adiciona o campo para a API Key
    $settings->add(new admin_setting_configtext(
        'local_ead_integration/apikey',
        get_string('setting_apikey', 'local_ead_integration'),
        get_string('setting_apikey_desc', 'local_ead_integration'),
        'd2ca33145b4628c5d4d21a6e3c05aa75', // Valor padrão do ambiente
        PARAM_TEXT
    ));

    // Adiciona o campo para o Username
    $settings->add(new admin_setting_configtext(
        'local_ead_integration/wsusername',
        get_string('setting_wsusername', 'local_ead_integration'),
        get_string('setting_wsusername_desc', 'local_ead_integration'),
        '1590e99c63d124e374345de71205ddb7c63a0b8d', // Valor padrão do ambiente
        PARAM_TEXT
    ));

    // Adiciona o campo para a Senha
    $settings->add(new admin_setting_configtext(
        'local_ead_integration/wspassword',
        get_string('setting_wspassword', 'local_ead_integration'),
        get_string('setting_wspassword_desc', 'local_ead_integration'),
        'afb94979f63f3038b84344d7ac37febe39748167', // Valor padrão do ambiente
        PARAM_TEXT
    ));

    $ADMIN->add('localplugins', $settings);
}