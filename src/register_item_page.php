<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once 'auth.php';
require_once 'db_connect.php';
require_login('index.php?error=pleaselogin');

// Busca de categorias e locais (lógica original mantida)
$categories = [];
$sql_cats = "SELECT id, name FROM categories ORDER BY name ASC";
if ($conn) {
    $result_cats = $conn->query($sql_cats);
    if ($result_cats) {
        while ($row = $result_cats->fetch_assoc()) {
            $categories[] = $row;
        }
    }
}
$locations = [];
$sql_locs = "SELECT id, name FROM locations ORDER BY name ASC";
if ($conn) {
    $result_locs = $conn->query($sql_locs);
    if ($result_locs) {
        while ($row = $result_locs->fetch_assoc()) {
            $locations[] = $row;
        }
    }
}

require_once 'templates/header.php';
?>

<style>
    .form-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 22px;
        align-items: start;
    }
    .form-grid .full-width {
        grid-column: 1 / -1;
    }
    .form-field {
        display: flex;
        flex-direction: column;
    }
    .form-field label {
        margin-bottom: 5px;
        font-weight: bold;
    }
    .form-field input, .form-field textarea, .form-field select {
        width: 100%;
        box-sizing: border-box;
    }
    .select2-container .select2-selection--single {
        height: 38px !important;
        padding: 5px 0;
    }
    .select2-container--default .select2-selection--single .select2-selection__rendered {
        line-height: 28px !important;
    }
    .select2-container--default .select2-selection--single .select2-selection__arrow {
        height: 36px !important;
    }
    /* Estilos para as mensagens de alerta */
    .alert {
        padding: 15px;
        margin-bottom: 20px;
        border: 1px solid transparent;
        border-radius: 4px;
    }
    .alert-success {
        color: #155724;
        background-color: #d4edda;
        border-color: #c3e6cb;
    }
    .alert-danger {
        color: #721c24;
        background-color: #f8d7da;
        border-color: #f5c6cb;
    }
</style>

<div class="container register-item-container">
    <h2>Cadastrar Novo Item Encontrado</h2>

    <?php
    // --- BLOCO PARA EXIBIR MENSAGENS DA SESSÃO ---
    if (isset($_SESSION['success_message'])) {
        echo '<div class="alert alert-success">' . $_SESSION['success_message'] . '</div>';
        unset($_SESSION['success_message']); // Limpa a mensagem após exibir
    }
    if (isset($_SESSION['error_message'])) {
        echo '<div class="alert alert-danger">' . $_SESSION['error_message'] . '</div>';
        unset($_SESSION['error_message']); // Limpa a mensagem após exibir
    }
    ?>

    <form action="add_item_handler.php" method="POST">
        <div class="form-grid">
            <div class="form-field">
                <label for="name">Nome do item:</label>
                <input type="text" id="name" name="name" required>
            </div>
            <div class="form-field">
                <label for="found_date">Data do achado:</label>
                <input type="date" id="found_date" name="found_date" required value="<?php echo date('Y-m-d'); ?>">
            </div>

            <div class="form-field">
                <label for="category_id">Categoria:</label>
                <select id="category_id" name="category_id" required style="width: 100%;">
                    <option></option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?php echo htmlspecialchars($category['id']); ?>">
                            <?php echo htmlspecialchars($category['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-field">
                <label for="location_id">Local onde foi encontrado:</label>
                <select id="location_id" name="location_id" required style="width: 100%;">
                    <option></option>
                    <?php foreach ($locations as $location): ?>
                        <option value="<?php echo htmlspecialchars($location['id']); ?>">
                            <?php echo htmlspecialchars($location['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-field full-width">
                <label for="description">Descrição (opcional):</label>
                <textarea id="description" name="description" rows="4"></textarea>
            </div>

            <div class="form-field full-width">
                <button type="submit" class="button-primary">Cadastrar Item</button>
            </div>
        </div>
    </form>
</div>

<?php
require_once 'templates/footer.php';
?>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
$(document).ready(function() {
    $('#category_id').select2({
        placeholder: "Pesquise ou selecione uma categoria",
        allowClear: true
    });

    $('#location_id').select2({
        placeholder: "Pesquise ou selecione um local",
        allowClear: true
    });
});
</script>