<?php
require_once 'auth.php'; // Includes start_secure_session()
require_once 'db_connect.php';

// Ensure session is started (start_secure_session might be called in auth.php)
// If not, uncomment: start_secure_session();

require_admin('../index.php?error=pleaselogin'); // require_login() might be more appropriate if any admin can access

// Line 7 (or around here): Fix for FILTER_SANITIZE_STRING
$item_ids_str = $_GET['item_ids'] ?? ''; // Default to empty string if not set

$page_error_message = $_SESSION['generate_donation_page_error_message'] ?? null;
unset($_SESSION['generate_donation_page_error_message']);

$item_ids = [];
$valid_item_ids_for_donation = [];
$item_summary_by_category = [];
$total_items_for_donation = 0;

if (empty($item_ids_str)) {
    $_SESSION['home_page_error_message'] = "Nenhum item selecionado para doação.";
    header('Location: /home.php');
    exit();
}

$item_ids_array = explode(',', $item_ids_str);
foreach ($item_ids_array as $id_str_loop) { // Renamed $id to $id_str_loop to avoid conflict if register_globals is on (though unlikely)
    $id_int = filter_var(trim($id_str_loop), FILTER_VALIDATE_INT);
    if ($id_int !== false && $id_int > 0) {
        $item_ids[] = $id_int;
    }
}

if (empty($item_ids)) {
    $_SESSION['home_page_error_message'] = "IDs de itens inválidos fornecidos.";
    header('Location: /home.php');
    exit();
}

// Fetch item details (name, category) for valid, 'Pendente' items
if ($conn && !empty($item_ids)) {
    $placeholders = implode(',', array_fill(0, count($item_ids), '?'));
    $sql_items = "SELECT i.id, i.name AS item_name, c.name AS category_name
                  FROM items i
                  JOIN categories c ON i.category_id = c.id
                  WHERE i.id IN ($placeholders) AND i.status = 'Pendente'";

    $stmt_items = $conn->prepare($sql_items);
    if ($stmt_items) {
        $types = str_repeat('i', count($item_ids));
        $stmt_items->bind_param($types, ...$item_ids);
        $stmt_items->execute();
        $result_items = $stmt_items->get_result();

        while ($row = $result_items->fetch_assoc()) {
            $valid_item_ids_for_donation[] = $row['id'];
            if (!isset($item_summary_by_category[$row['category_name']])) {
                $item_summary_by_category[$row['category_name']] = 0;
            }
            $item_summary_by_category[$row['category_name']]++;
            $total_items_for_donation++;
        }
        $stmt_items->close();
    } else {
        error_log("DB Prepare Error (fetch items for donation): " . $conn->error);
        $page_error_message = "Erro ao buscar detalhes dos itens. Tente novamente.";
    }
} else if (!$conn) {
     error_log("DB Connection failed on generate_donation_term_page.");
     $page_error_message = "Erro de conexão com o banco de dados.";
}


if ($total_items_for_donation === 0 && empty($page_error_message)) {
    if (count($item_ids) > 0) { // If some IDs were passed but none were valid/Pendente
        $_SESSION['home_page_error_message'] = "Nenhum dos itens selecionados está disponível para doação (podem não estar 'Pendentes' ou IDs são inválidos).";
    } else { // Should have been caught by earlier checks
         $_SESSION['home_page_error_message'] = "Nenhum item válido para doação.";
    }
    header('Location: /home.php');
    exit();
}

// Sort summary by category name for consistent display
ksort($item_summary_by_category);

$item_summary_display = [];
foreach ($item_summary_by_category as $category => $count) {
    $item_summary_display[] = htmlspecialchars($count) . " - " . htmlspecialchars($category);
}
$item_summary_str = implode(', ', $item_summary_display);


$current_user_name = $_SESSION['username'] ?? 'N/A';
$current_date = date('Y-m-d');
$current_time = date('H:i');

require_once 'templates/header.php';
?>

