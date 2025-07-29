<?php

require_once 'auth.php';
require_once 'db_connect.php';

require_login();

// Busca categorias para os filtros
$filter_categories = [];
$sql_filter_cats = "SELECT id, name FROM categories ORDER BY name ASC";
$result_filter_cats = $conn->query($sql_filter_cats);
if ($result_filter_cats && $result_filter_cats->num_rows > 0) {
    while ($row_fc = $result_filter_cats->fetch_assoc()) {
        $filter_categories[] = $row_fc;
    }
}

// Busca locais para os filtros
$filter_locations = [];
$sql_filter_locs = "SELECT id, name FROM locations ORDER BY name ASC";
$result_filter_locs = $conn->query($sql_filter_locs);
if ($result_filter_locs && $result_filter_locs->num_rows > 0) {
    while ($row_fl = $result_filter_locs->fetch_assoc()) {
        $filter_locations[] = $row_fl;
    }
}

// Inclui o manipulador de itens para a carga inicial da página
require_once 'get_items_handler.php';

$current_user_is_admin = is_admin();

require_once 'templates/header.php';
?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" integrity="sha512-Fo3rlalHpgO7oR/7X7k9+4o0p4l+7g4+1z6l5r5j5O6p6u+2r5z4b5+9z5o3l5p5u5w5t5v5u5w==" crossorigin="anonymous" referrerpolicy="no-referrer" />

