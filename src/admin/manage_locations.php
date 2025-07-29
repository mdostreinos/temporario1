<?php
// File: src/admin/manage_locations.php
require_once '../auth.php';
require_once '../db_connect.php';
header('Content-Type: text/html; charset=utf-8');
start_secure_session();
require_admin('../index.php');

// Fetch all locations for the list
$locations = [];
$sql_locations = "SELECT id, name FROM locations ORDER BY name ASC";
$result_locations = $conn->query($sql_locations);
if ($result_locations && $result_locations->num_rows > 0) {
    while ($row = $result_locations->fetch_assoc()) {
        $locations[] = $row;
    }
} elseif ($result_locations === false) {
    error_log("SQL Error (fetch_locations): " . $conn->error);
    $_SESSION['page_error_message'] = "Erro ao carregar lista de locais.";
}

require_once '../templates/header.php';
?>

<style>
/* --- Caixa cinza para o formulário de adicionar --- */
#addLocationSection {
    background-color: #f8f9fa;
    border: 1px solid #dee2e6;
    padding: 25px;
    border-radius: 8px;
    margin-bottom: 25px; 
}

#addLocationSection h3 {
    margin-top: 0;
    margin-bottom: 20px;
    text-align: center; /* ✅ Centraliza o título */
}

/* Estilos para alinhar o formulário de adicionar local */
.form-add-location-inline {
    display: flex;
    flex-wrap: wrap;
    align-items: flex-end; 
    gap: 15px; 
    justify-content: center; /* ✅ Garante a centralização do conjunto */
}

/* ✅ CORREÇÃO: Define uma largura fixa em vez de flex-grow para centralizar corretamente */
.form-add-location-inline .form-input-group {
    width: 450px; /* Largura fixa para o campo de nome */
}

.form-add-location-inline input[type="text"] {
    width: 100%;
    height: 38px;
    box-sizing: border-box;
}

/* Faz com que o container do botão não cresça, mantendo seu tamanho natural */
.form-add-location-inline .form-button-group {
    flex-grow: 0;
}

.form-add-location-inline button {
    height: 38px;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-weight: bold; /* ✅ Deixa o texto do botão em negrito via CSS */
}

/* ESTILOS PARA OS BOTÕES DE AÇÃO NA TABELA */
.admin-table .actions-cell {
    display: flex;
    gap: 8px;
}

.admin-table .button-edit,
.admin-table .button-delete {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 5px;
    padding: 6px 12px;
    font-size: 0.9em;
    font-weight: bold;
    border: none;
    border-radius: 5px;
    color: white;
    cursor: pointer;
    transition: background-color 0.2s;
}

.admin-table .button-edit {
    background-color: #007bff;
}
.admin-table .button-edit:hover {
    background-color: #0056b3;
}

.admin-table .button-delete {
    background-color: #dc3545; 
}
.admin-table .button-delete:hover {
    background-color: #c82333;
}

/* Novo estilo para o hover das linhas da tabela */
.admin-table tbody tr:hover {
    background-color: #FFFEB3; /* Amarelo FFFEB3 */
    cursor: pointer; /* Indica que a linha é interativa */
}
</style>

