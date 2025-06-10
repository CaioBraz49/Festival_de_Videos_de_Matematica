<?php
session_start();
// Verifica se o usuário está logado e é um administrador
if (!isset($_SESSION['user_id']) || $_SESSION['user_tipo'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}
require_once '../includes/config.php';

// Arquivos para o seletor de relatórios
$dir = '../relatorio/';
$arquivos = array_diff(scandir($dir), array('.', '..'));


// --- Botão deslizante exibe/oculta div ---
// Só responde em JSON se for AJAX (fetch)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['show_evaluators']) && isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
    $_SESSION['show_evaluators'] = (bool)$_POST['show_evaluators'];
    echo json_encode(['success' => true]);
    exit();
}
// Inicializa a sessão se não existir
if (!isset($_SESSION['show_evaluators'])) {
    $_SESSION['show_evaluators'] = true; // Valor padrão
}
// --- FIM DA PARTE EXISTENTE ---

// --- Configuração das categorias na tabela users. Na tabela users, a coluna categoria é uma string como "anos_finais_ef" ---
// Configuração das categorias e seus nomes para exibição
$categories_config = [
    'anos_finais_ef' => 'Anos Finais do Ensino Fundamental',
    'ensino_medio' => 'Ensino Médio',
    'grad_mat_afins' => 'Graduandos em Matemática ou áreas afins',
    'prof_acao' => 'Professores em Ação',
    'povos_orig_trad' => 'Povos Originários e Tradicionais',
    'com_geral' => 'Comunidade em Geral',
];

// --- PROCESSAMENTO DO FORMULÁRIO DE ATUALIZAÇÃO DE AVALIADOR (CATEGORIAS E LIMITES POR CATEGORIA) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_evaluator_settings'])) { 
    $user_id_to_update = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);

    if ($user_id_to_update) {
        $posted_assigned_categories = $_POST['assigned_categories'] ?? [];
        $posted_category_limits = $_POST['category_limits'] ?? [];
        
        $final_assigned_cat_keys_for_db = [];
        $pdo->beginTransaction();
        try {
            $stmt_delete_quotas = $pdo->prepare("DELETE FROM evaluator_category_quotas WHERE user_id = :user_id"); 
            $stmt_delete_quotas->execute([':user_id' => $user_id_to_update]);

            foreach ($categories_config as $cat_key => $cat_display_name) {
                if (isset($posted_assigned_categories[$cat_key]) && $posted_assigned_categories[$cat_key] == '1') {
                    $final_assigned_cat_keys_for_db[] = $cat_key;
                    if (isset($posted_category_limits[$cat_key])) {
                        $limit_str = trim($posted_category_limits[$cat_key]);
                        if ($limit_str !== '') { 
                            $limit_val = filter_var($limit_str, FILTER_VALIDATE_INT);
                            if ($limit_val !== false && $limit_val >= 0) {
                                $stmt_insert_quota = $pdo->prepare("INSERT INTO evaluator_category_quotas (user_id, category_key, quota) VALUES (:uid, :ckey, :q)"); 
                                $stmt_insert_quota->execute([':uid' => $user_id_to_update, ':ckey' => $cat_key, ':q' => $limit_val]);
                            } else {
                                if (!isset($_SESSION['warning_partial'])) $_SESSION['warning_partial'] = "";
                                $_SESSION['warning_partial'] .= "Limite inválido para categoria '{$cat_display_name}' do avaliador ID {$user_id_to_update} não foi salvo. "; 
                            }
                        }
                    }
                }
            }

            $categorias_db_string = implode(',', $final_assigned_cat_keys_for_db);
            $stmt_update_user = $pdo->prepare("UPDATE users SET categoria = :categoria WHERE id = :id"); 
            $stmt_update_user->execute([':categoria' => $categorias_db_string, ':id' => $user_id_to_update]);
            
            $pdo->commit();
            if (!isset($_SESSION['warning_partial'])) {
                 $_SESSION['success'] = "Configurações do avaliador ID " . htmlspecialchars($user_id_to_update) . " atualizadas com sucesso!";
            } else {
                 $_SESSION['warning_partial'] .= " Demais configurações do avaliador ID " . htmlspecialchars($user_id_to_update) . " foram salvas.";
            }
        } catch (PDOException $e) {
            $pdo->rollBack();
            $_SESSION['error'] = "Erro ao atualizar configurações do avaliador: " . $e->getMessage();
        }
    } else {
        $_SESSION['error'] = "ID de usuário inválido para atualizar configurações.";
    }
    
    $redirect_url = $_SERVER['PHP_SELF'];
    if (isset($_GET['categoria']) && $_GET['categoria'] !== 'todas') {
        $redirect_url .= '?categoria=' . urlencode($_GET['categoria']);
    }
    header("Location: " . $redirect_url);
    exit();
}

// --- Lógica para atualizar STATUS DO VÍDEO ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_update_video_status'])) { 
    $video_id_to_update = filter_input(INPUT_POST, 'video_id_to_update_status', FILTER_VALIDATE_INT);
    $new_status = $_POST['new_video_status'] ?? ''; 
    
    $allowed_statuses = ['pendente','correcao','aprovado','reprovado','aprovado_classificado']; 

    if ($video_id_to_update && in_array($new_status, $allowed_statuses)) {
        try {
            $stmt_update_video = $pdo->prepare("UPDATE videos SET status = :new_status WHERE id = :video_id"); 
            $stmt_update_video->execute([':new_status' => $new_status, ':video_id' => $video_id_to_update]);
            $_SESSION['success'] = "Status do vídeo ID " . htmlspecialchars($video_id_to_update) . " atualizado para '" . htmlspecialchars($new_status) . "' com sucesso!";
        } catch (PDOException $e) {
            $_SESSION['error'] = "Erro de banco de dados ao atualizar status: " . $e->getMessage();
        }
    } else {
         if (!$video_id_to_update) {
            $_SESSION['error'] = "ID do vídeo inválido para atualização de status.";
        } elseif (!in_array($new_status, $allowed_statuses)) {
            $_SESSION['error'] = "Status inválido ('" . htmlspecialchars($new_status) . "') fornecido. Status permitidos: " . implode(', ', $allowed_statuses) . ".";
        } else {
             $_SESSION['error'] = "Dados inválidos para atualização de status.";
        }
    }
    
    $redirect_url = $_SERVER['PHP_SELF'];
    if (isset($_GET['categoria']) && $_GET['categoria'] !== 'todas') {
        $redirect_url .= '?categoria=' . urlencode($_GET['categoria']);
    }
    header("Location: " . $redirect_url);
    exit();
}

