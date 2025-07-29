<?php
require_once 'auth.php'; // Includes start_secure_session()
require_once 'db_connect.php';

start_secure_session(); // Certifica-se de que a sessão segura foi iniciada
require_login(); // Allow any logged-in user to access this page initially

$term_id = filter_input(INPUT_GET, 'term_id', FILTER_VALIDATE_INT);
$context = strval($_GET['context'] ?? '');

$term_data = null;
$item_summary_text = 'Nenhum item associado ou erro ao carregar.';
$page_error_message = '';
$voltar_link = ($context === 'approval') ? '/admin/approve_donations_page.php' : '/manage_terms.php';

// Fetch Unidade Name from settings (Mantido da Devolução)
$unidade_nome_setting = 'N/A'; // Default value
if (isset($conn)) { // Check if $conn is already set
    $stmt_settings = $conn->prepare("SELECT unidade_nome FROM settings WHERE config_id = 1");
    if ($stmt_settings) {
        $stmt_settings->execute();
        $result_settings = $stmt_settings->get_result();
        if ($result_settings->num_rows > 0) {
            $setting_row = $result_settings->fetch_assoc();
            if (!empty($setting_row['unidade_nome'])) {
                $unidade_nome_setting = $setting_row['unidade_nome'];
            }
        }
        $stmt_settings->close();
    } else {
        error_log("In view_donation_term_page.php: Failed to prepare statement to fetch unidade_nome from settings: " . $conn->error);
    }
} else {
    error_log("In view_donation_term_page.php: Database connection \$conn is not available for fetching settings.");
}


if (!$term_id || $term_id <= 0) {
    $_SESSION['manage_terms_page_message'] = 'ID do termo de doação inválido ou não fornecido.';
    $_SESSION['manage_terms_page_message_type'] = 'error';
    header('Location: ' . $voltar_link);
    exit();
}

// Fetch donation term details, joining with companies if company_id is present
$sql_term = "SELECT
                dt.*,
                u.username AS registered_by_by_username,
                cmp.name AS company_name,
                cmp.cnpj AS company_cnpj,
                cmp.ie AS company_ie,
                cmp.responsible_name AS company_responsible_name,
                cmp.phone AS company_phone,
                cmp.email AS company_email,
                cmp.address_street AS company_address_street,
                cmp.address_number AS company_address_number,
                cmp.address_complement AS company_address_complement,
                cmp.address_neighborhood AS company_address_neighborhood,
                cmp.address_city AS company_address_city,
                cmp.address_state AS company_address_state,
                cmp.address_cep AS company_address_cep,
                cmp.observations AS company_observations
              FROM donation_terms dt
              LEFT JOIN users u ON dt.user_id = u.id
              LEFT JOIN companies cmp ON dt.company_id = cmp.id
              WHERE dt.term_id = ?";
$stmt_term = $conn->prepare($sql_term);

