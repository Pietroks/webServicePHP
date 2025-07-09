<?php
// Carrega o ambiente do Moodle.
require_once('../../config.php');
require_login();
require_admin();
require_once($CFG->dirroot . '/local/ead_integration/classes/webservice_client.php');

echo "<h1>Cadastrando Aluno de Teste Real no Sistema Externo</h1>";

$api_client = new \local_ead_integration\webservice_client();

// Parâmetros para o cadastro.
$params = [
    'CursoID' => '473034', // Usando um CursoID existente.
    'PoloID' => '0',
    'Nome' => 'Josee ' . date('d-m'), // Nome com a data para ser único.
    'CPF' => '79732845082', // Use um CPF válido e diferente a cada dia se necessário.
    'Email' => 'alunoteste.' . date('dm') . rand(100, 999) . '@meudominio.com',
    'CEP' => '80410210',
    'Numero' => '123'
];

echo "<p>Enviando os seguintes dados para o endpoint /cadastro:</p>";
echo "<pre>" . print_r($params, true) . "</pre>";

// A API de cadastro não é paginada, então o terceiro parâmetro é 'false'.
$resultado = $api_client->call('cadastro', $params, false);

echo "<h2>Resultado do Cadastro:</h2>";
echo "<pre>";

// --- CORREÇÃO FINAL DA VERIFICAÇÃO ---
// Agora verificamos se a resposta é um array e se o 'status' é igual a 1.
if (is_array($resultado) && isset($resultado['status']) && $resultado['status'] == 1) {
    echo "✅ SUCESSO! A API respondeu:\n";
    print_r($resultado);
} else {
    echo "❌ FALHA! A API retornou um erro:\n";
    print_r($resultado);
}
// --- FIM DA CORREÇÃO ---

echo "</pre>";