// --- Consultas para estatísticas ---
$qtd_videos = $pdo->query("SELECT COUNT(*) FROM videos")->fetchColumn(); 
$qtd_avaliadores = $pdo->query("SELECT COUNT(*) FROM users WHERE tipo = 'avaliador'")->fetchColumn(); 
$qtd_avaliacoes = $pdo->query("SELECT COUNT(*) FROM avaliacoes")->fetchColumn(); 

// --- Filtro de categorias para vídeos ---
$categoria_filtro = $_GET['categoria'] ?? 'todas';
$sql_videos = "SELECT v.id, v.titulo, v.tema, v.categoria, v.link_youtube, v.status, v.created_at, v.nome AS autor_nome, v.email AS autor_email, v.cidade, v.estado, v.telefone, v.instituicao_ensino, v.nivel_instituicao, v.autarquia, v.descricao, v.dados FROM videos v";
if ($categoria_filtro !== 'todas') {
    $sql_videos .= " WHERE v.categoria = :categoria";
}
$sql_videos .= " ORDER BY v.created_at DESC";

$stmt_videos = $pdo->prepare($sql_videos);
if ($categoria_filtro !== 'todas') {
    $stmt_videos->bindParam(':categoria', $categoria_filtro);
}
$stmt_videos->execute();
$videos = $stmt_videos->fetchAll(PDO::FETCH_ASSOC); 

// --- Consulta de avaliadores ---
$avaliadores = $pdo->query("SELECT id, nome, email, categoria FROM users WHERE tipo = 'avaliador' ORDER BY nome")->fetchAll(PDO::FETCH_ASSOC); 

function categoriaAtiva($categoria_str, $categoria_busca_key) { 
    return in_array($categoria_busca_key, explode(',', $categoria_str ?? ''));
}

$category_map_inverso = [
    'Anos Finais do Ensino Fundamental' => 'anos_finais_ef',
    'Ensino Médio' => 'ensino_medio',
    'Graduandos em Matemática ou áreas afins' => 'grad_mat_afins',
    'Professores em Ação' => 'prof_acao',
    'Povos Originários e Tradicionais' => 'povos_orig_trad',
    'Comunidade em Geral' => 'com_geral'
];

$avaliacoes_por_categoria = [];
$stmt_apc = $pdo->query("SELECT a.id_user, v.categoria, COUNT(*) as total FROM avaliacoes a JOIN videos v ON a.id_video = v.id GROUP BY a.id_user, v.categoria");

while ($row_apc = $stmt_apc->fetch(PDO::FETCH_ASSOC)) {
    // Busca a chave correspondente no mapa
    $category_key = $category_map_inverso[$row_apc['categoria']] ?? null;
    if ($category_key) {
        // Usa a category_key como índice do array
        $avaliacoes_por_categoria[$row_apc['id_user']][$category_key] = $row_apc['total'];
    }
}

$all_evaluator_quotas = [];
$stmt_fetch_all_quotas = $pdo->query("SELECT user_id, category_key, quota FROM evaluator_category_quotas"); 
while ($quota_row = $stmt_fetch_all_quotas->fetch(PDO::FETCH_ASSOC)) {
    $all_evaluator_quotas[$quota_row['user_id']][$quota_row['category_key']] = $quota_row['quota'];
}

