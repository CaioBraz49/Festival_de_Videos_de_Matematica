<?php
session_start();
// Verifica se o usuário está logado e é um administrador
if (!isset($_SESSION['user_id']) || $_SESSION['user_tipo'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}
// Inclui o arquivo de configuração do banco de dados
require_once '../includes/config.php';

// NOVA CONFIGURAÇÃO CENTRAL DE CATEGORIAS (ORDEM DESEJADA)
$categories_config = [
    'anos_finais_ef' => 'Anos Finais do Ensino Fundamental',
    'ensino_medio' => 'Ensino Médio',
    'grad_mat_afins' => 'Graduandos em Matemática ou áreas afins',
    'prof_acao' => 'Professores em Ação',
    'povos_orig_trad' => 'Povos Originários e Tradicionais',
    'com_geral' => 'Comunidade em Geral',
];

// --- INÍCIO DA LÓGICA DO ITEM 4: ATUALIZAR LIMITE DE VÍDEOS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['atualizar_limite_videos'])) {
    $novo_limite = filter_input(INPUT_POST, 'novo_limite_videos', FILTER_VALIDATE_INT);

    // Verifica se o valor é um inteiro não negativo
    if ($novo_limite !== false && $novo_limite >= 0) {
        // Verifica se a opção 'limite_videos' já existe
        $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM options WHERE option_name = 'limite_videos'");
        $stmt_check->execute();
        $option_exists = $stmt_check->fetchColumn();

        if ($option_exists) {
            $stmt_update_limit = $pdo->prepare("UPDATE options SET option_value = :novo_limite WHERE option_name = 'limite_videos'");
        } else {
            // Se não existir, insere uma nova linha (improvável dado seu bd.sql.txt, mas é uma boa prática)
            $stmt_update_limit = $pdo->prepare("INSERT INTO options (option_name, option_value) VALUES ('limite_videos', :novo_limite)");
        }
        
        if ($stmt_update_limit->execute([':novo_limite' => $novo_limite])) {
            $_SESSION['success'] = "Limite de vídeos por avaliador atualizado para " . htmlspecialchars($novo_limite) . " com sucesso!";
        } else {
            $_SESSION['error'] = "Erro ao atualizar o limite de vídeos.";
        }
    } else {
        $_SESSION['error'] = "Valor inválido para o limite de vídeos. Por favor, insira um número inteiro não negativo.";
    }
    // Recarrega a página para mostrar a mensagem e o valor atualizado
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}
// --- FIM DA LÓGICA DO ITEM 4 ---


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['atualizar_categorias'])) {
    $user_id = $_POST['user_id'];

    $categorias_avaliador = [];
    foreach (array_keys($categories_config) as $cat_key) {
        if (isset($_POST[$cat_key])) {
            $categorias_avaliador[] = $cat_key; // Salvar a chave da categoria
        }
    }

    $categorias_str = implode(',', $categorias_avaliador);

    $stmt = $pdo->prepare("UPDATE users SET categoria = :categoria WHERE id = :id");
    $stmt->bindParam(':categoria', $categorias_str);
    $stmt->bindParam(':id', $user_id);

    if ($stmt->execute()) {
        $_SESSION['success'] = "Categorias do avaliador atualizadas com sucesso!";
    } else {
        $_SESSION['error'] = "Erro ao atualizar categorias do avaliador";
    }

    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

$qtd_videos = $pdo->query("SELECT COUNT(*) FROM videos")->fetchColumn();
$qtd_avaliadores = $pdo->query("SELECT COUNT(*) FROM users WHERE tipo = 'avaliador'")->fetchColumn();
$qtd_avaliacoes = $pdo->query("SELECT COUNT(*) FROM avaliacoes")->fetchColumn();

$categoria_filtro = $_GET['categoria'] ?? 'todas';

$sql_videos = "SELECT v.* FROM videos v";
if ($categoria_filtro !== 'todas') {
    $sql_videos .= " WHERE v.categoria = :categoria";
}
$stmt_videos = $pdo->prepare($sql_videos);
if ($categoria_filtro !== 'todas') {
    $stmt_videos->bindParam(':categoria', $categoria_filtro);
}
$stmt_videos->execute();
$videos = $stmt_videos->fetchAll(PDO::FETCH_ASSOC);

// Removido $categorias_videos pois não estava sendo usado após a mudança no filtro.
// $categorias_videos = $pdo->query("SELECT DISTINCT categoria FROM videos")->fetchAll(PDO::FETCH_COLUMN); 

$avaliadores = $pdo->query("SELECT id, nome, email, categoria FROM users WHERE tipo = 'avaliador' ORDER BY nome")->fetchAll(PDO::FETCH_ASSOC);

function categoriaAtiva($categoria_str, $categoria_busca_key) { // Alterado o segundo parâmetro para _key para clareza
    return in_array($categoria_busca_key, explode(',', $categoria_str));
}

// Busca quantidade de avaliacoes por categoria para cada avaliador
$avaliacoes_por_categoria = [];
$stmt = $pdo->query("SELECT a.id_user, v.categoria, COUNT(*) as total FROM avaliacoes a JOIN videos v ON a.id_video = v.id GROUP BY a.id_user, v.categoria");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $avaliacoes_por_categoria[$row['id_user']][$row['categoria']] = $row['total'];
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Dashboard do Administrador</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="../includes/estilo.css?v=<?php echo time(); ?>">
    <style>
        .video-row {
            transition: all 0.3s;
        }
        .video-row:hover {
            background-color: #f8f9fa;
        }
        /* Estilos de badge do seu estilo.css */
        .badge-aprovado, .badge-success { /* .badge-success para o caso 'aprovado' */
            background-color: #28a745 !important;
            color: white !important;
        }
        .badge-reprovado, .badge-danger { /* .badge-danger para o caso 'reprovado' */
            background-color: #dc3545 !important; /* Usando danger color do Bootstrap */
            color: white !important;
        }
        .badge-pendente {
            background-color: #ff830f !important; /* Laranja do seu CSS */
            color: white !important;
        }
        .badge-reavaliar { 
            background-color: #ffd24d; /* Amarelo do seu CSS */
            color: #212529; 
        }
        .badge-correcao, .badge-warning { /* .badge-warning para o caso 'correcao' */
             background-color: #ffc107 !important; /* Bootstrap warning yellow */
             color: #212529 !important; /* Texto escuro para contraste */
        }
        /* Para a nova badge azul "Aprovado (finalista)" */
        .badge-primary { /* Bootstrap primary - geralmente azul */
            background-color: #007bff !important; /* Forçando um azul Bootstrap, se necessário */
            color: white !important;
        }
        .badge-secondary { /* Para status não mapeados ou default */
            background-color: #6c757d !important;
            color: white !important;
        }
         .badge-info { /* Para o caso 'terceiro' */
            background-color: #17a2b8 !important; /* Bootstrap info color */
            color: white !important;
        }
    </style>
</head>
<body>
<?php include '../includes/header.php'; ?>
    <div class="container mt-5">
        <h2>Bem-vindo ao Painel do Administrador</h2>
        <hr>
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($_SESSION['success']) ?>
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php elseif (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($_SESSION['error']) ?>
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <div class="row text-center">
            <div class="col-md-4">
                <div class="card card-custom-light-purple-bg mb-3">
                    <div class="card-body">
                        <h5 class="card-title">Vídeos cadastrados</h5>
                        <p class="card-text display-4"><?= $qtd_videos ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card card-custom-light-purple-bg mb-3">
                    <div class="card-body">
                        <h5 class="card-title">Avaliadores</h5>
                        <p class="card-text display-4"><?= $qtd_avaliadores ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card card-custom-light-purple-bg mb-3">
                    <div class="card-body">
                        <h5 class="card-title">Avaliações realizadas</h5>
                        <p class="card-text display-4"><?= $qtd_avaliacoes ?></p>
                    </div>
                </div>
            </div>
        </div>


        <div class="card mb-4">
            <div class="card-header card-header-custom-light-purple">
                <h5 class="mb-0">Configurar Limite de Avaliações por Avaliador</h5>
            </div>
            <div class="card-body">
                <?php
                $stmt_get_limite_atual = $pdo->query("SELECT option_value FROM options WHERE option_name = 'limite_videos'");
                $limite_atual_val = $stmt_get_limite_atual->fetchColumn();
                if ($limite_atual_val === false) {
                    $limite_atual_val = 8; 
                }
                ?>
                <form method="post" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
                    <div class="form-group">
                        <label for="limite_videos_input">Limite de Vídeos que cada Avaliador pode avaliar:</label>
                        <input type="number" class="form-control" name="novo_limite_videos" id="limite_videos_input" value="<?php echo htmlspecialchars($limite_atual_val); ?>" min="0" required>
                    </div>
                    <button type="submit" name="atualizar_limite_videos" class="btn btn-primary">Atualizar Limite</button>
                </form>
            </div>
        </div>
        
        <div class="card mb-4">
            <div class="card-header card-header-custom-light-purple">
                <h5 class="mb-0">Lista de Avaliadores</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="thead-light">
                            <tr>
                                <th>Nome</th>
                                <th>Email</th>
                                <?php foreach ($categories_config as $cat_key_loop => $cat_display_name): // Renomeada $cat_key para $cat_key_loop ?>
                                    <th><?= htmlspecialchars($cat_display_name) ?></th>
                                <?php endforeach; ?>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($avaliadores as $avaliador): ?>
                                <tr>
                                    <form method="post">
                                        <td><?= htmlspecialchars($avaliador['nome']) ?></td>
                                        <td><?= htmlspecialchars($avaliador['email']) ?></td>
                                        <?php
                                        $id_avaliador = $avaliador['id']; // Renomeada $id para $id_avaliador
                                        $user_categorias_str = $avaliador['categoria']; 
                                        ?>
                                        <?php foreach ($categories_config as $cat_key => $cat_display_name): ?>
                                            <td>
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" name="<?= $cat_key ?>" id="<?= $cat_key ?>_<?= $id_avaliador ?>" <?= categoriaAtiva($user_categorias_str, $cat_key) ? 'checked' : '' ?>>
                                                    <label class="form-check-label" for="<?= $cat_key ?>_<?= $id_avaliador ?>">
                                                        <?= $avaliacoes_por_categoria[$id_avaliador][$cat_display_name] ?? 0 ?>
                                                    </label>
                                                </div>
                                            </td>
                                        <?php endforeach; ?>
                                        <td>
                                            <input type="hidden" name="user_id" value="<?= $id_avaliador ?>">
                                            <button type="submit" name="atualizar_categorias" class="btn btn-primary btn-sm">Atualizar</button>
                                        </td>
                                    </form>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header card-header-custom-light-purple">
                <h5 class="mb-0">Filtrar Vídeos</h5>
            </div>
            <div class="card-body">
                <form method="get" class="form-inline">
                    <div class="form-group mr-3">
                        <label for="categoria_filtro" class="mr-2">Categoria:</label>
                        <?php
                        $categorias_para_filtro = array_values($categories_config);
                        ?>
                        <select name="categoria" id="categoria_filtro" class="form-control">
                            <option value="todas" <?= ($categoria_filtro ?? 'todas') === 'todas' ? 'selected' : '' ?>>Todas as Categorias</option>
                            <?php foreach ($categorias_para_filtro as $categoria_nome_exibicao): ?>
                                <option
                                    value="<?= htmlspecialchars($categoria_nome_exibicao) ?>"
                                    <?= ($categoria_filtro ?? '') === $categoria_nome_exibicao ? 'selected' : '' ?>
                                >
                                    <?= htmlspecialchars($categoria_nome_exibicao) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary">Filtrar</button>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header card-header-custom-light-purple">
                <h5 class="mb-0">Lista de Vídeos</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="thead-light">
                            <tr>
                                <th>Status</th>
                                <th>Título</th>
                                <th>Categoria</th>
                                <th>Link</th>
                                <th>Avaliações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($videos as $video): ?>
                            <?php
                            // PASSO 1: Buscar todas as avaliações para o vídeo atual ANTES de definir o status principal
                            $stmt_all_evals_for_this_video = $pdo->prepare("
                                SELECT a.parecer, u.nome, a.id_user
                                FROM avaliacoes a
                                JOIN users u ON a.id_user = u.id
                                WHERE a.id_video = :video_id_param
                                ORDER BY a.data_avaliacao DESC
                            ");
                            $stmt_all_evals_for_this_video->bindParam(':video_id_param', $video['id'], PDO::PARAM_INT);
                            $stmt_all_evals_for_this_video->execute();
                            $all_evaluations_for_this_video = $stmt_all_evals_for_this_video->fetchAll(PDO::FETCH_ASSOC);

                            // PASSO 2: Determinar a classe e o texto da badge de STATUS PRINCIPAL do vídeo
                            $video_status_text = ucfirst(htmlspecialchars($video['status']));
                            $status_video_key = strtolower(str_replace(' ', '-', $video['status']));
                            
                            // Mapeamento padrão de classes para o status principal do vídeo
                            if ($status_video_key === 'aprovado' || $status_video_key === 'avaliado') $status_final_class = 'success'; // 'avaliado' também como success
                            elseif ($status_video_key === 'reprovado') $status_final_class = 'danger';
                            elseif ($status_video_key === 'pendente') $status_final_class = 'pendente';
                            elseif ($status_video_key === 'reavaliar') $status_final_class = 'reavaliar';
                            elseif ($status_video_key === 'correcao') $status_final_class = 'warning';
                            else $status_final_class = 'secondary'; // Fallback

                            // Lógica ADICIONAL para alterar a cor da badge de status "Aprovado" principal
                            if ($video['status'] === 'aprovado') {
                                $count_finalista_evals = 0;
                                // Não precisamos contar $count_aprovado_normal para esta lógica específica,
                                // apenas o número total de avaliações e quantos são 'aprovado_classificado'
                                
                                foreach ($all_evaluations_for_this_video as $eval) {
                                    if ($eval['parecer'] === 'aprovado_classificado') {
                                        $count_finalista_evals++;
                                    }
                                }

                                // Condição: Exatamente 2 avaliações no total para este vídeo, e AMBAS são 'aprovado_classificado'
                                if (count($all_evaluations_for_this_video) == 2 && $count_finalista_evals == 2) {
                                    $status_final_class = 'primary'; // Azul para o status principal do vídeo
                                }
                                // Se a condição acima não for atendida (ex: 1 aprovado + 1 finalista, ou 2 aprovados normais, ou >2 avaliações),
                                // $status_final_class permanecerá 'success' (verde), conforme definido no mapeamento padrão acima.
                            }
                            ?>
                            <tr class="video-row">
                                <td>
                                    <span class="badge badge-<?= htmlspecialchars($status_final_class) ?>">
                                        <?= htmlspecialchars($video_status_text) ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars($video['titulo']) ?></td>
                                <td><?= htmlspecialchars($video['categoria']) ?></td>
                                <td><a href="<?= htmlspecialchars($video['link_youtube']) ?>" target="_blank">Ver no YouTube</a></td>
                                <td>
                                    <?php
                                    if (empty($all_evaluations_for_this_video)) {
                                        echo '<span class="text-muted">Nenhuma avaliação</span>';
                                    } else {
                                        foreach ($all_evaluations_for_this_video as $avaliacao_item):
                                            $parecer_text = '';
                                            $parecer_class = '';
                                            switch ($avaliacao_item['parecer']) {
                                                case 'aprovado':
                                                    $parecer_text = 'Aprovado'; $parecer_class = 'success'; break;
                                                case 'aprovado_classificado': 
                                                    $parecer_text = 'Aprovado'; $parecer_class = 'primary'; break;
                                                case 'reprovado':
                                                    $parecer_text = 'Reprovado'; $parecer_class = 'danger'; break;
                                                case 'correcao':
                                                    $parecer_text = 'Correção'; $parecer_class = 'warning'; break;
                                                case 'terceiro': 
                                                    $parecer_text = 'Terceiro'; $parecer_class = 'info'; break;
                                                default:
                                                    $parecer_text = ucfirst(htmlspecialchars($avaliacao_item['parecer']));
                                                    $parecer_class = 'secondary'; break;
                                            }
                                            echo '<div><a href="#" class="ver-detalhes" data-toggle="modal" data-target="#avaliacaoModal" data-video-id="' . htmlspecialchars($video['id']) . '" data-avaliador-id="' . htmlspecialchars($avaliacao_item['id_user']) . '" data-avaliador-nome="' . htmlspecialchars($avaliacao_item['nome']) . '">' . htmlspecialchars($avaliacao_item['nome']) . ': <span class="badge badge-' . htmlspecialchars($parecer_class) . '">' . htmlspecialchars($parecer_text) . '</span></a></div>';
                                        endforeach;
                                    }
                                    ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<div class="modal fade" id="avaliacaoModal" tabindex="-1" role="dialog" aria-labelledby="avaliacaoModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="avaliacaoModalLabel">Detalhes da Avaliação</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="modalAvaliacaoBody">
                </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Quando o botão "Ver Detalhes" é clicado
    $(document).on('click', '.ver-detalhes', function(e) {
        e.preventDefault();
        
        const videoId = $(this).data('video-id'); 
        const avaliadorId = $(this).data('avaliador-id'); 
        const avaliadorNome = $(this).data('avaliador-nome');
        
        // Atualiza o título do modal
        $('#avaliacaoModalLabel').text('Detalhes da Avaliação - ' + avaliadorNome); // Adicionado nome ao título
        
        // Mostra o spinner enquanto carrega
        $('#modalAvaliacaoBody').html(`
            <div class="text-center py-4">
                <div class="spinner-border text-primary" role="status">
                    <span class="sr-only">Carregando...</span>
                </div>
                <p class="mt-2">Carregando detalhes da avaliação...</p>
            </div>
        `);
        
        // Mostra o modal
        $('#avaliacaoModal').modal('show');
        
        // Faz a requisição AJAX
        $.ajax({
            url: 'get_avaliacao_details.php',
            method: 'POST',
            data: {
                video_id: videoId,
                avaliador_id: avaliadorId
            },
            success: function(response) {
                $('#modalAvaliacaoBody').html(response);
            },
            error: function(xhr, status, error) {
                console.error("Erro AJAX:", status, error);
                $('#modalAvaliacaoBody').html(`
                    <div class="alert alert-danger">
                        Erro ao carregar os detalhes da avaliação.<br>
                        ${xhr.status}: ${xhr.statusText}
                    </div>
                `);
            }
        });
    });
});
</script>

<?php include '../includes/footer.php'; ?>
</body>
</html>