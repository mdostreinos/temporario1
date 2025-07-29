<?php
require_once 'auth.php';
require_once 'db_connect.php';

require_login(); // Allow all logged-in users

$page_title = "Gerenciar Termos";
$page_error_message = '';
$current_user_is_admin = is_admin(); 

// --- BUSCAR DADOS PARA OS FILTROS ---
$institutions = [];
$sql_institutions = "SELECT DISTINCT institution_name FROM donation_terms WHERE institution_name IS NOT NULL AND institution_name != '' ORDER BY institution_name ASC";
$result_institutions = $conn->query($sql_institutions);
if ($result_institutions) {
    while ($row = $result_institutions->fetch_assoc()) {
        $institutions[] = $row['institution_name'];
    }
}
?>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<style>
    /* Estilos para o formulário de filtros */
    .form-filters .filter-row {
        width: 100%;
        display: flex;
        justify-content: center;
        align-items: flex-end;
        gap: 16px;
        flex-wrap: wrap;
    }

    /* Div que agrupa os botões para mantê-los juntos */
    .filter-buttons-group {
        display: flex;
        gap: 8px; /* Espaço entre o botão de filtrar e limpar */
    }

    .form-filters .filter-row > div {
        margin-bottom: 0;
    }
    
    /* Estilos consistentes para os campos e botões do filtro */
    .form-filters select,
    .form-filters .button-filter,
    .form-filters .button-filter-clear {
        height: 38px;
        padding: 0 15px;
        border: 1px solid #ccc;
        border-radius: 5px;
        vertical-align: middle;
        box-sizing: border-box;
        font-size: 14px;
        margin: 0;
        cursor: pointer;
    }

    .form-filters .button-filter {
        background-color: #007bff;
        color: white;
        border-color: #007bff;
    }
    
    .form-filters .button-filter-clear {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        text-decoration: none;
        background-color: #6c757d;
        color: white;
        border: 1px solid #6c757d;
    }
    .form-filters .button-filter-clear:hover {
        background-color: #5a6268;
        border-color: #545b62;
    }


    /* Ajustes para o Select2 */
    .select2-container {
        width: 100% !important;
    }
    .select2-container .select2-selection--single {
        height: 38px !important;
        border: 1px solid #ccc !important;
        border-radius: 5px !important;
    }
    .select2-container--default .select2-selection--single .select2-selection__rendered {
        line-height: 36px !important;
        padding-left: 15px !important;
    }
    .select2-container--default .select2-selection--single .select2-selection__arrow {
        height: 36px !important;
    }
    .filter-institution-wrapper {
        min-width: 250px;
        width: auto;
    }

    /* Estilo para o hover das linhas da tabela */
    .admin-table tbody tr:hover {
        background-color: #FFFEB3;
        cursor: pointer;
    }

    /* Correção para hover dos botões-link */
    .admin-table a.button-secondary {
        color: white !important;
        text-decoration: none;
    }
    .admin-table a.button-secondary:hover {
        color: white !important;
        text-decoration: none !important;
    }
</style>
<?php

// ==================================================================
// 1. CONFIGURAÇÃO E LÓGICA DE PAGINAÇÃO
// ==================================================================

const ITEMS_PER_PAGE = 8;

$current_page_dev = filter_input(INPUT_GET, 'page_dev', FILTER_VALIDATE_INT, ['options' => ['default' => 1, 'min_range' => 1]]);
$current_page_don = filter_input(INPUT_GET, 'page_don', FILTER_VALIDATE_INT, ['options' => ['default' => 1, 'min_range' => 1]]);

$offset_dev = ($current_page_dev - 1) * ITEMS_PER_PAGE;
$offset_don = ($current_page_don - 1) * ITEMS_PER_PAGE;

$filter_status = $_GET['filter_status'] ?? '';
$filter_institution = $_GET['filter_institution'] ?? '';


/**
 * Função reutilizável para renderizar os links de paginação.
 */
function render_pagination_links($current_page, $total_pages, $page_param_name, $other_params = []) {
    if ($total_pages <= 1) return;
    $query_params = array_merge($_GET, $other_params);
    unset($query_params[$page_param_name]);
    $range = 2;
    $pages_to_render = [];
    $potential_pages = [1, $total_pages];
    for ($i = $current_page - $range; $i <= $current_page + $range; $i++) {
        if ($i > 1 && $i < $total_pages) $potential_pages[] = $i;
    }
    sort($potential_pages);
    $potential_pages = array_unique($potential_pages);
    $prev_page = 0;
    foreach ($potential_pages as $p) {
        if ($p > $prev_page + 1) $pages_to_render[] = '...';
        $pages_to_render[] = $p;
        $prev_page = $p;
    }
    $build_url = function($page) use ($query_params, $page_param_name) {
        $query_params[$page_param_name] = $page;
        return htmlspecialchars("manage_terms.php?" . http_build_query($query_params));
    };
    if ($current_page > 1) echo '<a href="'.$build_url(1).'" class="pagination-link">Primeira</a>';
    else echo '<span class="pagination-link disabled">Primeira</span>';
    foreach ($pages_to_render as $item) {
        if ($item === '...') echo '<span class="pagination-dots">...</span>';
        elseif ($item == $current_page) echo '<span class="pagination-link current-page">'.$item.'</span>';
        else echo '<a href="'.$build_url($item).'" class="pagination-link">'.$item.'</a>';
    }
    if ($current_page < $total_pages) echo '<a href="'.$build_url($total_pages).'" class="pagination-link">Última</a>';
    else echo '<span class="pagination-link disabled">Última</span>';
}

