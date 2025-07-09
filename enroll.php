<?php
require_once('../../config.php');
require_login();
require_admin();

global $DB, $PAGE, $OUTPUT;

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/ead_integration/enroll.php'));
$PAGE->set_title('Gestão de Matrículas IESDE');
$PAGE->set_heading('Gestão de Matrículas IESDE');

echo $OUTPUT->header();

$action = optional_param('action', '', PARAM_ALPHA);
$moodle_userid = optional_param('moodle_userid', 0, PARAM_INT);
$moodle_courseid = optional_param('moodle_courseid', 0, PARAM_INT);

function print_message_box($message, $type = 'info') {
    $colors = [
        'success' => '#d4edda',
        'error' => '#f8d7da',
        'warning' => '#fff3cd',
        'info' => '#cce5ff'
    ];
    $color = isset($colors[$type]) ? $colors[$type] : $colors['info'];
    echo '<div style="border:1px solid #ccc; background-color: '.$color.'; padding:15px; margin:15px 0; border-radius:5px;">';
    echo $message;
    echo '</div>';
}

// Função para registrar logs na tabela eadintegration_logs
function register_log($user, $course, $action, $status, $message, $response) {
    global $DB;
    $log = new stdClass();
    $log->moodle_userid = $user->id;
    $log->moodle_courseid = $course->id;
    $log->action = $action;
    $log->status = $status;
    $log->message = $message;
    $log->response = $response;
    $log->timecreated = time();
    $DB->insert_record('eadintegration_logs', $log);
}

