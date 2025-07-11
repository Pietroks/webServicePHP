<?php
require_once('../../config.php');
require_login();
require_admin();

global $DB, $OUTPUT, $PAGE, $CFG;

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/ead_integration/index.php'));
$PAGE->set_title('Painel de Integração IESDE');
$PAGE->set_heading('Painel de Integração IESDE');

// --- Lógica de Busca e Paginação ---
$search_query = optional_param('search', '', PARAM_TEXT);
$status_filter = optional_param('status', '', PARAM_ALPHA);
$page = optional_param('page', 0, PARAM_INT);
$perpage = 20;

$sql_conditions = [];
$sql_params = [];

// Filtro por Status
if (!empty($status_filter)) {
    $sql_conditions[] = 'l.status = :status';
    $sql_params['status'] = $status_filter;
}

// Filtro por busca geral
if (!empty($search_query)) {
    $search_like = '%' . $search_query . '%';
    $sql_conditions[] = '(' . $DB->sql_like('u.firstname', ':s1', false) . " OR " .
                        $DB->sql_like('u.lastname', ':s2', false) . " OR " .
                        $DB->sql_like('u.email', ':s3', false) . " OR " .
                        $DB->sql_like('c.fullname', ':s4', false) . " OR " .
                        $DB->sql_like('l.response', ':s5', false) . ')';
    
    for ($i = 1; $i <= 5; $i++) {
        $sql_params['s' . $i] = $search_like;
    }
}

$sql_where = !empty($sql_conditions) ? 'WHERE ' . implode(' AND ', $sql_conditions) : '';

// --- Queries para Estatísticas e Logs ---
$total_enrolls = $DB->count_records('eadintegration_logs', ['action' => 'enroll', 'status' => 'success']);
$total_errors = $DB->count_records('eadintegration_logs', ['status' => 'error']);
$enrolls_24h = $DB->count_records_select('eadintegration_logs', 'action = :action AND status = :status AND timecreated >= :time', ['action' => 'enroll', 'status' => 'success', 'time' => time() - 86400]);
$errors_24h = $DB->count_records_select('eadintegration_logs', 'status = :status AND timecreated >= :time', ['status' => 'error', 'time' => time() - 86400]);

$sql_from = "FROM {eadintegration_logs} l
             JOIN {user} u ON u.id = l.moodle_userid
             JOIN {course} c ON c.id = l.moodle_courseid";

$total_logs = $DB->count_records_sql("SELECT COUNT(l.id) $sql_from $sql_where", $sql_params);
$logs = $DB->get_records_sql("SELECT l.*, u.firstname, u.lastname, c.fullname AS coursename $sql_from $sql_where ORDER BY l.id DESC", $sql_params, $page * $perpage, $perpage);

// --- Início do Layout ---
echo $OUTPUT->header();

