<?php
require_once('../../config.php');
require_login();
require_admin();

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/ead_integration/sync_logs.php'));
$PAGE->set_title('Logs de Sincronização IESDE');
$PAGE->set_heading('Logs de Sincronização IESDE');

echo $OUTPUT->header();

global $DB;

$logs = $DB->get_records('eadintegration_sync_logs', null, 'timecreated DESC', '*', 0, 50);

if ($logs) {
    echo '<table class="generaltable"><thead>
        <tr>
            <th>Data</th><th>CPF</th><th>Nome</th><th>Email</th><th>Status</th><th>Mensagem</th>
        </tr></thead><tbody>';

    foreach ($logs as $log) {
        echo '<tr>';
        echo '<td>' . date('d/m/Y H:i', $log->timecreated) . '</td>';
        echo '<td>' . s($log->cpf) . '</td>';
        echo '<td>' . s($log->nome) . '</td>';
        echo '<td>' . s($log->email) . '</td>';
        echo '<td style="color:' . ($log->sucesso ? 'green' : 'red') . ';">' . ($log->sucesso ? 'Sucesso' : 'Erro') . '</td>';
        echo '<td>' . s($log->mensagem) . '</td>';
        echo '</tr>';
    }

    echo '</tbody></table>';
} else {
    echo '<p>Nenhum log encontrado.</p>';
}
echo '<p><a href="' . new moodle_url('/local/ead_integration/enroll.php') . '" style="text-decoration:none; font-weight:bold; margin-top: 2rem;">← Voltar para gestão de matrículas</a></p>';
echo '<p><a href="' . new moodle_url('/local/ead_integration/index.php') . '" style="text-decoration:none; font-weight:bold; margin-top: 2rem;">← Voltar para dashboard</a></p>';

echo $OUTPUT->footer();
