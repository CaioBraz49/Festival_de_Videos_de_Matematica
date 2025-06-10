<?php
// Funções do sistema ATUALIZAÇÃO 07-06-2025

// conectar ao banco de dados
//include_once 'config.php';

// Extrai o ID do YouTube do link https://www.youtube.com/shorts/Fn_ZOlu_T7s
function extractYoutubeId($url) {
    // Padrão atualizado para cobrir mais casos
    $pattern = '/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|watch\?(?:.*&)*v=)|youtu\.be\/|shorts\/)([a-zA-Z0-9_-]{11})(?:\?[^#\s]*|$)/';
    preg_match($pattern, $url, $matches);
    return $matches[1] ?? null;
}
/*
function extractYoutubeId($url) {
    if (preg_match('/(?:v=|\/embed\/|\.be\/|\/shorts\/)([a-zA-Z0-9_-]{11})/', $url, $matches)) {
        return $matches[1];
    }
    return null;
}
*/

function youtubeDurationToSeconds($duration) {
    $interval = new DateInterval($duration);
    $seconds = ($interval->h * 3600) + ($interval->i * 60) + $interval->s;
    // Para vídeos maiores que 24h
    if (property_exists($interval, 'd')) {
        $seconds += $interval->d * 86400;
    }
    return $seconds;
}














// Função para inserir chaves/valores JSON no campo 'dados' de uma tabela SQL
function insertJsonData($table, $data) {
    // Verifica se o campo 'dados' existe na tabela
    if (!isset($data['dados']) || !is_array($data['dados'])) {
        return "Erro: O campo 'dados' deve ser um array.";
    }

    // Converte o array 'dados' para JSON
    $jsonData = json_encode($data['dados'], JSON_UNESCAPED_UNICODE);
    
    // Monta a query de inserção
    $columns = implode(", ", array_keys($data));
    $values = implode(", ", array_map(function($value) {
        return "'" . addslashes($value) . "'";
    }, array_values($data)));
    
    $query = "INSERT INTO `$table` ($columns) VALUES ($values, '$jsonData')";
    
    return $query;
}















?>