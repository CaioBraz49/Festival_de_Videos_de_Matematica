<?php
// Configurações básicas
$baseUrl = 'http://localhost/festival/includes/youtube-data.php';
$timeout = 30; // segundos

// Função para fazer a requisição
function fetchVideoData($id, $baseUrl, $timeout) {
    $url = $baseUrl . '?id=' . $id .'&update=true';
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT => $timeout,
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if (curl_errno($ch)) {
        $error = curl_error($ch);
        curl_close($ch);
        return [
            'id' => $id,
            'error' => 'cURL Error: ' . $error
        ];
    }
    
    curl_close($ch);
    
    $data = json_decode($response, true);
    
    return [
        'id' => $id,
        'http_code' => $httpCode,
        'data' => $data
    ];
}

// Loop de 1 a 10
echo "<h2>Processando vídeos ID 1 a 440</h2>";
echo "<table border='1'>";
echo "<tr><th>ID</th><th>HTTP Code</th><th>Resultado</th></tr>";

for ($id = 263; $id <= 440; $id++) {
    $result = fetchVideoData($id, $baseUrl, $timeout);
    
    echo "<tr>";
    echo "<td>" . $id . "</td>";
    echo "<td>" . ($result['http_code'] ?? 'N/A') . "</td>";
    
    if (isset($result['error'])) {
        echo "<td style='color:red'>" . htmlspecialchars($result['error']) . "</td>";
    } else {
        echo "<td><pre>" . htmlspecialchars(print_r($result['data'], true)) . "</pre></td>";
    }
    
    echo "</tr>";
    
    // Pequena pausa para não sobrecarregar o servidor
    if ($id < 10) {
        sleep(1);
    }
}

echo "</table>";
echo "<p>Processamento concluído!</p>";
?>