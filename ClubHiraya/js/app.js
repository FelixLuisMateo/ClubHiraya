// Simulating backend. Replace with real backend/database for prod!
const FOODS = [
    {id:1, name:"Lechon Baka", price:420, category:"Main Course", image:"assets/lechon baka.jpg"},
    {id:2, name:"Hoisin BBQ Pork Ribs", price:599, category:"Main Course", image:"assets/Hoisin BBQ Pork Ribs.jpg"},
    {id:3, name:"Mango Habanero", price:439, category:"Main Course", image:"assets/mango habanero.jpg"},
    {id:4, name:"Smoked Carbonara", price:349, category:"Main Course", image:"assets/Smoked Carbonara.jpg"},
    {id:5, name:"Mozzarella Poppers", price:280, category:"Appetizer", image:"assets/mozzarella poppers.jpg"},
    {id:6, name:"Salmon Tare-Tare", price:379, category:"Seafood Platter", image:"assets/salmon tare tare.jpg"},
    {id:7, name:"Chili Lime Chicken Wings", price:379, category:"Main Course", image:"assets/Chicken Wings (Chilli Lime).jpg"},
    {id:8, name:"Pepperoni Pizza", price:499, category:"Main Course", image:"assets/pepperoni.jpg"},
    {id:9, name:"Lechon Baka", price:420, category:"Main Course", image:"assets/lechon baka.jpg"},
    {id:10, name:"Hoisin BBQ Pork Ribs", price:599, category:"Main Course", image:"assets/Hoisin BBQ Pork Ribs.jpg"},
    {id:11, name:"Mango Habanero", price:439, category:"Main Course", image:"assets/mango habanero.jpg"},
    {id:12, name:"Smoked Carbonara", price:349, category:"Main Course", image:"assets/Smoked Carbonara.jpg"},
    {id:13, name:"Mozzarella Poppers", price:280, category:"Appetizer", image:"assets/mozzarella poppers.jpg"},
    {id:14, name:"Salmon Tare-Tare", price:379, category:"Seafood Platter", image:"assets/salmon tare tare.jpg"},
    {id:15, name:"Chili Lime Chicken Wings", price:379, category:"Main Course", image:"assets/Chicken Wings (Chilli Lime).jpg"},
    {id:16, name:"Pepperoni Pizza", price:499, category:"Main Course", image:"assets/pepperoni.jpg"},


    {id:17, name:"Seafood Cajun (1.5kg)", price:1099, category:"Seafood Platter", image:"assets/Seafood Cajun.jpg"},
    {id:18, name:"French Fries", price:150, category:"Side dish", image:"assets/fries.jpg"},
    {id:19, name:"Buffalo Wings", price:420, category:"Appetizer", image:"assets/buffalo.jpg"},
    {id:20, name:"Iced Tea", price:99, category:"Drinks", image:"assets/iced_tea.jpg"},
    {id:21, name:"Soft Drink", price:80, category:"Drinks", image:"assets/soda.jpg"},
    {id:22, name:"Calamari", price:350, category:"Seafood Platter", image:"assets/calamari.jpg"},
    {id:23, name:"Potato Wedges", price:170, category:"Side dish", image:"assets/potato_wedges.jpg"},
    {id:24, name:"Beef Steak", price:599, category:"Main Course", image:"assets/beefsteak.jpg"},
    {id:25, name:"Shrimp Tempura", price:420, category:"Seafood Platter", image:"assets/tempura.jpg"},
    {id:26, name:"Onion Rings", price:180, category:"Side dish", image:"assets/onion_rings.jpg"},
];

let order = [];
let drafts = JSON.parse(localStorage.getItem("drafts") || "[]"); // For demo; use DB for prod
let currentCategory = "Main Course";
let currentSearch = "";
let discountPercent = 0;
let orderNote = "";
let selectedOrderId = null; // NEW: for dropdown qty controls