// ==================================================================
// 2. BUSCA DE DADOS COM PAGINAÇÃO
// ==================================================================

$devolution_terms = [];
$total_devolution_terms = 0;
$donation_terms_list = [];
$total_donation_terms = 0;

if ($filter_status === '' || $filter_status === 'Devolvido') {
    $sql_count_dev = "SELECT COUNT(dd.id) AS total FROM devolution_documents dd";
    $result_count_dev = $conn->query($sql_count_dev);
    $total_devolution_terms = $result_count_dev ? (int)$result_count_dev->fetch_assoc()['total'] : 0;
    if ($total_devolution_terms > 0) {
        $sql_dev_terms = "SELECT dd.id AS devolution_id, i.name AS item_name, dd.devolution_timestamp, dd.owner_name, u.username AS processed_by_username, u.full_name AS processed_by_full_name FROM devolution_documents dd JOIN items i ON dd.item_id = i.id JOIN users u ON dd.returned_by_user_id = u.id ORDER BY dd.devolution_timestamp DESC LIMIT ? OFFSET ?";
        $stmt_dev = $conn->prepare($sql_dev_terms);
        if ($stmt_dev) {
            $limit_dev = ITEMS_PER_PAGE;
            $stmt_dev->bind_param('ii', $limit_dev, $offset_dev);
            $stmt_dev->execute();
            $result_dev_terms = $stmt_dev->get_result();
            $devolution_terms = $result_dev_terms->fetch_all(MYSQLI_ASSOC);
            $stmt_dev->close();
        } else {
            $page_error_message .= "Erro ao carregar termos de devolução. ";
        }
    }
}

$base_sql_don = "FROM donation_terms dt LEFT JOIN users u ON dt.user_id = u.id";
$conditions_don = [];
$params_don = [];
$types_don = "";

if (in_array($filter_status, ['Aguardando Aprovação', 'Doado', 'Reprovado'])) {
    $conditions_don[] = "dt.status = ?";
    $params_don[] = $filter_status;
    $types_don .= "s";
} elseif ($filter_status === 'Devolvido') {
    $conditions_don[] = "1=0";
}

if (!empty($filter_institution)) {
    $conditions_don[] = "dt.institution_name = ?";
    $params_don[] = $filter_institution;
    $types_don .= "s";
}

$where_clause_don = !empty($conditions_don) ? " WHERE " . implode(" AND ", $conditions_don) : "";

$sql_count_don = "SELECT COUNT(dt.term_id) AS total " . $base_sql_don . $where_clause_don;
$stmt_count_don = $conn->prepare($sql_count_don);
if ($stmt_count_don) {
    if (!empty($types_don)) {
        $stmt_count_don->bind_param($types_don, ...$params_don);
    }
    $stmt_count_don->execute();
    $total_donation_terms = (int)$stmt_count_don->get_result()->fetch_assoc()['total'];
    $stmt_count_don->close();
}

