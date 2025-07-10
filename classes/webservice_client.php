<?php
namespace local_ead_integration;

defined('MOODLE_INTERNAL') || die();

class webservice_client {
    private $base_url;
    private $api_key;
    private $username;
    private $password;
    private $max_retries = 3;
    private $retry_delay = 2; // segundos entre tentativas

    public function __construct() {
        $this->base_url = get_config('local_ead_integration', 'baseurl');
        $this->api_key   = get_config('local_ead_integration', 'apikey');
        $this->username  = get_config('local_ead_integration', 'wsusername');
        $this->password  = get_config('local_ead_integration', 'wspassword');
    }

    /**
     * Faz chamada à API com suporte a GET ou POST, retries e log em banco.
     *
     * @param string $endpoint Ex: 'cadastro'
     * @param array $params Parâmetros da requisição
     * @param bool $paginated Usa /web_servicePg/ se true
     * @param string $method 'POST' ou 'GET'
     * @param int|null $moodle_userid Opcional, para log
     * @param int|null $moodle_courseid Opcional, para log
     * @return array JSON decodificado ou erro formatado
     */
    public function call($endpoint, $params = [], $paginated = false, $method = 'POST', $moodle_userid = null, $moodle_courseid = null) {
        global $DB;

        $base_path = $paginated ? '/web_servicePg/' : '/web_service/';
        $url = $this->base_url . $base_path . $endpoint . '/format/json';
        $method = strtoupper($method);
        $attempt = 0;
        $final_response = null;

        do {
            $attempt++;
            $ch = curl_init();

            if ($method === 'GET' && !empty($params)) {
                $url .= '?' . http_build_query($params);
            }

            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_DIGEST);
            curl_setopt($ch, CURLOPT_USERPWD, $this->username . ':' . $this->password);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['EAD-API-KEY: ' . $this->api_key]);
            curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

            if ($method === 'POST') {
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
            }

            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curl_error = curl_error($ch);
            curl_close($ch);

            // Se 200 e resposta válida, decodifica
            if ($http_code === 200) {
                $decoded = json_decode($response, true);

                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $this->log_to_db($endpoint, 'success', $params, $decoded, null, $moodle_userid, $moodle_courseid);
                    return $decoded;
                }

                $this->log_to_db($endpoint, 'error', $params, $response, 'Erro ao decodificar JSON', $moodle_userid, $moodle_courseid);
                return [
                    'status' => 'error',
                    'message' => 'Erro ao decodificar JSON',
                    'response' => $response
                ];
            }

            // Caso falha HTTP
            $error_data = [
                'status' => 'error',
                'http_code' => $http_code,
                'curl_error' => $curl_error,
                'response' => $response
            ];

            $final_response = $error_data;

            if (!in_array($http_code, [408, 429, 500, 502, 503, 504])) {
                break; // erros que não vale a pena repetir
            }

            sleep($this->retry_delay);

        } while ($attempt < $this->max_retries);

        $this->log_to_db($endpoint, 'error', $params, $final_response['response'] ?? null, $final_response['curl_error'] ?? 'Erro desconhecido', $moodle_userid, $moodle_courseid);

        return $final_response;
    }

    /**
     * Registra chamada da API no banco (tabela: eadintegration_logs)
     */
    private function log_to_db($action, $status, $params, $response, $message = null, $userid = null, $courseid = null) {
        global $DB, $USER;

        $record = new \stdClass();
        $record->timecreated = time();
        $record->moodle_userid = $userid ?? $USER->id;
        $record->moodle_courseid = $courseid ?? 0;
        $record->action = $action;
        $record->status = $status;
        $record->message = is_string($message) ? $message : '';
        $record->response = is_string($response) ? $response : json_encode($response);

        $DB->insert_record('eadintegration_logs', $record);
    }
}
