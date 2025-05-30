<?php
// Configurações do banco de dados

// Necessario liberar o IP da sua máquina no cpanel do servidor

// Acesso ao banco de dados local xampp
$host = 'localhost';
$dbname = 'sicalis_festival';
$username = 'root';
$password = '';

// Conexão com o banco de dados
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);

} catch (PDOException $e) {
    die("Erro na conexão: " . $e->getMessage());
}

?>
