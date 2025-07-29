<?php
// Bloco único de PHP para toda a lógica antes do HTML
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../db_connect.php';

// 1. Inicia a sessão e verifica a permissão
start_secure_session();
require_super_admin();

// Array com os estados brasileiros
$brazilian_states = [
    'AC' => 'Acre', 'AL' => 'Alagoas', 'AP' => 'Amapá', 'AM' => 'Amazonas',
    'BA' => 'Bahia', 'CE' => 'Ceará', 'DF' => 'Distrito Federal', 'ES' => 'Espírito Santo',
    'GO' => 'Goiás', 'MA' => 'Maranhão', 'MT' => 'Mato Grosso', 'MS' => 'Mato Grosso do Sul',
    'MG' => 'Minas Gerais', 'PA' => 'Pará', 'PB' => 'Paraíba', 'PR' => 'Paraná',
    'PE' => 'Pernambuco', 'PI' => 'Piauí', 'RJ' => 'Rio de Janeiro', 'RN' => 'Rio Grande do Norte',
    'RS' => 'Rio Grande do Sul', 'RO' => 'Rondônia', 'RR' => 'Roraima', 'SC' => 'Santa Catarina',
    'SP' => 'São Paulo', 'SE' => 'Sergipe', 'TO' => 'Tocantins'
];
asort($brazilian_states);

// 2. Lógica da página (buscar dados do banco)
function get_all_settings($conn) {
    $stmt = $conn->prepare("SELECT * FROM settings WHERE config_id = 1");
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        return $result->fetch_assoc();
    }
    return [
        'unidade_nome' => '', 'cnpj' => '', 'endereco_rua' => '',
        'endereco_numero' => '', 'endereco_bairro' => '', 'endereco_cidade' => '',
        'endereco_estado' => '', 'endereco_cep' => ''
    ];
}

$current_settings = get_all_settings($conn);

// Aplica máscaras para exibição, se os dados existirem
if (!empty($current_settings['cnpj'])) {
    $v = preg_replace('/\D/', '', $current_settings['cnpj']);
    if (strlen($v) == 14) {
        $v = substr($v, 0, 2) . '.' . substr($v, 2, 3) . '.' . substr($v, 5, 3) . '/' . substr($v, 8, 4) . '-' . substr($v, 12, 2);
        $current_settings['cnpj'] = $v;
    }
}
if (!empty($current_settings['endereco_cep'])) {
    $v = preg_replace('/\D/', '', $current_settings['endereco_cep']);
    if (strlen($v) == 8) {
        $v = substr($v, 0, 5) . '-' . substr($v, 5, 3);
        $current_settings['endereco_cep'] = $v;
    }
}

// 3. Header é chamado para começar a "desenhar" a página
require_once __DIR__ . '/../templates/header.php';
?>
<style>
    /* ✅ AJUSTE: Estilos do painel aplicados diretamente no contêiner principal */
    .admin-container {
        max-width: 1024px;
        margin: 2rem auto;
        background-color: #fff;
        border: 1px solid #e9ecef;
        border-radius: 8px;
        padding: 2.5rem; /* Aumenta o padding interno para mais respiro */
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
    }
    
    .admin-container h3 {
        color: #0056b3;
        padding-bottom: 0.5rem;
        border-bottom: 2px solid #e9ecef;
        margin-bottom: 1.5rem;
        margin-top: 1rem;
        font-size: 1.25rem;
    }

    .admin-container h3:first-of-type {
        margin-top: 0;
    }

    .form-modern .form-row {
        display: flex;
        flex-wrap: wrap;
        gap: 24px;
        margin-bottom: 1.25rem;
    }

    .form-modern .form-group_col {
        display: flex;
        flex-direction: column;
        flex-grow: 1;
    }
    
    .form-row .flex-nome { flex: 3; }
    .form-row .flex-cnpj { flex: 2; }
    .form-row .flex-cep { flex: 2; }
    .form-row .flex-rua { flex: 4; }
    .form-row .flex-numero { flex: 1; }
    .form-row .flex-bairro { flex: 2; }
    .form-row .flex-cidade { flex: 2; }
    .form-row .flex-estado { flex: 1; }

    .cep-group {
        display: flex;
        align-items: flex-end;
        gap: 10px;
    }

    .cep-group .form-control {
        flex-grow: 1;
    }

    .cep-group button {
        flex-shrink: 0;
        height: 40px;
        display: inline-flex;
        align-items: center;
    }

    #cep_status {
        font-size: 0.85em;
        color: #6c757d;
        height: 1.2em;
        display: block;
        margin-top: 4px;
    }
    
    .cep-group button i,
    .form-action-buttons-group button i {
        margin-right: 8px;
    }

    .form-action-buttons-group {
        display: flex;
        justify-content: flex-end;
        margin-top: 2rem;
    }