$video_statuses_options_dropdown = [
    'pendente' => 'Pendente', 
    'correcao' => 'Correção Solicitada',
    'aprovado' => 'Aprovado', 
    'reprovado' => 'Reprovado', 
    'aprovado_classificado' => 'Aprovado e Classificado'
];
$video_statuses_display_texts = [
    'pendente' => 'Pendente', 
    'avaliado' => 'Avaliado (1ª Rodada)', 
    'correcao' => 'Correção Solicitada',
    'aprovado' => 'Aprovado', 
    'reprovado' => 'Reprovado', 
    'reavaliar' => 'Reavaliar (3º Parecer)', 
    'aprovado_classificado' => 'Aprovado e Classificado'
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Festival de Vídeos - Painel do Administrador</title>
    <link rel="icon" href="../img/logo_200x200.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    
    <link rel="stylesheet" href="../includes/estilo.css?v=<?php echo time(); ?>">
    
    <style>
        .video-row { transition: all 0.2s ease-in-out; }
        .video-row:hover { background-color: #e9ecef; }

        /* Specific Badge Styles based on "Código 1" class names */
        .badge.badge-pendente { background-color: #ff830f !important; color: white !important; }
        .badge.badge-avaliado { background-color: #20c997 !important; color: white !important; }
        .badge.badge-correcao { background-color: #ffc107 !important; color: #000 !important; }
        .badge.badge-aprovado { background-color: #198754 !important; color: white !important; }
        .badge.badge-reprovado { background-color: #dc3545 !important; color: white !important; }
        .badge.badge-reavaliar { background-color: #ffd24d !important; color: #000 !important; }
        .badge.badge-primary { background-color: #0d6efd !important; color: white !important; }
        .badge.badge-secondary { background-color: #6c757d !important; color: white !important; }
        .badge.badge-info { background-color: #0dcaf0 !important; color: #000 !important; }
        
        .status-update-form .form-select-sm { width: auto; display: inline-block; max-width: 220px; }
        .status-update-form .btn-sm { vertical-align: baseline; }
        
        .limit-input { width: 70px !important; margin-left: 5px; }

        .modal-draggable .modal-header { cursor: move; background-color: #f8f9fa; }
        .modal-dialog-centered { display: flex; align-items: center; min-height: calc(100% - 1rem); }

        /* Estilo para o switch */
        #evaluatorsToggleContainer .form-check-input { 
            width: 3.5em; 
            height: 1.75em;
            margin-top: 0.1em;
        }
        #evaluatorsToggleContainer .form-check-label {
             padding-left: 0.5em;
        }

        /* Estilos para modais */
        .modal {
            z-index: 1060;
        }
        
        #infoModal {
            z-index: 1070;
        }
        
        .modal-dialog {
            width: 600px;
            max-width: none;
        }
        
        #infoButton {
            background-color: #0dcaf0;
            border-color: #0dcaf0;
        }
        
        .modal-draggable .modal-header {
            cursor: move;
            background-color: #f8f9fa;
        }
    </style>
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <div class="container mt-4 mb-5">
        <h2 class="mb-3">Painel do Administrador</h2>
        
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($_SESSION['success']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php elseif (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($_SESSION['error']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>
        <?php if (isset($_SESSION['warning_partial'])): ?>
            <div class="alert alert-warning alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($_SESSION['warning_partial']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['warning_partial']); ?>
        <?php endif; ?>

        <div class="row text-center g-3 mb-4">
            <div class="col-md-4">
                <div class="card card-custom-light-purple-bg h-100">
                    <div class="card-body d-flex flex-column justify-content-center">
                        <h5 class="card-title">Vídeos Cadastrados</h5>
                        <p class="card-text display-4"><?= $qtd_videos ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card card-custom-light-purple-bg h-100">
                    <div class="card-body d-flex flex-column justify-content-center">
                        <h5 class="card-title">Avaliadores</h5>
                        <p class="card-text display-4"><?= $qtd_avaliadores ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card card-custom-light-purple-bg h-100">
                    <div class="card-body d-flex flex-column justify-content-center">
                        <h5 class="card-title">Avaliações Realizadas</h5>
                        <p class="card-text display-4"><?= $qtd_avaliacoes ?></p>
                    </div>
                </div>
            </div>
        </div>
        
<div class="card mb-4">
    <div class="card-header card-header-custom-light-purple text-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Lista de Avaliadores e Limites por Categoria</h5>
        <div class="form-check form-switch" id="evaluatorsToggleContainer">
            <input class="form-check-input" type="checkbox" role="switch" id="toggleEvaluators" 
                <?php echo $_SESSION['show_evaluators'] ? 'checked' : ''; ?>>
            <label class="form-check-label" for="toggleEvaluators">Exibir</label>
        </div>
    </div>
    <div class="card-body p-0"> <!-- Removido padding para aproveitar espaço -->
        <div id="evaluatorsTableWrapper" class="<?php echo $_SESSION['show_evaluators'] ? '' : 'd-none'; ?>">
            <div class="table-responsive" style="overflow-x: auto; max-width: 100vw; margin-left: -1px; margin-right: -1px;">
                <table class="table table-striped table-hover mb-0" style="table-layout: fixed; width: 100%;">
                    <thead class="table-light">
                        <tr>
                            <th rowspan="2" style="vertical-align: middle; width: 200px; min-width: 200px;">Avaliador</th>
                            <?php foreach ($categories_config as $cat_key => $cat_display_name): ?>
                                <th class="text-center" style="width: 100px; min-width: 100px; max-width: 100px; overflow: hidden; text-overflow: ellipsis;" title="<?= htmlspecialchars($cat_display_name) ?>">
                                    <?= htmlspecialchars(shortenText($cat_display_name, 10)) ?>
                                </th>
                            <?php endforeach; ?>
                            <th rowspan="2" style="vertical-align: middle; width: 80px; min-width: 80px;">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($avaliadores as $avaliador): ?>
                        <tr>
                            <form method="post" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF'] . (isset($_GET['categoria']) ? '?categoria=' . urlencode($_GET['categoria']) : '')); ?>">
                                <input type="hidden" name="user_id" value="<?= $avaliador['id'] ?>">
                                <td class="text-nowrap" style="max-width: 200px; overflow: hidden; text-overflow: ellipsis;">
                                    <span title="<?= htmlspecialchars($avaliador['nome'] . ' - ' . $avaliador['email']) ?>">
                                        <?= htmlspecialchars(shortenText($avaliador['nome'], 20)) ?><br>
                                        <small class="text-muted"><?= htmlspecialchars(shortenText($avaliador['email'], 20)) ?></small>
                                    </span>
                                </td>
                                <?php
                                $id_avaliador_loop = $avaliador['id'];
                                $user_categorias_str_loop = $avaliador['categoria'];
                                ?>
                                <?php foreach ($categories_config as $cat_key => $cat_display_name): ?>
                                    <td class="text-center p-1" style="min-width: 100px;">                        
                                        <div class="d-flex flex-column align-items-center">
                                            <input type="number" 
                                                   class="form-control form-control-sm limit-input"
                                                   name="category_limits[<?= $cat_key ?>]"
                                                   id="limit_<?= $cat_key ?>_<?= $id_avaliador_loop ?>"
                                                   value="<?= isset($all_evaluator_quotas[$id_avaliador_loop][$cat_key]) ? (int)$all_evaluator_quotas[$id_avaliador_loop][$cat_key] : 0 ?>"
                                                   placeholder="0" 
                                                   min="0" 
                                                   step="1"
                                                   style="width: 60px; margin: 2px auto;"
                                                   title="Limite para <?= htmlspecialchars($cat_display_name) ?>. 0 para ilimitado."
                                                   onchange="this.value = this.value === '' ? 0 : Math.max(0, parseInt(this.value) || 0)">
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="checkbox"
                                                       name="assigned_categories[<?= $cat_key ?>]"
                                                       id="<?= $cat_key ?>_assigned_<?= $id_avaliador_loop ?>"
                                                       value="1" <?= categoriaAtiva($user_categorias_str_loop, $cat_key) ? 'checked' : '' ?>
                                                       title="Atribuir categoria <?= htmlspecialchars($cat_display_name) ?>">
                                                <label class="form-check-label small" for="<?= $cat_key ?>_assigned_<?= $id_avaliador_loop ?>">
                                                    (<?= $avaliacoes_por_categoria[$id_avaliador_loop][$cat_key] ?? 0 ?>)
                                                </label>
                                            </div>
                                        </div>
                                    </td>
                                <?php endforeach; ?>
                                <td class="text-nowrap align-middle p-1">
                                    <button type="submit" name="update_evaluator_settings" class="btn btn-primary btn-sm py-0 px-2" style="font-size: 0.8rem;">Salvar</button>
                                </td>
                            </form>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php
// Função auxiliar para encurtar texto
function shortenText($text, $maxLength) {
    if (strlen($text) <= $maxLength) {
        return $text;
    }
    return substr($text, 0, $maxLength) . '...';
}
?>

        <div class="row">
        <div class="card mb-4 col-6">
            <div class="card-header card-header-custom-light-purple text-white">
                <h5 class="mb-0">Filtrar Vídeos</h5>
            </div>
            <div class="card-body">
                <form method="get" class="d-flex align-items-center flex-wrap">
                    <div class="me-3 mb-2 mb-md-0">
                        <label for="categoria_filtro" class="form-label me-2">Categoria:</label>
                        <select name="categoria" id="categoria_filtro" class="form-select form-select-sm d-inline-block" style="width:auto;">
                            <option value="todas" <?= ($categoria_filtro ?? 'todas') === 'todas' ? 'selected' : '' ?>>Todas as Categorias</option>
                            <?php foreach ($categories_config as $cat_key_filter => $cat_name_filter): ?>
                                <option value="<?= htmlspecialchars($cat_name_filter) ?>" <?= ($categoria_filtro ?? '') === $cat_name_filter ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($cat_name_filter) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm">Filtrar</button>
                </form>
            </div>
        </div>


<div class="card mb-4 col-6">
    <div class="card-header card-header-custom-light-purple text-white">
        <h5 class="mb-0">Selecionar Relatório</h5>
    </div>
    <div class="card-body">
        <form method="get" class="d-flex align-items-center flex-wrap">
            <div class="me-3 mb-2 mb-md-0">
                <label for="seletorRelatorio" class="form-label me-2">Arquivo:</label>
                <select id="seletorRelatorio" name="relatorio" class="form-select form-select-sm d-inline-block" style="width:auto;">
                    <option value="">-- Selecione um arquivo --</option>
                    <?php foreach ($arquivos as $arquivo): ?>
                        <?php
                            $nomeSemExtensao = pathinfo($arquivo, PATHINFO_FILENAME);
                            $nomeExibicao = strtoupper($nomeSemExtensao);
                        ?>
                        <option value="<?= htmlspecialchars($dir . $arquivo) ?>"><?= htmlspecialchars($nomeExibicao) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>
    </div>
</div>
        </div>



        <div class="card">
            <div class="card-header card-header-custom-light-purple text-white">
                <h5 class="mb-0">Lista de Vídeos</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Status</th>
                                <th>Título</th>
                                <th>Categoria</th>
                                <th>Autor(es)</th>
                                <th>Link</th>
                                <th>Avaliações</th>
                                <th style="min-width: 230px;">Mudar Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($videos)): ?>
                                <tr><td colspan="7" class="text-center">Nenhum vídeo encontrado.</td></tr>
                            <?php else: ?>
                                <?php foreach ($videos as $video_item): ?>
                                    <?php
                                    $stmt_evals = $pdo->prepare("SELECT a.parecer, u.nome, u.id as id_user FROM avaliacoes a JOIN users u ON a.id_user = u.id WHERE a.id_video = :vid ORDER BY a.data_avaliacao DESC");
                                    $stmt_evals->execute([':vid' => $video_item['id']]);
                                    $evaluations_for_this_video = $stmt_evals->fetchAll(PDO::FETCH_ASSOC);

                                    $status_key_raw = strtolower($video_item['status']);
                                    $status_text = $video_statuses_display_texts[$status_key_raw] ?? ucfirst(htmlspecialchars($video_item['status']));
                                    
                                    $badge_class = 'secondary'; // Default
                                    if ($status_key_raw === 'aprovado' || $status_key_raw === 'aprovado_classificado') $badge_class = 'aprovado';
                                    if ($status_key_raw === 'avaliado') $badge_class = 'avaliado';
                                    elseif ($status_key_raw === 'reprovado') $badge_class = 'reprovado';
                                    elseif ($status_key_raw === 'pendente') $badge_class = 'pendente';
                                    elseif ($status_key_raw === 'reavaliar') $badge_class = 'reavaliar';
                                    elseif ($status_key_raw === 'correcao') $badge_class = 'correcao';
                                    
                                    if ($video_item['status'] === 'aprovado' || $video_item['status'] === 'aprovado_classificado') {
                                        $count_finalista = 0;
                                        foreach ($evaluations_for_this_video as $ev) {
                                            if ($ev['parecer'] === 'aprovado_classificado') $count_finalista++;
                                        }
                                        if ($count_finalista > 0) {
                                            $is_truly_finalist = true;
                                            foreach($evaluations_for_this_video as $ev_check){
                                                if($ev_check['parecer'] === 'reprovado') { $is_truly_finalist = false; break; }
                                            }
                                            if($is_truly_finalist) $badge_class = 'primary';
                                        }
                                    }
                                    if ($video_item['status'] === 'aprovado_classificado') { 
                                        $badge_class = 'primary';
                                    }
                                    ?>
                                    <tr class="video-row">
                                        <td><span class="badge badge-<?= htmlspecialchars($badge_class) ?>"><?= htmlspecialchars($status_text) ?></span></td>
                                        <td><?= htmlspecialchars($video_item['titulo']) ?></td>
                                        <td><?= htmlspecialchars($video_item['categoria']) ?></td>
                                        <td>
                                            <?php 
                                            $autor_email_display = $video_item['autor_email'] ?? null; 
                                            $autor_nome_display = $video_item['autor_nome'] ?? 'N/A'; 
                                            if ($autor_email_display):
                                            ?>
                                            <button
                                                class="btn btn-outline-info btn-primary py-0 px-1"
                                                data-bs-toggle="modal"
                                                data-bs-target="#contactModal"
                                                data-email="<?= htmlspecialchars($autor_email_display) ?>"
                                                data-nome="<?= htmlspecialchars($autor_nome_display) ?>"
                                                data-video="<?= htmlspecialchars($video_item['titulo']) ?>"
                                                data-categoria="<?= htmlspecialchars($video_item['categoria']) ?>"
                                                data-cidade="<?= htmlspecialchars($video_item['cidade'] ?? '') ?>"
                                                data-estado="<?= htmlspecialchars($video_item['estado'] ?? '') ?>"
                                                data-telefone="<?= htmlspecialchars($video_item['telefone'] ?? '') ?>"
                                                data-instituicao_ensino="<?= htmlspecialchars($video_item['instituicao_ensino'] ?? '') ?>"
                                                data-nivel_instituicao="<?= htmlspecialchars($video_item['nivel_instituicao'] ?? '') ?>"
                                                data-autarquia="<?= htmlspecialchars($video_item['autarquia'] ?? '') ?>"
                                                data-tema="<?= htmlspecialchars($video_item['tema'] ?? '') ?>"
                                                data-descricao="<?= htmlspecialchars($video_item['descricao'] ?? '') ?>"
                                                data-dados='<?= htmlspecialchars(json_encode(json_decode($video_item['dados'] ?? '{}'), JSON_UNESCAPED_UNICODE), ENT_QUOTES, "UTF-8") ?>'
                                                title="Contatar: <?= htmlspecialchars($autor_nome_display) ?> <<?= htmlspecialchars($autor_email_display) ?>>"
                                            >
                                                <i class="bi bi-envelope"></i> <small><?= htmlspecialchars($autor_nome_display) ?></small>
                                            </button>
                                            <?php else: echo "<small class='text-muted'>" . htmlspecialchars($autor_nome_display) . "</small>"; endif; ?>
                                        </td>
                                        <td><a href="<?= htmlspecialchars($video_item['link_youtube']) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-danger py-0 px-1"><i class="bi bi-youtube"></i> <small>Ver</small></a></td>
                                        <td>
                                            <?php if (empty($evaluations_for_this_video)): echo '<span class="text-muted small">Nenhuma</span>'; else: ?>
                                                <?php foreach ($evaluations_for_this_video as $eval_item): ?>
                                                    <?php
                                                    $p_status_key = strtolower($eval_item['parecer']);
                                                    $p_text = $video_statuses_display_texts[$p_status_key] ?? ucfirst(htmlspecialchars($eval_item['parecer']));
                                                    $p_badge_class = 'secondary';
                                                    if ($p_status_key === 'aprovado') $p_badge_class = 'aprovado';
                                                    elseif ($p_status_key === 'aprovado_classificado') { $p_text = 'Aprovado (Class.)'; $p_badge_class = 'primary'; }
                                                    elseif ($p_status_key === 'reprovado') $p_badge_class = 'reprovado';
                                                    elseif ($p_status_key === 'correcao') $p_badge_class = 'correcao';
                                                    elseif ($p_status_key === 'terceiro') {$p_text = '3º Parecer'; $p_badge_class = 'info';}
                                                    ?>
                                                    <div>
                                                        <a href="#" class="ver-detalhes small" 
                                                           data-bs-toggle="modal" data-bs-target="#avaliacaoModal" 
                                                           data-video-id="<?= htmlspecialchars($video_item['id']) ?>" 
                                                           data-avaliador-id="<?= htmlspecialchars($eval_item['id_user']) ?>" 
                                                           data-avaliador-nome="<?= htmlspecialchars($eval_item['nome']) ?>"
                                                           data-dados="<?= htmlspecialchars($video_item['dados'] ?? '') ?>"
                                                        >
                                                            <?= htmlspecialchars($eval_item['nome']) ?>: <span class="badge badge-<?= htmlspecialchars($p_badge_class) ?>"><?= $p_text ?></span>
                                                        </a>
                                                    </div>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF'] . (isset($_GET['categoria']) ? '?categoria=' . urlencode($_GET['categoria']) : '')); ?>" method="POST" class="d-flex status-update-form">
                                                <input type="hidden" name="video_id_to_update_status" value="<?= htmlspecialchars($video_item['id']) ?>">
                                                <select name="new_video_status" class="form-select form-select-sm me-2" aria-label="Novo status para vídeo">
                                                    <?php foreach ($video_statuses_options_dropdown as $status_val_option => $status_txt_option): ?>
                                                        <option value="<?= htmlspecialchars($status_val_option) ?>" <?= (strtolower($video_item['status']) == $status_val_option ? 'selected' : '') ?>>
                                                            <?= htmlspecialchars($status_txt_option) ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <button type="submit" name="submit_update_video_status" class="btn btn-primary btn-sm" title="Salvar novo status"><i class="bi bi-check-lg"></i></button>
                                            </form>
                                            <!-- Botão para abrir o modal de atualização do link -->
                                            <?php
    // Decodifica o campo dados para verificar as chaves
    $dados_json = json_decode($video_item['dados'] ?? '{}', true);
    $tem_duration = isset($dados_json['duration_seconds']);
    $tem_privacy = isset($dados_json['privacyStatus']);
    $icone_cronometro_azul = $tem_duration && $dados_json['duration_seconds'] <= 360;
    $icone_escudo_azul = $tem_privacy && strtolower($dados_json['privacyStatus']) === 'unlisted';
?>
<button 
    type="button" 
    class="btn btn-warning btn-sm mt-2 update-link-btn"
    data-bs-toggle="modal"
    data-bs-target="#updateLinkModal"
    data-video-id="<?= htmlspecialchars($video_item['id']) ?>"
    data-video-link="<?= htmlspecialchars($video_item['link_youtube']) ?>"
    data-video-titulo="<?= htmlspecialchars($video_item['titulo']) ?>"
    title="Atualizar link do vídeo"
>
    <i class="bi bi-link-45deg"></i> Atualizar Link
</button>
<!-- Ícones de status do vídeo -->
<?php if ($tem_duration && $tem_privacy): ?>
    <i class="bi bi-stopwatch-fill ms-2" 
       title="Duração do vídeo"
       style="color:<?= $icone_cronometro_azul ? '#0d6efd' : '#dc3545' ?>; font-size:1.25em; vertical-align:middle; height:1.5em;"></i>
    <i class="bi bi-shield-lock-fill ms-1"
       title="Privacidade do vídeo"
       style="color:<?= $icone_escudo_azul ? '#0d6efd' : '#dc3545' ?>; font-size:1.25em; vertical-align:middle; height:1.5em;"></i>
<?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Detalhes da Avaliação -->
    <div class="modal fade" id="avaliacaoModal" tabindex="-1" aria-labelledby="avaliacaoModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="avaliacaoModalLabel">Detalhes da Avaliação</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                
                <div class="modal-body" id="modalAvaliacaoBody">
                    <!-- Conteúdo será carregado via AJAX -->
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Email/Informações -->
    <div class="modal fade modal-draggable" id="contactModal" tabindex="-1" aria-labelledby="contactModalLabel" aria-hidden="true" data-bs-backdrop="false">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="contactModalLabel">Enviar Mensagem</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                
                <form id="contactForm" method="post" action="../PHPMailer/mail.php">
                    <div class="modal-body">
                        <!-- Destinatário visível -->
                        <div class="input-group mb-3">
                            <span class="input-group-text">Para:</span>
                            <input type="text" class="form-control" id="modalDestinatarioVisivel" readonly>
                        </div>
       
                        <!-- Email (oculto) -->
                        <div class="input-group mb-3" style="display: none;">
                            <span class="input-group-text">Email:</span>
                            <input type="text" class="form-control" id="nome" name="nome">
                            <input type="email" class="form-control" id="email" name="email">
                            <input type="hidden" id="url_retorno" name="url_retorno" value="../avaliador/index.php">
                        </div>
                        
                        <!-- Assunto -->
                        <div class="input-group mb-3">
                            <span class="input-group-text">Assunto:</span>
                            <input type="text" class="form-control" id="assunto" name="assunto" value="FESTIVAL - Correção de vídeo" required>
                        </div>
                        
                        <!-- Mensagem -->
                        <div class="input-group mb-3">
                            <span class="input-group-text">Mensagem:</span>
                            <textarea class="form-control" id="mensagem" name="mensagem" rows="6" required>
    <p>Prezado(a) Participante,</p>

    <p>Agradecemos sua participação no <strong>9º Festival de Vídeos Digitais e Educação Matemática</strong>.</p>

    <p>Gostaríamos de solicitar, gentilmente, que realize correções no vídeo intitulado <em>"Nome_Vídeo"</em> até o dia <strong>30/06/2025</strong>.</p>

    <p>Desde já, agradecemos sua atenção e colaboração.</p>

    <p>Atenciosamente,</p>

    <p><strong>Equipe do Festival de Vídeos Digitais e Educação Matemática</strong></p>
                            </textarea>
                        </div>
                        
                        <input type="hidden" id="modalNome" name="modalNome">
                    </div>
                    
                    <div class="modal-footer">
                        <button type="button" class="btn btn-info me-auto" id="infoButton">
                            <i class="bi bi-info-circle"></i> Ver Informações
                        </button>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-send"></i> Enviar Mensagem
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal de Informações do Email -->
    <div class="modal fade" id="infoModal" tabindex="-1" aria-labelledby="infoModalLabel" aria-hidden="true" data-bs-backdrop="false">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="infoModalLabel">Mais informações</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <!-- Nav tabs -->
                    <ul class="nav nav-tabs mb-3" id="infoTab" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="contato-tab" data-bs-toggle="tab" data-bs-target="#contato" type="button" role="tab" aria-controls="contato" aria-selected="true">
                                Detalhes do Contato
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="video-tab" data-bs-toggle="tab" data-bs-target="#video" type="button" role="tab" aria-controls="video" aria-selected="false">
                                Detalhes do Vídeo
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="json-tab" data-bs-toggle="tab" data-bs-target="#json" type="button" role="tab" aria-controls="json" aria-selected="false">
                                Detalhes do(s) Autor(es)
                            </button>
                        </li>
                    </ul>
                    <!-- Tab panes -->
                    <div class="tab-content" id="infoTabContent">
                        <div class="tab-pane fade show active" id="contato" role="tabpanel" aria-labelledby="contato-tab">
                            <div id="contactInfoContato"></div>
                        </div>
                        <div class="tab-pane fade" id="video" role="tabpanel" aria-labelledby="video-tab">
                            <div id="contactInfoVideo"></div>
                        </div>
                        <div class="tab-pane fade" id="json" role="tabpanel" aria-labelledby="json-tab">
                            <div id="contactInfoJson" style="font-family:monospace; font-size:0.95em; white-space:pre-wrap; word-break:break-all;"></div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" id="backButton">Voltar</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal para atualizar o link do vídeo -->
    <div class="modal fade" id="updateLinkModal" tabindex="-1" aria-labelledby="updateLinkModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <form method="post" id="updateLinkForm" action="">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="updateLinkModalLabel">Atualizar Link do Vídeo</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="video_id_to_update_link" id="updateLinkVideoId">
                        <div class="mb-3">
                            <label for="updateLinkVideoTitulo" class="form-label">Título do Vídeo</label>
                            <input type="text" class="form-control" id="updateLinkVideoTitulo" readonly>
                        </div>
                        <div class="mb-3">
                            <label for="updateLinkInput" class="form-label">Novo Link do YouTube</label>
                            <input type="url" class="form-control" name="new_video_link" id="updateLinkInput" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" name="submit_update_video_link" class="btn btn-success">Salvar Link</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- TinyMCE Editor -->
    <script src="../../tinymce/tinymce.min.js"></script>

    <!-- Scripts relatórios -->
    <script>
    document.getElementById('seletorRelatorio').addEventListener('change', function () {
        const caminho = this.value;
        if (caminho) {
            window.open(caminho, '_blank');
            // opcional: resetar o select para a opção padrão
            this.value = "";
        }
    });
    </script>
    
    <!-- Scripts personalizados -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // --- JAVASCRIPT PARA O TOGGLE DE AVALIADORES ---
            const toggleEvaluatorsSwitch = document.getElementById('toggleEvaluators');
            const evaluatorsTableWrapper = document.getElementById('evaluatorsTableWrapper');

            if (toggleEvaluatorsSwitch && evaluatorsTableWrapper) {
                evaluatorsTableWrapper.classList.toggle('d-none', !toggleEvaluatorsSwitch.checked);

                toggleEvaluatorsSwitch.addEventListener('change', function() {
                    const isChecked = this.checked;
                    
                    evaluatorsTableWrapper.classList.toggle('d-none', !isChecked);
                    
                    fetch('index.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: 'show_evaluators=' + (isChecked ? '1' : '0')
                    })
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('Erro na resposta do servidor');
                        }
                        return response.json();
                    })
                    .then(data => { // <-- CORRIGIDO AQUI
                        if (!data.success) {
                            console.error('Erro ao atualizar a preferência');
                            toggleEvaluatorsSwitch.checked = !isChecked;
                            evaluatorsTableWrapper.classList.toggle('d-none', isChecked);
                        }
                    })
                    .catch(error => {
                        console.error('Erro na requisição:', error);
                        toggleEvaluatorsSwitch.checked = !isChecked;
                        evaluatorsTableWrapper.classList.toggle('d-none', isChecked);
                    });
                });
            }

            // Instancie o modal de avaliação UMA VEZ
            const avaliacaoModalEl = document.getElementById('avaliacaoModal');
            const avaliacaoModal = new bootstrap.Modal(avaliacaoModalEl);

            // Detalhes de avaliação (AJAX)
            document.querySelectorAll('.ver-detalhes').forEach(button => {
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    
                    const videoId = this.dataset.videoId;
                    const avaliadorId = this.dataset.avaliadorId;
                    const avaliadorNome = this.dataset.avaliadorNome;
                    
                    const modalBody = document.getElementById('modalAvaliacaoBody');
                    
                    document.getElementById('avaliacaoModalLabel').textContent = 'Detalhes da Avaliação - ' + avaliadorNome;
                    
                    modalBody.innerHTML = `
                        <div class="text-center py-4">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Carregando...</span>
                            </div>
                            <p class="mt-2">Carregando detalhes da avaliação...</p>
                        </div>
                    `;
                    
                    avaliacaoModal.show();
                    
                    fetch('get_avaliacao_details.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `video_id=${videoId}&avaliador_id=${avaliadorId}`
                    })
                    .then(response => response.text())
                    .then(data => {
                        modalBody.innerHTML = data;
                    })
                    .catch(error => {
                        console.error('Erro:', error);
                        modalBody.innerHTML = `
                            <div class="alert alert-danger">
                                Erro ao carregar os detalhes da avaliação.<br>
                                ${error}
                            </div>
                        `;
                    });
                });
            });

            // Script para manipular os modais - Versão Bootstrap 5 puro
            const contactModalEl = document.getElementById('contactModal');
            const infoModalEl = document.getElementById('infoModal');
            const contactModal = new bootstrap.Modal(contactModalEl);
            const infoModal = new bootstrap.Modal(infoModalEl);
            let currentContactData = {};
            let formData = {};

            // Quando o modal de contato é aberto
            contactModalEl.addEventListener('show.bs.modal', function(event) {
                const button = event.relatedTarget;
                if (!button) return;

                const email = button.getAttribute('data-email');
                const nome = button.getAttribute('data-nome');
                const video = button.getAttribute('data-video');
                const categoria = button.getAttribute('data-categoria');
                const cidade = button.getAttribute('data-cidade');
                const estado = button.getAttribute('data-estado');
                const telefone = button.getAttribute('data-telefone');
                const instituicao_ensino = button.getAttribute('data-instituicao_ensino');
                const nivel_instituicao = button.getAttribute('data-nivel_instituicao');
                const autarquia = button.getAttribute('data-autarquia');
                const tema = button.getAttribute('data-tema');
                const descricao = button.getAttribute('data-descricao');
                const dados = button.getAttribute('data-dados');

                currentContactData = { 
                    email, nome, video, categoria, 
                    cidade, estado, telefone, 
                    instituicao_ensino, nivel_instituicao, 
                    autarquia, tema, descricao, dados
                };

                document.getElementById('modalDestinatarioVisivel').value = nome + " <" + email + ">";
                document.getElementById('email').value = email;
                document.getElementById('nome').value = nome;
                document.getElementById('assunto').value = "FESTIVAL - Correção de vídeo: "+video;
                
                tinymce.get('mensagem').setContent(`<p>Prezado(a) ${nome},</p>

<p>Agradecemos sua participação no <strong>9º Festival de Vídeos Digitais e Educação Matemática</strong>.</p>

<p>Gostaríamos de solicitar, gentilmente, que realize correções no vídeo intitulado <em>"${video}"</em> (categoria: ${categoria}) até o dia <strong>30/06/2025</strong>.</p>

<p>Desde já, agradecemos sua atenção e colaboração.</p>

<p>Atenciosamente,</p>

<p><strong>Equipe do Festival de Vídeos Digitais e Educação Matemática</strong></p>`);
                
                setTimeout(() => {
                    document.getElementById('mensagem').focus();
                }, 500);
            });

            // Botão "Ver Informações"
            document.getElementById('infoButton')?.addEventListener('click', function() {
                formData = {
                    assunto: document.getElementById('assunto').value,
                    mensagem: tinymce.get('mensagem').getContent()
                };

                // Conteúdo das abas
                const contatoContent = `
                    <h6>Detalhes do Contato</h6>
                    <p><strong>Nome:</strong> ${currentContactData.nome} &lt;${currentContactData.email}&gt;</p>
                    <p><strong>Localização:</strong> ${currentContactData.cidade} - ${currentContactData.estado}</p>
                    <p><strong>Telefone:</strong> ${currentContactData.telefone || 'Não informado'}</p>
                    <p><strong>Instituição:</strong> ${currentContactData.instituicao_ensino || 'Não informada'} (${currentContactData.nivel_instituicao || 'Nível não informado'})</p>
                    <p><strong>Autarquia:</strong> ${currentContactData.autarquia || 'Não informada'}</p>
                `;
                const videoContent = `
                    <h6>Detalhes do Vídeo</h6>
                    <p><strong>Título:</strong> ${currentContactData.video}</p>
                    <p><strong>Categoria:</strong> ${currentContactData.categoria}</p>
                    <p><strong>Tema:</strong> ${currentContactData.tema || 'Não informado'}</p>
                    <p><strong>Descrição:</strong> ${currentContactData.descricao || 'Não informada'}</p>
                `;

                // Prepara o conteúdo da aba Json
                let jsonContent = '';
                try {
                    console.log('JSON recebido:', currentContactData.dados);
                    let dadosStr = currentContactData.dados || '{}';
                    const dadosObj = JSON.parse(dadosStr);

                    jsonContent = `
                        <strong>Autores:</strong> ${dadosObj.autores ?? ''}<br>
                        <strong>Sobre os autores:</strong> ${dadosObj.sobre_autores ?? ''}<br>
                        <strong>Relação dos autores:</strong> ${dadosObj.relacao_autores ?? ''}<br>
                        <strong>Experiência:</strong> ${dadosObj.experiencia ?? ''}<br>
                        <strong>Usa IA:</strong> ${dadosObj.usa_ia ?? ''}<br>
                        <strong>Participação anterior:</strong> ${dadosObj.participacao ?? ''}
                        <hr>
                        <details>
                            <summary>Visualizar JSON bruto</summary>
                            <pre>${JSON.stringify(dadosObj, null, 2)}</pre>
                        </details>
                    `;
                } catch(e) {
                    jsonContent = '<span class="text-danger">Erro ao ler JSON.</span>';
                }

                document.getElementById('contactInfoContato').innerHTML = contatoContent;
                document.getElementById('contactInfoVideo').innerHTML = videoContent;
                document.getElementById('contactInfoJson').innerHTML = jsonContent;

                // Aguarda o modal de contato fechar para abrir o de informações
                contactModalEl.addEventListener('hidden.bs.modal', function handler() {
                    infoModal.show();
                    contactModalEl.removeEventListener('hidden.bs.modal', handler);
                });
                contactModal.hide();
            });

            // Botão "Voltar"
            document.getElementById('backButton')?.addEventListener('click', function() {
                infoModalEl.addEventListener('hidden.bs.modal', function handler() {
                    contactModal.show();
                    infoModalEl.removeEventListener('hidden.bs.modal', handler);
                });
                infoModal.hide();

                setTimeout(() => {
                    document.getElementById('assunto').value = formData.assunto || 'FESTIVAL - Correção de vídeo';
                    tinymce.get('mensagem').setContent(formData.mensagem || '');
                    document.getElementById('mensagem').focus();
                }, 500);
            });

            // Funcionalidade de arrastar o modal
            const modalHeader = contactModalEl.querySelector('.modal-header');
            const modalDialog = contactModalEl.querySelector('.modal-dialog');
            let isDragging = false;
            let offsetX = 0;
            let offsetY = 0;

            contactModalEl.addEventListener('shown.bs.modal', function() {
                modalDialog.style.margin = '0';
                modalDialog.style.position = 'absolute';
                modalDialog.style.left = '50%';
                modalDialog.style.top = '50%';
                modalDialog.style.transform = 'translate(-50%, -50%)';
            });

            modalHeader.addEventListener('mousedown', function(e) {
                isDragging = true;
                const rect = modalDialog.getBoundingClientRect();
                offsetX = e.clientX - rect.left;
                offsetY = e.clientY - rect.top;
                modalDialog.style.cursor = 'grabbing';
                e.preventDefault();
            });

            document.addEventListener('mousemove', function(e) {
                if (!isDragging) return;
                modalDialog.style.left = (e.clientX - offsetX) + 'px';
                modalDialog.style.top = (e.clientY - offsetY) + 'px';
                modalDialog.style.transform = 'none';
            });

            document.addEventListener('mouseup', function() {
                isDragging = false;
                if (modalDialog) modalDialog.style.cursor = '';
            });

            // Inicialização do Editor de email TinyMCE
            tinymce.init({
                selector: 'textarea#mensagem',
                height: 200,
                width: '100%',
                language: 'pt_BR',
                menubar: false,
                plugins: 'link lists',
                toolbar: 'undo redo | bold italic | bullist numlist | alignleft aligncenter alignright | link',
                branding: false
            });

            document.querySelectorAll('.update-link-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    document.getElementById('updateLinkVideoId').value = this.getAttribute('data-video-id');
                    document.getElementById('updateLinkInput').value = this.getAttribute('data-video-link');
                    document.getElementById('updateLinkVideoTitulo').value = this.getAttribute('data-video-titulo');
                });
            });
        });
    </script>

    <?php include '../includes/footer.php'; ?>
</body>
</html>

<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_update_video_link'])) {
    $video_id = filter_input(INPUT_POST, 'video_id_to_update_link', FILTER_VALIDATE_INT);
    $new_link = trim($_POST['new_video_link'] ?? '');

    if ($video_id && filter_var($new_link, FILTER_VALIDATE_URL)) {
        try {
            $stmt = $pdo->prepare("UPDATE videos SET link_youtube = :link WHERE id = :id");
            $stmt->execute([':link' => $new_link, ':id' => $video_id]);
            // Atualiza o tempo do vídeo no json

            $ch = curl_init();
            $timeout = 2; // Tempo limite em segundos
            $url = "https://sicalis.com.br/festival/includes/youtube-data.php?id=".$video_id."&update=true";
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_TIMEOUT => $timeout,
            ]);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

            $_SESSION['success'] = "Link do vídeo atualizado com sucesso!";
        } catch (PDOException $e) {
            $_SESSION['error'] = "Erro ao atualizar o link: " . $e->getMessage();
        }
    } else {
        $_SESSION['error'] = "Dados inválidos para atualizar o link.";
    }
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}