<div class="container register-item-container">
    <h2>Registrar Termo de Doação</h2>

    <?php if ($page_error_message): ?>
        <p class="error-message"><?php echo htmlspecialchars($page_error_message); ?></p>
    <?php endif; ?>

    <?php if ($total_items_for_donation > 0): ?>
        <div class="data-section-rounded">
            <h4>Itens para Doação</h4>
            <p><?php echo $item_summary_str; ?>.</p>
            <p><strong>Total de itens: <?php echo $total_items_for_donation; ?></strong></p>
        </div>

        <form action="submit_donation_handler.php" method="POST" id="donationForm" class="form-modern">
            <fieldset class="data-section-rounded">
                <legend>Dados da Doação</legend>
                <div class="form-group">
                    <label for="responsible_donation">Responsável pela Doação (Sistema):</label>
                    <input type="text" id="responsible_donation" name="responsible_donation" value="<?php echo htmlspecialchars($current_user_name); ?>" required readonly class="form-control-readonly">
                </div>
                <div class="form-row">
                    <div class="form-group_col">
                        <label for="donation_date">Data da Doação:</label>
                        <input type="date" id="donation_date" name="donation_date" value="<?php echo $current_date; ?>" required class="form-control">
                    </div>
                    <div class="form-group_col">
                        <label for="donation_time">Hora da Doação:</label>
                        <input type="time" id="donation_time" name="donation_time" value="<?php echo $current_time; ?>" required class="form-control">
                    </div>
                </div>
                 <div class="form-row">
                    <div class="form-group_col_full">
                        <label for="company_id">Empresa/Instituição Recebedora: <span class="required-asterisk">*</span></label>
                        <select id="company_id" name="company_id" class="form-control" required style="width: 100%;">
                            <option></option> <?php // Select2 vai preencher ?>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                     <div class="form-group_col_full" id="company_details_preview" style="display:none; background-color: #f0f0f0; padding:10px; border-radius:4px; margin-top:10px;">
                        <strong>Detalhes da Empresa Selecionada:</strong><br>
                        <span id="preview_cnpj"></span><br>
                        <span id="preview_responsible"></span><br>
                        <span id="preview_phone"></span>
                        <span id="preview_address" style="font-size:0.9em; color: #555;"></span>
                    </div>
                </div>
            </fieldset>

            <fieldset class="data-section-rounded">
                <legend>Assinatura do Responsável da Instituição</legend>
                <p>Por favor, o responsável pela instituição deve assinar no quadro abaixo:</p>
                <div id="signaturePadContainer" style="border: 1px solid #ccc; max-width:400px; min-height:150px; height:200px; margin-bottom:10px; position: relative; touch-action: none;">
                    <canvas id="signatureCanvas" style="width: 100%; height: 100%; touch-action: none;"></canvas>
                </div>
                <button type="button" id="clearSignatureButton" class="button-secondary">Limpar Assinatura</button>
                <input type="hidden" name="signature_data" id="signatureDataInput">
            </fieldset>

            <input type="hidden" name="item_ids_for_donation" value="<?php echo htmlspecialchars(implode(',', $valid_item_ids_for_donation)); ?>">

            <div class="form-action-buttons-group" style="margin-top: 20px;">
                <a href="/home.php" class="button-secondary">Cancelar</a>
                <button type="submit" class="button-primary" id="submitDonationButton">Enviar para Aprovação</button>
            </div>
        </form>
    <?php else: ?>
        <p>Não há itens válidos para este termo de doação. Por favor, <a href="/home.php">volte</a> e selecione itens pendentes.</p>
    <?php endif; ?>
</div>

<!-- 1. Load jQuery and Select2 FIRST if not already in footer -->
<!-- Assuming jQuery and Select2 JS are loaded in footer.php or header.php globally -->
<!-- For this specific page, ensure Select2 CSS is available (usually in header.php) -->

<!-- 2. Load SignaturePad library -->
<script src="https://cdn.jsdelivr.net/npm/signature_pad@4.1.7/dist/signature_pad.umd.min.js"></script>