if ($total_donation_terms > 0) {
    $sql_don_terms = "SELECT dt.term_id, dt.created_at AS term_creation_date, dt.institution_name, dt.status, dt.reproval_reason, u.username AS registered_by_username, u.full_name AS registered_by_full_name " . $base_sql_don . $where_clause_don . " ORDER BY dt.created_at DESC LIMIT ? OFFSET ?";
    $stmt_don_terms = $conn->prepare($sql_don_terms);
    if ($stmt_don_terms) {
        $bind_args = [];
        $types_full = $types_don . 'ii';
        $bind_args[] = &$types_full;
        foreach ($params_don as $key => $value) {
            $bind_args[] = &$params_don[$key];
        }
        $limit_param = ITEMS_PER_PAGE;
        $bind_args[] = &$limit_param;
        $offset_param = $offset_don;
        $bind_args[] = &$offset_param;
        call_user_func_array([$stmt_don_terms, 'bind_param'], $bind_args);
        $stmt_don_terms->execute();
        $result_don_terms = $stmt_don_terms->get_result();
        while ($term = $result_don_terms->fetch_assoc()) {
            $item_summary_parts = [];
            $sql_summary = "SELECT c.name AS category_name, COUNT(dti.item_id) AS item_count FROM donation_term_items dti JOIN items i ON dti.item_id = i.id JOIN categories c ON i.category_id = c.id WHERE dti.term_id = ? GROUP BY c.name ORDER BY c.name ASC";
            $stmt_summary_inner = $conn->prepare($sql_summary);
            $stmt_summary_inner->bind_param("i", $term['term_id']);
            $stmt_summary_inner->execute();
            $result_summary_items = $stmt_summary_inner->get_result();
            while ($summary_item = $result_summary_items->fetch_assoc()) {
                $item_summary_parts[] = htmlspecialchars($summary_item['category_name'] ?? 'N/A') . ": " . htmlspecialchars($summary_item['item_count'] ?? 0);
            }
            $term['item_summary_text'] = empty($item_summary_parts) ? 'Nenhum item encontrado.' : implode(', ', $item_summary_parts);
            $stmt_summary_inner->close();
            $donation_terms_list[] = $term;
        }
        $stmt_don_terms->close();
    } else {
        $page_error_message .= "Erro ao carregar termos de doação. ";
    }
}

$total_pages_dev = $total_devolution_terms > 0 ? ceil($total_devolution_terms / ITEMS_PER_PAGE) : 1;
$total_pages_don = $total_donation_terms > 0 ? ceil($total_donation_terms / ITEMS_PER_PAGE) : 1;

require_once 'templates/header.php';
?>

