<?php
defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    // Página de configurações da API
    $settings = new admin_settingpage('local_ead_integration_settings', get_string('pluginname', 'local_ead_integration'));

    // Cabeçalho: Configurações da API
    $settings->add(new admin_setting_heading('local_ead_integration_api_settings', get_string('api_settings', 'local_ead_integration'), ''));

    $settings->add(new admin_setting_configtext(
        'local_ead_integration/baseurl',
        get_string('setting_baseurl', 'local_ead_integration'),
        get_string('setting_baseurl_desc', 'local_ead_integration'),
        'https://ead.portalava.com.br.homologacao.iesde.com.br',
        PARAM_URL
    ));

    $settings->add(new admin_setting_configtext(
        'local_ead_integration/apikey',
        get_string('setting_apikey', 'local_ead_integration'),
        get_string('setting_apikey_desc', 'local_ead_integration'),
        'd2ca33145b4628c5d4d21a6e3c05aa75',
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_configtext(
        'local_ead_integration/wsusername',
        get_string('setting_wsusername', 'local_ead_integration'),
        get_string('setting_wsusername_desc', 'local_ead_integration'),
        '1590e99c63d124e374345de71205ddb7c63a0b8d',
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_configtext(
        'local_ead_integration/wspassword',
        get_string('setting_wspassword', 'local_ead_integration'),
        get_string('setting_wspassword_desc', 'local_ead_integration'),
        'afb94979f63f3038b84344d7ac37febe39748167',
        PARAM_TEXT
    ));

    // Adiciona a seção na categoria "localplugins"
    $ADMIN->add('localplugins', $settings);

    // Agora registre os links de ferramentas diretamente no menu do admin
    $ADMIN->add('localplugins', new admin_externalpage(
        'local_ead_integration_enrollpage',
        get_string('enroll_page_title', 'local_ead_integration'),
        new moodle_url('/local/ead_integration/enroll.php')
    ));

    $ADMIN->add('localplugins', new admin_externalpage(
        'local_ead_integration_syncpage',
        get_string('sync_courses_page_title', 'local_ead_integration'),
        new moodle_url('/local/ead_integration/sincronizar_cursos.php')
    ));

    $ADMIN->add('localplugins', new admin_externalpage(
        'local_ead_integration_index',
        get_string('index_page_title', 'local_ead_integration'),
        new moodle_url('/local/ead_integration/index.php')
    ));

}

