<?php
// Carrega o núcleo do Moodle e autenticação.
require_once('../../config.php');
require_once($CFG->dirroot . '/course/lib.php');

// Requisitos de permissão
require_login();
require_admin();

// Pega a ação da URL. Se 'sync' estiver presente, executa a sincronização.
$action = optional_param('action', '', PARAM_ALPHA);

// Aumenta o tempo de execução apenas se estiver sincronizando
if ($action === 'sync') {
    @set_time_limit(600);
}

// Inicia a página Moodle (cabeçalho e navegação)
echo $OUTPUT->header();

// --- ESTILO E ESTRUTURA DA PÁGINA ---
?>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

<style>
    /* Estilos personalizados para a página */
    .course-card {
        transition: all 0.3s ease-in-out;
        margin-bottom: 1rem;
    }
    .status-icon {
        font-size: 1.5rem;
    }
    .card-body-content {
        flex-grow: 1;
    }
    .card-footer {
        font-size: 0.85rem;
        background-color: #f8f9fa;
    }
    /* Estilo para os cards de resumo */
    .summary-card p {
        margin-bottom: 0;
    }
</style>

<div class="container my-4 my-md-5">

    <div class="d-flex flex-column flex-md-row justify-content-md-between align-items-md-center mb-4">
        <h1 class="mb-3 mb-md-0"><i class="fas fa-tasks me-2"></i>Cursos Sincronizados</h1>

        <div class="d-flex gap-2">
            <a href="?action=sync" class="btn btn-primary">
                <i class="fas fa-sync-alt me-1"></i> Sincronizar Novos Cursos
            </a>
            <a href="<?php echo new moodle_url('/local/ead_integration/index.php'); ?>" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i> Voltar ao Painel
            </a>
        </div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <h5 class="card-title">Buscar e Filtrar Cursos</h5>
            <p class="card-text">Filtre a lista de cursos já existentes no Moodle ou os resultados da sincronização.</p>
            <input type="text" id="searchInput" class="form-control form-control-lg" placeholder="Digite para buscar...">
        </div>
    </div>

<?php
// --- LÓGICA DE EXIBIÇÃO E PROCESSAMENTO ---

global $DB;

// Função para renderizar os cards de resultado (agora com status 'local')
function render_course_card($status, $title, $message, $footer_text) {
    $status_map = [
        'sucesso'   => ['color' => 'success', 'icon' => 'fa-check-circle'],
        'existente' => ['color' => 'warning', 'icon' => 'fa-info-circle'],
        'local'     => ['color' => 'primary', 'icon' => 'fa-check-square'], // Novo status para cursos locais
        'erro'      => ['color' => 'danger',  'icon' => 'fa-times-circle'],
        'formato_invalido' => ['color' => 'secondary', 'icon' => 'fa-question-circle']
    ];
    $s = $status_map[$status];
    $html = '<div class="card course-card border-' . s($s['color']) . '" data-search-term="' . s(strtolower($title . ' ' . $footer_text)) . '">';
    $html .= '<div class="card-body d-flex align-items-center">';
    $html .= '<div class="me-3 text-' . s($s['color']) . '"><i class="fas ' . s($s['icon']) . ' status-icon"></i></div>';
    $html .= '<div class="card-body-content">';
    $html .= '<h6 class="card-title mb-1">' . s($title) . '</h6>';
    $html .= '<p class="card-text text-muted mb-0">' . s($message) . '</p>';
    $html .= '</div></div>';
    $html .= '<div class="card-footer text-muted">' . s($footer_text) . '</div></div>';
    return $html;
}