if ($stmt_term) {
    $stmt_term->bind_param("i", $term_id);
    if ($stmt_term->execute()) {
        $result_term = $stmt_term->get_result();
        if ($result_term->num_rows === 1) {
            $term_data = $result_term->fetch_assoc();

            // Use company data if available, otherwise fallback to legacy fields
            $term_data['display_institution_name'] = !empty($term_data['company_id']) ? $term_data['company_name'] : $term_data['institution_name'];
            $term_data['display_cnpj'] = !empty($term_data['company_id']) ? $term_data['company_cnpj'] : ($term_data['institution_cnpj'] ?? 'N/A');
            $term_data['display_ie'] = !empty($term_data['company_id']) ? ($term_data['company_ie'] ?? 'N/A') : ($term_data['institution_ie'] ?? 'N/A');
            $term_data['display_responsible_name'] = !empty($term_data['company_id']) ? $term_data['company_responsible_name'] : $term_data['institution_responsible_name'];
            $term_data['display_phone'] = !empty($term_data['company_id']) ? $term_data['company_phone'] : ($term_data['institution_phone'] ?? 'N/A');

            if (!empty($term_data['company_id'])) {
                $address_parts_display = [
                    htmlspecialchars($term_data['company_address_street'] ?? ''),
                    htmlspecialchars($term_data['company_address_number'] ?? ''),
                    htmlspecialchars($term_data['company_address_complement'] ?? ''),
                    htmlspecialchars($term_data['company_address_neighborhood'] ?? ''),
                    htmlspecialchars($term_data['company_address_city'] ?? ''),
                    htmlspecialchars($term_data['company_address_state'] ?? ''),
                    htmlspecialchars($term_data['company_address_cep'] ?? '')
                ];
            } else { // Fallback to legacy address fields
                    $address_parts_display = [
                    htmlspecialchars($term_data['institution_address_street'] ?? ''),
                    htmlspecialchars($term_data['institution_address_number'] ?? ''),
                    htmlspecialchars($term_data['institution_address_bairro'] ?? ''),
                    htmlspecialchars($term_data['institution_address_cidade'] ?? ''),
                    htmlspecialchars($term_data['institution_address_estado'] ?? ''),
                    htmlspecialchars($term_data['institution_address_cep'] ?? '')
                ];
            }
            $term_data['display_address'] = implode(', ', array_filter($address_parts_display));
            if (empty(trim($term_data['display_address']))) {
                $term_data['display_address'] = 'N/A';
            }


            if ($context === 'approval') {
                if ($term_data['status'] !== 'Aguardando Aprovação') {
                    $page_error_message = "Este termo (ID: " . htmlspecialchars($term_id) . ") não está 'Aguardando Aprovação'. Status Atual: " . htmlspecialchars($term_data['status']) . ". Ações de aprovação não são aplicáveis.";
                }
            }
        } else {
            $page_error_message = "Termo de doação com ID " . htmlspecialchars($term_id) . " não encontrado.";
        }
    } else {
        error_log("Error executing term fetch for view_donation_term_page (Term ID: $term_id): " . $stmt_term->error);
        $page_error_message = "Erro ao buscar dados do termo de doação. Tente novamente.";
    }
    $stmt_term->close();
} else {
    error_log("Error preparing term fetch for view_donation_term_page: " . $conn->error);
    $page_error_message = "Erro crítico ao preparar a busca dos dados do termo. Contacte o suporte.";
}

if ($term_data) {
    // Sumário dos itens
    $item_summary_parts = [];
    $sql_summary = "SELECT c.name AS category_name, COUNT(dti.item_id) AS item_count
                      FROM donation_term_items dti
                      JOIN items i ON dti.item_id = i.id
                      JOIN categories c ON i.category_id = c.id
                      WHERE dti.term_id = ?
                      GROUP BY c.name
                      ORDER BY c.name ASC";

    $stmt_summary = $conn->prepare($sql_summary);
    if ($stmt_summary) {
        $stmt_summary->bind_param("i", $term_data['term_id']);
        if ($stmt_summary->execute()) {
            $result_summary_items = $stmt_summary->get_result();
            while ($summary_item = $result_summary_items->fetch_assoc()) {
                $item_summary_parts[] = htmlspecialchars($summary_item['item_count']) . "x " . htmlspecialchars($summary_item['category_name']);
            }
            if (!empty($item_summary_parts)) {
                $item_summary_text = implode(', ', $item_summary_parts) . ".";
            } else {
                $item_summary_text = "Nenhum item individual listado para este termo.";
            }
        } else {
            error_log("Error executing item summary for view_donation_term_page (Term ID: {$term_data['term_id']}): " . $stmt_summary->error);
            $item_summary_text = 'Erro ao carregar o resumo dos itens.';
        }
        $stmt_summary->close();
    } else {
        error_log("Error preparing item summary for view_donation_term_page: " . $conn->error);
        $item_summary_text = 'Erro crítico ao preparar o resumo dos itens.';
    }
}

$can_approve_decline = (isset($_SESSION['user_role']) && ($_SESSION['user_role'] === 'superAdmin' || $_SESSION['user_role'] === 'admin-aprovador'));

require_once 'templates/header.php';
?>

