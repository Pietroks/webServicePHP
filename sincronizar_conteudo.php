<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Sincronização de Conteúdo EAD</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
  <style>
    body { background-color: #f8f9fa; padding: 30px; }
    .disciplina-card { margin-bottom: 30px; }
    iframe { max-width: 100%; height: 315px; }
    h2, h3 { color: #0d6efd; }
    .debug { font-size: 0.8em; color: #6c757d; }
  </style>
</head>
<body>
<div class="container">
  <h1 class="mb-4 text-primary">📚 Sincronização de Conteúdo EAD</h1>

<?php
require_once('../../config.php');
if (file_exists($CFG->dirroot . '/local/ead_integration/classes/webservice_client.php')) {
    require_once($CFG->dirroot . '/local/ead_integration/classes/webservice_client.php');
}

require_login();
require_admin();

$matriculaId = 2280500;
$gradeId = 439599;
$dataInicio = '01/01/2020';
$dataFim = '31/12/2025';
$pagina = 1;
$registrosPorPagina = 100;

$client = new \local_ead_integration\webservice_client();

$disciplinas = $client->call('getDisciplinas', [
    'GradeID' => $gradeId,
    'DtInicio' => $dataInicio,
    'DtFim' => $dataFim,
    'registros_pagina' => $registrosPorPagina,
    'pagina' => $pagina,
], true);

if (empty($disciplinas) || !is_array($disciplinas)) {
    echo "<div class='alert alert-warning'>Nenhuma disciplina encontrada.</div>";
} else {
    foreach ($disciplinas as $disciplina) {
        if (!is_array($disciplina)) continue;

        $disciplinaId = $disciplina['DisciplinaID'] ?? null;
        $disciplinaNome = htmlspecialchars($disciplina['Descricao'] ?? '[Nome da disciplina não encontrado]');

        echo "<div class='card disciplina-card shadow-sm'>";
        echo "<div class='card-header d-flex justify-content-between align-items-center'>";
        echo "<h2 class='h5 mb-0'>📘 {$disciplinaNome}</h2>";
        echo "<a href='#' class='btn btn-sm btn-outline-primary'>+ Importar para o Moodle</a>";
        echo "</div><div class='card-body'>";

        // Aulas e Vídeos
        $aulas = $client->call('getAulas', [
            'MatriculaID' => $matriculaId,
            'DisciplinaID' => $disciplinaId,
            'registros_pagina' => $registrosPorPagina,
            'pagina' => $pagina,
        ], true);

        if (!empty($aulas) && is_array($aulas)) {
            echo "<h5 class='mb-3'>🎥 Aulas com vídeo</h5><ul class='list-group mb-4'>";
            foreach ($aulas as $aula) {
                if (!is_array($aula)) continue;

                $titulo = htmlspecialchars($aula['Tema'] ?? '[Título não encontrado]');
                $aulaId = $aula['AulaID'] ?? null;

                echo "<li class='list-group-item'>";
                echo "<strong>📺 Aula:</strong> {$titulo}";

                if ($aulaId) {
                    $video = $client->call('getVideoAulaPlayer', [
                        'MatriculaID' => $matriculaId,
                        'AulaID' => $aulaId,
                        'registros_pagina' => 1,
                        'pagina' => 1
                    ], true);

                    $urlVideo = null;
                    $response = $video['response'] ?? '';
                    $urlVideo = trim($response, "\" \t\n\r\0\x0B\\");
                    $urlVideo = str_replace('\/', '/', $urlVideo);

                    if ($urlVideo) {
                        echo "<div class='ratio ratio-16x9 mt-2'><iframe src='" . htmlspecialchars($urlVideo) . "' frameborder='0' allowfullscreen></iframe></div>";
                    } else {
                        echo "<div class='text-muted'>⚠️ Vídeo não disponível.</div>";
                    }
                }
                echo "</li>";
            }
            echo "</ul>";
        }

        // PDFs
        $pdfs = $client->call('getPdfsDisciplina', [
            'MatriculaID' => $matriculaId,
            'DisciplinaID' => $disciplinaId,
            'registros_pagina' => $registrosPorPagina,
            'pagina' => $pagina,
        ], true);

        if (!empty($pdfs) && is_array($pdfs)) {
            echo "<h5>📄 PDFs disponíveis</h5><ul class='list-group'>";
            foreach ($pdfs as $pdf) {
                if (!is_array($pdf) || empty($pdf['LivroDisciplinaID'])) continue;

                $livroId = $pdf['LivroDisciplinaID'];
                $tituloPdf = !empty($pdf['Descricao']) ? htmlspecialchars(trim($pdf['Descricao'])) : '[Sem título]';

                $pdfDetalhe = $client->call('getPdf', [
                    'MatriculaID' => $matriculaId,
                    'LivroDisciplinaID' => $livroId
                ], true);

                $urlPdf = null;
                $response = $pdfDetalhe['response'] ?? '';
                $urlPdf = trim($response, "\" \t\n\r\0\x0B\\");
                $urlPdf = str_replace('\/', '/', $urlPdf);

                if ($urlPdf) {
                    echo "<li class='list-group-item'><a href='" . htmlspecialchars($urlPdf) . "' target='_blank'>{$tituloPdf}</a></li>";
                } else {
                    echo "<li class='list-group-item'>{$tituloPdf} (sem link)</li>";
                }
            }
            echo "</ul>";
        }

        echo "</div></div>"; // .card-body + .card
    }
}
?>

</div>
</body>
</html>