// Estilos
echo <<<HTML
<style>
.card { border: 1px solid #ddd; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
.card-header { background-color: #f7f7f7; padding: 12px 15px; font-weight: bold; border-bottom: 1px solid #ddd; }
.card-body { padding: 15px; }
.stat-card { text-align: center; }
.stat-card a { text-decoration: none; color: inherit; }
.stat-card .stat-number { font-size: 2.5em; font-weight: bold; }
.stat-card .stat-label { font-size: 1.1em; color: #555; }
.stat-card.errors:hover { background-color: #f8d7da; cursor: pointer; }
.stat-card.success:hover { background-color: #d4edda; cursor: pointer; }
.tool-buttons a { margin-right: 10px; margin-bottom: 10px; }
</style>
HTML;

echo '<h3>📊 Painel de Controle da Integração</h3>';
echo '<p>Visão geral e logs das matrículas realizadas através da integração com o IESDE.</p>';

// --- Cards de Estatísticas ---
echo '<div class="row">';
function render_stat_card($number, $label, $icon, $link_params = [], $card_class = '') {
    $url = new moodle_url('/local/ead_integration/index.php', $link_params);
    $html = '
    <div class="col-md-3">
        <div class="card stat-card ' . $card_class . '">
            <a href="' . $url . '">
                <div class="card-body">
                    <div class="stat-number">' . $icon . ' ' . $number . '</div>
                    <div class="stat-label">' . $label . '</div>
                </div>
            </a>
        </div>
    </div>';
    return $html;
}
echo render_stat_card($total_enrolls, 'Matrículas Totais', '✅', ['status' => 'success'], 'success');
echo render_stat_card($total_errors, 'Erros Totais', '❌', ['status' => 'error'], 'errors');
echo render_stat_card($enrolls_24h, 'Matrículas (24h)', '🚀');
echo render_stat_card($errors_24h, 'Erros (24h)', '🔥', ['status' => 'error'], 'errors');
echo '</div>';

// --- Card de Ações Rápidas e Ferramentas ---
echo '<div class="card"><div class="card-header">🛠️ Ações e Ferramentas</div><div class="card-body tool-buttons">';
echo '<a href="' . new moodle_url('/local/ead_integration/enroll.php') . '" class="btn btn-primary">➕ Nova Matrícula</a> ';
echo '<a href="' . new moodle_url('/admin/settings.php?section=local_ead_integration_settings') . '" class="btn btn-secondary">⚙️ Configurar Chaves de API</a>';
// BOTÕES PARA OS LOGS
echo '<a href="' . new moodle_url('/local/ead_integration/logs.php') . '" class="btn btn-info">📜 Ver Logs de Matrícula</a>';
echo '<a href="' . new moodle_url('/local/ead_integration/sync_logs.php') . '" class="btn btn-warning">📋 Ver Logs de Sincronização</a>';
echo '<hr><p>Para forçar a execução de tarefas agendadas (como a sincronização de cursos), execute o cron do Moodle via terminal (recomendado) ou navegador:</p>';
echo '<code>php ' . $CFG->dirroot . '/admin/cli/cron.php</code>';
echo '</div></div>';

// --- Card de Logs de Matrícula ---
echo '<div class="card">';
echo '<div class="card-header">📜 Logs de Matrícula</div>';
echo '<div class="card-body">';

echo '<form method="get">';
echo '<div class="input-group mb-3">';
echo '<input type="text" name="search" class="form-control" placeholder="Buscar por nome, email, CPF, curso ou ID da matrícula IESDE..." value="' . s($search_query) . '">';
echo '<div class="input-group-append">';
echo '<button class="btn btn-info" type="submit">🔎 Buscar</button> ';
echo '<a href="' . new moodle_url('/local/ead_integration/index.php') . '" class="btn btn-outline-secondary">Limpar</a>';
echo '</div></div></form>';

if ($logs) {
    $table = new html_table();
    $table->head = ['Status', 'Aluno', 'Curso', 'Data', 'Mensagem da API'];
    $table->attributes['class'] = 'generaltable';

    foreach ($logs as $log) {
        $status_icon = $log->status === 'success' ? '✅' : '❌';
        $user_link = new moodle_url('/user/profile.php', ['id' => $log->moodle_userid]);
        
        $response_data = json_decode($log->response);
        $message = isset($response_data->Mensagem) ? $response_data->Mensagem : $log->message;
        if (isset($response_data->MatriculaID)) {
             $message .= ' (ID: ' . $response_data->MatriculaID . ')';
        }

        $row = new html_table_row([
            $status_icon,
            html_writer::link($user_link, fullname($log)),
            $log->coursename,
            date('d/m/Y H:i', $log->timecreated),
            html_writer::tag('small', s($message))
        ]);
        if ($log->status === 'error') {
            $row->attributes['class'] = 'table-danger';
        }
        $table->data[] = $row;
    }
    echo html_writer::table($table);
    echo $OUTPUT->paging_bar($total_logs, $page, $perpage, new moodle_url('/local/ead_integration/index.php', ['search' => $search_query, 'status' => $status_filter]));
} else {
    echo $OUTPUT->notification('Nenhum log encontrado com os critérios de busca.');
}

echo '</div></div>';

echo $OUTPUT->footer();