<style>
    /* Estilos para impressão - PADRONIZADOS COM DEVOLUÇÃO */
    @media print {
        /* Reset de margens e paddings para o HTML e o corpo */
        html, body {
            margin: 0 !important;
            padding: 0 !important;
            width: 100%;
            height: 100%;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif !important;
            font-size: 9.5pt; /* Tamanho da fonte padrão para impressão */
            overflow: hidden;
            box-sizing: border-box;
            -webkit-print-color-adjust: exact !important;
            color-adjust: exact !important;
            color: #333333 !important; /* Cor padrão do texto */
            background-color: #ffffff !important; /* Fundo branco */
        }

        /* Define o tamanho da página e margens para A4 */
        @page {
            size: A4 portrait;
            margin: 0.5cm !important;
        }

        /* Oculta elementos que não devem ser impressos */
        header, footer, .navbar, .header-nav, #main-footer, .button-primary, .button-secondary,
        .form-filters, .admin-table, .error-message, .success-message, .info-message,
        nav, .term-actions, .approval-actions, #reprovalReasonModal, .no-print {
            display: none !important;
        }

        /* Container principal da página */
        .container.view-term-container {
            display: block !important;
            width: 100% !important;
            max-width: none !important;
            margin: 0 !important;
            padding: 0.3cm !important;
            box-sizing: border-box;
            max-height: 28.7cm;
            overflow: hidden;
            background-color: transparent !important;
            color: inherit !important;
            border: none !important;
            box-shadow: none !important;
        }

        /* Ajustes de Títulos */
        h3 {
            margin-top: 0 !important;
            margin-bottom: 0.3em !important;
            font-size: 1em !important;
            text-align: center;
            page-break-after: avoid;
            color: #007bff !important;
        }
        h4 {
            margin-top: 0.5em !important;
            margin-bottom: 0.2em !important;
            font-size: 1em !important;
            text-align: center;
            page-break-after: avoid;
            color: #007bff !important;
        }
        h5 {
            margin-top: 0.3em !important;
            margin-bottom: 0.3em !important;
            font-size: 0.9em !important;
            text-align: center;
            font-weight: bold;
            page-break-after: avoid;
            color: #007bff !important;
        }

        /* Ajustes para parágrafos */
        p {
            margin-bottom: 0.1em !important;
            line-height: 1.2;
            font-size: 0.9em;
            page-break-inside: avoid;
            color: inherit !important;
        }

        /* Labels e valores */
        .term-label { font-weight: bold; color: inherit !important; }
        .term-value { color: inherit !important; }

        /* Caixa de declaração */
        .donation-declaration {
            margin-top: 1.5em !important;
            margin-bottom: 1.5em !important;
            padding: 8px !important;
            border: 1px solid #c0c0c0 !important;
            background-color: #f8f8f8 !important;
            color: #333333 !important;
            font-size: 0.85em;
            line-height: 1.3;
            page-break-inside: avoid;
        }

        /* Seções genéricas do termo - Abordagem robusta com pseudo-elemento */
        .term-section {
            margin-bottom: 1.5em !important;   /* Espaçamento para acomodar a linha */
            padding-bottom: 0.5em !important;
            border: none !important; /* Remove qualquer borda */
            position: relative; /* Necessário para posicionar o pseudo-elemento 'after' */
            page-break-inside: avoid;
        }

        /* A LINHA HORIZONTAL - desenhada com um pseudo-elemento para máxima compatibilidade */
        .term-section::after {
            content: "";
            display: block;
            position: absolute;
            bottom: -0.8em; /* Posição da linha no espaço criado pela margem */
            left: 0;
            width: 100%;
            height: 1px;
            background-color: #b0b0b0 !important; /* Cor da linha, visível na impressão */
        }

        /* Evita que a última seção (assinatura) tenha uma linha embaixo dela */
        .term-section:last-of-type::after {
            display: none !important;
        }

        /* Assinatura - SELETOR ESPECÍFICO PARA REMOÇÃO DA BORDA */
        .view-term-container .signature-image {
            border: none !important;
            max-width: 60% !important;
            height: auto;
            display: block;
            margin: 10px auto 15px auto !important;
            page-break-inside: avoid;
            padding: 3px;
        }
    }
</style>

