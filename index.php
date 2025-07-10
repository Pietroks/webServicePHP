<?php
require_once('../../config.php');
require_login();
require_admin();

global $DB, $OUTPUT, $PAGE;

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/ead_integration/index.php'));
$PAGE->set_title('Integração IESDE');
$PAGE->set_heading('Painel da Integração IESDE');

echo $OUTPUT->header();

echo '<p>Este painel fornece uma visão geral da sincronização entre o Moodle e o IESDE.</p>';
echo '<hr>';

// 🔍 Filtros
$cpfemail = optional_param('cpfemail', '', PARAM_TEXT);
$data_de = optional_param('data_de', '', PARAM_TEXT);
$data_ate = optional_param('data_ate', '', PARAM_TEXT);

// Monta filtro SQL dinâmico
$where = [];
$params = [];

if ($cpfemail) {
    $where[] = '(cpf LIKE :cpf OR email LIKE :email)';
    $params['cpf'] = '%' . $cpfemail . '%';
    $params['email'] = '%' . $cpfemail . '%';
}


if ($data_de) {
    $where[] = 'timecreated >= :data_de';
    $params['data_de'] = strtotime($data_de . ' 00:00:00');
}
if ($data_ate) {
    $where[] = 'timecreated <= :data_ate';
    $params['data_ate'] = strtotime($data_ate . ' 23:59:59');
}

$sql_where = $where ? implode(' AND ', $where) : '1=1';

echo '<h3>🔍 Filtro de Logs</h3>';
echo '<form method="get" style="margin-bottom: 20px;">';
echo '<label>CPF ou Email: <input type="text" name="cpfemail" value="' . s($cpfemail) . '" /></label> ';
echo '<label>De: <input type="date" name="data_de" value="' . s($data_de) . '" /></label> ';
echo '<label>Até: <input type="date" name="data_ate" value="' . s($data_ate) . '" /></label> ';
echo '<button type="submit">🔎 Buscar</button> ';
echo '<a href="index.php">Limpar</a>';
echo '</form>';

// Última sincronização
$lastLog = $DB->get_record_sql("SELECT * FROM {eadintegration_sync_logs} ORDER BY timecreated DESC LIMIT 1");
$totalLogs = $DB->count_records('eadintegration_sync_logs');

echo '<h3>📅 Última Sincronização</h3>';
if ($lastLog) {
    echo '<ul>';
    echo '<li><strong>Data:</strong> ' . date('d/m/Y H:i', $lastLog->timecreated) . '</li>';
    echo '<li><strong>Usuário:</strong> ' . format_string($lastLog->nome) . ' (' . s($lastLog->email) . ')</li>';
    echo '<li><strong>CPF:</strong> ' . s($lastLog->cpf) . '</li>';
    echo '<li><strong>Status:</strong> ' . ($lastLog->sucesso ? '✅ Sucesso' : '❌ Erro') . '</li>';
    echo '<li><strong>Mensagem:</strong> ' . s($lastLog->mensagem) . '</li>';
    echo '</ul>';
} else {
    echo '<p>Nenhum registro de sincronização encontrado.</p>';
}

echo "<p><strong>Total de registros:</strong> {$totalLogs}</p>";
echo '<hr>';

// Últimos erros filtrados
$erros = $DB->get_records_select('eadintegration_sync_logs', "$sql_where AND sucesso = 0", $params, 'timecreated DESC', '*', 0, 10);

echo '<h3>❌ Últimos Erros Encontrados</h3>';
if ($erros) {
    echo '<table class="generaltable">';
    echo '<thead><tr><th>Data</th><th>CPF</th><th>Nome</th><th>Email</th><th>Mensagem</th></tr></thead><tbody>';
    foreach ($erros as $e) {
        echo '<tr>';
        echo '<td>' . date('d/m/Y H:i', $e->timecreated) . '</td>';
        echo '<td>' . s($e->cpf) . '</td>';
        echo '<td>' . s($e->nome) . '</td>';
        echo '<td>' . s($e->email) . '</td>';
        echo '<td>' . s($e->mensagem) . '</td>';
        echo '</tr>';
    }
    echo '</tbody></table>';
} else {
    echo '<p>Nenhum erro encontrado com os filtros aplicados.</p>';
}
echo '<hr>';

// ⚡ Botão de sincronização manual (instrução)
echo '<h3>🛠 Forçar Sincronização Manual</h3>';
echo '<p>Execute este comando no terminal do servidor:</p>';
echo '<pre>php admin/cli/cron.php</pre>';
echo '<p>Ou acesse o cron via navegador (se permitido): <a href="' . $CFG->wwwroot . '/admin/cron.php" target="_blank">' . $CFG->wwwroot . '/admin/cron.php</a></p>';
echo '<hr>';

// 🔗 Links úteis
echo '<h3>🔗 Ações Rápidas</h3>';
echo '<ul style="font-size: 1.1em;">';
echo '<li><a href="' . new moodle_url('/local/ead_integration/sync_logs.php') . '">📜 Ver todos os logs</a></li>';
echo '<li><a href="' . new moodle_url('/local/ead_integration/enroll.php') . '">👥 Gerenciar matrículas</a></li>';
echo '<li><a href="' . new moodle_url('/admin/settings.php?section=local_ead_integration') . '">⚙️ Configurações do plugin</a></li>';
echo '</ul>';

echo $OUTPUT->footer();