// Decide o que fazer com base na ação da URL
if ($action === 'sync') {
    // ########## MODO DE SINCRONIZAÇÃO ##########
    echo '<h2>Resultados da Sincronização em Tempo Real</h2>';
    echo '<div id="loading" class="text-center my-5"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Carregando...</span></div><p id="loading-message" class="mt-2">Iniciando busca na API...</p></div>';
    echo '<div id="resultsContainer"></div>';
    flush();

    try {
        $defaultcategory = $DB->get_record('course_categories', ['id' => 1], '*', MUST_EXIST);
    } catch (Exception $e) {
        echo $OUTPUT->notification('<strong>Erro Crítico:</strong> A categoria padrão com ID 1 não foi encontrada. Por favor, crie-a antes de executar este script.', 'error');
        echo $OUTPUT->footer();
        die();
    }

    $pagina_atual = 1;
    $total_cursos_api = 0; $cursos_criados = 0; $cursos_existentes = 0; $cursos_com_erro = 0; $cursos_invalidos = 0;
    $api_client = new \local_ead_integration\webservice_client();

    do {
        echo '<script>document.getElementById("loading-message").textContent = "Buscando e processando página ' . $pagina_atual . '...";</script>';
        flush();

        $params = ['DtInicio' => '01/01/2020', 'DtFim' => '31/12/2026', 'registros_pagina' => 100, 'pagina' => $pagina_atual];
        $cursos_da_pagina = $api_client->call('getCursos', $params, true);

        if (!empty($cursos_da_pagina) && is_array($cursos_da_pagina)) {
            $total_cursos_api += count($cursos_da_pagina);
            foreach ($cursos_da_pagina as $curso_iesde) {
                $results_html = '';
                if (!is_array($curso_iesde) || !isset($curso_iesde['CursoID'], $curso_iesde['Nome'])) {
                    $cursos_invalidos++;
                    $results_html = render_course_card('formato_invalido', 'Formato Inválido', 'O registro da API não tem os dados esperados.', 'Dados: ' . htmlspecialchars(print_r($curso_iesde, true)));
                } else {
                    $idnumber_iesde = $curso_iesde['CursoID'];
                    $nome_completo_iesde = trim($curso_iesde['Nome']);
                    if ($DB->record_exists('course', ['idnumber' => $idnumber_iesde])) {
                        $cursos_existentes++;
                        $results_html = render_course_card('existente', $nome_completo_iesde, 'Encontrado na API, mas já existe no Moodle.', "ID IESDE: {$idnumber_iesde}");
                    } else {
                        $newcourse = new stdClass();
                        $newcourse->fullname  = $nome_completo_iesde;
                        $newcourse->shortname = 'IESDE-' . $idnumber_iesde;
                        $newcourse->category  = $defaultcategory->id;
                        $newcourse->idnumber  = $idnumber_iesde;
                        $newcourse->visible   = 1;
                        $newcourse->format    = 'topics';
                        try {
                            $course = create_course($newcourse);
                            $cursos_criados++;
                            $results_html = render_course_card('sucesso', $nome_completo_iesde, "Curso novo criado com sucesso! ID Moodle: {$course->id}", "ID IESDE: {$idnumber_iesde}");
                        } catch (Exception $e) {
                            $cursos_com_erro++;
                            $results_html = render_course_card('erro', $nome_completo_iesde, 'Ocorreu um erro ao tentar criar o curso.', "Erro: " . s($e->getMessage()));
                        }
                    }
                }
                echo '<script>document.getElementById("resultsContainer").insertAdjacentHTML("beforeend", ' . json_encode($results_html) . ');</script>';
            }
            $pagina_atual++;
        } else {
            $cursos_da_pagina = [];
        }
    } while (!empty($cursos_da_pagina));

    echo '<script>document.getElementById("loading").style.display = "none";</script>';
    echo '<h3 class="mt-5">Sincronização Concluída!</h3>';
    // Renderiza o card de resumo no final da sincronização
    include 'summary_card.php';

} else {
    // ########## MODO DE VISUALIZAÇÃO PADRÃO ##########

    // Busca todos os cursos do Moodle que possuem um ID Number (indicando que vieram da sincronização)
    $local_courses = $DB->get_records_sql("SELECT id, fullname, shortname, idnumber FROM {course} WHERE idnumber IS NOT NULL AND idnumber <> '' ORDER BY fullname ASC");

    echo '<div id="resultsContainer">';
    if (empty($local_courses)) {
        echo $OUTPUT->notification('Nenhum curso sincronizado encontrado no Moodle. Clique no botão acima para iniciar a primeira sincronização.');
    } else {
        foreach ($local_courses as $course) {
            echo render_course_card(
                'local',
                $course->fullname,
                'Curso já existente no Moodle.',
                "ID IESDE (ID Number): {$course->idnumber}"
            );
        }
    }
    echo '</div>';
    
    // Mostra um resumo simples dos cursos locais
    $total_local = count($local_courses);
    ?>
    <div class="card mb-4">
        <div class="card-header"><strong>Resumo de Cursos no Moodle</strong></div>
        <div class="card-body text-center">
            <h4><?php echo $total_local; ?></h4>
            <p class="mb-0">Total de cursos com ID externo encontrados</p>
        </div>
    </div>
    <?php
}
?>
</div>

<script>
// O JavaScript de busca continua funcionando normalmente para os itens já carregados
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('searchInput');
    if (searchInput) {
        searchInput.addEventListener('keyup', function() {
            const searchTerm = searchInput.value.toLowerCase();
            const courseCards = document.querySelectorAll('#resultsContainer .course-card');
            
            courseCards.forEach(function(card) {
                const cardText = card.dataset.searchTerm || '';
                if (cardText.includes(searchTerm)) {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            });
        });
    }

    // Opcional: Desabilita o botão de sincronizar após o clique para evitar cliques duplos
    const syncButton = document.querySelector('a[href="?action=sync"]');
    if (syncButton) {
        syncButton.addEventListener('click', function() {
            syncButton.classList.add('disabled');
            syncButton.innerHTML = '<i class="fas fa-sync-alt fa-spin me-1"></i> Sincronizando...';
        });
    }
});
</script>

<?php
// Finaliza a página Moodle
echo $OUTPUT->footer();
?>