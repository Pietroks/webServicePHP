<?php
// Carrega o ambiente do Moodle.
require_once('../../config.php');
require_login();
require_admin();
require_once($CFG->dirroot . '/local/ead_integration/classes/webservice_client.php');

echo "<h1>Teste de Busca de Aulas de uma Disciplina</h1>";

// --- IDs de Exemplo ---
// Quando a IESDE fornecer os dados, substitua estes valores.
// Use um MatriculaID de um aluno que você cadastrou com sucesso.
$matricula_id_exemplo = '2273575'; 
// Use uma DisciplinaID que você encontrará com o testar_disciplinas.php
$disciplina_id_exemplo = '473032'; // Exemplo do Postman
// -----------------------------------------------------------------

$api_client = new \local_ead_integration\webservice_client();

$params = [
    'MatriculaID' => $matricula_id_exemplo,
    'DisciplinaID' => $disciplina_id_exemplo,
    'registros_pagina' => 100,
    'pagina' => 1
];

echo "<p>Buscando aulas para a Disciplina ID <b>{$disciplina_id_exemplo}</b> e Matrícula ID <b>{$matricula_id_exemplo}</b>...</p>";

// O endpoint /getAulas também é paginado
$resposta = $api_client->call('getAulas', $params, true);

if (empty($resposta) || !isset($resposta['info']) || !is_array($resposta['info'])) {
    echo "<h2>❌ FALHA! Nenhuma aula encontrada ou a API retornou um erro.</h2>";
    echo "<h3>Resposta da API:</h3>";
    echo "<pre>";
    var_dump($resposta);
    echo "</pre>";
    die();
}

$aulas = $resposta['info'];

echo "<h2>✅ SUCESSO! Aulas encontradas:</h2>";
echo "<pre>";
print_r($aulas);
echo "</pre>";

echo "<h3>Próximos passos:</h3>";
echo "<ul>";
echo "<li>Para cada aula, usar o <b>AulaID</b> para chamar <b>/getVideoAula</b> e obter o link do vídeo.</li>";
echo "<li>Para cada aula, usar o <b>AulaID</b> ou <b>DisciplinaID</b> para chamar <b>/getPdf</b> ou <b>/getPdfsDisciplina</b> e obter os materiais.</li>";
echo "<li>Adicionar estes links como recursos (URLs) dentro do curso correspondente no Moodle.</li>";
echo "</ul>";