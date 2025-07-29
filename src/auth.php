<?php

function start_secure_session() {
    if (!headers_sent()) {
        // These headers instruct the browser not to cache the page
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Expires: Sat, 01 Jan 2000 00:00:00 GMT'); // A date in the past
        // CORREÇÃO: A linha 'Content-Type: text/html' foi removida daqui.
    }

    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION['last_access'] = time();
}

function is_logged_in() {
    start_secure_session();
    return isset($_SESSION['user_id']);
}

function require_login($redirect_url = '/index.php') {
    if (!is_logged_in()) {
        header("Location: " . $redirect_url . "?error=pleaselogin");
        exit();
    }
}

function is_admin() {
    if (!is_logged_in()) {
        return false;
    }
    return isset($_SESSION['user_role']) &&
           ($_SESSION['user_role'] === 'admin' ||
            $_SESSION['user_role'] === 'admin-aprovador' ||
            $_SESSION['user_role'] === 'superAdmin');
}

function is_super_admin() {
    if (!is_logged_in()) {
        return false;
    }
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'superAdmin';
}

function require_admin($redirect_url = '/home.php', $error_message = 'Acesso negado. Permissões de administrador necessárias.') {
    if (!is_admin()) {
        if ($redirect_url === '/index.php' || $redirect_url === '/home.php') {
             header("Location: " . $redirect_url . "?error=" . urlencode($error_message));
        } else {
             header("Location: /home.php?error=" . urlencode($error_message));
        }
        exit();
    }
}

function require_super_admin($redirect_url = '/home.php', $error_message = 'Acesso negado. Permissões de super administrador necessárias.') {
    if (!is_super_admin()) {
        if ($redirect_url === '/index.php' || $redirect_url === '/home.php') {
             header("Location: " . $redirect_url . "?error=" . urlencode($error_message));
        } else {
             header("Location: /home.php?error=" . urlencode($error_message));
        }
        exit();
    }
}

?>