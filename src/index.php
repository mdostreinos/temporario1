<?php
// Inclui e inicia a sessão segura ANTES de qualquer outra lógica ou saída.
require_once __DIR__ . '/auth.php';
start_secure_session();

// If user is already logged in, redirect to home.php
if (isset($_SESSION['user_id'])) {
    header('Location: home.php');
    exit();
}

require_once 'templates/header.php';
?>

<div class="container login-container">
    <h2>Login</h2>

    <?php
    if (isset($_GET['error'])) {
        $errorMessage = '';
        switch ($_GET['error']) {
            case 'emptyfields':
                $errorMessage = 'Por favor, preencha usuário e senha.';
                break;
            case 'sqlerror':
                $errorMessage = 'Erro no sistema. Tente novamente mais tarde.';
                break;
            case 'wrongpassword': // Mantido para o caso de 'incorrect_password_for_ad_user'
                $errorMessage = 'Usuário ou senha incorreta.'; // Mensagem mais genérica
                break;
            case 'nouser': // Este erro não deve mais ocorrer se a lógica for AD primeiro.
                               // Será 'ldap_search_failed_or_user_not_found' ou 'user_not_registered_in_app'
                $errorMessage = 'Usuário não encontrado no sistema.';
                break;
            case 'pleaselogin':
                $errorMessage = 'Por favor, faça login para continuar.';
                break;
            case 'usernametoolong':
                $errorMessage = 'Nome de usuário muito longo.';
                break;
            case 'usernametooshort':
                $errorMessage = 'Nome de usuário muito curto.';
                break;
            case 'ad_auth_failed':
                $errorMessage = 'Falha na autenticação com o Active Directory. Verifique seu usuário e senha ou contate o suporte.';
                break;
            case 'user_not_registered_in_app':
                $errorMessage = 'Usuário autenticado no Active Directory, mas não cadastrado na aplicação. Contate o administrador.';
                break;
            case 'ldap_connection_failed':
                $errorMessage = 'Não foi possível conectar ao servidor de autenticação. Tente novamente mais tarde ou contate o suporte.';
                break;
            case 'ldap_search_failed_or_user_not_found':
                $errorMessage = 'Usuário não encontrado no Active Directory.';
                break;
            // Nota: 'anonymous_bind_failed' e 'multiple_users_found' de ldap_auth.php
            // são mapeados para 'ad_auth_failed' em login_handler.php para simplificar as mensagens ao usuário.
            default:
                $errorMessage = 'Ocorreu um erro desconhecido durante o login.';
        }
        echo '<p class="error-message">' . htmlspecialchars($errorMessage) . '</p>';
    }
    if (isset($_GET['message']) && $_GET['message'] == 'loggedout') {
        echo '<p class="success-message">Você foi desconectado com sucesso.</p>';
    }
    ?>

    <form action="login_handler.php" method="POST">
        <div>
            <label for="username">Usuário:</label>
            <input type="text" id="username" name="username" required>
        </div>
        <div>
            <label for="password">Senha:</label>
            <input type="password" id="password" name="password" required>
        </div>
        <div>
            <button type="submit">Entrar</button>
        </div>
    </form>
</div>

<?php require_once 'templates/footer.php'; ?>
