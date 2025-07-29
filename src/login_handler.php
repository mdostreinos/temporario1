<?php
session_start();
require_once 'db_connect.php'; // For database connection
require_once __DIR__ . '/ldap_auth.php'; // For AD authentication

// List of users to be authenticated locally
define('LOCAL_USERS', ['admin', 'info']);

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    // Basic field validation
    if (empty($username) || empty($password)) {
        header('Location: index.php?error=emptyfields');
        exit();
    }

    // Username length validation (can be adjusted)
    if (strlen($username) > 255) {
        header('Location: index.php?error=usernametoolong');
        exit();
    }
    if (strlen($username) < 1) {
        header('Location: index.php?error=usernametooshort');
        exit();
    }

    // Check if the user is in the LOCAL_USERS list
    if (in_array(strtolower($username), array_map('strtolower', LOCAL_USERS))) {
        // --- Local Authentication Logic ---
        $sql = "SELECT id, username, password, role FROM users WHERE username = ?";
        $stmt = $conn->prepare($sql);

        if ($stmt === false) {
            error_log("SQL Prepare Error for local user in login_handler.php: " . $conn->error);
            header('Location: index.php?error=sqlerror');
            exit();
        }

        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($user = $result->fetch_assoc()) {
            if (password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['user_role'] = $user['role'];
                error_log('Login Handler Session Data for user ' . $user['username'] . ': ' . print_r($_SESSION, true));
                header('Location: home.php');
                exit();
            } else {
                header('Location: index.php?error=wrongpassword');
                exit();
            }
        } else {
            // This case should ideally not be reached if 'admin' and 'info' are guaranteed to be in DB
            header('Location: index.php?error=nouser');
            exit();
        }
        $stmt->close();
    } else {
        // --- AD Authentication Logic ---
        $ad_auth_result = authenticate_ad_user($username, $password);

        if ($ad_auth_result === true) {
            // AD Authentication successful
            // Check if user exists in the local database and get their role
            $sql = "SELECT id, username, role FROM users WHERE username = ?";
            $stmt = $conn->prepare($sql);

            if ($stmt === false) {
                error_log("SQL Prepare Error for AD user in login_handler.php: " . $conn->error);
                header('Location: index.php?error=sqlerror');
                exit();
            }

            $stmt->bind_param("s", $username);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($user = $result->fetch_assoc()) {
                // User exists in local DB
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['user_role'] = $user['role'];
                header('Location: home.php');
                exit();
            } else {
                // User authenticated against AD, but not found in local application database
                header('Location: index.php?error=user_not_registered_in_app');
                exit();
            }
            $stmt->close();
        } else {
            // AD Authentication failed, $ad_auth_result contains the error code string
            $error_param = 'ad_auth_failed'; // Default error, maps to a generic AD failure message
            switch ($ad_auth_result) {
                case 'empty_password':
                    $error_param = 'emptyfields'; // "Por favor, preencha usuário e senha."
                    break;
                case 'ldap_connection_failed':
                    $error_param = 'ldap_connection_failed'; // "Não foi possível conectar ao servidor de autenticação."
                    break;
                case 'ldap_tls_failed':
                    // This error is from ldap_auth.php if StartTLS is enabled and fails.
                    // Map to a generic connection or AD auth failed message for the user.
                    $error_param = 'ldap_connection_failed'; // Or 'ad_auth_failed'
                    break;
                case 'ldap_bind_failed':
                    // This is the primary error from the new ldap_auth.php if ldap_bind fails.
                    // This usually means incorrect username/password for AD.
                    $error_param = 'wrongpassword'; // "Usuário ou senha incorreta."
                    break;
                // --- REMOVED OLDER, MORE GRANULAR ERROR CODES ---
                // case 'anonymous_bind_failed_or_user_pass_incorrect':
                // $error_param = 'ad_auth_failed';
                // break;
                // case 'ldap_search_failed_or_user_not_found':
                // $error_param = 'ldap_search_failed_or_user_not_found';
                // break;
                // case 'multiple_users_found':
                // $error_param = 'ad_auth_failed';
                // break;
                // case 'incorrect_password_for_ad_user': // Now covered by ldap_bind_failed -> wrongpassword
                // $error_param = 'wrongpassword';
                // break;
                default:
                    // If $ad_auth_result is something else unexpected, use a generic AD failure.
                    $error_param = 'ad_auth_failed';
            }
            header('Location: index.php?error=' . $error_param);
            exit();
        }
    }
} else {
    // Not a POST request
    header('Location: index.php');
    exit();
}

$conn->close();
?>
