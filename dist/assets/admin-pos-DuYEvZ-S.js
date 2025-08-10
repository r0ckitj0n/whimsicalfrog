const h={cart:[],allItems:[],cashCalculatorResolve:null,lastSaleData:null,init(){if(!document.querySelector(".pos-register"))return;const e=document.getElementById("pos-data");if(e)try{this.allItems=JSON.parse(e.textContent)}catch(a){console.error("Failed to parse POS data:",a),this.showPOSModal("Error","Could not load item data. Please refresh.","error");return}this.bindEventListeners(),this.showAllItems(),this.updateCartDisplay()},bindEventListeners(){const t=document.querySelector(".pos-register");t&&t.addEventListener("click",a=>{const s=a.target.closest("[data-action]")?.dataset.action;if(s)switch(s){case"toggle-fullscreen":this.toggleFullscreen();break;case"exit-pos":this.handleExit();break;case"browse-items":this.showAllItems();break;case"add-to-cart":this.addToCart(a.target.closest(".item-card").dataset.sku);break;case"remove-from-cart":this.removeFromCart(a.target.closest(".cart-item").dataset.sku);break;case"increment-quantity":this.updateQuantity(a.target.closest(".cart-item").dataset.sku,1);break;case"decrement-quantity":this.updateQuantity(a.target.closest(".cart-item").dataset.sku,-1);break;case"checkout":this.processCheckout();break}}),document.body.addEventListener("click",a=>{const s=a.target.closest("[data-action]")?.dataset.action;if(s)switch(s){case"print-receipt":this.printReceipt();break;case"email-receipt":this.lastSaleData&&this.emailReceipt(this.lastSaleData.orderId);break;case"finish-sale":this.finishSale();break;case"accept-cash":this.acceptCashPayment(parseFloat(a.target.dataset.total));break;case"set-cash-amount":this.setCashAmount(parseFloat(a.target.dataset.amount));break;case"close-modal":this.hidePOSModal();break}});const e=document.getElementById("skuSearch");e&&e.addEventListener("input",a=>this.filterItems(a.target.value)),document.addEventListener("keydown",a=>{if(document.querySelector(".pos-modal-overlay")){a.key==="Escape"&&(a.preventDefault(),this.hidePOSModal());return}if(a.key==="F1"&&(a.preventDefault(),document.getElementById("skuSearch")?.focus()),a.key==="F2"){a.preventDefault(),this.showAllItems();const s=document.getElementById("skuSearch");s&&(s.value="")}if(a.key==="F9"){a.preventDefault();const s=document.getElementById("checkoutBtn");s&&!s.disabled&&this.processCheckout()}a.key==="Escape"&&(a.preventDefault(),this.handleExit())})},toggleFullscreen(){document.fullscreenElement?document.exitFullscreen():document.documentElement.requestFullscreen().catch(t=>{alert(`Error attempting to enable full-screen mode: ${t.message} (${t.name})`)})},handleExit(){this.showPOSConfirm("Exit POS","Are you sure you want to exit the Point of Sale system?","Yes, Exit","Stay Here").then(t=>{t&&(window.location.href="/admin/dashboard")})},filterItems(t){const e=document.getElementById("itemsGrid");if(!e)return;const a=t.toLowerCase().trim();if(!a){this.showAllItems();return}const s=this.allItems.filter(o=>o.name.toLowerCase().includes(a)||o.sku.toLowerCase().includes(a));e.innerHTML=s.length>0?s.map(o=>this.createItemCard(o)).join(""):`<div class="pos-no-results">No items found for "${t}"</div>`},showAllItems(){const t=document.getElementById("itemsGrid");t&&(t.innerHTML=this.allItems.map(e=>this.createItemCard(e)).join(""))},createItemCard(t){const e=t.imageUrl?`/${t.imageUrl}`:"https://via.placeholder.com/150";return`
            <div class="item-card" data-action="add-to-cart" data-sku="${t.sku}">
                <img src="${e}" alt="${t.name}" class="item-image" loading="lazy">
                <div class="item-info">
                    <div class="item-name">${t.name}</div>
                    <div class="item-price">$${parseFloat(t.retailPrice).toFixed(2)}</div>
                </div>
            </div>
        `},addToCart(t){const e=this.allItems.find(s=>s.sku===t);if(!e)return;const a=this.cart.find(s=>s.sku===t);a?a.quantity++:this.cart.push({sku:e.sku,name:e.name,price:parseFloat(e.retailPrice),quantity:1}),this.updateCartDisplay()},removeFromCart(t){this.cart=this.cart.filter(e=>e.sku!==t),this.updateCartDisplay()},updateQuantity(t,e){const a=this.cart.find(s=>s.sku===t);a&&(a.quantity+=e,a.quantity<=0&&this.removeFromCart(t)),this.updateCartDisplay()},updateCartDisplay(){const t=document.getElementById("cartItems"),e=document.getElementById("posCartTotal"),a=document.getElementById("checkoutBtn");if(!(!t||!e||!a))if(this.cart.length===0)t.innerHTML=`
                <div class="empty-cart">
                    Cart is empty<br>
                    <small>Scan or search for items to add them</small>
                </div>`,e.textContent="$0.00",a.disabled=!0;else{t.innerHTML=this.cart.map(o=>`
                <div class="cart-item" data-sku="${o.sku}">
                    <div class="cart-item-details">
                        <div class="cart-item-name">${o.name}</div>
                        <div class="cart-item-price">$${o.price.toFixed(2)}</div>
                    </div>
                    <div class="cart-item-actions">
                        <button class="quantity-btn" data-action="decrement-quantity">-</button>
                        <span class="quantity">${o.quantity}</span>
                        <button class="quantity-btn" data-action="increment-quantity">+</button>
                        <button class="remove-btn" data-action="remove-from-cart">×</button>
                    </div>
                </div>
            `).join("");const s=this.cart.reduce((o,i)=>o+i.price*i.quantity,0);e.textContent=`$${s.toFixed(2)}`,a.disabled=!1}},async processCheckout(){if(this.cart.length===0){this.showPOSModal("Empty Cart","Please add items to the cart before completing a sale.","warning");return}const t=.0825,e=this.cart.reduce((n,r)=>n+r.price*r.quantity,0),a=e*t,s=e+a,o=await this.showPaymentMethodSelector(s);if(!o)return;let i=0,c=0;if(o==="Cash"){const n=await this.showCashCalculator(s);if(!n)return;i=n.received,c=n.change}if(await this.showPOSConfirm("Complete Sale",this.generateConfirmationContent({subtotal:e,taxAmount:a,total:s,paymentMethod:o,cashReceived:i,changeAmount:c,TAX_RATE:t}),"Process Sale","Cancel")){this.showPOSModal("Processing Sale","Please wait...","processing");try{const n={customerId:"POS001",itemIds:this.cart.map(l=>l.sku),quantities:this.cart.map(l=>l.quantity),colors:this.cart.map(()=>null),sizes:this.cart.map(()=>null),total:s,subtotal:e,taxAmount:a,taxRate:t,paymentMethod:o,paymentStatus:"Received",shippingMethod:"Customer Pickup",order_status:"Delivered"},d=await(await fetch("/api/orders",{method:"POST",headers:{"Content-Type":"application/json"},body:JSON.stringify(n)})).json();if(d.success)this.showReceiptModal({...n,orderId:d.orderId,cashReceived:i,changeAmount:c,timestamp:new Date});else throw new Error(d.error||"Checkout failed")}catch(n){console.error("Checkout error:",n),this.showPOSModal("Transaction Failed",`❌ Checkout failed: ${n.message}`,"error")}}},showReceiptModal(t){this.lastSaleData=t,this.hidePOSModal();const a=`
            <div class="pos-modal-content pos-modal-small">
                <div class="pos-modal-header pos-modal-header-success">
                    <h3 class="pos-modal-title">🧾 Transaction Complete</h3>
                </div>
                <div class="pos-modal-body pos-modal-body-scroll">${this.generateReceiptContent(t)}</div>
                <div class="pos-modal-footer">
                    <button class="btn btn-secondary" data-action="print-receipt">🖨️ Print Receipt</button>
                    <button class="btn btn-secondary" data-action="email-receipt">📧 Email Receipt</button>
                    <button class="btn btn-primary" data-action="finish-sale">✅ Finish Sale</button>
                </div>
            </div>`;this.showPOSModal("",a,"custom")},generateReceiptContent(t){const e=t.itemIds.map((a,s)=>{const o=this.cart.find(c=>c.sku===a),i=o.price*t.quantities[s];return`
                <div>
                    <div>
                        <div>${o.name}</div>
                        <div>SKU: ${a}</div>
                        <div>${t.quantities[s]} x $${o.price.toFixed(2)}</div>
                    </div>
                    <div>$${i.toFixed(2)}</div>
                </div>
            `}).join("");return`
            <div>
                <div><strong>Order ID:</strong> ${t.orderId}</div>
                <div><strong>Date:</strong> ${new Date(t.timestamp).toLocaleString()}</div>
                <div>${e}</div>
                <div>
                    <span>Subtotal:</span>
                    <span>$${t.subtotal.toFixed(2)}</span>
                </div>
                <div>
                    <span>Total:</span>
                    <span>$${t.total.toFixed(2)}</span>
                </div>
            </div>
        `},printReceipt(){const t=this.generateReceiptContent(this.lastSaleData),e=window.open("","PRINT","height=600,width=800");e.document.write(`<html><head><title>Receipt</title></head><body>${t}</body></html>`),e.document.close(),e.focus(),e.print(),e.close()},async emailReceipt(t){const e=prompt("Enter customer email address:");if(!e||!/^[\S]+@[\S]+\.[\S]+$/.test(e)){this.showPOSModal("Invalid Email","Please enter a valid email address.","error");return}this.showPOSModal("Sending Receipt...","Please wait...","info");try{const s=await(await fetch("/api/receipts/email",{method:"POST",headers:{"Content-Type":"application/json"},body:JSON.stringify({orderId:t,customerEmail:e,orderData:this.lastSaleData})})).json();if(s.success)this.showPOSModal("Email Sent!",s.message||`Receipt sent to ${e}`,"success");else throw new Error(s.error||"Unknown error")}catch(a){this.showPOSModal("Email Failed",a.message,"error")}},finishSale(){this.hidePOSModal(),this.cart=[],this.updateCartDisplay();const t=document.getElementById("skuSearch");t&&(t.value=""),this.showAllItems()},showPOSModal(t,e,a="info"){this.hidePOSModal();const s=document.createElement("div");s.id="posModal",s.className="pos-modal-overlay";let o;a==="custom"?o=e:o=`
                <div class="pos-modal-content pos-modal-small">
                    <div class="pos-modal-header pos-modal-header-${a}">
                        <h3 class="pos-modal-title">${t}</h3>
                        <button class="pos-modal-close" data-action="close-modal">×</button>
                    </div>
                    <div class="pos-modal-body">${e}</div>
                </div>`,s.innerHTML=`<div class="pos-modal-backdrop"></div>${o}`,document.body.appendChild(s)},hidePOSModal(){const t=document.getElementById("posModal");t&&t.remove(),this.cashCalculatorResolve&&(this.cashCalculatorResolve(null),this.cashCalculatorResolve=null)},showPOSConfirm(t,e,a="OK",s="Cancel"){return new Promise(o=>{const i=`
                <div class="pos-modal-content pos-modal-small">
                    <div class="pos-modal-header"><h3 class="pos-modal-title">${t}</h3></div>
                    <div class="pos-modal-body">${e}</div>
                    <div class="pos-modal-footer">
                        <button class="btn btn-secondary">${s}</button>
                        <button class="btn btn-primary">${a}</button>
                    </div>
                </div>`;this.showPOSModal("",i,"custom"),document.querySelector("#posModal .btn-primary").onclick=()=>{this.hidePOSModal(),o(!0)},document.querySelector("#posModal .btn-secondary").onclick=()=>{this.hidePOSModal(),o(!1)}})},showPaymentMethodSelector(t){const e=`
            <h3>Total Due: $${t.toFixed(2)}</h3>
            <div class="payment-methods">
                <button class="payment-btn" data-method="Cash">💵 Cash</button>
                <button class="payment-btn" data-method="Card">💳 Card</button>
                <button class="payment-btn" data-method="Other">📱 Other</button>
            </div>`;return new Promise(a=>{this.showPOSModal("Select Payment Method",e,"info"),document.querySelectorAll("#posModal .payment-btn").forEach(s=>{s.onclick=()=>{this.hidePOSModal(),a(s.dataset.method)}})})},showCashCalculator(t){return new Promise(e=>{this.cashCalculatorResolve=e;const a=`
                <h3>Total Due: $${t.toFixed(2)}</h3>
                <input type="number" id="cashReceived" placeholder="0.00" class="pos-cash-input">
                <div id="quickAmountButtons"></div>
                <div id="changeDue">Change: $0.00</div>
                <div id="insufficientFunds" style="display: none; color: red;">Insufficient funds</div>
                <button id="acceptCashBtn" class="btn btn-primary" data-action="accept-cash" data-total="${t}" disabled>Accept</button>`;this.showPOSModal("Cash Payment",a,"info"),this.generateQuickAmountButtons(t),document.getElementById("cashReceived").oninput=()=>this.calculateChange(t)})},generateQuickAmountButtons(t){const e=document.getElementById("quickAmountButtons");if(!e)return;const a=[{label:"Exact",amount:t}],s=Math.ceil(t);s!==t&&a.push({label:`$${s}`,amount:s}),[20,50,100].forEach(i=>{t<i&&a.push({label:`$${i}`,amount:i})});const o=[...new Map(a.map(i=>[i.amount,i])).values()].slice(0,4);e.innerHTML=o.map(i=>`<button class="quick-amount-btn" data-action="set-cash-amount" data-amount="${i.amount}">${i.label}</button>`).join("")},calculateChange(t){const e=parseFloat(document.getElementById("cashReceived").value)||0,a=e-t;document.getElementById("changeDue").textContent=`Change: $${Math.max(0,a).toFixed(2)}`;const s=document.getElementById("acceptCashBtn");s.disabled=e<t},setCashAmount(t){const e=document.getElementById("cashReceived");e.value=t.toFixed(2),e.dispatchEvent(new Event("input"))},acceptCashPayment(t){const e=parseFloat(document.getElementById("cashReceived").value);e>=t&&this.cashCalculatorResolve&&(this.cashCalculatorResolve({received:e,change:e-t}),this.cashCalculatorResolve=null,this.hidePOSModal())},generateConfirmationContent(t){let e=`<strong>Method:</strong> ${t.paymentMethod}`;return t.paymentMethod==="Cash"&&(e+=`<br><strong>Cash Received:</strong> $${t.cashReceived.toFixed(2)}`,e+=`<br><strong>Change Due:</strong> $${t.changeAmount.toFixed(2)}`),`
            <div>Subtotal: <strong>$${t.subtotal.toFixed(2)}</strong></div>
            <div>Sales Tax (${(t.TAX_RATE*100).toFixed(2)}%): $${t.taxAmount.toFixed(2)}</div>
            <hr>
            <div>Total: <strong>$${t.total.toFixed(2)}</strong></div>
            <hr>
            <div>${e}</div>`}};document.addEventListener("DOMContentLoaded",()=>{h.init()});
