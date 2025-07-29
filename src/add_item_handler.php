<?php
mb_internal_encoding('UTF-8'); // Define a codificação interna para funções mb_string
require_once 'auth.php';       // Inclui session_start() através de start_secure_session()
require_once 'db_connect.php';

start_secure_session(); // Garante que a sessão foi iniciada
require_login();        // Redireciona se não estiver logado

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = trim($_POST['name'] ?? '');
    $category_id = filter_input(INPUT_POST, 'category_id', FILTER_VALIDATE_INT);
    $location_id = filter_input(INPUT_POST, 'location_id', FILTER_VALIDATE_INT);
    $found_date = trim($_POST['found_date'] ?? '');
    $description = trim($_POST['description'] ?? '');
    if (empty($description)) {
        $description = null; // Armazena como NULO no BD se estiver vazio
    }
    $user_id = $_SESSION['user_id'];

    // --- VALIDAÇÕES COM MENSAGENS DE SESSÃO ---
    if (empty($name) || $category_id === false || $location_id === false || empty($found_date)) {
        $_SESSION['error_message'] = 'Por favor, preencha todos os campos obrigatórios.';
        header('Location: register_item_page.php');
        exit();
    }
    if (mb_strlen($name) > 255) {
        $_SESSION['error_message'] = 'O nome do item é muito longo (máximo 255 caracteres).';
        header('Location: register_item_page.php');
        exit();
    }
    if (mb_strlen($name) < 3) {
        $_SESSION['error_message'] = 'O nome do item é muito curto (mínimo 3 caracteres).';
        header('Location: register_item_page.php');
        exit();
    }
    if ($description !== null && mb_strlen($description) > 1000) {
        $_SESSION['error_message'] = 'A descrição é muito longa (máximo 1000 caracteres).';
        header('Location: register_item_page.php');
        exit();
    }
    if (!preg_match("/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])$/", $found_date)) {
        $_SESSION['error_message'] = 'O formato da data é inválido.';
        header('Location: register_item_page.php');
        exit();
    }

    // --- GERAÇÃO DO CÓDIGO DE BARRAS ---
    $category_code = '';
    $sql_cat_code = "SELECT code FROM categories WHERE id = ?";
    if ($stmt_cat_code = $conn->prepare($sql_cat_code)) {
        $stmt_cat_code->bind_param("i", $category_id);
        $stmt_cat_code->execute();
        $result_cat_code = $stmt_cat_code->get_result();
        if ($cat_row = $result_cat_code->fetch_assoc()) {
            $category_code = $cat_row['code'];
        }
        $stmt_cat_code->close();
    }
    if (empty($category_code)) {
        $_SESSION['error_message'] = 'Código da categoria não encontrado ou inválido.';
        header('Location: register_item_page.php');
        exit();
    }

    $next_seq_num = 1;
    $sql_seq = "SELECT MAX(CAST(SUBSTRING_INDEX(barcode, CONCAT(?, '-'), -1) AS UNSIGNED)) as max_seq FROM items WHERE category_id = ?";
    if ($stmt_seq = $conn->prepare($sql_seq)) {
        $stmt_seq->bind_param("si", $category_code, $category_id);
        $stmt_seq->execute();
        $result_seq = $stmt_seq->get_result();
        
        // --- INÍCIO DA CORREÇÃO ---
        // 1. Buscamos o resultado da query para a variável $seq_row.
        $seq_row = $result_seq->fetch_assoc();

        // 2. Verificamos se a variável $seq_row não é nula e se a chave 'max_seq' também não é nula.
        // Isso corrige os erros "Undefined variable" e "Trying to access array offset on value of type null".
        if ($seq_row && $seq_row['max_seq'] !== null) {
            $next_seq_num = intval($seq_row['max_seq']) + 1;
        }
        // --- FIM DA CORREÇÃO ---

        $stmt_seq->close();
    } else {
        $_SESSION['error_message'] = 'Erro ao gerar a sequência do código de barras.';
        error_log("SQL Prepare Error (seq_num): " . $conn->error);
        header('Location: register_item_page.php');
        exit();
    }

    $barcode = $category_code . '-' . str_pad($next_seq_num, 6, '0', STR_PAD_LEFT);

    // --- INSERÇÃO NO BANCO DE DADOS ---
    $sql_insert = "INSERT INTO items (name, category_id, location_id, found_date, description, user_id, barcode) VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmt_insert = $conn->prepare($sql_insert);

    if ($stmt_insert === false) {
        $_SESSION['error_message'] = 'Erro interno do servidor ao preparar para salvar o item.';
        error_log("SQL Prepare Error (insert_item): " . $conn->error);
        header('Location: register_item_page.php');
        exit();
    }

    $stmt_insert->bind_param("siissis", $name, $category_id, $location_id, $found_date, $description, $user_id, $barcode);

    if ($stmt_insert->execute()) {
        // SUCESSO! Define a mensagem e redireciona.
        $_SESSION['success_message'] = 'Item cadastrado com sucesso! Código de Barras: ' . htmlspecialchars($barcode);
    } else {
        // FALHA! Define a mensagem de erro e redireciona.
        $_SESSION['error_message'] = 'Falha ao cadastrar o item. Causa provável: Código de barras já existente.';
        error_log("SQL Execute Error (insert_item): " . $stmt_insert->error);
    }
    $stmt_insert->close();

} else {
    // Não é uma requisição POST
    $_SESSION['error_message'] = 'Requisição inválida.';
}

$conn->close();
// Redirecionamento final
header('Location: register_item_page.php');
exit();
?>