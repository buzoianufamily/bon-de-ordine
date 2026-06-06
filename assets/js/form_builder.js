/* Builder formular: campuri dinamice + preview live + salvare JSON */
(function(){
  const F = window.FORM || {};
  const $ = id => document.getElementById(id);
  const uid = () => 'f' + Math.random().toString(36).slice(2,7);
  const TYPES = {text:'Text scurt',tel:'Telefon',email:'Email',number:'Numar',textarea:'Text lung',select:'Lista (dropdown)',checkbox:'Bifa (da/nu)'};
  const esc = s => String(s==null?'':s).replace(/[<>&"]/g,c=>({'<':'&lt;','>':'&gt;','&':'&amp;','"':'&quot;'}[c]));

  let fields = (F.fields||[]).map(f=>({key:f.key||uid(),label:f.label||'',type:f.type||'text',required:!!f.required,options:f.options||[]}));

  const wrap = $('fields');
  function render(){
    wrap.innerHTML = fields.map((f,i)=>`
      <div class="fld" data-i="${i}">
        <div class="hd"><span class="h">Camp ${i+1}</span>
          <span style="flex:1"></span>
          <button type="button" class="iconbtn" data-act="up" ${i===0?'disabled':''}>↑</button>
          <button type="button" class="iconbtn" data-act="down" ${i===fields.length-1?'disabled':''}>↓</button>
          <button type="button" class="iconbtn del" data-act="del">🗑</button>
        </div>
        <div class="row2">
          <div><label>Eticheta</label><input data-f="label" value="${esc(f.label)}" placeholder="ex: Nume complet"></div>
          <div><label>Tip</label><select data-f="type">${Object.entries(TYPES).map(([v,n])=>`<option value="${v}" ${v===f.type?'selected':''}>${n}</option>`).join('')}</select></div>
        </div>
        <div data-opts style="margin-top:.6rem;${f.type==='select'?'':'display:none'}">
          <label>Optiuni (cate una pe linie)</label><textarea data-f="options" rows="3" placeholder="Optiune 1&#10;Optiune 2">${esc((f.options||[]).join('\n'))}</textarea>
        </div>
        <label class="chk" style="margin-top:.6rem"><input type="checkbox" data-f="required" ${f.required?'checked':''}> Camp obligatoriu</label>
      </div>`).join('') || '<p class="muted">Niciun camp. Apasa „Adauga camp".</p>';

    wrap.querySelectorAll('.fld').forEach(el=>{
      const i = +el.dataset.i;
      el.querySelector('[data-f="label"]').addEventListener('input',e=>{ fields[i].label=e.target.value; preview(); });
      el.querySelector('[data-f="type"]').addEventListener('change',e=>{ fields[i].type=e.target.value;
        el.querySelector('[data-opts]').style.display = e.target.value==='select'?'':'none'; preview(); });
      el.querySelector('[data-f="required"]').addEventListener('change',e=>{ fields[i].required=e.target.checked; preview(); });
      const opt=el.querySelector('[data-f="options"]'); if(opt) opt.addEventListener('input',e=>{ fields[i].options=e.target.value.split('\n').map(s=>s.trim()).filter(Boolean); preview(); });
      el.querySelectorAll('[data-act]').forEach(b=> b.onclick=()=>{
        const act=b.dataset.act;
        if(act==='del') fields.splice(i,1);
        else if(act==='up' && i>0) [fields[i-1],fields[i]]=[fields[i],fields[i-1]];
        else if(act==='down' && i<fields.length-1) [fields[i+1],fields[i]]=[fields[i],fields[i+1]];
        render(); preview();
      });
    });
  }

  function preview(){
    $('pvName').textContent = $('formName').value || 'Formular';
    $('pvFields').innerHTML = fields.map(f=>{
      const lab = esc(f.label||'(fara eticheta)') + (f.required?' <span style="color:#fca5a5">*</span>':'');
      if(f.type==='checkbox') return `<div class="pv-f"><label class="chk"><input type="checkbox" disabled> ${lab}</label></div>`;
      let inp;
      if(f.type==='textarea') inp='<textarea rows="2" disabled></textarea>';
      else if(f.type==='select') inp=`<select disabled><option>— alege —</option>${(f.options||[]).map(o=>`<option>${esc(o)}</option>`).join('')}</select>`;
      else inp=`<input type="${f.type}" disabled>`;
      return `<div class="pv-f"><label>${lab}</label>${inp}</div>`;
    }).join('') || '<p class="muted">Adauga campuri ca sa vezi formularul.</p>';
  }

  $('addField').onclick=()=>{ fields.push({key:uid(),label:'',type:'text',required:false,options:[]}); render(); preview(); };
  $('formName').addEventListener('input', preview);

  $('btnSave').onclick=async()=>{
    const name=$('formName').value.trim();
    if(!name){ QMS.toast('Pune un nume formularului','error'); return; }
    const clean = fields.filter(f=>f.label.trim()).map(f=>({key:f.key,label:f.label.trim(),type:f.type,required:!!f.required,options:f.type==='select'?(f.options||[]):[]}));
    const b=$('btnSave'); b.textContent='Se salveaza…'; b.disabled=true;
    const res=await QMS.api(F.saveUrl,{id:F.id,name,fields:clean});
    b.disabled=false; b.textContent='💾 Salveaza';
    if(res.ok){ F.id=res.id; QMS.toast('Formular salvat ✔','ok'); } else QMS.toast(res.error||'Eroare','error');
  };

  render(); preview();
})();
