<?php
function renderChangePassword() {
    // Keep this file minimal — only renders a link to the dedicated page.
    echo '
    <div class="change-password-section">
        <h2><i class="fa fa-user"></i> Profile & User Management</h2>
        <p><a href="change-password.php" class="change-password-link" style="display:inline-block;padding:8px 14px;background:#fff;border-radius:8px;border:1px solid #333;text-decoration:none;font-weight:700;">Change Password</a></p>
    </div>
    ';
}
?>