<style>
    /* Estilo visual para a label do checkbox quando ele está desabilitado (mantido) */
    input:disabled + label {
        color: #999;
        cursor: not-allowed;
        opacity: 0.7;
        text-decoration: line-through;
    }
    
    /* Estilo para o Tooltip Global controlado por JavaScript */
    #global-tooltip {
        position: fixed; /* Fixo na tela para escapar de containers com 'overflow' */
        background-color: #333;
        color: #fff;
        font-size: 13px;
        padding: 8px 12px;
        border-radius: 5px;
        white-space: nowrap;
        z-index: 9999; /* Z-index muito alto para garantir que fique na frente de tudo */
        pointer-events: none; /* Impede que o tooltip interfira com o mouse */
        opacity: 0;
        transition: opacity 0.2s ease-in-out;
    }

    /* Estilo para quebra de linha nas colunas Nome e Local */
    .wrap-text {
        white-space: normal;
        word-wrap: break-word;
        word-break: break-word;
    }
    
    /* Estilo Base da Tabela */
    .admin-table {
        width: 100%;
        table-layout: fixed; /* Essencial para que as larguras do CSS funcionem bem */
        border-collapse: collapse; /* Boa prática de estilo */
    }

    /* Centraliza o conteúdo da célula ID e ajusta o padding */
    .admin-table td.id-cell,
    .admin-table th.id-cell {
        text-align: center; /* Centraliza o texto */
        padding: 10px 0;   
    }

    /* Estilo para o hover na linha da tabela */
    .admin-table tbody tr:hover {
        background-color: #fffbb8 !important; /* Cor ao passar o mouse */
    }

    /* Estilos para os botões com ícones - Flexbox para alinhamento */
    .button-filter,
    .button-filter-clear {
        display: flex;
        align-items: center; 
        padding: 8px 12px; 
        text-align: left; 
        white-space: nowrap; 
        box-sizing: border-box;
    }

    .button-filter i,
    .button-filter-clear i {
        margin-right: 8px;
        flex-shrink: 0;
    }

    /* Estilo para a SPAN do texto dentro dos botões com ícone */
    .button-filter span,
    .button-filter-clear span {
        flex-grow: 1; 
        text-align: center; 
    }

    /* ======================================================= */
    /* ESTILOS CORRIGIDOS PARA ÍCONES DE AÇÃO NA TABELA */
    /* ======================================================= */
    .admin-table .actions-cell .actions-wrapper {
        display: flex;
        flex-direction: row;       /* CORREÇÃO: Força a direção horizontal */
        justify-content: center;
        align-items: center;
        gap: 15px;
        flex-wrap: nowrap;
    }

    .admin-table .actions-cell .action-icon {
        font-size: 1.2em;
        color: #555;
        text-decoration: none;
        cursor: pointer;
        transition: color 0.2s ease-in-out, transform 0.2s ease-in-out;
    }

    .admin-table .actions-cell .action-icon:hover {
        color: #007bff;
        transform: scale(1.2);
    }
    
    .admin-table .actions-cell .action-icon-delete {
        color: #c82333;
    }
    
    .admin-table .actions-cell .action-icon-delete:hover {
        color: #a21a28;
    }
    /* ======================================================= */
    
    /* ===== LARGURAS BASE PARA TELAS MAIORES (ex: 1920x1080) ===== */
    .admin-table th:nth-child(1), .admin-table td:nth-child(1) { width: 3%; min-width: 40px; }
    .admin-table th:nth-child(2), .admin-table td:nth-child(2) { width: 4%; min-width: 50px; }
    .admin-table th:nth-child(3), .admin-table td:nth-child(3) { width: 8%; min-width: 90px; }
    .admin-table th:nth-child(4), .admin-table td:nth-child(4) { width: 18%; }
    .admin-table th:nth-child(5), .admin-table td:nth-child(5) { width: 10%; }
    .admin-table th:nth-child(6), .admin-table td:nth-child(6) { width: 11%; }
    .admin-table th:nth-child(7), .admin-table td:nth-child(7) { width: 13%; }
    .admin-table th:nth-child(8), .admin-table td:nth-child(8) { width: 7%; }
    .admin-table th:nth-child(9), .admin-table td:nth-child(9) { width: 7%; }
    .admin-table th:nth-child(10), .admin-table td:nth-child(10){ width: 11%; }
    .admin-table th:nth-child(11), .admin-table td:nth-child(11){ width: 15%; min-width: 180px;}

    /* Estilos base para filtros e controles (para telas maiores que 1366px) */
    .form-filters .filter-group {
        display: flex;
        flex-wrap: wrap; 
        gap: 15px; 
        margin-bottom: 15px; 
    }

    /* MODIFICADO: Layout de 4 colunas para todos os grupos de filtros */
    .form-filters .filter-group > div {
        flex: 1 1 calc(25% - 11.25px); 
        max-width: calc(25% - 11.25px);
    }
    
    .filter-buttons {
        display: flex;
        gap: 10px;
        justify-content: flex-end; 
        flex-wrap: wrap;
    }

    .table-header-controls, .table-footer-container {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap; 
        gap: 10px;
    }

    /* Otimizações para 1366x768 */
    @media (max-width: 1366px) {
        .admin-table th:nth-child(5),
        .admin-table td:nth-child(5),
        .admin-table th:nth-child(10),
        .admin-table td:nth-child(10) {
            display: none;
        }
        .admin-table th:nth-child(1), .admin-table td:nth-child(1) { width: 3%; min-width: 35px; }
        .admin-table th:nth-child(2), .admin-table td:nth-child(2) { width: 4%; min-width: 45px; }
        .admin-table th:nth-child(3), .admin-table td:nth-child(3) { width: 10%; min-width: 95px; }
        .admin-table th:nth-child(4), .admin-table td:nth-child(4) { width: 20%; }
        .admin-table th:nth-child(6), .admin-table td:nth-child(6) { width: 11%; }
        .admin-table th:nth-child(7), .admin-table td:nth-child(7) { width: 14%; }
        .admin-table th:nth-child(8), .admin-table td:nth-child(8) { width: 10%; }
        .admin-table th:nth-child(9), .admin-table td:nth-child(9) { width: 8%; }
        .admin-table th:nth-child(11), .admin-table td:nth-child(11){ width: 16%; min-width: 150px;}
        .admin-table .actions-cell .actions-wrapper {
            gap: 12px;
        }
    }

    /* Otimizações para Telas de Tablet (ex: 1024px ou menos) */
    @media (max-width: 1024px) {
        .admin-table th:nth-child(6),
        .admin-table td:nth-child(6),
        .admin-table th:nth-child(8),
        .admin-table td:nth-child(8),
        .admin-table th:nth-child(9),
        .admin-table td:nth-child(9) {
            display: none;
        }
        .admin-table th:nth-child(1), .admin-table td:nth-child(1) { width: 5%; min-width: 30px; }  
        .admin-table th:nth-child(2), .admin-table td:nth-child(2) { width: 8%; min-width: 40px; }  
        .admin-table th:nth-child(3), .admin-table td:nth-child(3) { width: 18%; min-width: 80px; }
        .admin-table th:nth-child(4), .admin-table td:nth-child(4) { width: 40%; min-width: 120px; }
        .admin-table th:nth-child(7), .admin-table td:nth-child(7) { width: 30%; min-width: 140px; }
        .admin-table th:nth-child(11), .admin-table td:nth-child(11) { width: auto; min-width: 100px; }
        .admin-table .actions-cell .actions-wrapper {
            gap: 10px;
        }
        .admin-table .actions-cell .action-icon {
            font-size: 1.1em;
        }
    }
