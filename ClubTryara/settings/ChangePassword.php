<?php
function renderChangePassword() {
    echo '
    <div class="change-password-section">
        <h2><i class="fa fa-user"></i> Profile & User Management</h2>
        <form method="POST">
            <button type="submit" name="change_password">Change Password</button>
        </form>
    </div>
    ';

    if (isset($_POST['change_password'])) {
        // Show password change form
        echo '
        <form method="POST">
            <input type="text" name="username" placeholder="Username" required>
            <input type="password" name="old_password" placeholder="Old Password" required>
            <input type="password" name="new_password" placeholder="New Password" required>
            <button type="submit" name="update_password">Update Password</button>
        </form>
        ';
    }

    if (isset($_POST['update_password'])) {
        // Update password in your login table (replace with your actual DB code)
        $username = $_POST['username'];
        $old_password = $_POST['old_password'];
        $new_password = $_POST['new_password'];
        // Example code:
        // $conn = ...;
        // $result = mysqli_query($conn, "SELECT * FROM login WHERE username='$username' AND password='$old_password'");
        // if (mysqli_num_rows($result) > 0) {
        //     mysqli_query($conn, "UPDATE login SET password='$new_password' WHERE username='$username'");
        //     echo '<p>Password updated!</p>';
        // } else {
        //     echo '<p>Incorrect old password.</p>';
        // }
    }
}
?>