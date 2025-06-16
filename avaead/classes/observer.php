<?php
namespace local_avaead;

defined('MOODLE_INTERNAL') || die();

// É necessário incluir o arquivo de lib de notificações no Moodle 3.9
require_once($CFG->libdir.'/lib.php');

class observer {
    /**
     * Disparado quando um usuário é criado no Moodle.
     *
     * @param \core\event\user_created $event
     * @return void
     */
    public static function user_created(\core\event\user_created $event) {
        global $CFG;

        // Pega os dados do usuário do evento
        $user = $event->get_record();
        
        // Carrega os dados de perfil personalizados (CPF, CEP, etc.)
        profile_load_custom_fields($user);

        // Pega as configurações da API salvas no painel de admin
        $api_user = get_config('local_avaead', 'api_http_user');
        $api_pass = get_config('local_avaead', 'api_http_pass');
        $chave_acesso = get_config('local_avaead', 'chave_acesso');

        // Se alguma credencial estiver faltando, pare a execução para evitar erros.
        if (empty($api_user) || empty($api_pass) || empty($chave_acesso)) {
            mtrace('Credenciais da API AVA EAD não configuradas. Abortando.');
            return;
        }

        // --- Monta os parâmetros para a API ---
        $params = array(
            'CursoID' => 1234, // ATENÇÃO: Substitua por uma regra de negócio real
            'PoloID' => isset($user->profile['poloid']) ? $user->profile['poloid'] : 10, // Exemplo
            'Nome' => $user->firstname . ' ' . $user->lastname,
            'CPF' => isset($user->profile['cpf']) ? $user->profile['cpf'] : '',
            'Email' => $user->email,
            'CEP' => isset($user->profile['cep']) ? $user->profile['cep'] : '',
            'Numero' => isset($user->profile['numeroresidencia']) ? $user->profile['numeroresidencia'] : '',
            // Adicione aqui outros campos opcionais
        );
        
        if (empty($params['CursoID']) || empty($params['Nome']) || empty($params['CPF']) || empty($params['Email'])) {
            mtrace('Erro: Campos obrigatórios (CursoID, Nome, CPF, Email) estão faltando para o usuário ID: ' . $user->id);
            return;
        }

        mtrace('Tentando registrar novo usuário no AVA EAD. Dados: ' . print_r($params, true));

        // --- LÓGICA cURL ADAPTADA DO PDF ---
        $api_server = 'https://ead.portalava.com.br/web_service/cadastro';
        $chave_name = 'EAD-API-KEY';
        $format = 'json';

        $curl = curl_init();
        
        curl_setopt($curl, CURLOPT_URL, "{$api_server}/format/{$format}");
        curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_DIGEST);
        curl_setopt($curl, CURLOPT_USERPWD, "{$api_user}:{$api_pass}");
        curl_setopt($curl, CURLOPT_HTTPHEADER, array("{$chave_name}: {$chave_acesso}"));
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_HEADER, 0);
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($params));
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);

        $output = curl_exec($curl);
        
        if ($output === false) {
            mtrace('Erro no cURL ao chamar a API AVA EAD: ' . curl_error($curl));
        } else {
            mtrace('Resposta da API AVA EAD: ' . $output);
            
            $response = json_decode($output);
            if (isset($response->Status) && $response->Status == true) {
                // SUCESSO: Notificação para Moodle 3.9
                notification('Usuário ' . $user->id . ' registrado com sucesso no AVA EAD.', 'notifysuccess');
            } else {
                // ERRO: Notificação para Moodle 3.9
                $erromsg = isset($response->Mensagem) ? $response->Mensagem : 'Erro desconhecido.';
                notification('Falha ao registrar usuário ' . $user->id . ' no AVA EAD: ' . $erromsg, 'notifyproblem');
            }
        }
        
        curl_close($curl);
    }
}