/**
 * app-payment.js — Payment modal + save/print flow (with note + cabin optional)
 */
(function () {
  'use strict';
  function $id(id){return document.getElementById(id);}

  async function fetchServerRatesOnce(){
    if(window._app_payment_rates)return window._app_payment_rates;
    let sR=0.10,tR=0.12;
    try{
      const r=await fetch('api/get_settings.php',{cache:'no-store',credentials:'same-origin'});
      if(r.ok){
        const s=await r.json();
        sR=(Number(s.service_charge)||10)/100;
        tR=(Number(s.tax)||12)/100;
      }
    }catch(e){}
    window._app_payment_rates={serviceRate:sR,taxRate:tR};
    return window._app_payment_rates;
  }

  function readOrderArrayBestEffort(){
    try{
      if(window.appActions&&typeof window.appActions.gatherCartForPayload==='function'){
        const raw=window.appActions.gatherCartForPayload();
        if(Array.isArray(raw))return raw;
      }
    }catch(e){}
    try{
      if(typeof window.getOrder==='function'){
        const o=window.getOrder();
        if(Array.isArray(o))return o;
      }
    }catch(e){}
    if(Array.isArray(window.order))return window.order;
    try{
      const rows=document.querySelectorAll('#orderList .order-item');
      if(!rows.length)return[];
      const out=[];
      rows.forEach(r=>{
        const name=r.querySelector('.order-item-name')?.textContent.trim()||'';
        const qty=Number(r.querySelector('.order-qty-input')?.value||1);
        const priceEl=r.querySelector('.order-item-price');
        let line=0;
        if(priceEl?.dataset?.pricePhp)line=Number(priceEl.dataset.pricePhp);
        else line=Number((priceEl?.textContent||'').replace(/[^\d.-]/g,''))||0;
        const id=r.dataset?.id?Number(r.dataset.id):null;
        const unit=qty?line/qty:0;
        out.push({id,name,qty,price:unit,line_total:line});
      });
      return out;
    }catch(e){return[];}
  }

  async function computeTotalsFromOrder(){
    try{
      if(typeof window.computeNumbers==='function'){
        const n=window.computeNumbers();
        if(n&&typeof n.payable!=='undefined')return n;
      }
    }catch(e){}
    const ord=readOrderArrayBestEffort();
    if(!ord.length)return{subtotal:0,serviceCharge:0,tax:0,discountAmount:0,tablePrice:0,payable:0};
    const rates=await fetchServerRatesOnce();
    let subtotal=0;
    for(const it of ord){
      const q=Number(it.qty||1),u=Number(it.price||0);
      subtotal+=q*u;
    }
    const svc=subtotal*(rates.serviceRate||0.10),
          tax=subtotal*(rates.taxRate||0.12),
          disc=subtotal*(window.discountRate||0),
          tbl=parseFloat(document.body.dataset.reservedTablePrice)||0,
          pay=subtotal+svc+tax-disc+tbl;
    const r=v=>Math.round((Number(v)+Number.EPSILON)*100)/100;
    return{subtotal:r(subtotal),serviceCharge:r(svc),tax:r(tax),discountAmount:r(disc),tablePrice:r(tbl),payable:r(pay)};
  }

  function createPaymentModal(){
    if($id('paymentModal'))return $id('paymentModal');
    const overlay=document.createElement('div');
    overlay.id='paymentModal';
    overlay.style='position:fixed;inset:0;background:rgba(0,0,0,0.45);display:flex;align-items:center;justify-content:center;z-index:99999;';
    const card=document.createElement('div');
    card.style='width:820px;max-width:98%;max-height:92vh;overflow:auto;background:#fff;border-radius:12px;padding:20px;box-shadow:0 12px 36px rgba(0,0,0,0.35);';
    const header=document.createElement('div');
    header.style='display:flex;justify-content:space-between;align-items:center;';
    const t=document.createElement('div');t.textContent='How would you like to pay?';t.style='font-weight:800;font-size:18px;';
    const x=document.createElement('button');x.innerHTML='&times;';x.style='font-size:22px;border:none;background:transparent;cursor:pointer;';x.onclick=()=>overlay.remove();
    header.append(t,x);card.append(header);

    const row=document.createElement('div');
    row.style='display:flex;gap:10px;margin-top:14px;';
    const methods=['Cash','GCash','Bank Transfer'];
    const mBtns={};
    methods.forEach(m=>{
      const b=document.createElement('button');
      b.textContent=m;
      b.style='flex:1;padding:10px;border-radius:10px;border:2px solid #ddd;cursor:pointer;';
      b.onclick=()=>select(m);
      mBtns[m]=b;
      row.append(b);
    });
    card.append(row);

    const content=document.createElement('div');
    content.id='paymentModalContent';
    content.style='margin-top:16px;';
    const right=document.createElement('div');
    right.style='min-width:260px;max-width:34%;border-left:1px solid #eee;padding-left:12px;';
    const totalsWrap=document.createElement('div');
    totalsWrap.id='paymentTotals';
    right.append(totalsWrap);
    const wrap=document.createElement('div');
    wrap.style='display:flex;gap:18px;margin-top:14px;';
    wrap.append(content,right);
    card.append(wrap);

    const footer=document.createElement('div');
    footer.style='display:flex;justify-content:flex-end;margin-top:18px;';
    const cancel=document.createElement('button');
    cancel.textContent='Cancel';
    cancel.style='padding:10px 16px;border:1px solid #ccc;border-radius:8px;background:#fff;cursor:pointer;';
    cancel.onclick=()=>overlay.remove();
    const pay=document.createElement('button');
    pay.id='paymentConfirmBtn';
    pay.textContent='Save & Print';
    pay.style='padding:10px 16px;border:none;border-radius:8px;background:#d33fd3;color:#fff;cursor:pointer;margin-left:10px;';
    footer.append(cancel,pay);
    card.append(footer);
    overlay.append(card);
    document.body.append(overlay);

    let selected='Cash',timer=null;
    async function upd(){
      const n=(typeof window.computeNumbers==='function')?window.computeNumbers():await computeTotalsFromOrder();
      const s='₱';
      totalsWrap.innerHTML='<b>Summary</b>';
      const add=(l,v,b)=>{
        const d=document.createElement('div');
        d.style='display:flex;justify-content:space-between;margin:4px 0;';
        d.innerHTML=`<div>${l}</div><div style="font-weight:${b?800:600}">${s}${Number(v||0).toFixed(2)}</div>`;
        totalsWrap.append(d);
      };
      add('Subtotal',n.subtotal);
      add('Service',n.serviceCharge);
      add('Tax',n.tax);
      add('Discount',n.discountAmount);
      if(n.tablePrice>0)add('Reserved',n.tablePrice);
      add('Payable',n.payable,true);
    }
    function start(){stop();timer=setInterval(upd,700);}
    function stop(){if(timer)clearInterval(timer);}
    function buildCash(){
      content.innerHTML='<div style="font-size:13px;color:#444;">Enter cash amount given by customer:</div>';
      const inp=document.createElement('input');
      inp.type='number';
      inp.id='paymentCashGiven';
      inp.style='margin-top:8px;padding:8px;width:100%;';
      content.append(inp);
      const ch=document.createElement('div');
      ch.id='paymentChange';
      ch.style='margin-top:8px;font-weight:700;';
      ch.textContent='Change: ₱0.00';
      content.append(ch);
      inp.oninput=async()=>{
        const g=parseFloat(inp.value||0);
        const n=(typeof window.computeNumbers==='function')?window.computeNumbers():await computeTotalsFromOrder();
        const c=g-(n.payable||0);
        ch.textContent='Change: ₱'+(c>=0?c.toFixed(2):'0.00');
      };
      start();upd();
    }
    function buildGC(){
      content.innerHTML='<div style="font-size:13px;color:#444;">Enter payer name & reference (GCash):</div>';
      const n=document.createElement('input');n.id='paymentGcashName';n.placeholder='Payer name';n.style='margin-top:8px;padding:8px;width:100%;';
      const r=document.createElement('input');r.id='paymentGcashRef';r.placeholder='GCash ref';r.style='margin-top:8px;padding:8px;width:100%;';
      content.append(n,r);start();upd();
    }
    function buildBank(){
      content.innerHTML='<div style="font-size:13px;color:#444;">Enter payer name & bank ref:</div>';
      const n=document.createElement('input');n.id='paymentBankName';n.placeholder='Payer name';n.style='margin-top:8px;padding:8px;width:100%;';
      const r=document.createElement('input');r.id='paymentBankRef';r.placeholder='Bank ref';r.style='margin-top:8px;padding:8px;width:100%;';
      content.append(n,r);start();upd();
    }
    function select(m){
      selected=m;
      Object.keys(mBtns).forEach(k=>{mBtns[k].style.borderColor=(k===m)?'#000':'#ddd';});
      stop();
      if(m==='Cash')buildCash();
      else if(m==='GCash')buildGC();
      else buildBank();
    }
    overlay.modalApi={getSelectedMethod:()=>selected,close:()=>{stop();overlay.remove();}};
    select('Cash');
    return overlay;
  }

  function collectItemsForPayload(){
    const arr=readOrderArrayBestEffort();
    return arr.map(i=>({menu_item_id:i.id||null,item_name:i.name||'',qty:Number(i.qty||1),unit_price:Number(i.price||0),line_total:Number(i.line_total||0)}));
  }

  async function getTotalsForPayload(){
    try{
      if(typeof window.computeNumbers==='function'){
        const n=window.computeNumbers();
        if(n&&typeof n.payable!=='undefined')return n;
      }
    }catch(e){}
    return await computeTotalsFromOrder();
  }

  async function saveSaleToServer(p){
    const r=await fetch('php/save_and_print.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(p)});
    const t=await r.text();
    try{return JSON.parse(t);}catch(e){return{ok:false,raw:t};}
  }

  function clearOrderUI(){
    try{if(typeof clearOrder==='function')return clearOrder();}catch(e){}
    try{window.order=[];}catch(e){}
    if(typeof renderOrder==='function')renderOrder();
  }

  async function proceedSaveFlow(method,details,modal){
    const items = collectItemsForPayload();

    let reserved = null;
    try {
      const raw = sessionStorage.getItem('clubtryara:selected_table_v1');
      if (raw) reserved = JSON.parse(raw);
    } catch (e) {}

    const hasItems = items && items.length > 0;
    const hasCabin = !!reserved;

    if (!hasItems && !hasCabin) {
      alert('Please add items or select a cabin before saving.');
      return;
    }

    const totals = await getTotalsForPayload();

    const payload = {
      items,
      totals: Object.assign({}, totals, {
        discountType: window.discountType || 'Regular',
        discountRate: window.discountRate || 0
      }),
      payment_method: method,
      payment_details: details || {},
      table: reserved || null,
      note: window.orderNote || '' // ✅ include note
    };

    const btn=$id('paymentConfirmBtn');
    if(btn){btn.disabled=true;btn.textContent='Saving...';}
    try{
      const res=await saveSaleToServer(payload);
      const id=res.id||0;
      const meta={
        sale_id:id,
        payment_method:method,
        payment_details:details,
        cashGiven:details.given||details.cashGiven||0,
        change:details.change||0,
        discountType:window.discountType||'Regular',
        discountRate:window.discountRate||0
      };
      const form=document.createElement('form');
      form.action='php/print_receipt_payment.php';
      form.method='POST';
      form.innerHTML=`
        <input type="hidden" name="cart" value='${JSON.stringify(items)}'>
        <input type="hidden" name="totals" value='${JSON.stringify(totals)}'>
        <input type="hidden" name="reserved" value='${JSON.stringify(reserved || {})}'>
        <input type="hidden" name="meta" value='${JSON.stringify(meta)}'>
        <input type="hidden" name="note" value='${(window.orderNote || "").replace(/'/g,"&#39;").replace(/"/g,"&quot;")}'>
      `;
      document.body.appendChild(form);
      form.submit();

      clearOrderUI();
      if(modal)modal.close();
      alert('Sale saved (ID:'+id+')');
    }catch(e){alert('Save error:'+e);}
    if(btn){btn.disabled=false;btn.textContent='Save & Print';}
  }

  function wireProceed(){
    const b=document.getElementById('proceedBtn')||document.querySelector('.proceed-btn');
    if(!b)return;
    b.onclick=e=>{
      e.preventDefault();
      const o=createPaymentModal();
      const m=o.modalApi;
      const c=$id('paymentConfirmBtn');
      if(!c)return;
      c.onclick=async()=>{
        const sel=m.getSelectedMethod();
        if(!sel){alert('Select method');return;}
        let det={};
        if(sel==='Cash'){
          const g=+$id('paymentCashGiven')?.value||0;
          const n=await getTotalsForPayload();
          det={given:g,change:g-(n.payable||0)};
        }else if(sel==='GCash'){
          det={name:$id('paymentGcashName')?.value||'',ref:$id('paymentGcashRef')?.value||''};
        }else{
          det={name:$id('paymentBankName')?.value||'',ref:$id('paymentBankRef')?.value||''};
        }
        await proceedSaveFlow(sel.toLowerCase().replace(/\s+/g,'_'),det,m);
      };
    };
  }

  if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',wireProceed);
  else wireProceed();

  window.appPayments={openPaymentModal:function(m){const o=createPaymentModal();if(m)o.modalApi.selectMethod(m);return o.modalApi;}};

  document.addEventListener('click',e=>{
    const btn=e.target.closest('.discount-btn');
    if(!btn)return;
    const type=btn.dataset.type||btn.textContent.trim();
    window.discountType=type;
    if(/senior/i.test(type))window.discountRate=0.20;
    else if(/pwd/i.test(type))window.discountRate=0.15;
    else window.discountRate=0;
  });

  document.querySelectorAll('.discount-btn').forEach(btn=>{
    btn.addEventListener('click',()=>{
      const type=btn.dataset.type;
      let rate=0;
      if(type==='Senior Citizen')rate=0.20;
      else if(type==='PWD')rate=0.15;
      else rate=0;
      window.discountType=type;
      window.discountRate=rate;
      console.log('Discount set:',window.discountType,window.discountRate);
    });
  });

})();
