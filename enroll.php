<?php
require_once('../../config.php');
require_login();
require_admin();

global $DB, $PAGE;

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/ead_integration/enroll.php'));
$PAGE->set_title('Matricular Aluno no IESDE');
$PAGE->set_heading('Matricular Aluno no IESDE');

// Se o formulário foi enviado...
if ($_SERVER['REQUEST_METHOD'] === 'POST' && confirm_sesskey()) {
    $moodle_userid = required_param('moodle_userid', PARAM_INT);
    $moodle_courseid = required_param('moodle_courseid', PARAM_INT);

    // Pega os dados do usuário e do curso do Moodle
    $user = $DB->get_record('user', ['id' => $moodle_userid], '*', MUST_EXIST);
    $course = $DB->get_record('course', ['id' => $moodle_courseid], '*', MUST_EXIST);

    // Pega o CPF do campo de perfil personalizado
    $cpf = $DB->get_field_sql("
        SELECT uid.data
        FROM {user_info_data} uid
        JOIN {user_info_field} uif ON uid.fieldid = uif.id
        WHERE uid.userid = :userid AND uif.shortname = 'cpf'
    ", ['userid' => $user->id]);

    // Valida CPF
    if (empty($cpf)) {
        redirect(new moodle_url('/local/ead_integration/enroll.php'), '❌ Este usuário não possui um CPF cadastrado no perfil.', 5);
        exit;
    }

    // Verifica se já não foi matriculado
    if ($DB->record_exists('eadintegration_enrolls', ['moodle_userid' => $user->id, 'moodle_courseid' => $course->id])) {
        redirect(new moodle_url('/local/ead_integration/enroll.php'), 'Este usuário já possui uma matrícula IESDE para este curso.', 5);
        exit;
    }

    // Chama a API para cadastrar
    $api_client = new \local_ead_integration\webservice_client();
    $params = [
        'CursoID' => $course->idnumber, // Pegamos o ID do curso IESDE que guardamos no idnumber
        'PoloID'  => '0',
        'Nome'    => fullname($user),
        'CPF'     => preg_replace('/[^0-9]/', '', $cpf), // Garante apenas números
        'Email'   => $user->email,
        'CEP'     => '00000000', // Pode melhorar futuramente puxando do perfil
        'Numero'  => '0',        // Pode melhorar futuramente puxando do perfil
    ];

    $resultado = $api_client->call('cadastro', $params, false);

    echo $OUTPUT->header();
    echo "<h2>Processando Matrícula...</h2>";
    echo "<pre>";

    if (is_array($resultado) && isset($resultado['status']) && $resultado['status'] == 1) {
        // Deu certo! Vamos salvar na nossa tabela.
        $new_enrollment = new stdClass();
        $new_enrollment->moodle_userid = $user->id;
        $new_enrollment->moodle_courseid = $course->id;
        $new_enrollment->iesde_matriculaid = $resultado['MatriculaID'];
        $new_enrollment->timecreated = time();
        $new_enrollment->timemodified = time();

        $DB->insert_record('eadintegration_enrolls', $new_enrollment);

        echo "✅ SUCESSO! Aluno '".fullname($user)."' matriculado no curso '{$course->fullname}' com a Matrícula IESDE ID: " . $resultado['MatriculaID'];
    } else {
        echo "❌ FALHA! A API retornou um erro:\n";
        print_r($resultado);
    }

    echo "</pre>";
    echo $OUTPUT->footer();
    exit;
}

// Se não, mostra o formulário.
echo $OUTPUT->header();

$users = $DB->get_records('user', ['deleted' => 0], 'lastname ASC', 'id, firstname, lastname');
$courses = $DB->get_records_sql("
    SELECT id, fullname 
    FROM {course} 
    WHERE idnumber REGEXP '^[0-9]+$' 
    ORDER BY fullname ASC
");

echo '
<form method="post">
  <input type="hidden" name="sesskey" value="' . sesskey() . '">
  <div>
    <label for="moodle_userid">Selecione o Usuário:</label>
    <select name="moodle_userid" id="moodle_userid" required>
      <option value="">Selecione...</option>';
foreach ($users as $user) {
    echo '<option value="' . $user->id . '">' . fullname($user) . '</option>';
}
echo '
    </select>
  </div>
  <br>
  <div>
    <label for="moodle_courseid">Selecione o Curso IESDE:</label>
    <select name="moodle_courseid" id="moodle_courseid" required>
      <option value="">Selecione...</option>';
foreach ($courses as $course) {
    echo '<option value="' . $course->id . '">' . $course->fullname . '</option>';
}
echo '
    </select>
  </div>
  <br>
  <button type="submit">Matricular Aluno no IESDE</button>
</form>';

echo $OUTPUT->footer();
