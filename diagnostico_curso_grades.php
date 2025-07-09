<?php
require_once('../../config.php');
require_once($CFG->dirroot . '/local/ead_integration/classes/webservice_client.php');

require_login();
require_admin();

echo "<h1>Diagnóstico: Verificação de Grades e Disciplinas na API da IESDE</h1>";
echo "<p>Seguindo a recomendação do suporte: buscando todas as grades primeiro, sem filtrar por curso.</p>";

$api_client = new \local_ead_integration\webservice_client();

// 1. BUSCAR TODAS AS GRADES DISPONÍVEIS
$params_grades = [
    'DtInicio' => '01/01/2020',
    'DtFim' => '31/12/2026',
    'registros_pagina' => 200, // Pegar o máximo de grades possível
    'pagina' => 1
];

echo "<h2>1. Buscando todas as Grades Curriculares...</h2>";
$resposta_grades = $api_client->call('getGrades', $params_grades, true);

if (empty($resposta_grades) || empty($resposta_grades['info'])) {
    echo "<p>❌ <strong>FALHA:</strong> Nenhuma grade curricular foi encontrada no ambiente de testes da IESDE. Não é possível continuar. É necessário que eles cadastrem pelo menos uma grade.</p>";
    echo "<details><summary>📋 Detalhes da resposta da API</summary><pre>";
    print_r($resposta_grades);
    echo "</pre></details>";
    exit;
}

$grades = $resposta_grades['info'];
echo "<p style='color:green; font-weight:bold;'>✅ SUCESSO! ".count($grades)." grades encontradas.</p>";
echo "<hr>";

// 2. PARA CADA GRADE ENCONTRADA, BUSCAR AS DISCIPLINAS
echo "<h2>2. Verificando as disciplinas dentro de cada Grade...</h2>";
echo "<ul>";

foreach ($grades as $grade) {
    if (empty($grade['GradeID'])) {
        continue;
    }

    $gradeid = $grade['GradeID'];
    // Opcional: mostrar o nome do curso se a API o retornar junto com a grade
    $nome_curso_da_grade = isset($grade['Curso']) ? trim($grade['Curso']) : 'Curso Desconhecido';
    
    echo "<li><strong>Processando Grade ID: {$gradeid}</strong> (Curso: {$nome_curso_da_grade})";
    
    $params_disciplinas = [
        'GradeID' => $gradeid,
        'registros_pagina' => 100,
        'pagina' => 1
    ];

    $resposta_disciplinas = $api_client->call('getDisciplinas', $params_disciplinas, true);

    if (empty($resposta_disciplinas) || empty($resposta_disciplinas['info'])) {
        echo "<ul><li>⚠️ Nenhuma disciplina encontrada para esta grade.</li></ul>";
    } else {
        echo "<ul>";
        foreach ($resposta_disciplinas['info'] as $disciplina) {
            echo "<li style='color:blue;'>✅ Disciplina encontrada: ".trim($disciplina['Nome'])." (ID: {$disciplina['DisciplinaID']})</li>";
        }
        echo "</ul>";
    }
    echo "</li>";
}

echo "</ul>";
echo "<hr><h2>Diagnóstico Concluído.</h2>";
echo "<p>Se alguma disciplina foi listada acima, o próximo passo é usar uma <strong>DisciplinaID</strong> e uma <strong>MatriculaID</strong> para buscar as aulas com o script <strong>testar_aulas.php</strong>.</p>";

?>