<?php
// logout.php：ログアウト

require_once __DIR__ . '/includes/auth_check.php';

logout_user();
flash('ok', 'ログアウトしました。');
redirect('login.php');
