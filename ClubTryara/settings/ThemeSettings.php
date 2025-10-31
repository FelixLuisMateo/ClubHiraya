<?php
function renderThemeSettings() {
    $isDark = isset($_SESSION['dark_mode']) ? $_SESSION['dark_mode'] : false;
    $accent = isset($_SESSION['accent_color']) ? $_SESSION['accent_color'] : '#4B4BFF';

    // Handle POST
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $_SESSION['dark_mode'] = isset($_POST['dark_mode']);
        if (isset($_POST['accent_color'])) {
            $_SESSION['accent_color'] = $_POST['accent_color'];
        }
        // Redirect to avoid form resubmission prompt
        header("Location: " . $_SERVER['REQUEST_URI']);
        exit();
    }

    // Output form
    echo '
    <div>
        <h2>Theme Settings</h2>
        <form method="POST" style="display:flex;flex-direction:column;gap:16px;">
            <div style="display:flex;align-items:center;gap:18px;">
                <label style="font-weight:bold;">Dark Mode</label>
                <label class="switch">
                    <input type="checkbox" name="dark_mode" '.($isDark ? 'checked' : '').' onchange="this.form.submit()">
                    <span class="slider">
                        <span class="switch-on">ON</span>
                        <span class="switch-off">OFF</span>
                    </span>
                </label>
            </div>
            <div>
                <label style="font-weight:bold;">Accent Colors:</label>
                <input type="radio" name="accent_color" value="#4B4BFF" '.($accent=='#4B4BFF'?'checked':'').' onchange="this.form.submit()" style="background:#4B4BFF; border-radius:50%; width:30px; height:30px; border:none; margin-left:10px;">
                <input type="radio" name="accent_color" value="#D33FD3" '.($accent=='#D33FD3'?'checked':'').' onchange="this.form.submit()" style="background:#D33FD3; border-radius:50%; width:30px; height:30px; border:none; margin-left:10px;">
                <input type="radio" name="accent_color" value="#BDBDBD" '.($accent=='#BDBDBD'?'checked':'').' onchange="this.form.submit()" style="background:#BDBDBD; border-radius:50%; width:30px; height:30px; border:none; margin-left:10px;">
            </div>
        </form>
    </div>
    ';
}
?>            