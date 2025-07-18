<?php
require_once('../../config.php');
require_once($CFG->dirroot . '/local/ead_integration/classes/webservice_client.php');

// Pega o ID do curso e carrega o contexto completo
$courseid = required_param('id', PARAM_INT);
$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
require_login($course);

// Declaração de globais do Moodle
global $USER, $DB, $COURSE, $PAGE;

$pagina_aulas = optional_param('page_aulas', 1, PARAM_INT);
$pagina_pdfs = optional_param('page_pdfs', 1, PARAM_INT);

$base_url = new moodle_url('/local/ead_integration/sincronizar_conteudo.php', ['id' => $course->id]);

$PAGE->set_context(context_course::instance($course->id));
$PAGE->set_url($base_url);
$PAGE->set_title("Minhas Disciplinas EAD");

// Inicializa o cliente da API e parâmetros
$client = new \local_ead_integration\webservice_client();
$dataInicio = '01/01/2020';
$dataFim = '31/12/2025';
// ALTERADO: Aumentado para 50 itens por página, conforme solicitado.
$registrosPorPagina = 100;

// Inicia o output do HTML
echo $OUTPUT->header();

// NOVO: Bloco de estilos para um visual mais moderno
?>
<style>
    body {
        background-color: #f8f9fa;
    }
    .disciplina-card {
        border: none;
        border-radius: 0.75rem;
        transition: all 0.2s ease-in-out;
    }
    .disciplina-card .card-header {
        background-color: #e9ecef;
        border-bottom: none;
        font-weight: 500;
        border-top-left-radius: 0.75rem;
        border-top-right-radius: 0.75rem;
    }
    .list-group-item {
        border-left: none;
        border-right: none;
        padding: 1rem 1.25rem;
    }
    .list-group-item:first-child {
        border-top: none;
        border-top-left-radius: 0;
        border-top-right-radius: 0;
    }
    .list-group-item:last-child {
        border-bottom: none;
    }
    .list-group-item:hover {
        background-color: #f8f9fa;
    }
    .video-indisponivel {
        background-color: #fff3cd;
        border-color: #ffeeba;
        color: #856404;
        padding: 0.75rem 1.25rem;
        border-radius: 0.25rem;
        margin-top: 0.5rem;
    }
    iframe {
        border-radius: 0.5rem;
        border: 1px solid #dee2e6;
    }
    .pagination .page-link {
        border-radius: 0.25rem;
        margin: 0 0.25rem;
        border: none;
        background-color: #e9ecef;
        color: #495057;
    }
    .pagination .page-item.active .page-link, .pagination .page-link:hover {
        background-color: #007bff;
        color: white;
    }
    .pagination .page-item.disabled .page-link {
        background-color: #f8f9fa;
        color: #6c757d;
    }
</style>
<?php

// 1. Obter CPF do perfil do usuário
$cpfFieldId = $DB->get_field('user_info_field', 'id', ['shortname' => 'cpf']);
if (!$cpfFieldId) {
    echo "<div class='container'><div class='alert alert-danger'>❌ Campo de perfil 'cpf' não encontrado.</div></div>";
    echo $OUTPUT->footer();
    exit;
}
$cpf = $DB->get_field('user_info_data', 'data', ['userid' => $USER->id, 'fieldid' => $cpfFieldId]);
if (empty($cpf)) {
    echo "<div class='container'><div class='alert alert-danger'>❌ CPF não encontrado no seu perfil.</div></div>";
    echo $OUTPUT->footer();
    exit;
}

// 2. Buscar matrículas na API
$matriculas = $client->call('getMatriculas', [ 'CPF' => $cpf, 'DtInicio' => $dataInicio, 'DtFim' => $dataFim, 'Situacao' => 'A', 'registros_pagina' => 100, 'pagina' => 1 ], true);

// 3. Validação da resposta
if (!is_array($matriculas) || empty($matriculas) || !isset($matriculas[0]) || !is_array($matriculas[0])) {
    echo "<div class='container'><div class='alert alert-danger'>❌ Nenhuma matrícula EAD válida foi encontrada.</div></div>";
    echo $OUTPUT->footer();
    exit;
}

// 4. Encontrar a matrícula correspondente
$matriculaEncontrada = null;
$cursoIdApi = (int)$COURSE->idnumber;
foreach ($matriculas as $mat) {
    if (is_array($mat) && isset($mat['CursoID']) && (int)$mat['CursoID'] === $cursoIdApi) {
        $matriculaEncontrada = $mat;
        break;
    }
}

if (!$matriculaEncontrada) {
    echo "<div class='container'><div class='alert alert-danger'>❌ Você não possui matrícula EAD para este curso.</div></div>";
    echo $OUTPUT->footer();
    exit;
}

