<?php
function renderNotificationsSettings() {
    echo '
    <div>
        <h2>Notifications</h2>
        <form>
            <label>Enable Sound <input type="checkbox" disabled></label><br>
            <label>Order Alerts <input type="checkbox" disabled></label><br>
            <label>Low Stock Alerts <input type="checkbox" disabled></label>
        </form>
    </div>
    ';
}
?>