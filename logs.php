<?php
require_once('../../config.php');
require_login();
require_admin();

global $DB, $PAGE, $OUTPUT;

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/ead_integration/logs.php'));
$PAGE->set_title('Logs de Integração IESDE');
$PAGE->set_heading('Logs de Integração IESDE');

echo $OUTPUT->header();

$perpage = 20;
$page = optional_param('page', 0, PARAM_INT);
$search = optional_param('search', '', PARAM_RAW_TRIMMED);

$params = [];
$wheresql = '';

if (!empty($search)) {
    $wheresql = "WHERE u.firstname LIKE :search1 OR u.lastname LIKE :search2 OR c.fullname LIKE :search3";
    $params['search1'] = '%' . $search . '%';
    $params['search2'] = '%' . $search . '%';
    $params['search3'] = '%' . $search . '%';
}

$total = $DB->count_records_sql("
    SELECT COUNT(1)
    FROM {eadintegration_logs} l
    JOIN {user} u ON u.id = l.moodle_userid
    JOIN {course} c ON c.id = l.moodle_courseid
    $wheresql
", $params);

$limit = $perpage;
$offset = $page * $perpage;

// NÃO passar limit e offset no params
// $params['limit'] = $limit;
// $params['offset'] = $offset;

$logs = $DB->get_records_sql("
    SELECT l.*, u.firstname, u.lastname, c.fullname AS coursename
    FROM {eadintegration_logs} l
    JOIN {user} u ON u.id = l.moodle_userid
    JOIN {course} c ON c.id = l.moodle_courseid
    $wheresql
    ORDER BY l.timecreated DESC
    LIMIT $limit OFFSET $offset
", $params);

echo '<form method="get" action="">
    <input type="text" name="search" placeholder="Buscar por aluno ou curso" value="' . s($search) . '">
    <button type="submit">Buscar</button>
</form><br>';

if ($logs) {
    echo '<table border="1" cellpadding="8" cellspacing="0">';
    echo '<tr style="background:#f0f0f0;">
        <th>Data</th>
        <th>Aluno</th>
        <th>Curso</th>
        <th>Ação</th>
        <th>Status</th>
        <th>Mensagem</th>
        <th>Resposta da API</th>
    </tr>';

    foreach ($logs as $log) {
        echo '<tr>';
        echo '<td>' . date('d/m/Y H:i', $log->timecreated) . '</td>';
        echo '<td>' . fullname((object)['firstname' => $log->firstname, 'lastname' => $log->lastname]) . '</td>';
        echo '<td>' . $log->coursename . '</td>';
        echo '<td>' . ucfirst($log->action) . '</td>';
        echo '<td style="color:' . ($log->status === 'success' ? 'green' : 'red') . ';">' . $log->status . '</td>';
        echo '<td>' . s($log->message) . '</td>';
        echo '<td><pre style="max-width:400px; white-space:pre-wrap; word-wrap:break-word;">' . s($log->response) . '</pre></td>';
        echo '</tr>';
    }

    echo '</table>';

    echo $OUTPUT->paging_bar($total, $page, $perpage, new moodle_url('/local/ead_integration/logs.php', ['search' => $search]));
} else {
    echo '<p>Nenhum log encontrado.</p>';
}
echo '<p><a href="' . new moodle_url('/local/ead_integration/enroll.php') . '" style="text-decoration:none; font-weight:bold; margin-top: 2rem;">← Voltar para gestão de matrículas</a></p>';

echo $OUTPUT->footer();
