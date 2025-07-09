<?php
require_once('../../config.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/local/ead_integration/classes/webservice_client.php');

require_login();
require_admin();

global $DB;

echo "<h1>Sincronizando Cursos + Disciplinas da IESDE com o Moodle</h1>";

$api_client = new \local_ead_integration\webservice_client();

// Parâmetros da API de cursos
$paramsCursos = [
    'DtInicio' => '01/01/2020',
    'DtFim' => '31/12/2026',
    'registros_pagina' => 100,
    'pagina' => 1
];

echo "<p>🔍 Buscando cursos da API...</p>";
$cursos_iesde = $api_client->call('getCursos', $paramsCursos, true);

// Limpa cursos inválidos
$cursos_iesde = array_filter($cursos_iesde, function ($curso) {
    return is_array($curso) && isset($curso['CursoID'], $curso['Nome']);
});

if (empty($cursos_iesde)) {
    echo "<h2>❌ Nenhum curso válido encontrado na API.</h2>";
    exit;
}

echo "<h2>✅ " . count($cursos_iesde) . " cursos encontrados. Buscando disciplinas...</h2>";
echo "<pre>";

foreach ($cursos_iesde as $curso) {
    $cursoID = $curso['CursoID'];
    $cursoNome = trim($curso['Nome']);

    echo "\n==============================\n";
    echo "📚 Curso: {$cursoNome} (ID: {$cursoID})\n";

    // Buscar a Grade
    $paramsGrade = [
        'CursoID' => $cursoID,
        'DtInicio' => '01/01/2020',
        'DtFim' => '31/12/2026',
        'registros_pagina' => 1,
        'pagina' => 1
    ];

    $gradeResult = $api_client->call('getGrades', $paramsGrade, true);

    if (!isset($gradeResult['info'][0]['GradeID'])) {
        echo "---> ❌ Nenhuma grade encontrada para este curso.\n";
        continue;
    }

    $gradeID = $gradeResult['info'][0]['GradeID'];
    echo "---> ✅ Grade encontrada: {$gradeID}\n";

    // Buscar Disciplinas
    $paramsDisciplinas = [
        'GradeID' => $gradeID,
        'registros_pagina' => 100,
        'pagina' => 1
    ];

    $disciplinasResult = $api_client->call('getDisciplinas', $paramsDisciplinas, true);

    if (empty($disciplinasResult['info']) || !is_array($disciplinasResult['info'])) {
        echo "---> ⚠️ Nenhuma disciplina encontrada para esta grade.\n";
        continue;
    }

    echo "---> ✅ Disciplinas encontradas:\n";
    foreach ($disciplinasResult['info'] as $disc) {
        if (isset($disc['Nome'], $disc['DisciplinaID'])) {
            echo "     - {$disc['Nome']} (ID: {$disc['DisciplinaID']})\n";
        } else {
            echo "     ⚠️ Disciplina inválida recebida. Dados incompletos.\n";
        }
    }
}

echo "</pre><h2>✅ Sincronização finalizada.</h2>";