<div class="container admin-container">
    <h2><?php echo htmlspecialchars($page_title); ?></h2>

    <?php if (!empty($page_error_message)): ?>
        <p class="error-message"><?php echo htmlspecialchars(trim($page_error_message)); ?></p>
    <?php endif; ?>

    <form method="GET" action="manage_terms.php" class="form-filters" style="margin-bottom: 20px;">
        <div class="filter-row">
            <div>
                <label for="filter_status">Status do Termo:</label>
                <select id="filter_status" name="filter_status">
                    <option value="" <?php if (empty($filter_status)) echo 'selected'; ?>>Todos os Status</option>
                    <option value="Aguardando Aprovação" <?php if ($filter_status === 'Aguardando Aprovação') echo 'selected'; ?>>Aguardando Aprovação</option>
                    <option value="Doado" <?php if ($filter_status === 'Doado') echo 'selected'; ?>>Doado</option>
                    <option value="Reprovado" <?php if ($filter_status === 'Reprovado') echo 'selected'; ?>>Reprovado</option>
                    <option value="Devolvido" <?php if ($filter_status === 'Devolvido') echo 'selected'; ?>>Devolvido</option>
                    </select>
            </div>
            <div class="filter-institution-wrapper">
                <label for="filter_institution">Instituição:</label>
                <select id="filter_institution" name="filter_institution" class="live-search-select">
                    <option value="">Todas as Instituições</option>
                    <?php foreach ($institutions as $institution): ?>
                        <option value="<?php echo htmlspecialchars($institution); ?>" <?php if ($filter_institution === $institution) echo 'selected'; ?>>
                            <?php echo htmlspecialchars($institution); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="filter-buttons-group">
                <button type="submit" class="button-filter">Filtrar</button>
                <a href="manage_terms.php" class="button-filter-clear">Limpar Filtros</a>
            </div>

        </div>
    </form>
    
    <?php if ($filter_status === '' || $filter_status === 'Devolvido'): ?>
    <h3>Termos de Devolução</h3>
    <?php if (!empty($devolution_terms)): ?>
        <table class="admin-table">
              <thead>
                <tr><th>ID</th><th>Nome do Item</th><th>Data/Hora Devolução</th><th>Recebedor</th><th>Processado Por</th><th>Ações</th></tr>
            </thead>
            <tbody>
                <?php foreach ($devolution_terms as $term): ?>
                <tr>
                    <td><?php echo htmlspecialchars($term['devolution_id']); ?></td>
                    <td><?php echo htmlspecialchars($term['item_name'] ?? 'N/A'); ?></td>
                    <td><?php echo htmlspecialchars(date('d/m/Y H:i:s', strtotime($term['devolution_timestamp'] ?? ''))); ?></td>
                    <td><?php echo htmlspecialchars($term['owner_name'] ?? 'N/A'); ?></td>
                    <td>
                        <?php $dev_display_name = !empty(trim($term['processed_by_full_name'] ?? '')) ? $term['processed_by_full_name'] : $term['processed_by_username']; echo htmlspecialchars($dev_display_name ?? 'N/A'); ?>
                    </td>
                    <td class="actions-cell">
                        <a href="manage_devolutions.php?view_id=<?php echo htmlspecialchars($term['devolution_id']); ?>&from=terms" class="button-secondary">Ver Termo</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <div class="table-footer-container" style="display: flex; justify-content: space-between; align-items: center; margin-top: 15px;">
            <div class="item-count-info">
                <span>Exibindo <strong><?php echo count($devolution_terms); ?></strong> de <strong><?php echo $total_devolution_terms; ?></strong> termos.</span>
            </div>
            <div class="pagination">
                <?php render_pagination_links($current_page_dev, $total_pages_dev, 'page_dev', ['page_don' => $current_page_don]); ?>
            </div>
        </div>
    <?php else: ?>
        <p class="info-message">Nenhum termo de devolução encontrado.</p>
    <?php endif; ?>
    <?php endif; ?>

    <?php if ($filter_status === '' || in_array($filter_status, ['Aguardando Aprovação', 'Doado', 'Reprovado'])): ?>
    <?php if ($filter_status === ''): ?> <hr style="margin: 30px 0;"> <?php endif; ?>
    <h3>Termos de Doação</h3>
    <?php if (!empty($donation_terms_list)): ?>
        <table class="admin-table">
            <thead>
                <tr><th>ID Termo</th><th>Data Criação</th><th>Instituição</th><th>Status</th><th>Registrado Por</th><th>Resumo dos Itens</th><th>Ações</th></tr>
            </thead>
            <tbody>
                <?php foreach ($donation_terms_list as $term): ?>
                <tr>
                    <td><?php echo htmlspecialchars($term['term_id']); ?></td>
                    <td><?php echo htmlspecialchars(date('d/m/Y H:i:s', strtotime($term['term_creation_date'] ?? ''))); ?></td>
                    <td><?php echo htmlspecialchars($term['institution_name'] ?? 'N/A'); ?></td>
                    <td>
                        <?php
                            $raw_status_term = $term['status'] ?? 'N/A';
                            
                            // Lógica de Estilo Inline - A PROVA DE FALHAS
                            $style_attr = 'display: inline-block; padding: 3px 7px; border-radius: 4px; font-size: 0.9em; font-weight: bold; text-align: center;';
                            switch ($raw_status_term) {
                                case 'Aguardando Aprovação':
                                    $style_attr .= ' background-color: #fff3cd; color: #856404; border: 1px solid #ffeeba;';
                                    break;
                                case 'Doado':
                                    $style_attr .= ' background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb;';
                                    break;
                                case 'Reprovado':
                                    $style_attr .= ' background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb;';
                                    break;
                                default:
                                    // Estilo padrão para status não mapeados
                                    $style_attr .= ' background-color: #e9ecef; color: #495057; border: 1px solid #ced4da;';
                                    break;
                            }
                        ?>
                        <span style="<?php echo $style_attr; ?>">
                            <?php echo htmlspecialchars($raw_status_term); ?>
                        </span>
                    </td>
                    <td>
                        <?php $don_display_name = !empty(trim($term['registered_by_full_name'] ?? '')) ? $term['registered_by_full_name'] : $term['registered_by_username']; echo htmlspecialchars($don_display_name ?? 'N/A'); ?>
                    </td>
                    <td><?php echo $term['item_summary_text']; ?></td>
                    <td class="actions-cell">
                              <?php
                                  $view_term_link = "view_donation_term_page.php?term_id=" . htmlspecialchars($term['term_id']);
                                  $button_text = "Ver Termo";
                                  if ($current_user_is_admin && ($term['status'] ?? '') === 'Aguardando Aprovação' && isset($_SESSION['user_role']) && in_array($_SESSION['user_role'], ['superAdmin', 'admin-aprovador'])) {
                                      $view_term_link .= "&context=approval";
                                      $button_text = "Analisar Pendência";
                                  }
                              ?>
                        <a href="<?php echo $view_term_link; ?>" class="button-secondary"><?php echo $button_text; ?></a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <div class="table-footer-container" style="display: flex; justify-content: space-between; align-items: center; margin-top: 15px;">
            <div class="item-count-info">
                <span>Exibindo <strong><?php echo count($donation_terms_list); ?></strong> de <strong><?php echo $total_donation_terms; ?></strong> termos.</span>
            </div>
            <div class="pagination">
                <?php render_pagination_links($current_page_don, $total_pages_don, 'page_don', ['page_dev' => $current_page_dev]); ?>
            </div>
        </div>
    <?php else: ?>
        <p class="info-message">Nenhum termo de doação encontrado.</p>
    <?php endif; ?>
    <?php endif; ?>
</div>

<script>
$(document).ready(function() {
    $('#filter_institution').select2({
        placeholder: "Selecione uma instituição",
        allowClear: true
    });
});
</script>

<?php
require_once 'templates/footer.php';
?>