<!-- 3. THEN include your custom script -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    // --- Select2 for Company Selection ---
    $('#company_id').select2({
        placeholder: "Pesquise ou selecione uma empresa",
        allowClear: true,
        ajax: {
            url: '/admin/get_companies_handler.php', // Adjusted path
            dataType: 'json',
            delay: 250, // milliseconds to wait before sending request
            data: function (params) {
                return {
                    q: params.term, // search term
                    page: params.page || 1
                };
            },
            processResults: function (data, params) {
                params.page = params.page || 1;
                return {
                    results: data.results, // `data.results` should be an array of {id: '', text: ''} objects
                    pagination: {
                        more: data.pagination.more // `data.pagination.more` should be true if there are more results
                    }
                };
            },
            cache: true
        },
        minimumInputLength: 0, // Can be 0 to show results on focus, or >0 to require typing
        language: { // Basic localization example
            inputTooShort: function(args) {
                var remainingChars = args.minimum - args.input.length;
                return "Por favor, insira " + remainingChars + " ou mais caracteres";
            },
            noResults: function() {
                return "Nenhuma empresa encontrada";
            },
            searching: function() {
                return "Buscando...";
            },
            errorLoading: function() {
                return "Não foi possível carregar os resultados.";
            }
        }
    }).on('select2:select', function (e) {
        var data = e.params.data;
        if(data && data.id) {
            // Fetch full company details for preview (optional, could also be returned by get_companies_handler)
            // For now, we'll use what's available in `data` if `get_companies_handler` is extended
            // or make another AJAX call if needed.
            // Let's assume `get_companies_handler.php` can be modified to return more details or we make a new one.
            // For simplicity, we'll initially just show basic info from the selection.

            // Simple preview based on current data from get_companies_handler
            $('#preview_cnpj').text('CNPJ: ' + (data.cnpj || 'N/A'));
            // We need responsible name and phone from the company data.
            // This requires get_companies_handler to return more fields or a new AJAX call.
            // For now, let's make a direct call to fetch full details of the selected company.
             $.ajax({
                url: '/admin/get_companies_handler.php', // Re-use or create a specific one like get_company_details.php
                data: { id_exact: data.id }, // Add a parameter to get specific company by ID
                dataType: 'json',
                success: function(companyDetails) {
                    // Assuming get_companies_handler can return a single company if id_exact is passed
                    // and that it returns an object (not an array) or the first element of results.
                    let details = companyDetails.results && companyDetails.results.length > 0 ? companyDetails.results[0] : null;
                    if(details && details.full_data) { // Assuming full_data is where extended info is
                        $('#preview_responsible').text('Responsável: ' + (details.full_data.responsible_name || 'N/A'));
                        $('#preview_phone').text('Telefone: ' + (details.full_data.phone || 'N/A'));
                        let address = [
                            details.full_data.address_street,
                            details.full_data.address_number,
                            details.full_data.address_complement,
                            details.full_data.address_neighborhood,
                            details.full_data.address_city,
                            details.full_data.address_state,
                            details.full_data.address_cep
                        ].filter(Boolean).join(', ');
                        $('#preview_address').text('Endereço: ' + (address || 'N/A'));
                        $('#company_details_preview').show();
                    } else if (data.name) { // Fallback if full_data is not available
                         // If get_companies_handler.php is not modified yet to provide full details for a single ID,
                         // this preview will be limited.
                        $('#preview_responsible').text('Responsável: (Detalhes completos indisponíveis no preview)');
                        $('#preview_phone').text('Telefone: (Detalhes completos indisponíveis no preview)');
                        $('#preview_address').text('');
                        $('#company_details_preview').show();
                    }
                },
                error: function() {
                     $('#preview_responsible').text('Responsável: Erro ao buscar detalhes.');
                     $('#preview_phone').text('');
                     $('#preview_address').text('');
                     $('#company_details_preview').show();
                }
            });
        } else {
            $('#company_details_preview').hide();
        }
    }).on('select2:unselect', function (e) {
        $('#company_details_preview').hide();
        $('#preview_cnpj').text('');
        $('#preview_responsible').text('');
        $('#preview_phone').text('');
        $('#preview_address').text('');
    });


    // --- Input Masking Logic (Removed as it's not needed for this form anymore) ---
    // const cnpjInput = document.getElementById('institution_cnpj'); ... etc.

    // --- Signature Pad Integration ---
    const canvas = document.getElementById('signatureCanvas');
    const signaturePadContainer = document.getElementById('signaturePadContainer');
    let signaturePad = null;

    function resizeCanvas() {
        if (!canvas || !signaturePadContainer) return;
        const ratio =  Math.max(window.devicePixelRatio || 1, 1);

        const containerWidth = signaturePadContainer.offsetWidth;
        if (containerWidth === 0) {
            return;
        }

        canvas.width = containerWidth * ratio;
        canvas.height = signaturePadContainer.offsetHeight * ratio;
        canvas.getContext("2d").scale(ratio, ratio);
        if (signaturePad) {
            signaturePad.clear();
        } else {
            const ctx = canvas.getContext("2d");
            ctx.clearRect(0, 0, canvas.width, canvas.height);
        }
    }

    if (canvas) {
        setTimeout(function() {
            // Check if SignaturePad is loaded
            if (typeof SignaturePad === 'undefined') {
                console.error('SignaturePad library not loaded. Check script path or CDN.');
                // Optionally, display an error message to the user on the page
                const padContainer = document.getElementById('signaturePadContainer');
                if(padContainer) padContainer.innerHTML = '<p class="error-message" style="padding:10px;">Erro ao carregar o campo de assinatura. A biblioteca SignaturePad não foi encontrada.</p>';
                return;
            }

            signaturePad = new SignaturePad(canvas, {
                backgroundColor: 'rgb(255, 255, 255)'
            });
            console.log('Signature Pad initialized:', signaturePad);
            resizeCanvas();

            let resizeTimeout;
            window.addEventListener("resize", function() {
                clearTimeout(resizeTimeout);
                resizeTimeout = setTimeout(resizeCanvas, 250);
            });
        }, 100);

    } else {
        console.error("Signature canvas element not found!");
    }

    const clearSignatureButton = document.getElementById('clearSignatureButton');
    if (clearSignatureButton) {
        clearSignatureButton.addEventListener('click', function() {
            if (signaturePad) {
                signaturePad.clear();
            } else {
                console.error("Attempted to clear non-existent signature pad.");
            }
        });
    }

    const donationForm = document.getElementById('donationForm');
    const signatureDataInput = document.getElementById('signatureDataInput');
    const submitButton = document.getElementById('submitDonationButton');

    if (donationForm && signatureDataInput) {
        donationForm.addEventListener('submit', function(event) {
            if (!signaturePad || signaturePad.isEmpty()) {
                alert("Por favor, forneça a assinatura do responsável da instituição.");
                event.preventDefault();
                if(submitButton) submitButton.disabled = false; // Re-enable button if submission is blocked
                return false;
            }
            // Ensure signature data is captured before form proceeds
            signatureDataInput.value = signaturePad.toDataURL('image/png');
        });
    }

    // More robust submit button disabling logic
    if(donationForm && submitButton) {
        donationForm.addEventListener('submit', function(event) {
            // If the signature check above (or any other client-side validation) fails and calls event.preventDefault(),
            // this part might not be strictly necessary for the disabling effect,
            // but it's a good practice to disable on successful submission start.
            if (!event.defaultPrevented) { // Only disable if submission is not already prevented
                 submitButton.disabled = true;
                 // Optional: Add a message like "Processando..."
                 // submitButton.textContent = 'Processando...';
            }
        });
    }
});
</script>

<?php require_once 'templates/footer.php'; ?>
