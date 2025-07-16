<?php
defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    // 1. Crie uma nova categoria de administração para o seu plugin.
    // O primeiro parâmetro 'local_ead_integration' é um nome único para a categoria.
    // O segundo é o título que aparecerá no menu, pego do seu arquivo de idioma.
    $ADMIN->add('root', new admin_category('local_ead_integration_category', get_string('pluginname', 'local_ead_integration')));

    // --- PÁGINA DE CONFIGURAÇÕES DA API ---
    // Adicione a página de configurações à sua NOVA categoria ('local_ead_integration_category')
    // O título da página já é definido aqui, então o cabeçalho extra é desnecessário.
    $settings = new admin_settingpage('local_ead_integration_settings', get_string('api_settings', 'local_ead_integration'));

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
    
    // Adiciona a página de configurações à sua categoria
    $ADMIN->add('local_ead_integration_category', $settings);

    // --- PÁGINAS DE FERRAMENTAS ---
    // Agora adicione os links das ferramentas à sua NOVA categoria.
    
    // Link para o Dashboard (index.php)
    $ADMIN->add('local_ead_integration_category', new admin_externalpage(
        'local_ead_integration_index',
        get_string('index_page_title', 'local_ead_integration'),
        new moodle_url('/local/ead_integration/index.php')
    ));

    // Link para a página de Matrículas (enroll.php)
    $ADMIN->add('local_ead_integration_category', new admin_externalpage(
        'local_ead_integration_enrollpage', 
        get_string('enroll_page_title', 'local_ead_integration'),
        new moodle_url('/local/ead_integration/enroll.php')
    ));

    // Link para a página de Sincronização de Cursos
    $ADMIN->add('local_ead_integration_category', new admin_externalpage(
        'local_ead_integration_syncpage',
        get_string('sync_courses_page_title', 'local_ead_integration'),
        new moodle_url('/local/ead_integration/sincronizar_cursos.php')
    ));
}