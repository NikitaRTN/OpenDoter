(() => {
 'use strict';
 const root = document.querySelector('[data-profile-workspace]');
 if (!root) return;
 const form = root.querySelector('[data-profile-filters]');
 const rows = root.querySelector('[data-profile-rows]');
 const summary = root.querySelector('[data-profile-summary]');
 const status = root.querySelector('[data-profile-status]');
 const prev = root.querySelector('[data-page-prev]'), next = root.querySelector('[data-page-next]');
 const heroes = JSON.parse(root.querySelector('[data-profile-heroes]').textContent);
 const names = {combat:'Боевой вклад',survival:'Выживаемость',damage:'Урон героям',push:'Пуш',economy:'Экономика',support:'Поддержка'};
 const modes = {23:'Turbo',22:'All Pick',2:'Captain’s Mode',3:'Random Draft',4:'Single Draft',5:'All Random',13:'Режим 13'};
 const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
 const fmt = n => n == null ? 'Нет данных' : new Intl.NumberFormat('ru-RU', {maximumFractionDigits:0}).format(n);
 const clock = s => `${Math.floor(s / 60)}:${String(s % 60).padStart(2,'0')}`;
 const base = new URL(window.location.href); base.search = ''; base.hash = '';
 let page = 1, abort = null, request = 0, current = null;
 function query() {
   const q = new URLSearchParams();
   for (const [key,value] of new FormData(form)) if (value !== '') q.set(key,value);
   if(page > 1) q.set('page',String(page));
   return q;
 }
 function restore() {
   const q = new URLSearchParams(location.search); form.reset();
   for (const el of form.elements) if(el.name && q.has(el.name)) {
     if(el.name==='patch' && ![...el.options].some(o=>o.value===q.get(el.name))) el.add(new Option(`Патч ${q.get(el.name)}`,q.get(el.name)));
     el.value=q.get(el.name);
   }
   // Old turbo=1 links naturally mean all modes; default now includes Turbo.
   page=Math.min(10000,Math.max(1,Number(q.get('page'))||1));
 }
 function notify(msg,error=false) {status.textContent=msg;status.classList.toggle('pw-error',error);}
 function cards(s) {
   const c = [
     ['OD Impact v1',s.impact==null?'Нет оценки':`${fmt(s.impact)} / 1000`,`${s.rated_games} из ${s.games} матчей оценены; покрытие метрик ${s.coverage}%`],
     ['Урон героям / мин',fmt(s.hero_dpm),`Данные: ${s.hero_dpm_games} матчей`],
     ['Урон зданиям / мин',fmt(s.tower_dpm),`Данные: ${s.tower_dpm_games} матчей`],
     ['Победы в выборке',s.games?`${Math.round(s.wins/s.games*100)}%`:'Нет данных',`${s.wins} побед из ${s.games} матчей`]
   ];
   return c.map(([a,b,c])=>`<div class="pw-stat"><span>${esc(a)}</span><strong>${esc(b)}</strong><small>${esc(c)}</small></div>`).join('');
 }
 function render(data, browserCache=false) {
   current=data;page=data.page;
   const chosen=form.elements.patch.value;
   const patches=data.patches||[];
   if(patches.length){
     form.elements.patch.innerHTML='<option value="">Все патчи</option>'+patches.slice().reverse().map(p=>`<option value="${esc(p.id)}">${esc(p.name)}</option>`).join('');
     if(chosen && !patches.some(p=>String(p.id)===chosen)) form.elements.patch.add(new Option(`Патч ${chosen}`,chosen));
     form.elements.patch.value=chosen;
   }
   summary.innerHTML=cards(data.summary)+`<p class="pw-summary-note">Сводка: последние ${data.summary.games} подходящих матчей (максимум 100), не вся история аккаунта. ${Object.keys(data.summary.modes).length>1?'Смешаны разные режимы — для сравнения выберите один.':''} Оценка приблизительная; линия известна в ${data.summary.roles_known} матчах.</p>`;
   rows.innerHTML=data.matches.length?data.matches.map((m,i)=>{
     const h=heroes[String(m.hero_id)]||{};const name=h.localized_name||`Герой ${m.hero_id}`;
     const image=h.name?`https://cdn.cloudflare.steamstatic.com/apps/dota2/images/dota_react/heroes/${h.name.replace('npc_dota_hero_','')}.png`:'';
     const won=Boolean(m.radiant_win)===(Number(m.player_slot)<128);
     const url=new URL(`matches/${encodeURIComponent(m.match_id)}/overview`,window.OD_BASE?new URL(window.OD_BASE,location.origin):location.origin+'/');
     const dt=m.start_time?new Date(m.start_time*1000).toLocaleString('ru-RU',{day:'2-digit',month:'2-digit',year:'numeric',hour:'2-digit',minute:'2-digit'}):'Нет даты';
     return `<tr><td><a class="pw-match-link" href="${esc(url.href)}">${image?`<img loading="lazy" src="${esc(image)}" alt="">`:''}<span>${esc(name)}<small>#${esc(m.match_id)}</small></span></a></td><td class="${won?'pw-win':'pw-loss'}">${won?'Победа':'Поражение'}</td><td>${esc(modes[m.game_mode]||`Режим ${m.game_mode}`)}</td><td>${fmt(m.kills)} / ${fmt(m.deaths)} / ${fmt(m.assists)}</td><td>${fmt(m.hero_damage)}</td><td>${fmt(m.tower_damage)}</td><td><button type="button" class="pw-score" data-score="${i}">${m.impact.score==null?'Нет оценки':`${fmt(m.impact.score)} / 1000`}</button><small>${m.impact.coverage}% метрик</small></td><td>${esc(clock(m.duration||0))}<small>${esc(dt)}</small></td></tr>`;
   }).join(''):'<tr><td colspan="8">Матчей с такими фильтрами нет. Попробуйте убрать линию, патч или ограничение по дате.</td></tr>';
   prev.disabled=page<=1;next.disabled=!data.has_next;
   root.querySelector('[data-page-label]').textContent=`Страница ${page}`;
   const age=data.cache.age_seconds;
   notify(data.cache.stale?`Источник недоступен: показан устаревший кэш (${Math.round(age/60)} мин.). Повторите обновление позже.`:`${browserCache?'Из кэша браузера. ':''}История получена ${age<60?'меньше минуты':Math.floor(age/60)+' мин.'} назад. Кэш — 5 минут. Turbo ${form.elements.game_mode.value==='23'?'выбран':form.elements.game_mode.value?'не включён в выбранный режим':'включён'}.`,data.cache.stale);
   root.querySelector('[data-match-breakdown]').hidden=true;
 }
 async function load({push=false,refresh=false}={}) {
   if(abort) abort.abort();abort=new AbortController();const token=++request;
   const q=query();const publicURL=new URL(base);publicURL.search=q;
   if(push) history.pushState({},'',publicURL);
   const key=`od-profile-v1:${root.dataset.account}:${q}`;
   let cached=null;
   try {cached=JSON.parse(sessionStorage.getItem(key));}catch{}
   if(cached){
     const age=(cached.data.cache.age_seconds||0)+Math.max(0,(Date.now()-cached.at)/1000);
     cached.data.cache={...cached.data.cache,age_seconds:Math.floor(age),stale:age>=300||cached.data.cache.stale};
     if(!refresh && age<300 && !cached.data.cache.stale){render(cached.data,true);root.removeAttribute('aria-busy');return;}
     render(cached.data,true);
   }
   notify('Обновляем историю…');root.setAttribute('aria-busy','true');prev.disabled=true;next.disabled=true;
   q.set('data','insights');if(refresh)q.set('refresh','1');
   const url=new URL(base);url.search=q;
   try{
     const res=await fetch(url,{signal:abort.signal,headers:{Accept:'application/json'}});
     const data=await res.json();if(!res.ok||!Array.isArray(data.matches))throw Error(data.error||data.message||`HTTP ${res.status}`);
     if(token!==request)return;
     render(data);
     if(!data.cache.stale)try{sessionStorage.setItem(key,JSON.stringify({at:Date.now(),data}));}catch{}
   }catch(err){
     if(err.name!=='AbortError'&&token===request){notify(`Не удалось обновить историю. ${current?'Сохранены ранее показанные данные; они могут не соответствовать новым фильтрам. ':''}${err.message}`,true);prev.disabled=true;next.disabled=true;}
   }finally{if(token===request)root.removeAttribute('aria-busy');}
 }
 form.addEventListener('submit',e=>{e.preventDefault();page=1;load({push:true});});
 root.querySelector('[data-reset]').addEventListener('click',()=>{form.reset();page=1;load({push:true});});
 root.querySelector('[data-refresh]').addEventListener('click',()=>load({refresh:true}));
 prev.addEventListener('click',()=>{if(page>1){page--;load({push:true});}});
 next.addEventListener('click',()=>{page++;load({push:true});});
 window.addEventListener('popstate',()=>{restore();load();});
 root.querySelector('[data-hero-search]').addEventListener('input',e=>{
   let visible=0;const text=e.target.value.trim().toLocaleLowerCase();
   root.querySelectorAll('[data-hero-name]').forEach(row=>{row.hidden=!row.dataset.heroName.toLocaleLowerCase().includes(text);if(!row.hidden)visible++;});
   root.querySelector('[data-hero-empty]').hidden=visible>0;
 });
 let sortKey='games',descending=true;
 root.addEventListener('click',e=>{
   const hero=e.target.closest('[data-select-hero]');if(hero){form.elements.hero_id.value=hero.dataset.selectHero;page=1;load({push:true});root.querySelector('#profile-history').scrollIntoView({behavior:'auto'});}
   const sort=e.target.closest('[data-hero-sort]');if(sort){const key=sort.dataset.heroSort;descending=key===sortKey?!descending:true;sortKey=key;const body=root.querySelector('[data-hero-list]');[...body.rows].sort((a,b)=>(Number(b.dataset[key])-Number(a.dataset[key]))*(descending?1:-1)).forEach(r=>body.append(r));}
   const score=e.target.closest('[data-score]');if(score&&current){
     const m=current.matches[Number(score.dataset.score)];const details=root.querySelector('[data-match-breakdown]');
     const content=Object.entries(m.impact.components).map(([key,v])=>`<li>${esc(names[key])}: <strong>${fmt(v.score)} / 1000</strong>, вес ${fmt(v.weight)}%</li>`).join('');
     root.querySelector('[data-breakdown-content]').innerHTML=`<p>Матч #${esc(m.match_id)}: ${fmt(m.impact.score)}${m.impact.score==null?'':' / 1000'}. Покрытие: ${m.impact.coverage}%. Линия ${m.impact.role_known?'известна':'неизвестна'}; настоящая позиция игрока не определена.</p><ul>${content||'<li>Недостаточно данных для оценки.</li>'}</ul><p>Компоненты объединяются с доступными весами; отсутствующие показатели не получают ноль. Оценка не устанавливает намерения игрока.</p>`;
     details.hidden=false;details.open=true;details.scrollIntoView({behavior:'auto'});
   }
 });
 restore();load();
})();