</style>

<div class="container home-container">
    <h2>Itens Encontrados</h2>

    <?php
    // Exibe mensagens de sucesso/erro
    if (isset($_GET['message'])) {
        $successMessage = '';
        if ($_GET['message'] == 'itemadded' && isset($_GET['barcode'])) {
            $successMessage = 'Item cadastrado com sucesso! Código de Barras: <strong>' . htmlspecialchars($_GET['barcode']) . '</strong>';
        } elseif ($_GET['message'] == 'itemupdated') {
            $successMessage = 'Item atualizado com sucesso!';
        } elseif ($_GET['message'] == 'itemdeleted') {
            $successMessage = 'Item excluído com sucesso!';
        }
        if (!empty($successMessage)) {
            echo '<p class="success-message">' . $successMessage . '</p>';
        }
    }

    if (isset($_GET['error'])) {
        $error_map = [
            'deletefailed' => 'Erro ao tentar excluir o item.',
            'accessdenied' => 'Acesso negado. Permissões de administrador necessárias.'
        ];
        $error_key = $_GET['error'];
        $errorMessage = $error_map[$error_key] ?? 'Ocorreu um erro desconhecido (' . htmlspecialchars($error_key) . ').';
        echo '<p class="error-message">' . $errorMessage . '</p>';
    }
    ?>

    <form id="filterForm" class="form-filters">
        <div class="filter-group top-filters">
            <div>
                <label for="filter_item_name">Nome do Item (contém):</label>
                <input type="text" id="filter_item_name" name="filter_item_name" value="<?php echo htmlspecialchars($_GET['filter_item_name'] ?? ''); ?>" placeholder="Digite parte do nome...">
            </div>

            <div>
                <label for="filter_category_id">Categoria:</label>
                <select id="filter_category_id" name="filter_category_id">
                    <option value="">Todas as Categorias</option>
                    <?php foreach ($filter_categories as $category): ?>
                        <option value="<?php echo htmlspecialchars($category['id']); ?>" <?php echo (isset($_GET['filter_category_id']) && $_GET['filter_category_id'] == $category['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($category['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label for="filter_status">Status do Item:</label>
                <select id="filter_status" name="filter_status">
                    <option value="" <?php echo (!isset($_GET['filter_status']) || $_GET['filter_status'] == '') ? 'selected' : ''; ?>>Todos os Status</option>
                    <option value="Pendente" <?php echo (isset($_GET['filter_status']) && $_GET['filter_status'] == 'Pendente') ? 'selected' : ''; ?>>Pendente</option>
                    <option value="Devolvido" <?php echo (isset($_GET['filter_status']) && $_GET['filter_status'] == 'Devolvido') ? 'selected' : ''; ?>>Devolvido</option>
                    <option value="Doado" <?php echo (isset($_GET['filter_status']) && $_GET['filter_status'] == 'Doado') ? 'selected' : ''; ?>>Doado</option>
                    <option value="Aguardando Aprovação" <?php echo (isset($_GET['filter_status']) && $_GET['filter_status'] == 'Aguardando Aprovação') ? 'selected' : ''; ?>>Aguardando Aprovação</option>
                </select>
            </div>

            <div>
                <label for="filter_barcode">Código de Barras:</label>
                <input type="text" id="filter_barcode" name="filter_barcode" value="<?php echo htmlspecialchars($_GET['filter_barcode'] ?? ''); ?>" placeholder="Digite o código de barras...">
            </div>
        </div>
        <div class="filter-group bottom-filters">
            <div>
                <label for="filter_location_id">Local:</label>
                <select id="filter_location_id" name="filter_location_id">
                    <option value="">Todos os Locais</option>
                    <?php foreach ($filter_locations as $location): ?>
                        <option value="<?php echo htmlspecialchars($location['id']); ?>" <?php echo (isset($_GET['filter_location_id']) && $_GET['filter_location_id'] == $location['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($location['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="filter_days_waiting">Tempo Aguardando:</label>
                <select id="filter_days_waiting" name="filter_days_waiting">
                    <option value="">Qualquer</option>
                    <option value="0-7" <?php echo (isset($_GET['filter_days_waiting']) && $_GET['filter_days_waiting'] == '0-7') ? 'selected' : ''; ?>>0-7 dias</option>
                    <option value="8-30" <?php echo (isset($_GET['filter_days_waiting']) && $_GET['filter_days_waiting'] == '8-30') ? 'selected' : ''; ?>>8-30 dias</option>
                    <option value="31-9999" <?php echo (isset($_GET['filter_days_waiting']) && $_GET['filter_days_waiting'] == '31-9999') ? 'selected' : ''; ?>>31+ dias</option>
                </select>
            </div>
            <div>
                <label for="filter_found_date_start">Achado de (data):</label>
                <input type="date" id="filter_found_date_start" name="filter_found_date_start" value="<?php echo htmlspecialchars($_GET['filter_found_date_start'] ?? ''); ?>">
            </div>
            <div>
                <label for="filter_found_date_end">Até (data):</label>
                <input type="date" id="filter_found_date_end" name="filter_found_date_end" value="<?php echo htmlspecialchars($_GET['filter_found_date_end'] ?? ''); ?>">
            </div>
        </div>
        <div class="filter-buttons">
            <button type="submit" class="button-filter"><i class="fas fa-check"></i><span>Aplicar Filtros</span></button>
            <button type="reset" id="clearFiltersButton" class="button-filter-clear"><i class="fas fa-broom"></i><span>Limpar Filtros</span></button>
        </div>
    </form>
    <hr>
    
    <div class="table-header-controls">
        <?php if ($current_user_is_admin): ?>
        <div class="action-bar">
            <?php
            $filter_keys = ['filter_item_name', 'filter_barcode', 'filter_category_id', 'filter_status', 'filter_location_id', 'filter_days_waiting', 'filter_found_date_start', 'filter_found_date_end'];
            $is_filter_active = false;
            foreach ($filter_keys as $key) {
                if (!empty($_GET[$key])) {
                    $is_filter_active = true;
                    break;
                }
            }
            $tooltip_attr = '';
            $checkbox_attrs = '';
            if (!$is_filter_active) {
                $tooltip_attr = 'data-tooltip="Para habilitar este recurso, primeiro aplique algum filtro."';
                $checkbox_attrs = 'disabled';
            }
            ?>
            
            <span class="tooltip-wrapper" <?php echo $tooltip_attr; ?>>
                <input type="checkbox" id="selectFilteredCheckbox" <?php echo $checkbox_attrs; ?>>
                <label for="selectFilteredCheckbox">Selecionar Itens Filtrados</label>
            </span>

            <button id="devolverButton" class="button-secondary" disabled>Devolver Selecionados</button>
            <button id="doarButton" class="button-secondary" disabled>Doar Selecionados</button>
            <button id="imprimirCodBarrasButton" class="button-secondary" disabled>Imprimir Cód. Barras</button>
        </div>
        <?php endif; ?>

        <div id="pagination-top" class="pagination">
           <?php
            if (!empty($items) && $total_pages > 1) {
                $pages_to_render = []; $range = 2; $potential_pages = [];
                for ($i = $current_page - $range; $i <= $current_page + $range; $i++) { if ($i > 0 && $i <= $total_pages) $potential_pages[] = $i; }
                if (!in_array(1, $potential_pages)) array_unshift($potential_pages, 1);
                if (!in_array($total_pages, $potential_pages)) $potential_pages[] = $total_pages;
                $potential_pages = array_unique($potential_pages); sort($potential_pages);
                $prev_page_num = 0;
                foreach ($potential_pages as $page_num) { if ($page_num > $prev_page_num + 1) $pages_to_render[] = '...'; $pages_to_render[] = $page_num; $prev_page_num = $page_num; }
                $query_params = $_GET; unset($query_params['page']); $base_url = "home.php?" . http_build_query($query_params);
                if ($current_page > 1) echo '<a href="'.$base_url.'&page=1" class="pagination-link">Primeira Página</a>'; else echo '<span class="pagination-link disabled">Primeira Página</span>';
                foreach ($pages_to_render as $page_item) { if ($page_item === '...') echo '<span class="pagination-dots">...</span>'; elseif ($page_item == $current_page) echo '<span class="pagination-link current-page">'.$page_item.'</span>'; else echo '<a href="'.$base_url.'&page='.$page_item.'" class="pagination-link">'.$page_item.'</a>'; }
                if ($current_page < $total_pages) echo '<a href="'.$base_url.'&page='.$total_pages.'" class="pagination-link">Última Página</a>'; else echo '<span class="pagination-link disabled">Última Página</span>';
            }
            ?>
        </div>
    </div>

    <div id="itemListContainer">
        <?php if (!empty($items)): ?>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th class="checkbox-cell"></th>
                        <th class="id-cell">ID</th>
                        <th>Status</th>
                        <th class="wrap-text">Nome</th>
                        <th>Imagem C.B.</th>
                        <th>Categoria</th> 
                        <th class="wrap-text">Local Encontrado</th>
                        <th class="wrap-text">Data Achado</th>
                        <th class="wrap-text">Dias Aguardando</th>
                        <th class="wrap-text">Registrado por</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                        <tr>
                            <td class="checkbox-cell"><input type="checkbox" class="item-checkbox" name="selected_items[]" value="<?php echo htmlspecialchars($item['id']); ?>"></td>
                            <td class="id-cell"><?php echo htmlspecialchars($item['id']); ?></td>
                            <td class="status-cell">
                                <?php
                                $raw_status = $item['status'];
                                $status_text_display = htmlspecialchars($raw_status);
                                $class_name_normalized = strtolower($raw_status);
                                $char_map_simple = ['á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'å' => 'a', 'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e', 'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i', 'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o', 'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ç' => 'c', 'ñ' => 'n'];
                                $class_name_normalized = strtr($class_name_normalized, $char_map_simple);
                                $class_name_normalized = preg_replace('/[^a-z0-9\s-]/', '', $class_name_normalized);
                                $class_name_normalized = preg_replace('/[\s-]+/', '-', $class_name_normalized);
                                $status_class_name_final = trim($class_name_normalized, '-');
                                ?>
                                <span class="item-status status-<?php echo $status_class_name_final; ?>"><?php echo $status_text_display; ?></span>
                            </td>
                            <td class="wrap-text"><?php echo htmlspecialchars($item['name']); ?></td>
                            <td><?php if (!empty($item['barcode'])): ?><svg id="barcode-<?php echo htmlspecialchars($item['id']); ?>" class="barcode-image"></svg><?php else: ?>N/A<?php endif; ?></td>
                            <td><?php echo htmlspecialchars($item['category_name'] ?? 'N/A'); ?> (<?php echo htmlspecialchars($item['category_code'] ?? 'N/A'); ?>)</td>
                            <td class="wrap-text"><?php echo htmlspecialchars($item['location_name'] ?? 'N/A'); ?></td>
                            <td class="wrap-text"><?php echo htmlspecialchars(date("d/m/Y", strtotime($item['found_date']))); ?></td>
                            <td class="wrap-text"><?php echo htmlspecialchars($item['days_waiting'] ?? '0'); ?> dias</td>
                            <td class="wrap-text">
                                <?php
                                $display_name = !empty(trim($item['registered_by_full_name'] ?? '')) ? $item['registered_by_full_name'] : $item['registered_by_username'];
                                echo htmlspecialchars($display_name ?? 'Usuário Removido');
                                ?>
                            </td>
                            <td class="actions-cell home-actions-cell">
                                <div class="actions-wrapper">
                                    <i class="fas fa-search action-icon action-view-description" 
                                       data-tooltip="Ver Descrição"
                                       data-description="<?php echo htmlspecialchars($item['description'] ?? ''); ?>" 
                                       data-itemid="<?php echo htmlspecialchars($item['id']); ?>"></i>

                                    <?php if ($item['status'] === 'Pendente' && $current_user_is_admin): ?>
                                        <a href="admin/edit_item_page.php?id=<?php echo htmlspecialchars($item['id']); ?>" 
                                           class="action-icon" 
                                           data-tooltip="Editar">
                                            <i class="fas fa-pen-to-square"></i>
                                        </a>
                                        <i class="fas fa-trash action-icon action-icon-delete action-delete-item" 
                                           data-tooltip="Excluir" 
                                           data-item-id="<?php echo htmlspecialchars($item['id']); ?>"></i>

                                    <?php elseif ($item['status'] === 'Devolvido' && !empty($item['devolution_document_id'])): ?>
                                        <a href="manage_devolutions.php?view_id=<?php echo htmlspecialchars($item['devolution_document_id']); ?>" 
                                           class="action-icon" 
                                           data-tooltip="Ver Termo de Devolução">
                                            <i class="fas fa-file-lines"></i>
                                        </a>
                                    <?php elseif ($item['status'] === 'Doado' && !empty($item['donation_document_id'])): ?>
                                        <a href="manage_donations.php?view_id=<?php echo htmlspecialchars($item['donation_document_id']);?>" 
                                           class="action-icon" 
                                           data-tooltip="Ver Termo de Doação">
                                            <i class="fas fa-file-lines"></i>
                                        </a>
                                    <?php else: ?>
                                        <span style="color: #ccc;">---</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p class="info-message">Nenhum item encontrado com os filtros atuais.</p>
        <?php endif; ?>
    </div>

    <div id="table-footer" class="table-footer-container" style="display: flex; justify-content: space-between; align-items: center; margin-top: 15px;">
        <div id="item-count-container" class="item-count-info">
            <?php if (!empty($items)): ?>
                <span style="font-size: 0.9em; color: #555;">Exibindo <strong><?php echo count($items); ?></strong> de <strong><?php echo $total_items; ?></strong> itens</span>
            <?php endif; ?>
        </div>
        <div id="pagination-bottom" class="pagination pagination-bottom-right">
             <?php
            if (!empty($items) && $total_pages > 1) {
                if ($current_page > 1) echo '<a href="'.$base_url.'&page=1" class="pagination-link">Primeira Página</a>'; else echo '<span class="pagination-link disabled">Primeira Página</span>';
                foreach ($pages_to_render as $page_item) { if ($page_item === '...') echo '<span class="pagination-dots">...</span>'; elseif ($page_item == $current_page) echo '<span class="pagination-link current-page">'.$page_item.'</span>'; else echo '<a href="'.$base_url.'&page='.$page_item.'" class="pagination-link">'.$page_item.'</a>'; }
                if ($current_page < $total_pages) echo '<a href="'.$base_url.'&page='.$total_pages.'" class="pagination-link">Última Página</a>'; else echo '<span class="pagination-link disabled">Última Página</span>';
            }
            ?>
        </div>
    </div>
</div>

<div id="itemDetailModal" class="modal" style="display: none;">
    </div>

<div id="global-tooltip" style="display: none;"></div>

<script>
// Passa as variáveis do PHP para o JS
const initial_php_items = <?php echo json_encode($items); ?>;
const initialTotalItems = <?php echo (int)($total_items ?? 0); ?>;
const initialFiltersActive = <?php echo json_encode($is_filter_active); ?>;
const current_user_is_admin = <?php echo json_encode($current_user_is_admin); ?>;
</script>

<?php require_once 'templates/footer.php'; ?>