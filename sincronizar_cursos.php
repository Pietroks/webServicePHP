<?php
// Carrega o núcleo do Moodle e autenticação.
require_once('../../config.php');
require_once($CFG->dirroot . '/course/lib.php'); // Necessário para usar a função create_course()

require_login();     // Garante que o usuário esteja logado
require_admin();     // Garante que apenas administradores possam executar o script

echo "<h1>Sincronizando Cursos da IESDE com o Moodle</h1>";

global $DB;

// Instancia o cliente da API local
$api_client = new \local_ead_integration\webservice_client();

// Define os parâmetros da consulta à API de cursos
$params = [
    'DtInicio' => '01/01/2020',
    'DtFim' => '31/12/2026',
    'registros_pagina' => 100,
    'pagina' => 1
];

echo "<p>Buscando lista de cursos da API...</p>";

// Faz a chamada para a API
$cursos_iesde = $api_client->call('getCursos', $params, true);

// Verifica se a API retornou cursos
if (empty($cursos_iesde)) {
    echo "<h2>Nenhum curso encontrado na API.</h2>";
    die();
}

echo "<h2>Encontrados " . count($cursos_iesde) . " cursos na API. Verificando e criando no Moodle...</h2>";
echo "<pre>";

// Verifica se a categoria padrão (ID = 1) existe
try {
    $defaultcategory = $DB->get_record('course_categories', ['id' => 1], '*', MUST_EXIST);
} catch (Exception $e) {
    echo "<strong>Erro:</strong> A categoria com ID 1 não existe. Crie uma categoria padrão antes de continuar.\n";
    die();
}

// Itera sobre os cursos retornados pela API
foreach ($cursos_iesde as $curso_iesde) {
    // Verificação de integridade do item
    if (!is_array($curso_iesde) || !isset($curso_iesde['CursoID'], $curso_iesde['Nome'])) {
        echo "---> ERRO: Formato inválido de curso recebido da API. Dados brutos:\n";
        var_dump($curso_iesde);
        echo "\n\n";
        continue;
    }

    $idnumber_iesde = $curso_iesde['CursoID'];
    $nome_completo_iesde = trim($curso_iesde['Nome']);

    echo "Processando curso: '{$nome_completo_iesde}' (ID IESDE: {$idnumber_iesde})\n";

    // Verifica se o curso já existe no Moodle pelo campo "idnumber"
    if ($DB->record_exists('course', ['idnumber' => $idnumber_iesde])) {
        echo "---> AVISO: Curso já existe no Moodle. Pulando.\n\n";
        continue;
    }

    echo "---> AÇÃO: Criando curso no Moodle...\n";

    // Cria objeto com os dados do novo curso
    $newcourse = new stdClass();
    $newcourse->fullname  = $nome_completo_iesde;
    $newcourse->shortname = 'IESDE-' . $idnumber_iesde;
    $newcourse->category  = $defaultcategory->id;
    $newcourse->idnumber  = $idnumber_iesde;
    $newcourse->visible   = 1; // Curso visível
    $newcourse->format    = 'topics'; // Formato por tópicos

    try {
        // Cria o curso usando a função oficial do Moodle
        $course = create_course($newcourse);
        echo "---> SUCESSO! Curso '{$course->fullname}' criado no Moodle com ID: {$course->id}\n\n";
    } catch (Exception $e) {
        echo "---> ERRO ao criar o curso: " . $e->getMessage() . "\n\n";
    }
}

echo "</pre><h2>Sincronização de cursos concluída.</h2>";
