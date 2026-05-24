<?php
// admin/includes/admin_alerts.php
if (isset($_SESSION['success'])) {
    echo '<div class="alert alert-success alert-dismissible fade show py-2"><i class="bi bi-check-circle me-2"></i>'.htmlspecialchars($_SESSION['success']).'<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
    unset($_SESSION['success']);
}
if (isset($_SESSION['error'])) {
    echo '<div class="alert alert-danger alert-dismissible fade show py-2"><i class="bi bi-exclamation-triangle me-2"></i>'.htmlspecialchars($_SESSION['error']).'<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
    unset($_SESSION['error']);
}
