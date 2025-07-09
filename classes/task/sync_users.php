<?php
namespace local_ead_integration\task;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/local/ead_integration/classes/webservice_client.php');

class sync_users extends \core\task\scheduled_task {

    public function get_name() {
        return get_string('sync_users_task', 'local_ead_integration');
    }

    public function execute() {
        global $CFG;

        mtrace("Iniciando a tarefa de sincronização de usuários do EAD.");

        $api_client = new \local_ead_integration\webservice_client();

        $params = [
            'dataDe' => date('Y-m-d', strtotime('-1 day')),
            'dataAte' => date('Y-m-d'),
        ];

        $alunos = $api_client->call('getAlunosAlterados', $params);

        if (!$alunos || !is_array($alunos)) {
            mtrace("Nenhum aluno encontrado ou erro na API.");
            return;
        }

        foreach ($alunos as $aluno_data) {
            if (\core_user::get_user_by_username($aluno_data->CPF) || \core_user::get_user_by_email($aluno_data->Email)) {
                mtrace("Usuário com CPF {$aluno_data->CPF} já existe. Pulando.");
                continue;
            }

            $newuser = new \stdClass();
            $newuser->username = $aluno_data->CPF;
            $newuser->password = \core_user::hash_password($aluno_data->CPF);
            $newuser->firstname = $aluno_data->Nome;
            $newuser->lastname = ' ';
            $newuser->email = $aluno_data->Email;
            $newuser->auth = 'manual';
            $newuser->confirmed = 1;
            $newuser->lang = $CFG->lang;
            $newuser->city = isset($aluno_data->cidade) ? $aluno_data->cidade : ' ';
            $newuser->country = 'BR';

            try {
                $userid = \core_user::create_user($newuser);
                mtrace("Usuário {$newuser->firstname} (ID: {$userid}) criado com sucesso.");
            } catch (\Exception $e) {
                mtrace("Erro ao criar usuário com CPF {$aluno_data->CPF}: " . $e->getMessage());
            }
        }

        mtrace("Tarefa de sincronização de usuários concluída.");
    }
}