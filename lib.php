<?php
defined('MOODLE_INTERNAL') || die();

/**
 * Função central para realizar chamadas cURL para a API da AVA IESDE.
 *
 * @param string $endpoint A URL do web service a ser chamado.
 * @param array $params Os parâmetros a serem enviados na requisição POST.
 * @return string|bool A resposta do web service ou false em caso de falha.
 */
function local_integracao_ava_call_api($endpoint, $params) {
    // Busca as credenciais das configurações salvas no Moodle.
    $api_http_user = get_config('local_integracao_ava', 'api_http_user');
    $api_http_pass = get_config('local_integracao_ava', 'api_http_pass');
    $chave_acesso = get_config('local_integracao_ava', 'chave_acesso');
    $chave_name = get_config('local_integracao_ava', 'chave_name');
    $format = 'json';

    if (empty($api_http_user) || empty($api_http_pass) || empty($chave_acesso)) {
        error_log('Plugin Integração AVA: As credenciais da API não estão configuradas nas configurações do plugin.');
        return false;
    }

    $curl = curl_init();

    curl_setopt($curl, CURLOPT_URL, "{$endpoint}/format/{$format}");
    curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_DIGEST);
    curl_setopt($curl, CURLOPT_USERPWD, "{$api_http_user}:{$api_http_pass}");
    curl_setopt($curl, CURLOPT_HTTPHEADER, array("{$chave_name}: {$chave_acesso}"));
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($curl, CURLOPT_HEADER, 0);
    curl_setopt($curl, CURLOPT_POST, 1);
    curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($params));
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);

    $output = curl_exec($curl);

    if (curl_errno($curl)) {
        error_log('Plugin Integração AVA: Erro na chamada cURL - ' . curl_error($curl));
        curl_close($curl);
        return false;
    }

    curl_close($curl);
    return $output;
}

/**
 * Prepara os dados e chama a API para cadastrar uma nova matrícula.
 *
 * @param stdClass $userdata Objeto com os dados do usuário/aluno.
 * @param int $courseid ID do curso na IESDE.
 * @param int $poloid ID do polo.
 * @return string Resposta da API.
 */
function local_integracao_ava_cadastrar_matricula($userdata, $courseid, $poloid) {
    $endpoint = 'https://ead.portalava.com.br/web_service/cadastro';

    // Parâmetros a serem enviados para a API da IESDE.
    $params = array(
        'CursoID' => $courseid,
        'PoloID' => $poloid,
        'Nome' => $userdata->firstname . ' ' . $userdata->lastname,
        'CPF' => $userdata->cpf,
        'Email' => $userdata->email,
        'RG' => !empty($userdata->rg) ? $userdata->rg : '',
        'CEP' => !empty($userdata->cep) ? $userdata->cep : '',
        'Numero' => !empty($userdata->numero) ? $userdata->numero : '',
        // Adicione outros campos aqui, se você os tiver criado no perfil do Moodle.
    );

    return local_integracao_ava_call_api($endpoint, $params);
}

/**
 * Classe que observa os eventos do Moodle.
 */
class local_integracao_ava_observer {

    /**
     * É chamado quando um usuário é inscrito em um curso.
     *
     * @param \core\event\user_enrolment_created $event
     * @return void
     */
    public static function user_enrolment_created(\core\event\user_enrolment_created $event) {
        global $DB;

        // 1. Obter dados do evento
        $userid = $event->relateduserid;

        // 2. Obter o objeto completo do usuário e seus campos de perfil
        $user = $DB->get_record('user', array('id' => $userid));
        if (!$user) {
            return;
        }
        profile_load_custom_fields($user);

        // 3. Obter o ID do curso da IESDE a partir das configurações do curso no Moodle
        $course = $DB->get_record('course', ['id' => $event->courseid]);
        $curso_id_api = !empty($course->idnumber) ? $course->idnumber : null;
        
        // 4. Mapear os dados do aluno do Moodle
        $userdata = new stdClass();
        $userdata->firstname = $user->firstname;
        $userdata->lastname = $user->lastname;
        $userdata->email = $user->email;
        $userdata->cpf = !empty($user->profile['cpf']) ? $user->profile['cpf'] : '';
        $userdata->cep = !empty($user->profile['cep']) ? $user->profile['cep'] : '';
        $userdata->numero = !empty($user->profile['numero']) ? $user->profile['numero'] : '';
        $userdata->rg = !empty($user->profile['rg']) ? $user->profile['rg'] : ''; // Exemplo se você tiver o campo RG
        $polo_id_api = !empty($user->profile['poloid']) ? $user->profile['poloid'] : 0;

        // 5. VALIDAÇÃO PRÉVIA: Verificar se os dados obrigatórios do Moodle não estão vazios
        $campos_obrigatorios = [
            'CursoID' => $curso_id_api,
            'PoloID' => $polo_id_api,
            'CPF' => $userdata->cpf,
            'Email' => $userdata->email,
            'CEP' => $userdata->cep,
            'Numero' => $userdata->numero
        ];

        foreach ($campos_obrigatorios as $nome_campo => $valor) {
            if (empty($valor)) {
                mtrace("Plugin Integração AVA: ERRO DE VALIDAÇÃO. O campo obrigatório '{$nome_campo}' está vazio para o usuário '{$user->email}'. A matrícula não será enviada.");
                return; // Para a execução da função aqui.
            }
        }

        // 6. Chamar a função da API
        mtrace("Plugin Integração AVA: Disparado evento de inscrição para o usuário {$user->email} no curso com ID IESDE {$curso_id_api}. Todos os campos obrigatórios estão presentes.");
        
        $response = local_integracao_ava_cadastrar_matricula($userdata, $curso_id_api, $polo_id_api);

        // 7. Tratar e registrar a resposta da API
        if ($response) {
            $result = json_decode($response);
            if (isset($result->Status) && $result->Status == true) {
                mtrace("Plugin Integração AVA: SUCESSO. Usuário inscrito na API. Mensagem: " . $result->Mensagem);
            } else {
                $error_message = isset($result->Mensagem) ? $result->Mensagem : 'Erro desconhecido retornado pela API.';
                mtrace("Plugin Integração AVA: ERRO. Falha ao inscrever usuário na API. Mensagem: " . $error_message);
            }
        } else {
            mtrace("Plugin Integração AVA: ERRO. Falha na comunicação com a API (sem resposta).");
        }
    }
}