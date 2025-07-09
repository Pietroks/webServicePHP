<?php
// Carrega o ambiente do Moodle.
require_once('../../config.php');
require_login();
require_admin();
require_once($CFG->dirroot . '/local/ead_integration/classes/webservice_client.php');

echo "<h1>Teste de Busca de Cursos</h1>";

$api_client = new \local_ead_integration\webservice_client();

// Parâmetros obrigatórios para o /getCursos, conforme a documentação [cite: 438]
$params = [
    'DtInicio' => '01/01/2020', // Uma data de início antiga para pegar tudo
    'DtFim' => '31/12/2026',    // Uma data de fim no futuro
    'registros_pagina' => 100,
    'pagina' => 1
];

echo "<p>Chamando o endpoint <code>/getCursos</code>...</p>";

// Faz a chamada, passando 'true' como terceiro parâmetro para usar o endpoint paginado /web_servicePg/
$cursos = $api_client->call('getCursos', $params, true);

if (empty($cursos) || !is_array($cursos)) {
    echo "<h2>❌ FALHA! Nenhum curso foi encontrado ou a API retornou um erro.</h2>";
    echo "<pre>";
    var_dump($cursos);
    echo "</pre>";
    die();
}

echo "<h2>✅ SUCESSO! Cursos encontrados:</h2>";
echo "<pre>";
print_r($cursos);
echo "</pre>";