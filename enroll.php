<?php
require_once('../../config.php');
require_login();
require_admin();

global $DB, $PAGE, $OUTPUT, $CFG;

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/ead_integration/enroll.php'));
$PAGE->set_title('Gestão de Matrículas IESDE');
$PAGE->set_heading('Gestão de Matrículas IESDE');

echo $OUTPUT->header();

// Parâmetros do formulário
$action = optional_param('action', '', PARAM_ALPHA);
$moodle_userids = optional_param_array('moodle_userids', [], PARAM_INT);
$moodle_courseid = optional_param('moodle_courseid_hidden', 0, PARAM_INT); // Campo oculto para o curso
$moodle_courseid_revert = optional_param('moodle_courseid_revert', 0, PARAM_INT); // Para reversão
$moodle_userid_revert = optional_param('moodle_userid_revert', 0, PARAM_INT); // Para reversão


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

function register_log($user_id, $course_id, $action, $status, $message, $response) {
    global $DB;
    $log = new stdClass();
    $log->moodle_userid = $user_id;
    $log->moodle_courseid = $course_id;
    $log->action = $action;
    $log->status = $status;
    $log->message = $message;
    $log->response = $response;
    $log->timecreated = time();
    $DB->insert_record('eadintegration_logs', $log);
}


/**
 * Cria a atividade de URL para acessar o conteúdo EAD se ela ainda não existir no curso.
 * @param stdClass $course O objeto do curso do Moodle.
 * @return void
 */
function create_ead_activity_if_not_exists($course) {
    global $DB, $CFG;

    require_once($CFG->dirroot . '/mod/url/locallib.php');

    // Nome da atividade que vamos procurar ou criar.
    $activity_name = 'Acessar Conteúdo IESD';
    $activity_filename = 'sincronizar_conteudo.php'; // Nome do seu script de visualização

    // Verifica se já existe uma atividade de URL com um link para o nosso script.
    $sql = "SELECT 1
              FROM {url} u
              JOIN {course_modules} cm ON u.id = cm.instance
              JOIN {modules} m ON cm.module = m.id
             WHERE m.name = 'url'
               AND cm.course = :courseid
               AND u.name = :activityname
               AND u.externalurl LIKE :fileurl";

    $params = [
        'courseid' => $course->id,
        'activityname' => $activity_name,
        'fileurl' => '%' . $activity_filename . '%'
    ];

    if ($DB->record_exists_sql($sql, $params)) {
        // Se já existe, não faz nada.
        return;
    }

    // Se não existe, vamos criar!
    // Inclui a biblioteca para adicionar/atualizar módulos de curso.
    require_once($CFG->dirroot . '/course/lib.php');

    require_once($CFG->dirroot . '/course/modlib.php');


    // Monta a URL externa completa.
    $external_url = (new moodle_url('/local/ead_integration/' . $activity_filename, ['id' => $course->id]))->out(false);

    $moduleid = $DB->get_field('modules', 'id', ['name' => 'url'], MUST_EXIST);

    // Cria o objeto de dados para o recurso URL.
    $url_instance = new stdClass();
    $url_instance->course        = $course->id;
    $url_instance->name          = $activity_name;
    $url_instance->intro         = 'Clique neste link para acessar as videoaulas e materiais de apoio do seu curso.';
    $url_instance->introformat   = FORMAT_HTML;
    $url_instance->externalurl   = $external_url;
    $url_instance->display       = 0; // automático
    $url_instance->visible       = 1;
    $url_instance->visibleoncoursepage = 1;
    $url_instance->groupmode     = 0;
    $url_instance->groupingid    = 0;
    $url_instance->showdescription = 0;
    $url_instance->completion    = 0;
    $url_instance->modulename    = 'url';
    $url_instance->module        = $moduleid;
    $url_instance->section       = 0; // coloca na primeira seção
    $url_instance->timemodified  = time();
    $url_instance->add           = 'url'; // necessário para `add_moduleinfo`



    // Adiciona a instância do módulo ao curso.
    $moduleinfo = add_moduleinfo($url_instance, $course); 

    if ($moduleinfo) {
        print_message_box('✅ Atividade "Acessar Conteúdo EAD" criada automaticamente no curso.', 'success');
    } else {
        print_message_box('⚠️ Não foi possível criar a atividade "Acessar Conteúdo EAD".', 'warning');
    }
}

