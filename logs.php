<?php
require_once('../../config.php');
require_login();
require_admin();

global $DB, $PAGE, $OUTPUT, $CFG;

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/ead_integration/logs.php'));
$PAGE->set_title('Logs de Integração IESDE');
$PAGE->set_heading('Logs de Integração IESDE');

// --- Lógica de Busca e Paginação ---
$search_query = optional_param('search', '', PARAM_RAW_TRIMMED);
$page = optional_param('page', 0, PARAM_INT);
$perpage = 20;

$sql_conditions = [];
$sql_params = [];

if (!empty($search_query)) {
    $search_like = '%' . $search_query . '%';
    // Busca unificada e mais completa
    $sql_conditions[] = '(' . $DB->sql_like('u.firstname', ':s1', false) . " OR " .
                        $DB->sql_like('u.lastname', ':s2', false) . " OR " .
                        $DB->sql_like('u.email', ':s3', false) . " OR " .
                        $DB->sql_like('c.fullname', ':s4', false) . " OR " .
                        $DB->sql_like('l.response', ':s5', false) . ')'; // Busca no JSON de resposta
    
    for ($i = 1; $i <= 5; $i++) {
        $sql_params['s' . $i] = $search_like;
    }
}

$sql_where = !empty($sql_conditions) ? 'WHERE ' . implode(' AND ', $sql_conditions) : '';

// --- Queries ---
$sql_from = "FROM {eadintegration_logs} l
             JOIN {user} u ON u.id = l.moodle_userid
             JOIN {course} c ON c.id = l.moodle_courseid";

$total_logs = $DB->count_records_sql("SELECT COUNT(l.id) $sql_from $sql_where", $sql_params);
$logs = $DB->get_records_sql("SELECT l.*, u.firstname, u.lastname, c.fullname AS coursename $sql_from $sql_where ORDER BY l.id DESC", $sql_params, $page * $perpage, $perpage);


// --- Início do Layout ---
echo $OUTPUT->header();

echo <<<HTML
<style>
.card { border: 1px solid #ddd; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
.card-header { background-color: #f7f7f7; padding: 12px 15px; font-weight: bold; border-bottom: 1px solid #ddd; }
.card-body { padding: 15px; }
.response-textarea { width: 100%; min-height: 80px; font-family: monospace; font-size: 0.9em; background: #f9f9f9; border: 1px solid #eee; resize: vertical; }
#log-table tbody tr.hidden-row { display: none; }
.footer-actions a { margin-right: 10px; }
</style>
HTML;

echo '<p>Aqui você pode analisar cada operação de matrícula registrada entre o Moodle e o IESDE.</p>';

// --- Card de Logs com Filtro ---
echo '<div class="card">';
echo '<div class="card-header">Filtro e Resultados</div>';
echo '<div class="card-body">';

// Formulário de busca dinâmico
echo '<div class="input-group mb-3">';
echo '<input type="text" id="dynamic-search-input" class="form-control" placeholder="Digite para filtrar os resultados visíveis...">';
echo '</div>';

// Formulário de busca no BD (para paginação)
echo '<form method="get" class="mb-3">';
echo '  <input type="hidden" name="search" id="server-search-input" value="' . s($search_query) . '">';
echo '  <button class="btn btn-info" type="submit">🔎 Buscar em todos os logs</button> ';
echo '  <a href="' . new moodle_url('/local/ead_integration/logs.php') . '" class="btn btn-outline-secondary">Limpar Busca</a>';
echo '</form>';


if ($logs) {
    $table = new html_table();
    $table->id = 'log-table';
    $table->head = ['Status', 'Aluno', 'Curso', 'Ação', 'Data', 'Resposta da API'];
    $table->attributes['class'] = 'generaltable';

    foreach ($logs as $log) {
        $status_icon = $log->status === 'success' ? '✅' : '❌';
        $user_link = new moodle_url('/user/profile.php', ['id' => $log->moodle_userid]);
        
        $response_formatted = json_encode(json_decode($log->response), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $response_field = html_writer::tag('textarea', $response_formatted, ['class' => 'response-textarea', 'rows' => 4, 'readonly' => 'readonly']);
        
        $row = new html_table_row([
            $status_icon,
            html_writer::link($user_link, fullname($log)),
            $log->coursename,
            ucfirst($log->action),
            date('d/m/Y H:i', $log->timecreated),
            $response_field
        ]);

        if ($log->status === 'error') {
            $row->attributes['class'] = 'table-danger';
        }
        
        $searchable_data = strtolower(
            fullname($log) . ' ' . $log->coursename . ' ' . $log->action . ' ' . $log->status . ' ' . $log->response
        );
        $row->attributes['data-search'] = $searchable_data;
        
        $table->data[] = $row;
    }
    echo html_writer::table($table);
    echo $OUTPUT->paging_bar($total_logs, $page, $perpage, new moodle_url('/local/ead_integration/logs.php', ['search' => $search_query]));
} else {
    echo $OUTPUT->notification('Nenhum log encontrado.');
}

echo '</div></div>'; // Fim do card

echo '<div class="footer-actions">';
echo '<a href="' . new moodle_url('/local/ead_integration/index.php') . '" class="btn btn-secondary">← Voltar ao Painel</a>';
// BOTÃO RESTAURADO:
echo '<a href="' . new moodle_url('/local/ead_integration/enroll.php') . '" class="btn btn-secondary">← Voltar para Matrículas</a>';
echo '</div>';


echo $OUTPUT->footer();

// JAVASCRIPT PARA A BUSCA DINÂMICA
echo <<<JS
<script>
document.addEventListener('DOMContentLoaded', function() {
    const dynamicInput = document.getElementById('dynamic-search-input');
    const serverInput = document.getElementById('server-search-input');
    const table = document.getElementById('log-table');
    const rows = table ? table.querySelectorAll('tbody tr') : [];

    if (dynamicInput) {
        // Popula o campo de filtro dinâmico com o valor da busca do servidor
        dynamicInput.value = serverInput.value;
        
        // Função para filtrar as linhas
        const filterRows = () => {
            const filter = dynamicInput.value.toLowerCase();
            rows.forEach(row => {
                const searchData = row.getAttribute('data-search');
                if (searchData && searchData.includes(filter)) {
                    row.classList.remove('hidden-row');
                } else {
                    row.classList.add('hidden-row');
                }
            });
        };

        // Filtra ao digitar
        dynamicInput.addEventListener('input', filterRows);
        
        // Atualiza o campo de busca do servidor antes de submeter o form
        dynamicInput.form.addEventListener('submit', function() {
            serverInput.value = dynamicInput.value;
        });

        // Filtra os resultados iniciais que vieram do servidor
        filterRows();
    }
});
</script>
JS;