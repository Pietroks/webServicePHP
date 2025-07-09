<?php
namespace local_ead_integration\task;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/local/ead_integration/classes/webservice_client.php');

class sync_users extends \core\task\scheduled_task {

    public function get_name() {
        return get_string('sync_users_task', 'local_ead_integration');
    }

    public function execute() {
        global $CFG, $DB;

        mtrace("🔁 Iniciando a tarefa de sincronização de usuários do EAD.");

        $api_client = new \local_ead_integration\webservice_client();

        $params = [
            'dataDe' => date('Y-m-d', strtotime('-1 day')),
            'dataAte' => date('Y-m-d'),
        ];

        $alunos = $api_client->call('getAlunosAlterados', $params, false);

        if (!$alunos || !is_array($alunos)) {
            mtrace("⚠️ Nenhum aluno encontrado ou erro na API.");
            return;
        }

        foreach ($alunos as $aluno_data) {
            $cpf = $aluno_data['CPF'];
            $email = $aluno_data['Email'];
            $nome = $aluno_data['Nome'];
            $cidade = $aluno_data['cidade'] ?? '';
            
            $log = new \stdClass();
            $log->cpf = $cpf;
            $log->email = $email;
            $log->nome = $nome;
            $log->timecreated = time();

            // Verifica se já existe um usuário com esse CPF ou email
            $user = $DB->get_record_select('user', "username = :cpf OR email = :email", ['cpf' => $cpf, 'email' => $email]);

            if ($user) {
                $updated = false;

                // Verifica se algum campo mudou
                if ($user->firstname !== $nome) {
                    $user->firstname = $nome;
                    $updated = true;
                }

                if (!empty($cidade) && $user->city !== $cidade) {
                    $user->city = $cidade;
                    $updated = true;
                }

                if ($user->email !== $email) {
                    $user->email = $email;
                    $updated = true;
                }

                if ($updated) {
                    try {
                        \core_user::update_user($user);
                        $log->sucesso = 1;
                        $log->mensagem = "Usuário atualizado com sucesso. ID: {$user->id}";
                        mtrace("🔄 Usuário atualizado: {$user->username} (ID: {$user->id})");
                    } catch (\Exception $e) {
                        $log->sucesso = 0;
                        $log->mensagem = "Erro ao atualizar usuário: " . $e->getMessage();
                        mtrace("❌ " . $log->mensagem);
                    }
                } else {
                    $log->sucesso = 1;
                    $log->mensagem = "Usuário já existe e está atualizado. ID: {$user->id}";
                    mtrace("⏩ Nenhuma alteração necessária para {$user->username}");
                }

            } else {
                // Criação de novo usuário
                $newuser = new \stdClass();
                $newuser->username = $cpf;
                $newuser->password = \core_user::hash_password($cpf);
                $newuser->firstname = $nome;
                $newuser->lastname = '.';
                $newuser->email = $email;
                $newuser->auth = 'manual';
                $newuser->confirmed = 1;
                $newuser->lang = $CFG->lang;
                $newuser->city = $cidade ?: ' ';
                $newuser->country = 'BR';

                try {
                    $userid = \core_user::create_user($newuser);
                    $log->sucesso = 1;
                    $log->mensagem = "Usuário criado com sucesso. ID: $userid";
                    mtrace("✅ Usuário criado: {$newuser->username} (ID: $userid)");
                } catch (\Exception $e) {
                    $log->sucesso = 0;
                    $log->mensagem = "Erro ao criar usuário: " . $e->getMessage();
                    mtrace("❌ " . $log->mensagem);
                }
            }

            $DB->insert_record('eadintegration_sync_logs', $log);
        }

        mtrace("✅ Tarefa de sincronização de usuários concluída.");
    }
}