<div class="container view-term-container">
    <?php if (!empty($page_error_message)): ?>
        <h3 class="no-print">Erro ao Visualizar Termo</h3>
        <p class="error-message no-print"><?php echo htmlspecialchars($page_error_message); ?></p>
        <p class="no-print"><a href="<?php echo htmlspecialchars($voltar_link); ?>" class="button-secondary">Voltar para Termos</a></p>

    <?php elseif ($term_data): ?>

        <h3>Detalhes do Termo de Doação #<?php echo htmlspecialchars($term_data['term_id']); ?></h3>

        <div class="term-section">
            <h4>Dados da Doação</h4>
            <p><strong class="term-label">ID do Termo:</strong> <span class="term-value"><?php echo htmlspecialchars($term_data['term_id']); ?></span></p>
            <p>
                <strong class="term-label">Data e Hora da Doação (Registro do Termo):</strong>
                <span class="term-value">
                    <?php
                        $created_datetime = DateTime::createFromFormat('Y-m-d H:i:s', $term_data['created_at']);
                        echo htmlspecialchars($created_datetime ? $created_datetime->format('d/m/Y H:i:s') : 'Data Inválida');
                        echo " (Evento em: ";
                        $donation_datetime_str = $term_data['donation_date'] . ' ' . $term_data['donation_time'];
                        if (strlen($term_data['donation_time']) == 5) $donation_datetime_str .= ':00';
                        $donation_datetime = DateTime::createFromFormat('Y-m-d H:i:s', $donation_datetime_str);
                        echo htmlspecialchars($donation_datetime ? $donation_datetime->format('d/m/Y H:i') : 'Data/Hora do Evento Inválida');
                        echo ")";
                    ?>
                </span>
            </p>
            <p><strong class="term-label">Responsável pela Doação (Sistema):</strong> <span class="term-value"><?php echo htmlspecialchars($term_data['responsible_donation']); ?></span></p>
            <p><strong class="term-label">Registrado Por (Usuário):</strong> <span class="term-value"><?php echo htmlspecialchars($term_data['registered_by_username'] ?? 'N/A'); ?></span></p>
            <p><strong class="term-label">Status do Termo:</strong> <span class="term-value"><?php echo htmlspecialchars($term_data['status']); ?></span></p>
        </div>

        <div class="term-section">
            <h4>Instituição Recebedora</h4>
            <p><strong class="term-label">Nome da Instituição:</strong> <span class="term-value"><?php echo htmlspecialchars($term_data['display_institution_name']); ?></span></p>
            <p><strong class="term-label">CNPJ:</strong> <span class="term-value"><?php echo htmlspecialchars($term_data['display_cnpj']); ?></span></p>
            <p><strong class="term-label">IE (Inscrição Estadual):</strong> <span class="term-value"><?php echo htmlspecialchars($term_data['display_ie']); ?></span></p>
            <p><strong class="term-label">Nome do Responsável (Instituição):</strong> <span class="term-value"><?php echo htmlspecialchars($term_data['display_responsible_name']); ?></span></p>
            <p><strong class="term-label">Telefone:</strong> <span class="term-value"><?php echo htmlspecialchars($term_data['display_phone']); ?></span></p>
            <p><strong class="term-label">Endereço:</strong> <span class="term-value"><?php echo $term_data['display_address']; ?></span></p>
            <?php if(!empty($term_data['company_id']) && !empty($term_data['company_email'])): ?>
                <p><strong class="term-label">Email:</strong> <span class="term-value"><?php echo htmlspecialchars($term_data['company_email']); ?></span></p>
            <?php endif; ?>
        </div>

        <div class="term-section">
            <h4>Itens Doados</h4>
            <p><span class="term-value"><?php echo $item_summary_text; ?></span></p>
        </div>

        <div class="donation-declaration">
            <h5>Declaração de Doação</h5>
            <p>
                Declaro, para os devidos fins, que o(s) item(ns) descrito(s) neste termo foi(ram) doado(s) voluntariamente ao setor de Achados e Perdidos - Sesc <?php echo htmlspecialchars($unidade_nome_setting); ?>, e a instituição <?php echo htmlspecialchars($term_data['display_institution_name']); ?> reconhece o recebimento.
            </p>
        </div>

        <div class="term-section">
            <h4>Assinatura do Recebedor</h4>
            <?php if (!empty($term_data['signature_image_path']) && file_exists($term_data['signature_image_path'])): ?>
                <img src="<?php echo htmlspecialchars($term_data['signature_image_path']); ?>" class="signature-image" alt="Assinatura do Recebedor">
            <?php elseif (!empty($term_data['signature_image_path'])): ?>
                <p class="error-message no-print">Imagem da assinatura não encontrada em: <?php echo htmlspecialchars($term_data['signature_image_path']); ?></p>
            <?php else: ?>
                <p><em>Nenhuma assinatura registrada para este termo.</em></p>
            <?php endif; ?>
        </div>

        <?php if ($context === 'approval' && isset($term_data['status']) && $term_data['status'] === 'Aguardando Aprovação' && $can_approve_decline): ?>
            <form id="approvalForm" action="/admin/process_donation_approval_handler.php" method="POST" class="no-print" style="display: none;">
                <input type="hidden" name="term_id" value="<?php echo htmlspecialchars($term_data['term_id']); ?>">
            </form>
            <div class="term-actions approval-actions no-print" style="margin-top: 20px; padding-top:20px; border-top: 1px solid #eee; display:flex; gap:10px; justify-content:center;">
                <button type="button" class="button-primary" onclick="if(confirm('Tem certeza que deseja APROVAR este termo de doação?')) { document.getElementById('approvalForm').submit(); }">Aprovar Termo</button>
                <button type="button" id="openReprovalModalButton" class="button-delete">Reprovar Termo</button>
            </div>
        <?php endif; ?>
        <div class="term-actions no-print" style="margin-top: 30px; text-align:center;">
            <?php if ($term_data && $term_data['status'] !== 'Reprovado'): ?>
                <button onclick="window.print();" class="button-primary">Imprimir Termo</button>
            <?php endif; ?>
            <a href="<?php echo htmlspecialchars($voltar_link); ?>" class="button-secondary" style="margin-left:10px;">Voltar para Termos</a>
        </div>
    <?php else: ?>
        <?php
            if(empty($page_error_message)) {
                $page_error_message = "Não foi possível carregar os dados do termo de doação ou o status atual não permite a visualização neste contexto.";
            }
        ?>
        <h3 class="no-print">Erro ao Visualizar Termo</h3>
        <p class="error-message no-print"><?php echo htmlspecialchars($page_error_message); ?></p>
        <p class="no-print"><a href="<?php echo htmlspecialchars($voltar_link); ?>" class="button-secondary">Voltar para Termos</a></p>
    <?php endif; ?>
