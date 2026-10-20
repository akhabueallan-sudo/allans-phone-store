<?php
require_once __DIR__ . '/includes/bootstrap.php';
// Sign-out is a POST with a CSRF token, so another site cannot log people out by embedding a link.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_valid()) {
    logout_user();
    session_start();                 // fresh session just to carry the goodbye message
    flash('success', 'You have been signed out.');
}
redirect('index.php');