// ----- FOOD GRID RENDER -----
function renderFoods() {
    const grid = document.getElementById('foodsGrid');
    let filtered = FOODS.filter(f => f.category === currentCategory);
    if(currentSearch) {
        filtered = filtered.filter(f=>f.name.toLowerCase().includes(currentSearch.toLowerCase()));
    }
    grid.innerHTML = '';
    if(filtered.length === 0) {
        grid.innerHTML = `<div style="grid-column:1/-1;text-align:center;color:#888;font-size:17px;padding:50px 0;">No products found.</div>`;
        return;
    }
    filtered.forEach(food => {
        const div = document.createElement('div');
        div.className = 'food-card';
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
            if(found) {
                found.qty += 1;
            } else {
                order.push({id:food.id, name:food.name, price:food.price, qty:1, image:food.image});
            }
            selectedOrderId = food.id; // NEW: open dropdown for this food
            renderOrderList();
            renderOrderCompute();
        };
        grid.appendChild(div);
    });
}

// ----- CATEGORIES -----
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.category-btn').forEach(btn => {
        btn.addEventListener('click', function () {
            document.querySelectorAll('.category-btn').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            currentCategory = this.dataset.category;
            renderFoods();
        });
    });
    renderFoods();
});

// ----- SEARCH -----
document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('searchBox').oninput = function() {
        currentSearch = this.value;
        renderFoods();
    };
});

