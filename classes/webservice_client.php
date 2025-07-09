<?php
namespace local_ead_integration;

defined('MOODLE_INTERNAL') || die();

class webservice_client {
    private $base_url;
    private $api_key;
    private $username;
    private $password;

    public function __construct() {
        $this->base_url = get_config('local_ead_integration', 'baseurl');
        $this->api_key = get_config('local_ead_integration', 'apikey');
        $this->username = get_config('local_ead_integration', 'wsusername');
        $this->password = get_config('local_ead_integration', 'wspassword');
    }

    /**
     * Faz uma chamada para um endpoint do Web Service
     * @param string $endpoint O endpoint a ser chamado
     * @param array $params Os parâmetros a serem enviados
     * @param bool $paginated Indica se deve usar o endpoint paginado '/web_servicePg/'
     * @return mixed O resultado decodificado do JSON ou array com erro em caso de falha
     */
    public function call($endpoint, $params = [], $paginated = false) {
        $base_path = $paginated ? '/web_servicePg/' : '/web_service/';
        $url = $this->base_url . $base_path . $endpoint . '/format/json';

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_DIGEST);
        curl_setopt($ch, CURLOPT_USERPWD, $this->username . ':' . $this->password);

        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'EAD-API-KEY: ' . $this->api_key
        ]);

        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);

        if ($http_code !== 200) {
            mtrace("Erro na chamada da API para {$endpoint}: HTTP {$http_code}, Erro: {$curl_error}, Resposta: {$response}");

            return [
                'status' => 'error',
                'http_code' => $http_code,
                'curl_error' => $curl_error,
                'response' => $response
            ];
        }

        $decoded = json_decode($response, true);

        // Retorna JSON se válido, ou o erro bruto se falhou o parse
        return is_array($decoded) ? $decoded : [
            'status' => 'error',
            'http_code' => $http_code,
            'curl_error' => $curl_error,
            'response' => $response
        ];
    }
}
