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
$registrosParaApi = 200;      // Pede até 200 vídeos para a API.
$itensPorPaginaDisplay = 10;  // Mostra 10 vídeos por página na tela.

// Inicia o output do HTML
echo $OUTPUT->header();

?>
<style>
    /* SEU CSS PERMANECE O MESMO */
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
        border: 1px solid #0d1d2eff;
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

        // 1. Pede um bloco grande de vídeos (até 200) para a API, apenas da primeira página.
        $cache_videos = cache::make('local_ead_integration', 'videos');
        $cachekey_videos = 'disciplina_' . $disciplinaId . '_mat_' . $matriculaId;
        if (!($todosOsVideos = $cache_videos->get($cachekey_videos))) {
            // Se NÃO achou no cache (CACHE MISS):

            // 1. Busca a lista de até 200 vídeos na API (lento).
            $aulasRaw = $client->call('getAulas', [ 'MatriculaID' => $matriculaId, 'DisciplinaID' => $disciplinaId, 'registros_pagina' => $registrosParaApi, 'pagina' => 1 ], true);
            
            // 2. Limpa os duplicados.
            $todosOsVideos = [];
            $idsUnicos = [];
            if (is_array($aulasRaw)) {
                foreach ($aulasRaw as $aula) {
                    if (is_array($aula) && !empty($aula['AulaID']) && !in_array($aula['AulaID'], $idsUnicos)) {
                        $todosOsVideos[] = $aula;
                        $idsUnicos[] = $aula['AulaID'];
                    }
                }
            }

            foreach ($todosOsVideos as $key => $aula) {
                    $video = $client->call('getVideoAulaPlayer', [ 'MatriculaID' => $matriculaId, 'AulaID' => $aula['AulaID'], 'registros_pagina' => 1, 'pagina' => 1 ], true);
                    $urlVideo = '';
                    if (is_array($video) && !empty($video['response']) && is_string($video['response'])) {
                        $decoded_url = json_decode($video['response']);
                        if (json_last_error() === JSON_ERROR_NONE && is_string($decoded_url)) {
                            $urlVideo = $decoded_url;
                        }
                    }
                    // Adiciona a URL do player diretamente no array do vídeo.
                    $todosOsVideos[$key]['url_player'] = $urlVideo;
                }

                // 4. Salva a lista completa (com URLs) no cache por 15 minutos (900 segundos).
                $cache_videos->set($cachekey_videos, $todosOsVideos, 900);
            }

        $totalDeVideos = count($todosOsVideos);
        if ($totalDeVideos > 0) {
            $totalPaginas = ceil($totalDeVideos / $itensPorPaginaDisplay);
            if ($pagina_aulas > $totalPaginas) { $pagina_aulas = $totalPaginas; }
            if ($pagina_aulas < 1) { $pagina_aulas = 1; }

            $offset = ($pagina_aulas - 1) * $itensPorPaginaDisplay;
            $aulasNestaPagina = array_slice($todosOsVideos, $offset, $itensPorPaginaDisplay);

            echo "<div class='p-3'><h5 class='mb-3'><i class='bi bi-camera-reels-fill me-2'></i>Aulas (Página {$pagina_aulas} de {$totalPaginas})</h5></div><ul class='list-group list-group-flush'>";
            
            // Loop de exibição agora é super rápido, pois não faz chamadas à API.
            foreach ($aulasNestaPagina as $aula) {
                $titulo = htmlspecialchars($aula['Tema']);
                $urlVideo = $aula['url_player']; // Pega a URL que já buscamos e guardamos.

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

            // 6. Monta os controles de paginação LOCAL.
            if ($totalPaginas > 1) {
                echo '<div class="card-footer bg-white py-3">';
                echo '<nav aria-label="Navegação das Aulas"><ul class="pagination justify-content-center mb-0">';
                
                // Botão Anterior
                if ($pagina_aulas > 1) {
                    $url_anterior = new moodle_url($base_url, ['page_aulas' => $pagina_aulas - 1, 'page_pdfs' => $pagina_pdfs]);
                    echo '<li class="page-item"><a class="page-link" href="' . $url_anterior . '">&laquo;</a></li>';
                } else {
                    echo '<li class="page-item disabled"><span class="page-link">&laquo;</span></li>';
                }
                
                // Botões Numéricos
                for ($i = 1; $i <= $totalPaginas; $i++) {
                    $activeClass = ($i == $pagina_aulas) ? 'active' : '';
                    $url_pagina = new moodle_url($base_url, ['page_aulas' => $i, 'page_pdfs' => $pagina_pdfs]);
                    echo '<li class="page-item ' . $activeClass . '"><a class="page-link" href="' . $url_pagina . '">' . $i . '</a></li>';
                }

                // Botão Próxima
                if ($pagina_aulas < $totalPaginas) {
                    $url_proxima = new moodle_url($base_url, ['page_aulas' => $pagina_aulas + 1, 'page_pdfs' => $pagina_pdfs]);
                    echo '<li class="page-item"><a class="page-link" href="' . $url_proxima . '">&raquo;</a></li>';
                } else {
                    echo '<li class="page-item disabled"><span class="page-link">&raquo;</span></li>';
                }
                
                echo '</ul></nav></div>';
            }

        } else {
            echo "<div class='p-3'><p class='text-muted'>Nenhuma aula encontrada para esta disciplina.</p></div>";
        }

        // === BLOCO DE PDFs COM PAGINAÇÃO ===
        $cache_pdfs = cache::make('local_ead_integration', 'pdfs');
        $cachekey_pdfs = 'disciplina_' . $disciplinaId . '_mat_' . $matriculaId;
        if (!($todosOsPdfs = $cache_pdfs->get($cachekey_pdfs))) {
            $pdfsRaw = $client->call('getPdfsDisciplina', [ 'MatriculaID' => $matriculaId, 'DisciplinaID' => $disciplinaId, 'registros_pagina' => 100, 'pagina' => 1 ], true);
            $todosOsPdfs = [];
            if (is_array($pdfsRaw)) {
                foreach ($pdfsRaw as $pdf) {
                    if (is_array($pdf) && !empty($pdf['Descricao']) && !empty($pdf['LivroDisciplinaID'])) {
                        $pdfDetalhe = $client->call('getPdf', [ 'MatriculaID' => $matriculaId, 'LivroDisciplinaID' => $pdf['LivroDisciplinaID'] ], true);
                        $urlPdf = '';
                        if (is_array($pdfDetalhe) && !empty($pdfDetalhe['response'])) {
                            $decodedPdf = json_decode($pdfDetalhe['response']);
                            if (json_last_error() === JSON_ERROR_NONE && is_string($decodedPdf)) {
                                $urlPdf = $decodedPdf;
                            }
                        }
                        $pdf['url_pdf'] = $urlPdf;
                        $todosOsPdfs[] = $pdf;
                    }
                }
            }
            $cache_pdfs->set($cachekey_pdfs, $todosOsPdfs, 900);
        }
        if (!empty($todosOsPdfs)) {
            echo "<div class='p-3 border-top'><h5 class='mb-3'><i class='bi bi-file-earmark-pdf-fill me-2'></i>Materiais (PDF)</h5></div><ul class='list-group list-group-flush'>";
            foreach ($todosOsPdfs as $pdf) {
                $tituloPdf = htmlspecialchars($pdf['Descricao']);
                $urlPdf = $pdf['url_pdf'];
                if (filter_var($urlPdf, FILTER_VALIDATE_URL)) {
                    echo "<li class='list-group-item'><a href='" . htmlspecialchars($urlPdf) . "' target='_blank' class='text-decoration-none'><i class='bi bi-box-arrow-up-right me-2'></i>{$tituloPdf}</a></li>";
                }
            }
            echo "</ul>";
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