<?php
require_once('../../config.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/local/ead_integration/classes/webservice_client.php');

require_login();
require_admin();

echo "<h1>Sincronizando Cursos + Disciplinas da IESDE com o Moodle</h1>";

global $DB;

// Instancia o cliente da API
$api_client = new \local_ead_integration\webservice_client();

// Parâmetros da API de cursos
$params = [
    'DtInicio' => '01/01/2020',
    'DtFim' => '31/12/2026',
    'registros_pagina' => 100,
    'pagina' => 1
];

echo "<p>Buscando cursos da API...</p>";
$cursos_iesde = $api_client->call('getCursos', $params, true);

// Limpa cursos inválidos
$cursos_iesde = array_filter($cursos_iesde, function ($curso) {
    return is_array($curso) && isset($curso['CursoID'], $curso['Nome']);
});

if (empty($cursos_iesde)) {
    echo "<h2>❌ Nenhum curso válido encontrado na API.</h2>";
    die();
}

echo "<h2>✅ " . count($cursos_iesde) . " cursos encontrados. Buscando disciplinas...</h2>";
echo "<pre>";

foreach ($cursos_iesde as $curso_iesde) {
    $curso_id = $curso_iesde['CursoID'];
    $nome_curso = trim($curso_iesde['Nome']);

    echo "\n==============================\n";
    echo "Curso: {$nome_curso} (ID: {$curso_id})\n";

    // Busca a Grade do curso
    $params_grade = [
        'CursoID' => $curso_id,
        'DtInicio' => '01/01/2020',
        'DtFim' => '31/12/2026',
        'registros_pagina' => 1,
        'pagina' => 1
    ];

    $resposta_grade = $api_client->call('getGrades', $params_grade, true);

    if (empty($resposta_grade['info'][0]['GradeID'])) {
        echo "---> ❌ Nenhuma Grade encontrada para este curso.\n";
        continue;
    }

    $grade_id = $resposta_grade['info'][0]['GradeID'];
    echo "---> ✅ Grade encontrada: {$grade_id}\n";

    // Busca disciplinas da grade
    $params_disciplinas = [
        'GradeID' => $grade_id,
        'registros_pagina' => 100,
        'pagina' => 1
    ];

    $disciplinas = $api_client->call('getDisciplinas', $params_disciplinas, true);

    if (empty($disciplinas['info'])) {
        echo "---> ⚠️ Nenhuma disciplina encontrada para esta grade.\n";
        continue;
    }

    echo "---> ✅ Disciplinas encontradas:\n";

    foreach ($disciplinas['info'] as $disc) {
        echo "     - {$disc['Nome']} (ID: {$disc['DisciplinaID']})\n";
    }
}

echo "</pre><h2>✅ Sincronização finalizada.</h2>";
