<?php
// Carrega o ambiente do Moodle.
require_once('../../config.php');

// Garante que apenas administradores possam ver esta página.
require_login();
require_admin();

// Inclui nossa classe que se comunica com a API.
require_once($CFG->dirroot . '/local/ead_integration/classes/webservice_client.php');

echo "<h1>Cadastrando Aluno de Teste Real no Sistema Externo</h1>";

$api_client = new \local_ead_integration\webservice_client();

// Parâmetros para o cadastro.
$params = [
    'CursoID' => '473032',
    'PoloID' => '0',
    'Nome' => 'Jaoaaaaa Teste Final ',
    'CPF' => '81648307043', // Use um CPF novo e válido a cada teste
    'Email' => 'alunofinal' . rand(1000, 9999) . '@meudominio.com',
    'CEP' => '80410210',
    'Numero' => '123'
];

echo "<p>Enviando os seguintes dados para o endpoint /cadastro:</p>";
echo "<pre>" . print_r($params, true) . "</pre>";

$resultado = $api_client->call('cadastro', $params);

echo "<h2>Resultado do Cadastro:</h2>";
echo "<pre>";

// --- INÍCIO DA CORREÇÃO ---
// Agora verificamos se o status é 1 para confirmar o sucesso.
if (isset($resultado->status) && $resultado->status == 1) {
    echo "✅ SUCESSO! O servidor respondeu:\n";
    print_r($resultado);
} else {
    echo "❌ FALHA! A API retornou um erro:\n";
    print_r($resultado);
}
// --- FIM DA CORREÇÃO ---

echo "</pre>";