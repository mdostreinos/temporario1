<?php
// File: src/admin/manage_users.php
require_once '../auth.php';
require_once '../db_connect.php';

start_secure_session();
require_super_admin('../index.php'); // Redirect se não for super admin

$page_title = "Gerenciamento de Usuários";

// --- BUSCAR DADOS PARA O FILTRO DE USUÁRIOS ---
$all_users_for_filter = [];
$sql_all_users = "SELECT id, username, full_name FROM users ORDER BY full_name, username ASC";
$result_all_users = $conn->query($sql_all_users);
if ($result_all_users) {
    while ($row = $result_all_users->fetch_assoc()) {
        $all_users_for_filter[] = $row;
    }
}
?>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<style>
    /* Caixa de seção reutilizável para agrupar os formulários */
    .admin-section-box {
        background-color: #f8f9fa;
        border: 1px solid #dee2e6;
        border-radius: 8px;
        padding: 15px 24px;
        margin-bottom: 30px;
    }

    .admin-section-box h3 {
        margin-top: 0;
        margin-bottom: 15px;
        text-align: center;
        font-weight: bold;
        color: #495057;
    }

    /* Estilos para o formulário de registro */
    .register-form-row {
        display: flex;
        flex-direction: row;
        align-items: flex-end;
        gap: 15px;
        width: 100%;
    }
    .register-form-row .form-field-group {
        flex: 1;
        display: flex;
        flex-direction: column;
    }
    .register-form-row label {
        text-align: left;
        margin-bottom: 5px;
        font-size: 14px;
        color: #495057;
    }
    .register-form-row input[type="text"],
    .register-form-row input[type="password"],
    .register-form-row select {
        width: 100%;
        box-sizing: border-box;
        padding: 8px;
        height: 38px;
        border: 1px solid #ccc;
        border-radius: 5px;
    }
    .register-form-row button {
        width: 100%;
        padding: 8px 15px;
        box-sizing: border-box;
        height: 38px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
    }


    /* Estilos para o formulário de filtros */
    .form-filters .filter-row {
        width: 100%; display: flex; justify-content: center; align-items: flex-end; gap: 16px; flex-wrap: wrap;
    }
    .filter-buttons-group {
        display: flex; gap: 8px;
    }
    .form-filters .filter-row > div {
        margin-bottom: 0;
    }
    .form-filters select, .form-filters .button-filter, .form-filters .button-filter-clear {
        height: 38px; padding: 0 15px; border: 1px solid #ccc; border-radius: 5px; vertical-align: middle; box-sizing: border-box; font-size: 14px; margin: 0; cursor: pointer;
        display: inline-flex; align-items: center; justify-content: center; gap: 6px;
    }
    .form-filters .button-filter {
        background-color: #007bff; color: white; border-color: #007bff;
    }
    .form-filters .button-filter-clear {
        text-decoration: none; background-color: #6c757d; color: white; border: 1px solid #6c757d;
    }
    .form-filters .button-filter-clear:hover {
        background-color: #5a6268; border-color: #545b62;
    }
    .select2-container {
        width: 100% !important;
    }
    .select2-container .select2-selection--single {
        height: 38px !important; border: 1px solid #ccc !important; border-radius: 5px !important;
    }
    .select2-container--default .select2-selection--single .select2-selection__rendered {
        line-height: 36px !important; padding-left: 15px !important;
    }
    .select2-container--default .select2-selection--single .select2-selection__arrow {
        height: 36px !important;
    }
    .filter-user-wrapper {
        min-width: 300px; width: auto;
    }

    /* ✅ ESTILOS PARA OS BOTÕES DE AÇÃO NA TABELA */
    .user-actions-cell .user-actions-content-wrapper {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
    }
    .user-actions-cell .button-edit,
    .user-actions-cell .button-delete,
    .user-actions-cell .button-secondary {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 5px 10px;
        border-radius: 4px;
        border: none;
        color: white;
        font-weight: bold;
        font-size: 0.9em;
        text-decoration: none;
        cursor: pointer;
    }
    .user-actions-cell .button-edit { background-color: #007bff; }
    .user-actions-cell .button-delete { background-color: #dc3545; }

    /* Novo estilo para o hover das linhas da tabela */
    .admin-table tbody tr:hover {
        background-color: #FFFEB3; /* Amarelo FFFEB3 */
        cursor: pointer; /* Indica que a linha é interativa */
    }
</style>
<?php
// ==================================================================
// LÓGICA DE PAGINAÇÃO
// ==================================================================

const ITEMS_PER_PAGE = 10;

$current_page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT, ['options' => ['default' => 1, 'min_range' => 1]]);
$filter_user_id = filter_input(INPUT_GET, 'filter_user', FILTER_VALIDATE_INT);
$offset = ($current_page - 1) * ITEMS_PER_PAGE;

function render_pagination_links($current_page, $total_pages, $page_param_name, $other_params = []) {
    if ($total_pages <= 1) return;
    $query_params = array_merge($_GET, $other_params);
    unset($query_params[$page_param_name]);
    $range = 2; $pages_to_render = []; $potential_pages = [1, $total_pages];
    for ($i = $current_page - $range; $i <= $current_page + $range; $i++) { if ($i > 1 && $i < $total_pages) $potential_pages[] = $i; }
    sort($potential_pages); $potential_pages = array_unique($potential_pages);
    $prev_page = 0;
    foreach ($potential_pages as $p) { if ($p > $prev_page + 1) $pages_to_render[] = '...'; $pages_to_render[] = $p; $prev_page = $p; }
    $build_url = function($page) use ($query_params, $page_param_name) { $query_params[$page_param_name] = $page; return htmlspecialchars("manage_users.php?" . http_build_query($query_params)); };
    if ($current_page > 1) echo '<a href="'.$build_url(1).'" class="pagination-link">Primeira</a>'; else echo '<span class="pagination-link disabled">Primeira</span>';
    foreach ($pages_to_render as $item) { if ($item === '...') echo '<span class="pagination-dots">...</span>'; elseif ($item == $current_page) echo '<span class="pagination-link current-page">'.$item.'</span>'; else echo '<a href="'.$build_url($item).'" class="pagination-link">'.$item.'</a>'; }
    if ($current_page < $total_pages) echo '<a href="'.$build_url($total_pages).'" class="pagination-link">Última</a>'; else echo '<span class="pagination-link disabled">Última</span>';
}

// ==================================================================
// BUSCA DE DADOS COM PAGINAÇÃO E FILTRO
// ==================================================================
$users = [];
$total_users = 0;

$base_sql = "FROM users";
$conditions = [];
$params = [];
$types = "";

if ($filter_user_id) {
    $conditions[] = "id = ?";
    $params[] = $filter_user_id;
    $types .= "i";
}

$where_clause = !empty($conditions) ? " WHERE " . implode(" AND ", $conditions) : "";

$sql_count = "SELECT COUNT(id) as total " . $base_sql . $where_clause;
$stmt_count = $conn->prepare($sql_count);
if ($stmt_count) {
    if (!empty($types)) {
        $stmt_count->bind_param($types, ...$params);
    }
    $stmt_count->execute();
    $total_users = (int)$stmt_count->get_result()->fetch_assoc()['total'];
    $stmt_count->close();
}

$total_pages = $total_users > 0 ? ceil($total_users / ITEMS_PER_PAGE) : 1;
if ($current_page > $total_pages) { $current_page = $total_pages; }

if ($total_users > 0) {
    $sql_users = "SELECT id, username, full_name, role " . $base_sql . $where_clause . " ORDER BY username ASC LIMIT ? OFFSET ?";
    $types .= "ii";
    $params_with_pagination = array_merge($params, [ITEMS_PER_PAGE, $offset]);
    $stmt_users = $conn->prepare($sql_users);
    if ($stmt_users) {
        if (!empty($types)) {
            $stmt_users->bind_param($types, ...$params_with_pagination);
        }
        $stmt_users->execute();
        $result_users = $stmt_users->get_result();
        $users = $result_users->fetch_all(MYSQLI_ASSOC);
        $stmt_users->close();
    } else {
        $_SESSION['page_error_message'] = "Erro ao preparar a busca de usuários.";
    }
}

require_once '../templates/header.php';
?>

<div class="container admin-container">
    <h2><?php echo htmlspecialchars($page_title); ?></h2>

    <?php
    if (isset($_GET['success'])) {
        $success_messages = ['useradded' => 'Novo usuário adicionado com sucesso!','passwordreset' => 'Senha do usuário redefinida com sucesso!','userupdated' => 'Usuário atualizado com sucesso!','userdeleted' => 'Usuário excluído com sucesso!'];
        if (isset($success_messages[$_GET['success']])) {
            echo '<p class="success-message">' . htmlspecialchars($success_messages[$_GET['success']]) . '</p>';
        }
    }
    if (isset($_SESSION['page_error_message'])) {
        echo '<p class="error-message">' . htmlspecialchars($_SESSION['page_error_message']) . '</p>';
        unset($_SESSION['page_error_message']);
    }
    ?>

    <div class="admin-section-box">
        <h3>Registrar Novo Usuário</h3>
        <form action="user_management_handler.php" method="POST" class="form-admin register-user-form">
            <input type="hidden" name="action" value="register_user">
            <div class="register-form-row">
                <div class="form-field-group">
                    <label for="username_reg">Usuário:</label>
                    <input type="text" id="username_reg" name="username" required>
                </div>
                <div class="form-field-group">
                    <label for="full_name_reg">Nome Completo:</label>
                    <input type="text" id="full_name_reg" name="full_name" maxlength="255">
                </div>
                <div class="form-field-group">
                    <label for="role_reg">Função:</label>
                    <select id="role_reg" name="role" required>
                        <option value="common">Comum</option>
                        <option value="admin">Admin</option>
                        <option value="admin-aprovador">Admin Aprovador</option>
                        <option value="superAdmin">SuperAdmin</option>
                    </select>
                </div>
                <div class="form-field-group">
                    <button type="submit" class="button-primary"><i class="fa-solid fa-plus"></i> Registrar Usuário</button>
                </div>
            </div>
        </form>
    </div>
    
    <h3>Lista de Usuários</h3>

    <form method="GET" action="manage_users.php" class="form-filters admin-section-box">
        <div class="filter-row">
            <div class="filter-user-wrapper">
                <label for="filter_user">Filtrar por Usuário:</label>
                <select id="filter_user" name="filter_user">
                    <option value="">Todos os Usuários</option>
                    <?php foreach ($all_users_for_filter as $user_option): ?>
                        <option value="<?php echo htmlspecialchars($user_option['id']); ?>" <?php if ($filter_user_id == $user_option['id']) echo 'selected'; ?>>
                            <?php echo htmlspecialchars($user_option['full_name'] . ' (' . $user_option['username'] . ')'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-buttons-group">
                <button type="submit" class="button-filter"><i class="fa-solid fa-check"></i> Filtrar</button>
                <a href="manage_users.php" class="button-filter-clear"><i class="fa-solid fa-broom"></i> Limpar</a>
            </div>
        </div>
    </form>

    <?php if (!empty($users)): ?>
    <table class="admin-table">
        <thead>
            <tr>
                <th>ID</th><th>Usuário</th><th>Nome Completo</th><th>Função</th><th>Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($users as $user): ?>
            <tr>
                <td><?php echo htmlspecialchars($user['id']); ?></td>
                <td><?php echo htmlspecialchars($user['username']); ?></td>
                <td><?php echo htmlspecialchars(!empty($user['full_name']) ? $user['full_name'] : 'N/A'); ?></td>
                <td><?php echo htmlspecialchars($user['role']); ?></td>
                <td class="actions-cell user-actions-cell">
                    <div class="user-actions-content-wrapper">
                        <a href="edit_user_page.php?id=<?php echo htmlspecialchars($user['id']); ?>" class="button-edit"><i class="fa-solid fa-edit"></i> Editar</a>
                        
                        <?php if (!($user['username'] === 'admin' && $_SESSION['username'] !== 'admin')): ?>
                            <?php if ($user['username'] !== 'admin' || ($_SESSION['username'] === 'admin' && $user['id'] == $_SESSION['user_id'] ) ): ?>
                                <form action="user_management_handler.php" method="POST" class="form-inline" onsubmit="return confirmPasswordReset(this);">
                                    <input type="hidden" name="action" value="reset_password">
                                    <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($user['id']); ?>">
                                    <input type="password" id="new_password_<?php echo $user['id']; ?>" name="new_password" placeholder="Nova Senha (mín. 6)" required minlength="6" class="form-inline-input">
                                    <button type="submit" class="button-secondary"><i class="fa-solid fa-rotate-right"></i> Resetar</button>
                                </form>
                            <?php else: ?>
                                <small><em>(Reset Indisponível)</em></small>
                            <?php endif; ?>
                        <?php endif; ?>

                        <?php if ($user['username'] !== 'admin' && $user['id'] != $_SESSION['user_id']): ?>
                            <form action="user_management_handler.php" method="POST" onsubmit="return confirm('Tem certeza que deseja excluir este usuário? Esta ação não pode ser desfeita.');">
                                <input type="hidden" name="action" value="delete_user">
                                <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($user['id']); ?>">
                                <button type="submit" class="button-delete"><i class="fa-solid fa-trash"></i> Excluir</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    
    <div class="table-footer-container" style="display: flex; justify-content: space-between; align-items: center; margin-top: 15px;">
        <div class="item-count-info">
            <span>Exibindo <strong><?php echo count($users); ?></strong> de <strong><?php echo $total_users; ?></strong> usuários.</span>
        </div>
        <div class="pagination">
            <?php render_pagination_links($current_page, $total_pages, 'page', []); ?>
        </div>
    </div>

    <?php else: ?>
    <p>Nenhum usuário encontrado com os filtros atuais.</p>
    <?php endif; ?>
</div>

<script>
function confirmPasswordReset(form) {
    const newPassword = form.new_password.value;
    if (newPassword.length < 6) {
        alert("A nova senha deve ter pelo menos 6 caracteres.");
        return false;
    }
    return confirm("Tem certeza que deseja redefinir a senha para este usuário?");
}

$(document).ready(function() {
    $('#filter_user').select2({
        placeholder: "Selecione um usuário para filtrar",
        allowClear: true
    });
});
</script>

<?php
require_once '../templates/footer.php';
?>