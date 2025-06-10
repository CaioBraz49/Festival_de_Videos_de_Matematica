<?php
// Configurações do banco de dados

// Configuração segura para carregar Variáveis de Ambiente .env
$envFile = $_SERVER['DOCUMENT_ROOT'] . '/.env'; // Assume que o .env está no nível raiz

if (!file_exists($envFile) || !is_readable($envFile)) {
    die('Erro: Arquivo .env não encontrado ou sem permissão de leitura');
}

$env = parse_ini_file($envFile);
if ($env === false) {
    die('Erro: Falha ao analisar o arquivo .env (formato inválido)');
}

// Verifica se as variáveis necessárias estão definidas
$requiredVars = ['FEST_DB_HOST', 'FEST_DB_DBNAME', 'FEST_DB_USERNAME', 'FEST_DB_PASSWORD'];
foreach ($requiredVars as $var) {
    if (!isset($env[$var])) {
        die("Erro: Variável $var não definida no .env");
    }
}

// Atribuições seguras
$apikey_youtube = $env['FEST_APIKEY_YOUTUBE'] ?? '';
$host = $env['FEST_DB_HOST'] ?? '';
$dbname = $env['FEST_DB_DBNAME'] ?? '';
$username = $env['FEST_DB_USERNAME'] ?? '';
$password = $env['FEST_DB_PASSWORD'] ?? '';


// Exibição de vídeos para o avaliador
$video_eval_limit_from_env = $env['FEST_VIDEO_EVAL_LIMIT'] ?? '15'; // Padrão 15 se não definido
$video_eval_order_from_env = $env['FEST_VIDEO_EVAL_ORDER'] ?? 'CREATED_AT_ASC'; // Padrão data de criação ASC se não definido

// Validação e sanitização do limite
$video_eval_limit = filter_var($video_eval_limit_from_env, FILTER_VALIDATE_INT, [
    'options' => ['default' => 10, 'min_range' => 1]
]);
if ($video_eval_limit === false || $video_eval_limit < 1) {
    $video_eval_limit = 10; // Garante um valor padrão sensível
}

// Validação e sanitização da ordenação para evitar SQL Injection
$allowed_orders = ['RAND', 'ID_ASC', 'ID_DESC', 'TITLE_ASC', 'TITLE_DESC', 'CREATED_AT_ASC', 'CREATED_AT_DESC'];
$video_eval_order = strtoupper(trim($video_eval_order_from_env));
if (!in_array($video_eval_order, $allowed_orders)) {
    $video_eval_order = 'CREATED_AT_ASC'; // Fallback para uma ordenação segura
}

// Conexão com o banco de dados
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); // <<< ADICIONE ESTA LINHA
} catch (PDOException $e) {
    die("Erro na conexão: " . $e->getMessage());
}

?>