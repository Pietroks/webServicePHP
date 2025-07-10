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

        // Datas de busca (últimas 24h)
        $params = [
            'dataDe' => date('Y-m-d', strtotime('-1 day')),
            'dataAte' => date('Y-m-d'),
        ];

        $alunos = $api_client->call('getAlunosAlterados', $params, false);

        if (!$alunos || !is_array($alunos)) {
            mtrace("⚠️ Nenhum aluno encontrado ou erro na API.");
            return;
        }

        // Verifica se existe o campo personalizado 'cpf'
        $cpffield = $DB->get_record('user_info_field', ['shortname' => 'cpf']);

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

            // Busca usuário por CPF OU e-mail
            $user = $DB->get_record_select('user', "username = :cpf OR email = :email", [
                'cpf' => $cpf,
                'email' => $email
            ]);

            if ($user) {
                $updated = false;

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
                        $log->userid = $user->id;
                        $log->sucesso = 1;
                        $log->mensagem = "Usuário atualizado com sucesso.";
                        $log->status = 'updated';
                        mtrace("🔄 Usuário atualizado: {$user->username} (ID: {$user->id})");

                        // Atualiza campo CPF personalizado
                        if ($cpffield) {
                            self::update_profile_field($user->id, $cpffield->id, $cpf);
                        }
                    } catch (\Exception $e) {
                        $log->sucesso = 0;
                        $log->status = 'error';
                        $log->mensagem = "Erro ao atualizar usuário: " . $e->getMessage();
                        mtrace("❌ " . $log->mensagem);
                    }
                } else {
                    $log->userid = $user->id;
                    $log->sucesso = 1;
                    $log->mensagem = "Usuário já está atualizado.";
                    $log->status = 'skipped';
                    mtrace("⏩ Nenhuma alteração necessária para {$user->username}");
                }

            } else {
                // Criação de novo usuário
                $newuser = new \stdClass();
                $newuser->username = $cpf;
                $newuser->password = password_hash($cpf, PASSWORD_DEFAULT);
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
                    $log->userid = $userid;
                    $log->sucesso = 1;
                    $log->mensagem = "Usuário criado com sucesso.";
                    $log->status = 'created';
                    mtrace("✅ Usuário criado: {$newuser->username} (ID: $userid)");

                    // Salva CPF no campo personalizado
                    if ($cpffield) {
                        self::update_profile_field($userid, $cpffield->id, $cpf);
                    }
                } catch (\Exception $e) {
                    $log->sucesso = 0;
                    $log->status = 'error';
                    $log->mensagem = "Erro ao criar usuário: " . $e->getMessage();
                    mtrace("❌ " . $log->mensagem);
                }
            }

            // Salva o log da operação
            $DB->insert_record('eadintegration_sync_logs', $log);
        }

        mtrace("✅ Tarefa de sincronização de usuários concluída.");
    }

    /**
     * Atualiza ou insere valor no campo personalizado do usuário
     */
    private static function update_profile_field($userid, $fieldid, $value) {
        global $DB;

        $data = $DB->get_record('user_info_data', [
            'userid' => $userid,
            'fieldid' => $fieldid
        ]);

        if ($data) {
            $data->data = $value;
            $DB->update_record('user_info_data', $data);
        } else {
            $DB->insert_record('user_info_data', [
                'userid' => $userid,
                'fieldid' => $fieldid,
                'data' => $value
            ]);
        }
    }
}