</div>

<div id="reprovalReasonModal" class="modal no-print" style="display: none;">
    <div class="modal-content">
        <span class="modal-close-button" id="closeReprovalModal">&times;</span>
        <h3>Reprovar Termo de Doação</h3>
        <form id="reprovalForm" action="/admin/process_donation_approval_handler.php" method="POST">
            <input type="hidden" name="action" value="decline">
            <input type="hidden" name="term_id" value="<?php echo htmlspecialchars($term_data['term_id'] ?? ''); ?>">
            <div>
                <label for="reproval_reason_text">Motivo da Reprovação (obrigatório):</label>
                <textarea id="reproval_reason_text" name="reproval_reason" rows="4" required style="width: 95%; padding: 8px; border: 1px solid #ccc; border-radius: 4px;"></textarea>
            </div>
            <div class="form-action-buttons-group" style="margin-top:15px; justify-content: flex-end;">
                <button type="button" id="cancelReprovalButton" class="button-secondary">Cancelar</button>
                <button type="submit" class="button-delete">Confirmar Reprovação</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const reprovalModal = document.getElementById('reprovalReasonModal');
    const openModalButton = document.getElementById('openReprovalModalButton');
    const closeModalButton = document.getElementById('closeReprovalModal');
    const cancelReprovalButton = document.getElementById('cancelReprovalButton');
    const reprovalForm = document.getElementById('reprovalForm');

    const currentTermIdForModal = '<?php echo htmlspecialchars($term_data['term_id'] ?? ''); ?>';
    const hiddenTermIdInput = reprovalForm ? reprovalForm.querySelector('input[name="term_id"]') : null;

    if (hiddenTermIdInput && currentTermIdForModal) {
        hiddenTermIdInput.value = currentTermIdForModal;
    }

    if (openModalButton && reprovalModal) {
        openModalButton.addEventListener('click', function() {
            if (hiddenTermIdInput && hiddenTermIdInput.value) {
                reprovalModal.style.display = 'block';
            } else {
                alert("Erro: ID do termo não definido para reprovação.");
            }
        });
    }
    if (closeModalButton && reprovalModal) {
        closeModalButton.addEventListener('click', function() {
            reprovalModal.style.display = 'none';
        });
    }
    if (cancelReprovalButton && reprovalModal) {
        cancelReprovalButton.addEventListener('click', function() {
            reprovalModal.style.display = 'none';
        });
    }

    window.addEventListener('click', function(event) {
        if (event.target == reprovalModal) {
            reprovalModal.style.display = 'none';
        }
    });

    if (reprovalForm) {
        reprovalForm.addEventListener('submit', function(event) {
            const reasonText = document.getElementById('reproval_reason_text');
            if (reasonText && reasonText.value.trim() === '') {
                alert('Por favor, forneça o motivo da reprovação.');
                event.preventDefault();
            }
        });
    }
});
</script>

<?php
require_once 'templates/footer.php';
?>