// PROCESSAMENTO
if ($_SERVER['REQUEST_METHOD'] === 'POST' && confirm_sesskey()) {

    if ($action === 'revert') {
        // Reversão
        $user = $DB->get_record('user', ['id' => $moodle_userid], '*', MUST_EXIST);
        $course = $DB->get_record('course', ['id' => $moodle_courseid], '*', MUST_EXIST);

        $enroll = $DB->get_record('eadintegration_enrolls', [
            'moodle_userid' => $user->id,
            'moodle_courseid' => $course->id
        ], '*', IGNORE_MISSING);

        echo '<h2>Revertendo Matrícula</h2>';
        echo '<pre>';

        if (!$enroll) {
            print_message_box('⚠️ Matrícula local não encontrada para esse usuário e curso.', 'warning');
        } else {
            $api_client = new \local_ead_integration\webservice_client();

            $params_cancel = [
                'MatriculaID' => $enroll->iesde_matriculaid,
                'Situacao' => 'I'
            ];
            $cancel_result = $api_client->call('situacao', $params_cancel, false);

            // Registra log da reversão
            register_log(
                $user,
                $course,
                'revert',
                (isset($cancel_result['status']) && $cancel_result['status'] == 1) ? 'success' : 'error',
                json_encode($cancel_result),
                json_encode($cancel_result)
            );

            if (is_array($cancel_result) && isset($cancel_result['status'])) {
                if ($cancel_result['status'] === 1) {
                    print_message_box("✅ Matrícula inativada com sucesso na API IESDE.", 'success');

                    // Remove da tabela local
                    $DB->delete_records('eadintegration_enrolls', ['id' => $enroll->id]);
                    print_message_box("✅ Registro local removido.", 'success');

                    // Desmatricula no Moodle
                    require_once($CFG->dirroot . '/enrol/manual/locallib.php');
                    $enrol_plugin = enrol_get_plugin('manual');
                    if ($instances = enrol_get_instances($course->id, true)) {
                        foreach ($instances as $instance) {
                            if ($instance->enrol === 'manual') {
                                $enrol_plugin->unenrol_user($instance, $user->id);
                                print_message_box("✅ Usuário desmatriculado no Moodle.", 'success');
                                break;
                            }
                        }
                    }
                } else {
                    print_message_box('❌ Erro ao inativar matrícula na API:<br><pre>' . print_r($cancel_result, true) . '</pre>', 'error');
                }
            } else {
                print_message_box('❌ Resposta inesperada da API:<br><pre>' . print_r($cancel_result, true) . '</pre>', 'error');
            }
        }
        echo '</pre>';
        echo '<a href="' . new moodle_url('/local/ead_integration/enroll.php') . '">← Voltar para gestão de matrículas</a>';
        echo $OUTPUT->footer();
        exit;
    }

    if ($action === 'enroll' || $action === '') {
        // Matrícula
        $user = $DB->get_record('user', ['id' => $moodle_userid], '*', MUST_EXIST);
        $course = $DB->get_record('course', ['id' => $moodle_courseid], '*', MUST_EXIST);

        $cpf = $DB->get_field_sql("
            SELECT uid.data
            FROM {user_info_data} uid
            JOIN {user_info_field} uif ON uid.fieldid = uif.id
            WHERE uid.userid = :userid AND uif.shortname = 'cpf'
        ", ['userid' => $user->id]);

        if (empty($cpf)) {
            print_message_box('❌ Este usuário não possui CPF cadastrado no perfil personalizado "cpf".', 'error');
            echo '<a href="' . new moodle_url('/local/ead_integration/enroll.php') . '">← Voltar</a>';
            echo $OUTPUT->footer();
            exit;
        }

        if ($DB->record_exists('eadintegration_enrolls', ['moodle_userid' => $user->id, 'moodle_courseid' => $course->id])) {
            print_message_box('❌ Este usuário já possui uma matrícula IESDE para este curso.', 'error');
            echo '<a href="' . new moodle_url('/local/ead_integration/enroll.php') . '">← Voltar</a>';
            echo $OUTPUT->footer();
            exit;
        }

        $api_client = new \local_ead_integration\webservice_client();
        $params = [
            'CursoID' => $course->idnumber,
            'PoloID' => '0',
            'Nome' => fullname($user),
            'CPF' => preg_replace('/[^0-9]/', '', $cpf),
            'Email' => $user->email,
            'CEP' => '00000000',
            'Numero' => '0',
        ];

        echo '<h2>Processando Matrícula...</h2><pre>';

        $resultado = $api_client->call('cadastro', $params, false);

        // Registra log da matrícula
        register_log(
            $user,
            $course,
            'enroll',
            (isset($resultado['status']) && $resultado['status'] == 1) ? 'success' : 'error',
            json_encode($resultado),
            json_encode($resultado)
        );

        if (is_array($resultado) && isset($resultado['status'])) {
            if ($resultado['status'] == 1) {
                $new_enrollment = new stdClass();
                $new_enrollment->moodle_userid = $user->id;
                $new_enrollment->moodle_courseid = $course->id;
                $new_enrollment->iesde_matriculaid = $resultado['MatriculaID'];
                $new_enrollment->timecreated = time();
                $new_enrollment->timemodified = time();

                $DB->insert_record('eadintegration_enrolls', $new_enrollment);

                print_message_box("✅ Aluno '" . fullname($user) . "' matriculado no curso '{$course->fullname}' com Matrícula IESDE ID: " . $resultado['MatriculaID'], 'success');

                require_once($CFG->dirroot . '/enrol/manual/locallib.php');
                $enrol = enrol_get_plugin('manual');
                $instances = enrol_get_instances($course->id, true);

                foreach ($instances as $instance) {
                    if ($instance->enrol === 'manual') {
                        $enrol->enrol_user($instance, $user->id, 5);
                        print_message_box("✅ Usuário matriculado também no Moodle.", 'success');
                        break;
                    }
                }
            } else {
                print_message_box('❌ Falha ao matricular: <pre>' . print_r($resultado, true) . '</pre>', 'error');
            }
        } else {
            print_message_box('❌ Resposta inesperada da API:<br><pre>' . print_r($resultado, true) . '</pre>', 'error');
        }

        echo '</pre>';
        echo '<a href="' . new moodle_url('/local/ead_integration/enroll.php') . '">← Voltar para gestão de matrículas</a>';
        echo $OUTPUT->footer();
        exit;
    }
}

// Formulário e lista

$users = $DB->get_records('user', ['deleted' => 0], 'lastname ASC', 'id, firstname, lastname, email');
$courses = $DB->get_records('course', [], 'fullname ASC', 'id, fullname, idnumber');
$iesde_courses = array_filter($courses, function($course) {
    return !empty($course->idnumber) && is_numeric($course->idnumber);
});

echo '<h2>Nova Matrícula</h2>';
echo '<form method="post">';
echo '<input type="hidden" name="sesskey" value="' . sesskey() . '">';
echo '<input type="hidden" name="action" value="enroll">';

echo '<label for="moodle_userid">Selecione o Usuário:</label><br>';
echo '<select name="moodle_userid" id="moodle_userid" required>';
echo '<option value="">-- Selecione --</option>';
foreach ($users as $user) {
    echo '<option value="' . $user->id . '">' . fullname($user) . ' (' . $user->email . ')</option>';
}
echo '</select><br><br>';

echo '<label for="moodle_courseid">Selecione o Curso IESDE:</label><br>';
echo '<select name="moodle_courseid" id="moodle_courseid" required>';
echo '<option value="">-- Selecione --</option>';
foreach ($iesde_courses as $course) {
    echo '<option value="' . $course->id . '">' . $course->fullname . '</option>';
}
echo '</select><br><br>';

echo '<button type="submit" style="padding:10px 20px; font-weight:bold;">✅ Matricular Aluno</button>';
echo '</form>';

// Lista matrículas existentes

$existing_enrollments = $DB->get_records('eadintegration_enrolls');
if ($existing_enrollments) {
    echo '<hr><h2>Matrículas Existentes</h2><ul style="list-style:none; padding-left:0;">';
    foreach ($existing_enrollments as $enroll) {
        $u = $DB->get_record('user', ['id' => $enroll->moodle_userid]);
        $c = $DB->get_record('course', ['id' => $enroll->moodle_courseid]);
        if (!$u || !$c) {
            continue;
        }

        echo '<li style="margin-bottom:10px; padding:10px; border:1px solid #ddd; border-radius:5px;">';
        echo '<strong>' . fullname($u) . '</strong> — Curso: <em>' . $c->fullname . '</em><br>';
        echo 'Matrícula IESDE ID: ' . $enroll->iesde_matriculaid . '<br>';

        // Botão de reverter matrícula
        echo '<form method="post" style="display:inline-block; margin-top:5px;" onsubmit="return confirm(\'Deseja realmente reverter esta matrícula? Isso irá inativar o aluno no sistema da IESDE e desmatriculá-lo no Moodle.\');">';
        echo '<input type="hidden" name="sesskey" value="' . sesskey() . '">';
        echo '<input type="hidden" name="action" value="revert">';
        echo '<input type="hidden" name="moodle_userid" value="' . $u->id . '">';
        echo '<input type="hidden" name="moodle_courseid" value="' . $c->id . '">';
        echo '<button type="submit" style="padding:6px 12px; background:#dc3545; color:#fff; border:none; border-radius:3px; cursor:pointer;">❌ Reverter Matrícula</button>';
        echo '</form>';

        echo '</li>';
    }
    echo '</ul>';
}

echo '<hr><p><a href="' . new moodle_url('/local/ead_integration/logs.php') . '">📄 Ver logs de integração</a></p>';
echo '<hr><p><a href="' . new moodle_url('/local/ead_integration/sync_logs.php') . '">📋 Ver logs de sincronização de usuários</a></p>';

echo $OUTPUT->footer();
