// ===============================
// REAL BACKEND CONNECTION VERSION
// ===============================

// ----- GLOBAL VARIABLES -----
let FOODS = [];
let order = [];
let drafts = JSON.parse(localStorage.getItem("drafts") || "[]");
let currentCategory = "Main Course";
let currentSearch = "";
let discountPercent = 0;
let orderNote = "";
let selectedOrderId = null;

// ----- LOAD FOODS FROM DATABASE -----
async function fetchFoods() {
    try {
        const response = await fetch("foods.php");
        FOODS = await response.json();
        renderFoods();
    } catch (err) {
        console.error("Failed to fetch foods:", err);
    }
}

// ----- FOOD GRID RENDER -----
function renderFoods() {
    const grid = document.getElementById("foodsGrid");
    if (!grid) return;
    let filtered = FOODS.filter(f => f.category === currentCategory);
    if (currentSearch) {
        filtered = filtered.filter(f => f.name.toLowerCase().includes(currentSearch.toLowerCase()));
    }
    grid.innerHTML = "";
    if (filtered.length === 0) {
        grid.innerHTML = `<div style="grid-column:1/-1;text-align:center;color:#888;font-size:17px;padding:50px 0;">No products found.</div>`;
        return;
    }
    filtered.forEach(food => {
        const div = document.createElement("div");
        div.className = "food-card";
        div.dataset.id = food.id;
        div.dataset.name = food.name;
        div.dataset.price = food.price;
        div.dataset.category = food.category;
        div.dataset.image = food.image;
        div.innerHTML = `
            <img src="${food.image}" alt="${food.name}">
            <div class="food-label">${food.name}</div>
            <div class="food-price">₱${food.price}</div>
        `;
        div.onclick = () => {
            let found = order.find(i => i.id == food.id);
            if (found) {
                found.qty += 1;
            } else {
                order.push({ id: food.id, name: food.name, price: parseFloat(food.price), qty: 1, image: food.image });
            }
            selectedOrderId = food.id;
            renderOrderList();
            renderOrderCompute();
        };
        grid.appendChild(div);
    });
}

// ----- CATEGORIES -----
document.addEventListener("DOMContentLoaded", () => {
    document.querySelectorAll(".category-btn").forEach(btn => {
        btn.addEventListener("click", function () {
            document.querySelectorAll(".category-btn").forEach(b => b.classList.remove("active"));
            this.classList.add("active");
            currentCategory = this.dataset.category;
            renderFoods();
        });
    });
});

// ----- SEARCH -----
document.addEventListener("DOMContentLoaded", () => {
    const searchBox = document.getElementById("searchBox");
    if (searchBox) {
        searchBox.oninput = function () {
            currentSearch = this.value;
            renderFoods();
        };
    }
});

// ----- ORDER LIST -----
function renderOrderList() {
    const list = document.getElementById("orderList");
    list.innerHTML = "";
    order.forEach(item => {
        const div = document.createElement("div");
        div.className = "order-item";
        div.style.cursor = "pointer";
        div.innerHTML = `
            <span class="order-item-name" style="font-weight:bold;">${item.name}</span>
            <span class="order-qty-text">x${item.qty}</span>
            <span style="min-width:80px;text-align:right;">₱${(item.price * item.qty).toFixed(2)}</span>
            <button class="remove-item-btn" data-id="${item.id}">&times;</button>
        `;
        div.onclick = e => {
            if (e.target.classList.contains("remove-item-btn")) return;
            selectedOrderId = selectedOrderId === item.id ? null : item.id;
            renderOrderList();
        };
        list.appendChild(div);

        if (selectedOrderId === item.id) {
            const dropdown = document.createElement("div");
            dropdown.className = "order-qty-dropdown";
            dropdown.innerHTML = `
                <button class="order-qty-btn" data-id="${item.id}" data-delta="-1">-</button>
                <input class="order-qty-input" type="number" value="${item.qty}" min="1" data-id="${item.id}">
                <button class="order-qty-btn" data-id="${item.id}" data-delta="1">+</button>
            `;
            list.appendChild(dropdown);

            dropdown.querySelectorAll(".order-qty-btn").forEach(btn => {
                btn.onclick = ev => {
                    ev.stopPropagation();
                    const id = +btn.dataset.id;
                    const delta = +btn.dataset.delta;
                    let idx = order.findIndex(item => item.id === id);
                    if (idx > -1) {
                        if (order[idx].qty + delta <= 0) {
                            order.splice(idx, 1);
                            selectedOrderId = null;
                        } else {
                            order[idx].qty += delta;
                        }
                        renderOrderList();
                        renderOrderCompute();
                    }
                };
            });
            dropdown.querySelectorAll(".order-qty-input").forEach(input => {
                input.onchange = ev => {
                    ev.stopPropagation();
                    const id = +input.dataset.id;
                    let qty = parseInt(input.value) || 1;
                    let idx = order.findIndex(item => item.id === id);
                    if (idx > -1) {
                        if (qty <= 0) {
                            order.splice(idx, 1);
                            selectedOrderId = null;
                        } else {
                            order[idx].qty = qty;
                        }
                        renderOrderList();
                        renderOrderCompute();
                    }
                };
            });
        }
    });

    list.querySelectorAll(".remove-item-btn").forEach(btn => {
        btn.onclick = ev => {
            ev.stopPropagation();
            const id = +btn.dataset.id;
            order = order.filter(item => item.id !== id);
            if (selectedOrderId === id) selectedOrderId = null;
            renderOrderList();
            renderOrderCompute();
        };
    });
}