// PROCESSAMENTO
if ($_SERVER['REQUEST_METHOD'] === 'POST' && confirm_sesskey()) {
    if ($action === 'revert') {
        $user = $DB->get_record('user', ['id' => $moodle_userid_revert], '*', MUST_EXIST);
        $course = $DB->get_record('course', ['id' => $moodle_courseid_revert], '*', MUST_EXIST);
        $enroll = $DB->get_record('eadintegration_enrolls', ['moodle_userid' => $user->id, 'moodle_courseid' => $course->id], '*', IGNORE_MISSING);

        echo '<h2>Revertendo Matrícula</h2><pre>';
        if (!$enroll) {
            print_message_box('⚠️ Matrícula local não encontrada.', 'warning');
        } else {
            $api_client = new \local_ead_integration\webservice_client();
            $params_cancel = ['MatriculaID' => $enroll->iesde_matriculaid, 'Situacao' => 'I'];
            $cancel_result = $api_client->call('situacao', $params_cancel, false);
            
            $is_success = is_array($cancel_result) && isset($cancel_result['status']) && $cancel_result['status'] == 1;
            register_log($user->id, $course->id, 'revert', $is_success ? 'success' : 'error', json_encode($cancel_result), json_encode($cancel_result));

            if ($is_success) {
                print_message_box("✅ Matrícula inativada no IESDE.", 'success');
                $DB->delete_records('eadintegration_enrolls', ['id' => $enroll->id]);
                print_message_box("✅ Registro local removido.", 'success');
                
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
                print_message_box('❌ Erro na API do IESDE:<br><pre>' . print_r($cancel_result, true) . '</pre>', 'error');
            }
        }
        echo '</pre><a href="' . new moodle_url('/local/ead_integration/enroll.php') . '">← Voltar</a>';
        echo $OUTPUT->footer();
        exit;
    }

    if ($action === 'enroll') {
        $course = $DB->get_record('course', ['id' => $moodle_courseid], '*', MUST_EXIST);
        echo '<h2>Processando Matrículas...</h2>';

        if (empty($moodle_userids)) {
            print_message_box('❌ Nenhum aluno foi selecionado.', 'error');
        } else {
            $activity_checked = false; 
            foreach ($moodle_userids as $moodle_userid) {
                $user = $DB->get_record('user', ['id' => $moodle_userid]);
                echo '<hr><h4>Processando: ' . fullname($user) . '</h4>';

                $cpf = $DB->get_field_sql("SELECT data FROM {user_info_data} uid JOIN {user_info_field} uif ON uid.fieldid = uif.id WHERE uid.userid = :userid AND uif.shortname = 'cpf'", ['userid' => $user->id]);

                if (empty($cpf)) {
                    print_message_box('❌ Usuário sem CPF cadastrado. Matrícula ignorada.', 'error');
                    register_log($user->id, $course->id, 'enroll_skipped', 'error', 'CPF não encontrado', '');
                    continue;
                }

                if ($DB->record_exists('eadintegration_enrolls', ['moodle_userid' => $user->id, 'moodle_courseid' => $course->id])) {
                    print_message_box('❌ Usuário já possui matrícula IESDE neste curso. Matrícula ignorada.', 'warning');
                    continue;
                }

                $api_client = new \local_ead_integration\webservice_client();
                $params = [
                    'CursoID' => $course->idnumber,
                    'PoloID'  => '0',
                    'Nome'    => fullname($user),
                    'CPF'     => preg_replace('/[^0-9]/', '', $cpf),
                    'Email'   => $user->email,
                    'CEP'     => '00000000',
                    'Numero'  => '0',
                ];

                $resultado = $api_client->call('cadastro', $params, false);
                $is_success = is_array($resultado) && isset($resultado['status']) && $resultado['status'] == 1;
                register_log($user->id, $course->id, 'enroll', $is_success ? 'success' : 'error', json_encode($resultado), json_encode($resultado));

                if ($is_success) {
                    if (!$activity_checked) {
                        create_ead_activity_if_not_exists($course);
                        $activity_checked = true; // Só cria uma vez por matrícula
                    } 
                    $new_enrollment = new stdClass();
                    $new_enrollment->moodle_userid = $user->id;
                    $new_enrollment->moodle_courseid = $course->id;
                    $new_enrollment->iesde_matriculaid = $resultado['MatriculaID'];
                    $new_enrollment->timecreated = time();
                    $new_enrollment->timemodified = time();
                    $DB->insert_record('eadintegration_enrolls', $new_enrollment);
                    print_message_box("✅ Aluno matriculado no IESDE com ID: " . $resultado['MatriculaID'], 'success');
                    
                    require_once($CFG->dirroot . '/enrol/manual/locallib.php');
                    $enrol_plugin = enrol_get_plugin('manual');
                    if ($instances = enrol_get_instances($course->id, true)) {
                        foreach ($instances as $instance) {
                            if ($instance->enrol === 'manual') {
                                $enrol_plugin->enrol_user($instance, $user->id, 5); // 5 = role student
                                print_message_box("✅ Usuário matriculado também no Moodle.", 'success');
                                break;
                            }
                        }
                    }
                } else {
                    print_message_box('❌ Falha ao matricular no IESDE: <pre>' . print_r($resultado, true) . '</pre>', 'error');
                }
            }
        }
        echo '<a href="' . new moodle_url('/local/ead_integration/enroll.php') . '">← Voltar</a>';
        echo $OUTPUT->footer();
        exit;
    }
}