<div class="container admin-container">
    <h2>Gerenciar Locais de Achados</h2>

    <?php
    // Display messages from redirects
    if (isset($_GET['success'])) {
        $success_messages = [
            'loc_added' => 'Novo local adicionado com sucesso!',
            'loc_updated' => 'Local atualizado com sucesso!',
            'loc_deleted' => 'Local excluído com sucesso!',
        ];
        if (isset($success_messages[$_GET['success']])) {
            echo '<p class="success-message">' . htmlspecialchars($success_messages[$_GET['success']]) . '</p>';
        }
    }
    if (isset($_GET['error'])) {
        $error_messages = [
            'emptyfields_addloc' => 'Nome é obrigatório para adicionar local.',
            'loc_exists' => 'Um local com este Nome já existe.',
            'add_loc_failed' => 'Falha ao adicionar novo local.',
            'emptyfields_editloc' => 'Nome é obrigatório para editar local.',
            'loc_exists_edit' => 'Outro local com este Nome já existe.',
            'edit_loc_failed' => 'Falha ao atualizar local.',
            'invalid_action' => 'Ação inválida especificada.',
            'invalid_id_delete' => 'ID inválido para exclusão.',
            'loc_in_use' => 'Este local está em uso por um ou mais itens e não pode ser excluído.',
            'loc_not_found_delete' => 'Local não encontrado para exclusão.',
            'delete_loc_failed' => 'Falha ao excluir local.',
        ];
        $error_key = $_GET['error'];
        $display_message = $error_messages[$error_key] ?? 'Ocorreu um erro desconhecido.';
        echo '<p class="error-message">' . htmlspecialchars($display_message) . '</p>';
    }
    if (isset($_GET['message']) && $_GET['message'] == 'loc_nochange') {
        echo '<p class="info-message">Nenhuma alteração detectada no local.</p>';
    }
    if (isset($_SESSION['page_error_message'])) {
        echo '<p class="error-message">' . htmlspecialchars($_SESSION['page_error_message']) . '</p>';
        unset($_SESSION['page_error_message']);
    }
    ?>

    <div id="addLocationSection">
        <h3>Adicionar Novo Local</h3>
        <form action="location_handler.php" method="POST" class="form-admin form-add-location-inline">
            <input type="hidden" name="action" value="add_location">
            <div class="form-input-group">
                <label for="name_add_loc">Nome do Local</label>
                <input type="text" id="name_add_loc" name="name" required>
            </div>
            <div class="form-button-group">
                <button type="submit" class="button-primary">
                    <i class="fa-solid fa-plus"></i>
                    <strong>Adicionar Local</strong>
                </button>
            </div>
        </form>
    </div>

    <hr> 
    
    <div id="editLocationSection" style="display:none;"> 
        <h3>Editar Local</h3>
        <form action="location_handler.php" method="POST" class="form-admin">
            <input type="hidden" name="action" value="edit_location">
            <input type="hidden" id="edit_location_id" name="id">
            <div>
                <label for="name_edit_loc">Nome do Local:</label>
                <input type="text" id="name_edit_loc" name="name" required>
            </div>
            <div class="form-action-buttons-group">
                <button type="button" class="button-secondary" onclick="hideEditForm('editLocationSection', 'addLocationSection')">Cancelar</button>
                <button type="submit" class="button-primary">Salvar Alterações</button>
            </div>
        </form>
    </div>

    <h3>Lista de Locais</h3>
    <?php if (!empty($locations)): ?>
    <table class="admin-table">
        <colgroup>
            <col style="width: 80px;">
            <col style="width: auto;">
            <col style="width: 220px;">
        </colgroup>
        <thead>
            <tr>
                <th>ID</th>
                <th>Nome</th>
                <th>Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($locations as $location): ?>
            <tr>
                <td><?php echo htmlspecialchars($location['id']); ?></td>
                <td><?php echo htmlspecialchars($location['name']); ?></td>
                <td class="actions-cell">
                    <button type="button" class="button-edit" onclick="populateEditLocationForm(<?php echo htmlspecialchars($location['id']); ?>)"><i class="fa-solid fa-edit"></i> Editar</button>
                    <button type="button" class="button-delete" onclick="confirmDeleteLocation(<?php echo htmlspecialchars($location['id']); ?>)"><i class="fa-solid fa-trash"></i> Excluir</button>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php else: ?>
    <p>Nenhum local encontrado.</p>
    <?php endif; ?>
</div>

<script>
function populateEditLocationForm(locationId) {
    fetch(`location_handler.php?action=get_location&id=${locationId}`)
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            document.getElementById('edit_location_id').value = data.data.id;
            document.getElementById('name_edit_loc').value = data.data.name;
            document.getElementById('editLocationSection').style.display = 'block';
            document.getElementById('addLocationSection').style.display = 'none'; 
            window.scrollTo(0, document.getElementById('editLocationSection').offsetTop - 20); 
        } else {
            alert('Erro ao buscar dados do local: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Fetch error:', error);
        alert('Ocorreu um erro de comunicação ao buscar dados do local.');
    });
}

function hideEditForm(formToHideId, formToShowId) {
    document.getElementById(formToHideId).style.display = 'none';
    if (formToShowId) {
        document.getElementById(formToShowId).style.display = 'block';
    }
}

function confirmDeleteLocation(locationId) {
    if (confirm('Tem certeza que deseja excluir este local? Esta ação não pode ser desfeita.')) {
        window.location.href = `location_handler.php?action=delete_location&id=${locationId}`;
    }
}
</script>

<?php
if (isset($conn) && $conn instanceof mysqli) {
    // $conn->close();
}
require_once '../templates/footer.php';
?>