// ----- ORDER COMPUTATION -----
function renderOrderCompute() {
    let subtotal = order.reduce((sum, item) => sum + item.price * item.qty, 0);
    let service = subtotal * 0.1;
    let tax = subtotal * 0.12;
    let discount = (subtotal + service + tax) * discountPercent;
    let total = subtotal + service + tax - discount;
    document.getElementById("orderCompute").innerHTML = `
        <div class="compute-actions">
            <button class="compute-btn add" id="addManualItemBtn">Add</button>
            <button class="compute-btn discount" id="discountBtn">Discount</button>
            <button class="compute-btn note" id="noteBtn">Note</button>
        </div>
        <div class="compute-row"><span>Subtotal:</span><span>₱${subtotal.toFixed(2)}</span></div>
        <div class="compute-row"><span>Service Charge:</span><span>₱${service.toFixed(2)}</span></div>
        <div class="compute-row"><span>Tax:</span><span>₱${tax.toFixed(2)}</span></div>
        <div class="compute-row"><span>Discount:</span>
            <span>₱${discount.toFixed(2)}${discountPercent>0?` <span style="color:#10be27;font-weight:bold;">(${(discountPercent*100).toFixed(0)}% Off)</span>`:""}</span></div>
        <div class="compute-row total"><span>Payable Amount:</span><span>₱${total.toFixed(2)}</span></div>
        ${orderNote ? `<div class="compute-row"><span style="color:#3a3ac7;font-size:14px;"><b>Note:</b> ${orderNote}</span></div>` : ""}
    `;

    document.getElementById("discountBtn").onclick = showDiscountModal;
    document.getElementById("noteBtn").onclick = showNoteModal;
}

// ----- NEW ORDER, DRAFT, REFRESH -----
document.addEventListener("DOMContentLoaded", () => {
    document.getElementById("newOrderBtn").onclick = () => {
        order = [];
        discountPercent = 0;
        orderNote = "";
        selectedOrderId = null;
        renderOrderList();
        renderOrderCompute();
    };
    document.getElementById("refreshBtn").onclick = () => {
        fetchFoods();
        renderOrderList();
        renderOrderCompute();
    };
    document.getElementById("draftBtn").onclick = () => showDraftModal();
    renderOrderList();
    renderOrderCompute();
});

// ----- MODALS -----
function showDiscountModal() {
    const modal = document.createElement("div");
    modal.className = "modal";
    modal.innerHTML = `<div class="modal-content">
        <span class="close-btn" id="closeDiscountModal">&times;</span>
        <h3>Apply Discount</h3>
        <button class="discount-opt" data-value="0.20">Senior Citizen (20%)</button>
        <button class="discount-opt" data-value="0.00">No Discount</button>
    </div>`;
    document.body.appendChild(modal);

    modal.querySelectorAll(".discount-opt").forEach(btn => {
        btn.onclick = () => {
            discountPercent = +btn.dataset.value;
            renderOrderCompute();
            modal.remove();
        };
    });
    modal.querySelector("#closeDiscountModal").onclick = () => modal.remove();
}

function showNoteModal() {
    const modal = document.createElement("div");
    modal.className = "modal";
    modal.innerHTML = `<div class="modal-content">
        <span class="close-btn" id="closeNoteModal">&times;</span>
        <h3>Order Note</h3>
        <textarea id="orderNoteInput" style="width:95%;height:60px;">${orderNote || ""}</textarea>
        <br><button id="saveOrderNoteBtn">Save Note</button>
    </div>`;
    document.body.appendChild(modal);

    modal.querySelector("#saveOrderNoteBtn").onclick = () => {
        orderNote = modal.querySelector("#orderNoteInput").value;
        renderOrderCompute();
        modal.remove();
    };
    modal.querySelector("#closeNoteModal").onclick = () => modal.remove();
}

// ----- INITIALIZE -----
document.addEventListener("DOMContentLoaded", () => {
    fetchFoods(); // load foods from database
    const orderSection = document.querySelector(".order-section");
    if (orderSection) {
        orderSection.style.display = "flex";
        orderSection.style.flexDirection = "column";
        orderSection.style.justifyContent = "flex-end";
    }
});