// Formulário
$users = $DB->get_records('user', ['deleted' => 0], 'lastname ASC, firstname ASC', 'id, firstname, lastname, email');
$courses = $DB->get_records('course', [], 'fullname ASC', 'id, fullname, idnumber');
$iesde_courses = array_filter($courses, function($course) {
    return !empty($course->idnumber) && is_numeric($course->idnumber);
});

echo '<div class="container" style="max-width: 800px; margin: auto;">';
echo '<h2 style="margin-bottom: 20px;">📘 Nova Matrícula</h2>';
echo '<form method="post" style="background: #f9f9f9; padding: 20px; border-radius: 10px; box-shadow: 0 0 8px rgba(0,0,0,0.05);">';
echo '<input type="hidden" name="sesskey" value="' . sesskey() . '">';
echo '<input type="hidden" name="action" value="enroll">';

// BUSCA DE CURSO (RESTAURADA)
echo '<div style="margin-bottom: 20px;">';
echo '<label for="moodle_courseid_display" style="font-weight: bold; display:block; margin-bottom:5px;">🎓 Selecionar Curso IESDE</label>';
echo '<input type="hidden" name="moodle_courseid_hidden" id="moodle_courseid_hidden">';
echo '<input list="lista-cursos" id="moodle_courseid_display" placeholder="Digite o nome do Curso para buscar" required style="width:100%; padding:8px; border-radius:5px; border:1px solid #ccc;">';
echo '<datalist id="lista-cursos">';
foreach ($iesde_courses as $course) {
    echo '<option data-id="' . $course->id . '" value="' . $course->fullname . '">';
}
echo '</datalist>';
echo '</div>';


// SELEÇÃO DE MÚLTIPLOS ALUNOS (MANTIDA)
echo '<div style="margin-bottom: 20px;">';
echo '<label for="user-search-input" style="font-weight: bold; display:block; margin-bottom:5px;">👤 Selecionar Alunos</label>';
echo '<input type="text" id="user-search-input" placeholder="Digite para buscar alunos..." style="width:100%; padding:8px; margin-bottom:10px; border-radius:5px; border:1px solid #ccc;">';
echo '<div id="user-checkbox-list" style="max-height: 300px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; background:white; border-radius:5px;">';
foreach ($users as $user) {
    echo '<div class="user-item" data-fullname="' . strtolower(fullname($user)) . '">';
    echo '<label><input type="checkbox" name="moodle_userids[]" value="' . $user->id . '"> ' . fullname($user) . ' (' . $user->email . ')</label>';
    echo '</div>';
}
echo '</div>';
echo '</div>';

echo '<button type="submit" style="width:100%; padding:12px; font-weight:bold; background-color:#007bff; color:white; border:none; border-radius:6px; font-size:16px;">✅ Matricular Alunos Selecionados</button>';
echo '</form>';
echo '</div>';


