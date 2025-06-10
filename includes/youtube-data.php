<?php
// http://localhost/festival/admin/youtube-data.php?id=8&update=true
session_start();
include_once 'function.php'; // Aqui deve estar a função extractYoutubeId
include_once 'config.php'; // Aqui deve estar sua conexão PDO em $pdo e a chave $apikey_youtube
$apikey_youtube = "AIzaSyBVsoIUufsVs-lMtK6Dfvw4TgF-B-d1x0s"; // Recupera a chave da API do YouTube



header('Content-Type: application/json');

// Recebe o id do vídeo
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$forceUpdate = isset($_GET['update']) && $_GET['update'] === 'true';

if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'ID inválido']);
    exit;
}

// Busca o vídeo no banco
$stmt = $pdo->prepare("SELECT id, link_youtube, dados FROM videos WHERE id = ?");
$stmt->execute([$id]);
$video = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$video) {
    http_response_code(404);
    echo json_encode(['error' => 'Vídeo não encontrado']);
    exit;
}

// Extrai o ID do YouTube do link

/*
function extractYoutubeId($url) {
    if (preg_match('/(?:v=|\/embed\/|\.be\/)([a-zA-Z0-9_-]{11})/', $url, $matches)) {
        return $matches[1];
    }
    return null;
}
*/

$youtubeId = extractYoutubeId($video['link_youtube']);
if (!$youtubeId) {
    http_response_code(400);
    echo json_encode(['error' => 'Link do YouTube inválido']);
    exit;
}

// Decodifica o campo dados
$dados = [];
if (!empty($video['dados'])) {
    $dados = json_decode($video['dados'], true);
    if (!is_array($dados)) $dados = [];
}

// Só retorna se já tem duration e privacyStatus E não for update forçado
if (!$forceUpdate && !empty($dados['duration']) && !empty($dados['privacyStatus'])) {
    echo json_encode(['message' => 'Já possui duration e privacyStatus', 'dados' => $dados]);
    exit;
}

// Busca na API do YouTube usando cURL
$apiUrl = "https://www.googleapis.com/youtube/v3/videos?id={$youtubeId}&part=contentDetails,status&key={$apikey_youtube}";

// Substitua o file_get_contents por cURL:
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Se necessário, mas o ideal é true em produção
$response = curl_exec($ch);
if ($response === false) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao acessar a API do YouTube via cURL: ' . curl_error($ch)]);
    curl_close($ch);
    exit;
}
curl_close($ch);

$json = json_decode($response, true);
if (empty($json['items'][0])) {
    http_response_code(404);
    echo json_encode(['error' => 'Vídeo não encontrado na API '.$apiUrl]);
    exit;
}

$contentDetails = $json['items'][0]['contentDetails'] ?? [];
$status = $json['items'][0]['status'] ?? [];

$duration = $contentDetails['duration'] ?? null;
$privacyStatus = $status['privacyStatus'] ?? null;

$duration_seconds = null;
if ($duration) {
    try {
        $duration_seconds = youtubeDurationToSeconds($duration);
    } catch (Exception $e) {
        $duration_seconds = null;
    }
}

if (!$duration || !$privacyStatus) {
    http_response_code(500);
    echo json_encode(['error' => 'Não foi possível obter duration ou privacyStatus']);
    exit;
}

// Atualiza o JSON
$dados['duration'] = $duration;
$dados['duration_seconds'] = $duration_seconds;
$dados['privacyStatus'] = $privacyStatus;

$stmt = $pdo->prepare("UPDATE videos SET dados = ? WHERE id = ?");
$stmt->execute([json_encode($dados, JSON_UNESCAPED_UNICODE), $id]);

echo json_encode([
    'success' => true,
    'duration' => $duration,
    'duration_seconds' => $duration_seconds,
    'privacyStatus' => $privacyStatus,
    'dados' => $dados
]);