// 5. Extrair IDs e gravar no banco
$matriculaId = $matriculaEncontrada['MatriculaID'];
$gradeId = $matriculaEncontrada['GradeID'];

if (!$DB->record_exists('eadintegration_enrolls', ['moodle_userid' => $USER->id, 'moodle_courseid' => $COURSE->id])) {
    $DB->insert_record('eadintegration_enrolls', [ 'moodle_userid' => $USER->id, 'moodle_courseid' => $COURSE->id, 'iesde_matriculaid' => $matriculaId, 'timecreated' => time(), 'timemodified' => time() ]);
}

?>
<div class="container mt-4">
    <div class="d-flex align-items-center mb-4">
        <i class="bi bi-collection-play-fill me-3" style="font-size: 2.5rem; color: #007bff;"></i>
        <h1 class="mb-0">Minhas Disciplinas</h1>
    </div>
<?php
$disciplinas = $client->call('getDisciplinas', [ 'GradeID' => $gradeId, 'DtInicio' => $dataInicio, 'DtFim' => $dataFim, 'registros_pagina' => 100, 'pagina' => 1 ], true);

if (empty($disciplinas) || !is_array($disciplinas) || !isset($disciplinas[0])) {
    echo "<div class='alert alert-warning'>Nenhuma disciplina encontrada para esta grade.</div>";
} else {
    foreach ($disciplinas as $disciplina) {
        if (!is_array($disciplina) || empty($disciplina['DisciplinaID'])) continue;

        $disciplinaId = $disciplina['DisciplinaID'];
        $nomeDisciplina = $disciplina['Descricao'] ?? '[Sem nome]';
        $disciplinaNomeExibicao = htmlspecialchars($nomeDisciplina);

        if (!$DB->record_exists('eadintegration_disciplinas', ['moodle_courseid' => $COURSE->id, 'disciplinaid' => $disciplinaId])) {
            $DB->insert_record('eadintegration_disciplinas', [
                'moodle_courseid' => $COURSE->id, 'gradeid' => $gradeId, 'disciplinaid' => $disciplinaId,
                'nome' => $nomeDisciplina, 'timecreated' => time(), 'timemodified' => time()
            ]);
        }

        echo "<div class='card disciplina-card shadow-sm mb-4'>";
        echo "<div class='card-header d-flex align-items-center'><i class='bi bi-book-half me-2'></i><h2 class='h5 mb-0'>{$disciplinaNomeExibicao}</h2></div>";
        echo "<div class='card-body p-0'>";

        // === BLOCO DE AULAS COM PAGINAÇÃO ===
        $aulasRaw = $client->call('getAulas', [ 'MatriculaID' => $matriculaId, 'DisciplinaID' => $disciplinaId, 'registros_pagina' => $registrosPorPagina, 'pagina' => $pagina_aulas ], true);

        $aulas = [];
        if (is_array($aulasRaw)) {
            foreach ($aulasRaw as $aula) {
                if (is_array($aula) && !empty($aula['Tema']) && !empty($aula['AulaID'])) {
                    $aulas[] = $aula;
                }
            }
        }

        if (!empty($aulas)) {
            echo "<div class='p-3'><h5 class='mb-3'><i class='bi bi-camera-reels-fill me-2'></i>Aulas</h5></div><ul class='list-group list-group-flush'>";
            foreach ($aulas as $aula) {
                $titulo = htmlspecialchars($aula['Tema']);
                $aulaId = $aula['AulaID'];
                $video = $client->call('getVideoAulaPlayer', [ 'MatriculaID' => $matriculaId, 'AulaID' => $aulaId, 'registros_pagina' => 1, 'pagina' => 1 ], true);
                $urlVideo = '';
                if (is_array($video) && !empty($video['response']) && is_string($video['response'])) {
                    $decoded_url = json_decode($video['response']);
                    if (json_last_error() === JSON_ERROR_NONE && is_string($decoded_url)) {
                        $urlVideo = $decoded_url;
                    }
                }
                echo "<li class='list-group-item'>";
                echo "<strong><i class='bi bi-play-btn me-2'></i>{$titulo}</strong>";
                if (filter_var($urlVideo, FILTER_VALIDATE_URL)) {
                    echo "<div class='ratio ratio-16x9 mt-2'><iframe src='" . htmlspecialchars($urlVideo) . "' frameborder='0' allowfullscreen></iframe></div>";
                } else {
                    echo "<div class='video-indisponivel mt-2'><i class='bi bi-exclamation-triangle-fill me-2'></i>Vídeo indisponível no momento.</div>";
                }
                echo "</li>";
            }
            echo "</ul>";

            echo '<div class="card-footer bg-white py-3">';
            echo '<nav aria-label="Navegação das Aulas"><ul class="pagination justify-content-center mb-0">';
            if ($pagina_aulas > 1) {
                $url_anterior = new moodle_url($base_url, ['page_aulas' => $pagina_aulas - 1, 'page_pdfs' => $pagina_pdfs]);
                echo '<li class="page-item"><a class="page-link" href="' . $url_anterior . '">&laquo; Anteriores</a></li>';
            } else {
                echo '<li class="page-item disabled"><span class="page-link">&laquo; Anteriores</span></li>';
            }
            if (is_array($aulasRaw) && count($aulasRaw) >= $registrosPorPagina) {
                $url_proxima = new moodle_url($base_url, ['page_aulas' => $pagina_aulas + 1, 'page_pdfs' => $pagina_pdfs]);
                echo '<li class="page-item"><a class="page-link" href="' . $url_proxima . '">Próximas &raquo;</a></li>';
            } else {
                echo '<li class="page-item disabled"><span class="page-link">Próximas &raquo;</span></li>';
            }
            echo '</ul></nav></div>';

        } else {
            echo "<div class='p-3'><p class='text-muted'>Nenhuma aula encontrada para esta disciplina.</p></div>";
        }


        // === BLOCO DE PDFs COM PAGINAÇÃO ===
        $pdfsRaw = $client->call('getPdfsDisciplina', [ 'MatriculaID' => $matriculaId, 'DisciplinaID' => $disciplinaId, 'registros_pagina' => $registrosPorPagina, 'pagina' => $pagina_pdfs ], true);
        
        $pdfs = [];
        if (is_array($pdfsRaw)) {
            foreach ($pdfsRaw as $pdf) {
                if (is_array($pdf) && !empty($pdf['Descricao']) && !empty($pdf['LivroDisciplinaID'])) {
                    $pdfs[] = $pdf;
                }
            }
        }
        
        if (!empty($pdfs)) {
            echo "<div class='p-3 border-top'><h5 class='mb-3'><i class='bi bi-file-earmark-pdf-fill me-2'></i>Materiais (PDF)</h5></div><ul class='list-group list-group-flush'>";
            foreach ($pdfs as $pdf) {
                $livroId = $pdf['LivroDisciplinaID'];
                $tituloPdf = htmlspecialchars($pdf['Descricao']);
                $pdfDetalhe = $client->call('getPdf', [ 'MatriculaID' => $matriculaId, 'LivroDisciplinaID' => $livroId ], true);
                $urlPdf = '';
                if (is_array($pdfDetalhe) && !empty($pdfDetalhe['response'])) {
                    $decodedPdf = json_decode($pdfDetalhe['response']);
                    if (json_last_error() === JSON_ERROR_NONE && is_string($decodedPdf)) {
                        $urlPdf = $decodedPdf;
                    }
                }
                if (filter_var($urlPdf, FILTER_VALIDATE_URL)) {
                    echo "<li class='list-group-item'><a href='" . htmlspecialchars($urlPdf) . "' target='_blank' class='text-decoration-none'><i class='bi bi-box-arrow-up-right me-2'></i>{$tituloPdf}</a></li>";
                }
            }
            echo "</ul>";

            echo '<div class="card-footer bg-white py-3 border-top-0">';
            echo '<nav aria-label="Navegação dos PDFs"><ul class="pagination justify-content-center mb-0">';
            if ($pagina_pdfs > 1) {
                $url_anterior = new moodle_url($base_url, ['page_aulas' => $pagina_aulas, 'page_pdfs' => $pagina_pdfs - 1]);
                echo '<li class="page-item"><a class="page-link" href="' . $url_anterior . '">&laquo; Anteriores</a></li>';
            } else {
                echo '<li class="page-item disabled"><span class="page-link">&laquo; Anteriores</span></li>';
            }
            if (is_array($pdfsRaw) && count($pdfsRaw) >= $registrosPorPagina) {
                $url_proxima = new moodle_url($base_url, ['page_aulas' => $pagina_aulas, 'page_pdfs' => $pagina_pdfs + 1]);
                echo '<li class="page-item"><a class="page-link" href="' . $url_proxima . '">Próximos &raquo;</a></li>';
            } else {
                echo '<li class="page-item disabled"><span class="page-link">Próximos &raquo;</span></li>';
            }
            echo '</ul></nav></div>';

        } else {
             echo "<div class='p-3 border-top'><p class='text-muted'>Nenhum material PDF encontrado para esta disciplina.</p></div>";
        }

        echo "</div></div>"; // Fim do card-body e card
    }
}
?>
</div>
<?php
echo $OUTPUT->footer();
?>