// LISTA DE MATRÍCULAS EXISTENTES
$existing_enrollments = $DB->get_records('eadintegration_enrolls', [], 'id DESC');
if ($existing_enrollments) {
    echo '<hr style="margin-top: 40px;">';
    
    // Gaveta (Accordion)
    echo '<details class="enrollment-accordion" style="margin-top: 20px;">';
    echo '<summary style="font-size: 1.5em; font-weight: bold; cursor: pointer; padding: 10px; background: #e9ecef; border-radius: 8px;">📄 Matrículas Existentes (Clique para expandir)</summary>';
    
    // Container com a busca e a lista
    echo '<div style="padding-top: 20px;">';

    // Campo de busca
    echo '<input type="text" id="enrollment-search-input" placeholder="Buscar por nome, CPF ou ID da matrícula..." style="width: 100%; padding: 10px; margin-bottom: 20px; border-radius: 5px; border: 1px solid #ccc;">';

    echo '<ul id="enrollment-list" style="list-style:none; padding:0;">';

    foreach ($existing_enrollments as $enroll) {
        $u = $DB->get_record('user', ['id' => $enroll->moodle_userid]);
        $c = $DB->get_record('course', ['id' => $enroll->moodle_courseid]);
        if (!$u || !$c) continue;

        // Pega o CPF do usuário para a busca
        $cpf = $DB->get_field('user_info_data', 'data', ['userid' => $u->id, 'fieldid' => $DB->get_field('user_info_field', 'id', ['shortname' => 'cpf'])]);
        
        // Constrói o texto de busca (nome + cpf + id)
        $search_data = strtolower(fullname($u) . ' ' . $cpf . ' ' . $enroll->iesde_matriculaid);

        echo '<li class="enrollment-item" data-search="' . htmlspecialchars($search_data) . '" style="margin-bottom:15px; padding:15px; background:#f1f1f1; border-radius:8px;">';
        echo '<div><strong>' . fullname($u) . '</strong></div>';
        echo '<div>CPF: <code>' . ($cpf ?: 'Não informado') . '</code></div>';
        echo '<div>Curso: <em>' . $c->fullname . '</em></div>';
        echo '<div>ID Matrícula IESDE: <code>' . $enroll->iesde_matriculaid . '</code></div>';
        echo '<form method="post" style="margin-top:10px;" onsubmit="return confirm(\'Deseja realmente reverter esta matrícula?\');">';
        echo '<input type="hidden" name="sesskey" value="' . sesskey() . '">';
        echo '<input type="hidden" name="action" value="revert">';
        echo '<input type="hidden" name="moodle_userid_revert" value="' . $u->id . '">';
        echo '<input type="hidden" name="moodle_courseid_revert" value="' . $c->id . '">';
        echo '<button type="submit" style="padding:8px 16px; background:#dc3545; color:white; border:none; border-radius:5px; cursor:pointer;">❌ Reverter Matrícula</button>';
        echo '</form>';
        echo '</li>';
    }

    echo '</ul>';
    echo '</div>'; // Fim do container
    echo '</details>'; // Fim da gaveta
}

// JAVASCRIPT
echo <<<JS
<script>
// Script para popular o campo oculto do curso
document.getElementById('moodle_courseid_display').addEventListener('input', function() {
    const input = this.value;
    const options = document.querySelectorAll('#lista-cursos option');
    let found = false;
    options.forEach(opt => {
        if (opt.value === input) {
            document.getElementById('moodle_courseid_hidden').value = opt.dataset.id;
            found = true;
        }
    });
    if (!found) {
        document.getElementById('moodle_courseid_hidden').value = '';
    }
});

// Script para filtrar a lista de alunos
document.getElementById('user-search-input').addEventListener('input', function() {
    const filter = this.value.toLowerCase();
    const userItems = document.querySelectorAll('#user-checkbox-list .user-item');
    
    userItems.forEach(item => {
        const fullname = item.dataset.fullname;
        if (fullname.includes(filter)) {
            item.style.display = '';
        } else {
            item.style.display = 'none';
        }
    });
});

document.getElementById('enrollment-search-input').addEventListener('input', function() {
    const filter = this.value.toLowerCase();
    const listItems = document.querySelectorAll('#enrollment-list .enrollment-item');
    
    listItems.forEach(item => {
        const searchData = item.dataset.search;
        if (searchData.includes(filter)) {
            item.style.display = '';
        } else {
            item.style.display = 'none';
        }
    });
});
</script>
JS;

echo '<hr>';
echo '<p><a href="' . new moodle_url('/local/ead_integration/logs.php') . '" style="color:#007bff; font-weight:bold;">📄 Ver logs de integração</a></p>';
echo '<p><a href="' . new moodle_url('/local/ead_integration/sync_logs.php') . '" style="color:#007bff; font-weight:bold;">📋 Ver logs de sincronização de usuários</a></p>';
echo '<p><a href="' . new moodle_url('/local/ead_integration/index.php') . '" style="color:#007bff; font-weight:bold;">📋 Acessar o dashboard</a></p>';

echo $OUTPUT->footer();