// ----- ORDER LIST -----
function renderOrderList() {
    const list = document.getElementById('orderList');
    list.innerHTML = '';
    order.forEach(item => {
        // Always visible: format as [Name] [xQty] [Total Price] [X]
        const div = document.createElement('div');
        div.className = 'order-item';
        div.style.cursor = 'pointer';
        div.innerHTML = `
            <span class="order-item-name" style="font-weight:bold;">${item.name}</span>
            <span class="order-qty-text">x${item.qty}</span>
            <span style="min-width:80px;text-align:right;">₱${(item.price * item.qty).toFixed(2)}</span>
            <button class="remove-item-btn" data-id="${item.id}">&times;</button>
        `;
        // Clicking the row toggles the qty dropdown for this food
        div.onclick = (e) => {
            if (e.target.classList.contains('remove-item-btn')) return;
            selectedOrderId = selectedOrderId === item.id ? null : item.id;
            renderOrderList();
        };
        list.appendChild(div);

        // Dropdown for quantity controls if selected
        if(selectedOrderId === item.id) {
            const dropdown = document.createElement('div');
            dropdown.className = 'order-qty-dropdown';
            dropdown.innerHTML = `
                <button class="order-qty-btn" data-id="${item.id}" data-delta="-1">-</button>
                <input class="order-qty-input" type="number" value="${item.qty}" min="1" data-id="${item.id}">
                <button class="order-qty-btn" data-id="${item.id}" data-delta="1">+</button>
            `;
            list.appendChild(dropdown);

            // - and + buttons in dropdown
            dropdown.querySelectorAll('.order-qty-btn').forEach(btn => {
                btn.onclick = function (ev) {
                    ev.stopPropagation();
                    const id = +this.dataset.id;
                    const delta = +this.dataset.delta;
                    let idx = order.findIndex(item => item.id === id);
                    if (idx > -1) {
                        if (order[idx].qty + delta <= 0) {
                            order.splice(idx,1);
                            selectedOrderId = null;
                        } else {
                            order[idx].qty += delta;
                        }
                        renderOrderList();
                        renderOrderCompute();
                    }
                };
            });
            // Textbox in dropdown
            dropdown.querySelectorAll('.order-qty-input').forEach(input => {
                input.onchange = function (ev) {
                    ev.stopPropagation();
                    const id = +this.dataset.id;
                    let qty = parseInt(this.value) || 1;
                    let idx = order.findIndex(item => item.id === id);
                    if (idx > -1) {
                        if (qty <= 0) {
                            order.splice(idx,1);
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

    // X button to remove item
    list.querySelectorAll('.remove-item-btn').forEach(btn => {
        btn.onclick = function (ev) {
            ev.stopPropagation();
            const id = +this.dataset.id;
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
    document.getElementById('orderCompute').innerHTML = `
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

    document.getElementById('discountBtn').onclick = showDiscountModal;
    document.getElementById('noteBtn').onclick = showNoteModal;
}

// ----- NEW ORDER, DRAFT, FAKE REFRESH BUTTONS -----
document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('newOrderBtn').onclick = () => {
        order = [];
        discountPercent = 0;
        orderNote = "";
        selectedOrderId = null;
        renderOrderList();
        renderOrderCompute();
    };
    document.getElementById('refreshBtn').onclick = () => {
        // Fake refresh: re-render everything, do not clear state
        renderFoods();
        renderOrderList();
        renderOrderCompute();
    };
    document.getElementById('draftBtn').onclick = () => {
        showDraftModal();
    };
    renderOrderList();
    renderOrderCompute();
});

// ----- DISCOUNT & NOTE MODAL -----
function showDiscountModal() {
    const modal = document.createElement('div');
    modal.className = 'modal';
    modal.innerHTML = `<div class="modal-content">
        <span class="close-btn" id="closeDiscountModal">&times;</span>
        <h3>Apply Discount</h3>
        <button class="discount-opt" data-value="0.20">Senior Citizen (20%)</button>
        <button class="discount-opt" data-value="0.00">No Discount</button>
    </div>`;
    document.body.appendChild(modal);

    modal.querySelectorAll('.discount-opt').forEach(btn => {
        btn.onclick = () => {
            discountPercent = +btn.dataset.value;
            renderOrderCompute();
            modal.remove();
        }
    });
    modal.querySelector('#closeDiscountModal').onclick = () => modal.remove();
}
function showNoteModal() {
    const modal = document.createElement('div');
    modal.className = 'modal';
    modal.innerHTML = `<div class="modal-content">
        <span class="close-btn" id="closeNoteModal">&times;</span>
        <h3>Order Note</h3>
        <textarea id="orderNoteInput" style="width:95%;height:60px;">${orderNote||""}</textarea>
        <br>
        <button id="saveOrderNoteBtn">Save Note</button>
    </div>`;
    document.body.appendChild(modal);

    modal.querySelector('#saveOrderNoteBtn').onclick = () => {
        orderNote = modal.querySelector('#orderNoteInput').value;
        renderOrderCompute();
        modal.remove();
    };
    modal.querySelector('#closeNoteModal').onclick = () => modal.remove();
}

// ----- DRAFT MODAL -----
function showDraftModal() {
    const modal = document.getElementById('draftModal');
    const draftListDiv = document.createElement('div');
    draftListDiv.style.marginTop = "20px";
    draftListDiv.innerHTML = "<b>Saved Drafts:</b><br>";
    if (drafts.length === 0) {
        draftListDiv.innerHTML += "<div style='color:#bbb;padding:10px 0;'>No drafts yet</div>";
    } else {
        drafts.forEach((d, idx) => {
            draftListDiv.innerHTML += `
                <div class="draft-item" data-idx="${idx}" style="padding:7px 0;cursor:pointer;color:#0074ff;text-decoration:underline;">
                    ${d.name||"Untitled"} <span style="color:#888;font-size:13px;">(${d.order.length} items)</span>
                </div>
            `;
        });
    }
    let content = modal.querySelector('.modal-content');
    content.querySelectorAll('.draft-item').forEach(el=>el.remove());
    content.appendChild(draftListDiv);

    draftListDiv.querySelectorAll('.draft-item').forEach(div => {
        div.onclick = function() {
            let idx = +this.dataset.idx;
            if (typeof idx === "number" && drafts[idx]) {
                order = JSON.parse(JSON.stringify(drafts[idx].order));
                discountPercent = drafts[idx].discount||0;
                orderNote = drafts[idx].note||"";
                selectedOrderId = null;
                renderOrderList();
                renderOrderCompute();
                modal.classList.add('hidden');
            }
        }
    });

    modal.classList.remove('hidden');
    document.getElementById('closeDraftModal').onclick = () => modal.classList.add('hidden');
    document.getElementById('saveDraftBtn').onclick = () => {
        const name = document.getElementById('draftNameInput').value.trim();
        if(order.length === 0) {
            alert("Order is empty!");
            return;
        }
        drafts.push({
            name: name,
            order: JSON.parse(JSON.stringify(order)),
            discount: discountPercent,
            note: orderNote
        });
        localStorage.setItem("drafts", JSON.stringify(drafts));
        alert("Draft saved!");
        modal.classList.add('hidden');
    };
}

// Ensure order-section pushes compute to bottom
document.addEventListener('DOMContentLoaded', () => {
    // Fix for layout: always at bottom
    let orderSection = document.querySelector('.order-section');
    if(orderSection) {
        orderSection.style.display = "flex";
        orderSection.style.flexDirection = "column";
        orderSection.style.justifyContent = "flex-end";
    }
});