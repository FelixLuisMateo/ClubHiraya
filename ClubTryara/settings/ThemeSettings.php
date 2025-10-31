<?php
function renderThemeSettings() {
    $isDark = isset($_SESSION['dark_mode']) ? $_SESSION['dark_mode'] : false;
    $accent = isset($_SESSION['accent_color']) ? $_SESSION['accent_color'] : '#4B4BFF';

    echo '
    <div>
        <h2>Theme Settings</h2>
        <form method="POST">
            <label>Dark Mode
                <input type="checkbox" name="dark_mode" '.($isDark?'checked':'').' onchange="this.form.submit()">
            </label>
            <br>
            <label>Accent Colors:</label>
            <input type="radio" name="accent_color" value="#4B4BFF" '.($accent=='#4B4BFF'?'checked':'').' onchange="this.form.submit()" style="background:#4B4BFF; border-radius:50%; width:30px; height:30px;">
            <input type="radio" name="accent_color" value="#D33FD3" '.($accent=='#D33FD3'?'checked':'').' onchange="this.form.submit()" style="background:#D33FD3; border-radius:50%; width:30px; height:30px;">
            <input type="radio" name="accent_color" value="#BDBDBD" '.($accent=='#BDBDBD'?'checked':'').' onchange="this.form.submit()" style="background:#BDBDBD; border-radius:50%; width:30px; height:30px;">
        </form>
    </div>
    ';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['dark_mode'])) {
            $_SESSION['dark_mode'] = true;
        } else {
            $_SESSION['dark_mode'] = false;
        }
        if (isset($_POST['accent_color'])) {
            $_SESSION['accent_color'] = $_POST['accent_color'];
        }
    }
}
?>