</style>

<div class="container admin-container">
    <h2 style="text-align: center; margin-bottom: 2.5rem;">Configurações do Sistema</h2>

    <?php
    if (isset($_GET['success'])) {
        echo '<p class="success-message">Configurações salvas com sucesso!</p>';
    }
    if (isset($_GET['error'])) {
        $error_message = 'Ocorreu um erro ao salvar as configurações.';
        if (!empty($_SESSION['settings_error_message'])) {
            $error_message = htmlspecialchars($_SESSION['settings_error_message']);
            unset($_SESSION['settings_error_message']);
        } elseif ($_GET['error'] === 'validation') {
            $error_message = 'Erro de validação. Verifique os campos.';
        }
        echo '<p class="error-message">' . $error_message . '</p>';
    }
    ?>

    <form action="settings_handler.php" method="POST" class="form-admin form-modern">
        <h3>Dados da Unidade</h3>
        <div class="form-row">
            <div class="form-group_col flex-nome">
                <label for="unidade_nome">Nome da Unidade:</label>
                <input type="text" id="unidade_nome" name="unidade_nome" class="form-control" value="<?php echo htmlspecialchars($current_settings['unidade_nome'] ?? ''); ?>" maxlength="255">
            </div>
            <div class="form-group_col flex-cnpj">
                <label for="cnpj">CNPJ:</label>
                <input type="text" id="cnpj" name="cnpj" class="form-control" value="<?php echo htmlspecialchars($current_settings['cnpj'] ?? ''); ?>" maxlength="18" required>
            </div>
        </div>

        <h3>Endereço Completo</h3>
        <div class="form-row">
            <div class="form-group_col flex-cep">
                <label for="endereco_cep">CEP:</label>
                <div class="cep-group">
                    <input type="text" id="endereco_cep" name="endereco_cep" class="form-control" value="<?php echo htmlspecialchars($current_settings['endereco_cep'] ?? ''); ?>" maxlength="9">
                    <button type="button" id="search_cep_button" class="button-secondary">
                        <i class="fa-solid fa-magnifying-glass"></i> Buscar
                    </button>
                </div>
                <span id="cep_status"></span>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group_col flex-rua">
                <label for="endereco_rua">Rua:</label>
                <input type="text" id="endereco_rua" name="endereco_rua" class="form-control" value="<?php echo htmlspecialchars($current_settings['endereco_rua'] ?? ''); ?>" maxlength="255">
            </div>
            <div class="form-group_col flex-numero">
                <label for="endereco_numero">Número:</label>
                <input type="text" id="endereco_numero" name="endereco_numero" class="form-control" value="<?php echo htmlspecialchars($current_settings['endereco_numero'] ?? ''); ?>" maxlength="10">
            </div>
        </div>

        <div class="form-row">
            <div class="form-group_col flex-bairro">
                <label for="endereco_bairro">Bairro:</label>
                <input type="text" id="endereco_bairro" name="endereco_bairro" class="form-control" value="<?php echo htmlspecialchars($current_settings['endereco_bairro'] ?? ''); ?>" maxlength="100">
            </div>
            <div class="form-group_col flex-cidade">
                <label for="endereco_cidade">Cidade:</label>
                <input type="text" id="endereco_cidade" name="endereco_cidade" class="form-control" value="<?php echo htmlspecialchars($current_settings['endereco_cidade'] ?? ''); ?>" maxlength="100">
            </div>
            <div class="form-group_col flex-estado">
                <label for="endereco_estado">Estado:</label>
                <select id="endereco_estado" name="endereco_estado" class="form-control">
                    <option value="">Selecione...</option>
                    <?php foreach ($brazilian_states as $uf => $state_name): ?>
                        <option value="<?php echo $uf; ?>" <?php if (($current_settings['endereco_estado'] ?? '') === $uf) echo 'selected'; ?>>
                            <?php echo htmlspecialchars($state_name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="form-action-buttons-group">
            <button type="submit" class="button-primary">
                <i class="fa-solid fa-floppy-disk"></i> Salvar Configurações
            </button>
        </div>
    </form>
</div>

<script>
// Scripts de máscara
document.getElementById('cnpj').addEventListener('input', function (e) {
    let v = e.target.value.replace(/\D/g, '');
    if (v.length > 14) v = v.slice(0, 14);
    v = v.replace(/^(\d{2})(\d)/, '$1.$2');
    v = v.replace(/^(\d{2})\.(\d{3})(\d)/, '$1.$2.$3');
    v = v.replace(/\.(\d{3})(\d)/, '.$1/$2');
    v = v.replace(/(\d{4})(\d)/, '$1-$2');
    e.target.value = v;
});

document.getElementById('endereco_cep').addEventListener('input', function (e) {
    let v = e.target.value.replace(/\D/g, '');
    if (v.length > 8) v = v.slice(0, 8);
    v = v.replace(/^(\d{5})(\d)/, '$1-$2');
    e.target.value = v;
});

document.getElementById('endereco_numero').addEventListener('input', function (e) {
    e.target.value = e.target.value.replace(/\D/g, '');
});

// Script de Busca de CEP
const cepInput = document.getElementById('endereco_cep');
const searchButton = document.getElementById('search_cep_button');
const statusSpan = document.getElementById('cep_status');
const ruaInput = document.getElementById('endereco_rua');
const bairroInput = document.getElementById('endereco_bairro');
const cidadeInput = document.getElementById('endereco_cidade');
const estadoInput = document.getElementById('endereco_estado');
const numeroInput = document.getElementById('endereco_numero');

const searchCep = async () => {
    const cep = cepInput.value.replace(/\D/g, '');

    if (cep.length !== 8) {
        statusSpan.textContent = 'CEP inválido.';
        return;
    }

    statusSpan.textContent = 'Buscando...';
    
    try {
        const response = await fetch(`https://viacep.com.br/ws/${cep}/json/`);
        const data = await response.json();

        if (data.erro) {
            statusSpan.textContent = 'CEP não encontrado.';
            ruaInput.value = '';
            bairroInput.value = '';
            cidadeInput.value = '';
            estadoInput.value = '';
        } else {
            statusSpan.textContent = 'Endereço encontrado!';
            ruaInput.value = data.logradouro;
            bairroInput.value = data.bairro;
            cidadeInput.value = data.localidade;
            estadoInput.value = data.uf;
            numeroInput.value = ''; 
            numeroInput.focus(); 
        }
    } catch (error) {
        statusSpan.textContent = 'Erro na busca. Tente novamente.';
        console.error("Erro ao buscar CEP:", error);
    }
};

cepInput.addEventListener('blur', searchCep);
searchButton.addEventListener('click', searchCep);
</script>

<?php
require_once __DIR__ . '/../templates/footer.php';
?>