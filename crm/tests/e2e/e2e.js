// Full end-to-end run through the real UI on a fresh MariaDB database.
const {chromium}=require('playwright');const fs=require('fs');
const B='http://127.0.0.1:8124', PW='Strong12345pass', SP=process.argv[2], RUN=process.argv[3]||'1';
const issues=[];const step=()=>process.stdout.write('.');
(async()=>{const b=await chromium.launch();
const newPage=async(vp={width:1280,height:900})=>{const c=await b.newContext({viewport:vp,acceptDownloads:true});const p=await c.newPage();
  p.on('console',m=>{if(m.type()==='error'&&!(/status of (403|404)/.test(m.text())))issues.push(`[console] ${p.url()} ${m.text().slice(0,160)}`)});
  p.on('pageerror',e=>issues.push(`[js] ${e.message}`));
  p.on('response',r=>{if(r.status()>=500)issues.push(`[${r.status()}] ${r.url()}`)});return {c,p}};
const expect=(cond,msg)=>{if(!cond)issues.push('[fail] '+msg)};
const has=async(p,text)=>(await p.content()).includes(text);
const login=async(p,email)=>{await p.goto(B+'/login');await p.fill('#email',email);await p.fill('#password',PW);await Promise.all([p.waitForNavigation(),p.click('form button.p')]);};
const logout=async(p)=>{await Promise.all([p.waitForNavigation(),p.click('form[action$="/logout"] button')]);};
const overflow=async(p)=>p.evaluate(()=>document.documentElement.scrollWidth-innerWidth);

// 1. Setup wizard
let {c,p}=await newPage();globalThis.P=p;
await p.goto(B+'/');expect(p.url().endsWith('/setup'),'first visit goes to setup');
await p.fill('#company_name','E2E Pharma Pvt Ltd');await p.fill('#gstin','03ABCDE1234F1Z5');
await p.fill('#admin_name','Owner Admin');await p.fill('#admin_email','owner@e2e.in');await p.fill('#password',PW);await p.fill('#password_confirmation',PW);
await Promise.all([p.waitForNavigation(),p.click('form button.p')]);step();
expect(await has(p,'E2E Pharma Pvt Ltd'),'company name shown after setup');
expect(await has(p,'Solvix Web')&&await has(p,'82190 60177')&&await has(p,'Damini Rana'),'credit footer shown');
// 2. Team
const addUser=async(name,email,role,locale,mgr)=>{await p.goto(B+'/users/new');await p.fill('#name',name);await p.fill('#email',email);await p.selectOption('#role',role);await p.selectOption('#locale',locale);
  if(mgr)await p.selectOption('#manager_id',{label:mgr});await p.fill('#password',PW);await Promise.all([p.waitForNavigation(),p.click('form button.p')]);expect(await has(p,name),'user added '+name)};
await addUser('Mona Manager','mgr@e2e.in','manager','hing');
await addUser('Rita Rep','rita@e2e.in','rep','hi','Mona Manager');
await addUser('Sohan Rep','sohan@e2e.in','rep','hing','Mona Manager');step();
// 3. Targets
await p.goto(B+'/targets');const tInputs=await p.$$('input[name^="targets["]');expect(tInputs.length===3,'3 target inputs, got '+tInputs.length);
for(const i of tInputs)await i.fill('500000');await Promise.all([p.waitForNavigation(),p.click('form[action*="/targets"] button.p')]);expect(await has(p,'₹5.00 L'),'targets saved');step();
await logout(p);

// 4. Rita (Hindi): punch in, add lead, log call, record sale
await login(p,'rita@e2e.in');expect(await has(p,'लीड्स'),'Hindi UI for Rita');
await Promise.all([p.waitForNavigation(),p.click('form[action$="/attendance/punch"] button')]);expect(await has(p,'पंच आउट'),'after punch in, button shows punch out');
await p.goto(B+'/leads/new');await p.fill('#firm_name','Clash Firm');await p.fill('#phone','+91 98111 00099');await p.fill('#city','Indore');await p.fill('#expected_value','300000');
await Promise.all([p.waitForNavigation(),p.click('form button.p')]);expect(/\/leads\/\d+$/.test(p.url()),'lead created');
await p.selectOption('#type','call');await p.selectOption('#outcome','connected');await p.fill('#note','Called, interested in PCD');await p.selectOption('#a_status','interested');
await Promise.all([p.waitForNavigation(),p.click('form[action$="/activity"] button.p')]);expect(await has(p,'Called, interested in PCD'),'activity logged');
await p.goto(B+'/targets');await p.selectOption('#lead_id',{label:'Clash Firm'});await p.fill('#amount','120000');
await Promise.all([p.waitForNavigation(),p.click('form[action$="/sales"] button.p')]);expect(await has(p,'₹1,20,000'),'sale recorded');step();
await p.goto(B+'/tasks');await p.fill('#title','Send COA to Clash Firm');await p.selectOption('#priority','high');await Promise.all([p.waitForNavigation(),p.click('form[action$="/tasks"] button.p')]);
expect(await has(p,'Send COA to Clash Firm'),'task created');
await p.goto(B+'/');expect(await has(p,'Send COA to Clash Firm'),'task shows in dashboard reminders');
await p.goto(B+'/tasks');await Promise.all([p.waitForNavigation(),p.selectOption('select[data-autosubmit][name="status"]','done')]);expect(await p.$eval('select[data-autosubmit][name="status"]',e=>e.value)==='done','task completed');
await p.fill('#gq','98111 00099');await Promise.all([p.waitForNavigation(),p.press('#gq','Enter')]);expect(await has(p,'Clash Firm'),'top search finds lead by phone');
// mobile check for Rita
{const m=await newPage({width:390,height:844});await login(m.p,'rita@e2e.in');for(const u of ['/','/leads','/targets','/attendance/me']){await m.p.goto(B+u);const o=await overflow(m.p);expect(o<=0,'mobile overflow '+u+' '+o)}
 await m.p.click('[data-menu]');expect(await m.p.$eval('.sidebar',e=>e.classList.contains('open')),'mobile menu opens');await m.p.mouse.click(370,600);expect(!(await m.p.$eval('.sidebar',e=>e.classList.contains('open'))),'mobile menu closes on outside tap');await m.c.close();}
await logout(p);

// 5. Sohan: same client → clash; import file
await login(p,'sohan@e2e.in');
await p.goto(B+'/leads/new');await p.fill('#firm_name','Clash Firm Copy');await p.fill('#phone','9811100099');await Promise.all([p.waitForNavigation(),p.click('form button.p')]);
expect(p.url().endsWith('/leads'),'clash redirects to list');expect(await has(p,'flash warn'),'clash warning shown');expect(!(await has(p,'Rita')),'Sohan not told who owns it');
await p.goto(B+'/leads/import');await p.setInputFiles('#file',SP+'/e2e-import.xlsx');await Promise.all([p.waitForNavigation(),p.click('form[enctype] button.p')]);
const kpis=await p.$$eval('.kpi .v',e=>e.map(x=>x.textContent.trim()));expect(kpis.slice(0,4).join(',')==='2,0,1,0','import result 2 added/0/1 clash/0 skipped, got '+kpis.join(','));
await p.goto(B+'/leads');expect(await has(p,'Import One')&&!(await has(p,'Clash Firm<')),'Sohan sees own imports only');
expect((await p.goto(B+'/clashes')).status()===403,'rep blocked from clashes');step();
await p.goto(B+'/leads');
await logout(p);

// 6. Manager: resolve clash, confirm sale, today board, export
await login(p,'mgr@e2e.in');
await p.goto(B+'/clashes');const opens=await p.$$('form[action*="/resolve"]');expect(opens.length===1,'one open clash (repeat attempts merged), got '+opens.length);
await p.selectOption('form[action*="/resolve"] select',{label:'Sohan Rep'});await Promise.all([p.waitForNavigation(),p.click('form[action*="/resolve"] button')]);expect(await has(p,'Sohan Rep'),'clash resolved to Sohan');
await p.goto(B+'/targets');await Promise.all([p.waitForNavigation(),p.selectOption('select[data-autosubmit][name="status"]','confirmed')]);
await p.goto(B+'/attendance/today');expect(await has(p,'Abhi kaam par')||await has(p,'Working now'),'Rita shown working now');
await p.goto(B+'/products/new');await p.fill('#name','E2E Pan-D');await p.fill('#composition','Pantoprazole 40 mg + Domperidone 30 mg');await p.fill('#rate','22.4');await p.fill('#moq','10000');
await Promise.all([p.waitForNavigation(),p.click('form[action$="/products"] button.p')]);expect(await has(p,'E2E Pan-D'),'manager adds product');
expect((await p.goto(B+'/analytics')).status()===200,'analytics opens for manager');
expect((await p.goto(B+'/customers')).status()===200,'customers opens');
await p.goto(B+'/leads');
const [dl]=await Promise.all([p.waitForEvent('download'),p.click('a[href*="export"][href*="xlsx"]')]);const dpath=await dl.path();expect(fs.statSync(dpath).size>3000,'export file has content');step();
await logout(p);

// 7. Sohan now owns Clash Firm; Rita does not
await login(p,'sohan@e2e.in');await p.goto(B+'/leads');expect(await has(p,'Clash Firm'),'Sohan now sees Clash Firm');
expect(!(await has(p,'/leads/export')),'rep has no export button by default');
await p.goto(B+'/products');expect(await has(p,'E2E Pan-D')&&!(await has(p,'/products/1/edit')),'rep sees products read-only');await logout(p);
await login(p,'rita@e2e.in');await p.goto(B+'/leads');expect(!(await has(p,'Clash Firm')),'Rita no longer sees Clash Firm');
await p.goto(B+'/targets');expect(await has(p,'₹1.20 L'),'Rita sale confirmed counts in target');
await Promise.all([p.waitForNavigation(),p.click('form[action$="/attendance/punch"] button')]);expect(await has(p,'पंच इन'),'punch out works');
await logout(p);

// 8. Admin: person leaving, audit, settings
await login(p,'owner@e2e.in');await p.goto(B+'/users');
const ritaEdit=await p.$$eval('tr',rows=>{const r=rows.find(x=>x.textContent.includes('Rita Rep'));return r?r.querySelector('a').href:null});
await p.goto(ritaEdit);await p.uncheck('input[name="active"]');await p.selectOption('#move_leads_to',{label:'Sohan Rep'});await Promise.all([p.waitForNavigation(),p.click('form button.p')]);
await p.goto(B+'/audit');expect(await has(p,'lead.export')&&await has(p,'clash.resolve')&&await has(p,'lead.import'),'audit log has export/import/clash');
await p.goto(B+'/office-hours');await p.fill('#office_start','10:00');await Promise.all([p.waitForNavigation(),p.click('form[action$="/office-hours"] button.p')]);expect(await has(p,'flash ok'),'office hours saved');
await p.goto(B+'/settings');await p.fill('#company_name','E2E Pharma Pvt Ltd');await Promise.all([p.waitForNavigation(),p.click('form[action$="/settings"] button.p')]);expect(await has(p,'flash ok'),'settings saved');
await p.goto(B+'/roles');await p.check('input[name="perm[rep][export_leads]"]');await Promise.all([p.waitForNavigation(),p.click('form[action$="/roles"] button.p')]);expect(await has(p,'flash ok'),'permissions saved');
await p.goto(B+'/masters');await p.fill('#newsource','E2E Expo');await Promise.all([p.waitForNavigation(),p.click('form[action$="/masters"]:has(#newsource) button')]);expect(await has(p,'E2E Expo'),'master value added');
for(const u of ['/','/leads','/customers','/tasks','/products','/analytics','/search?q=e2e','/clashes','/targets','/attendance/today','/attendance/register','/office-hours','/users','/roles','/masters','/settings','/audit','/notifications','/profile']){const r=await p.goto(B+u);expect(r.status()===200,'admin page '+u+' '+r.status());expect(await has(p,'Solvix Web'),'credit on '+u)}
await logout(p);
await login(p,'sohan@e2e.in');await p.goto(B+'/leads');expect(await has(p,'/leads/export'),'rep export button appears after admin allows it');await logout(p);
await p.goto(B+'/login');await p.fill('#email','rita@e2e.in');await p.fill('#password',PW);await p.click('form button.p');await p.waitForLoadState();expect(await has(p,'flash bad'),'deactivated Rita cannot log in');
// 9. WhatsApp, website form, away cover, inbox, quotation, marketing
const crypto=require('crypto');
await login(p,'owner@e2e.in');
await p.goto(B+'/whatsapp');await p.fill('#access_token','e2e-fake-token');await p.fill('#app_secret','e2e-app-secret');
await Promise.all([p.waitForNavigation(),p.click('form[action$="/whatsapp"] button.p')]);expect(await has(p,'flash ok'),'WhatsApp settings saved');
expect(!(await has(p,'e2e-fake-token'))&&!(await has(p,'e2e-app-secret')),'saved secrets never shown again');
const vtok=await p.$$eval('dl.dl dt',dts=>{const i=dts.findIndex(d=>/Verify|सत्यापन/.test(d.textContent));return i<0?'':dts[i].nextElementSibling.textContent.trim()});
const addForm='form[action$="/whatsapp/numbers"]';
await p.fill(addForm+' input[name="label"]','Sohan WhatsApp');await p.fill(addForm+' input[name="display_phone"]','+91 90000 00001');await p.fill(addForm+' input[name="phone_number_id"]','109876543210987');
await p.selectOption(addForm+' select[name="user_id"]',{label:'Sohan Rep'});await Promise.all([p.waitForNavigation(),p.click(addForm+' button')]);expect(await has(p,'Sohan WhatsApp'),'WhatsApp number added');
const formUrl=(await p.$eval('p.secret',e=>e.textContent.trim()));
let r=await p.request.get(B+'/webhook/whatsapp?hub.mode=subscribe&hub.verify_token='+encodeURIComponent(vtok)+'&hub.challenge=4242');expect(vtok.length>10&&r.status()===200&&(await r.text())==='4242','Meta webhook verification works');
r=await p.request.get(B+'/webhook/whatsapp?hub.mode=subscribe&hub.verify_token=wrong&hub.challenge=4242');expect(r.status()===403,'wrong verify token refused '+r.status());
r=await p.request.post(formUrl,{form:{name:'Web Visitor',phone:'+91 97000 11122',firm:'Web Enquiry Firm',city:'Pune',message:'Need PCD franchise list',website:''}});expect(r.status()===200,'website form accepted '+r.status());
r=await p.request.post(B+'/webhook/lead/wrongtoken',{form:{name:'X',phone:'9999999999'}});expect(r.status()===404,'website form with wrong token refused '+r.status());
await p.goto(B+'/leads?q=Web+Enquiry');expect(await has(p,'Web Enquiry Firm'),'website enquiry became a lead');
for(const u of ['/inbox','/quotes','/quotes/new','/campaigns','/campaigns/new','/ready-replies','/whatsapp','/automations','/system']){const rr=await p.goto(B+u);expect(rr.status()===200,'admin page '+u+' '+rr.status());expect(await has(p,'Solvix Web'),'credit on '+u)}
await p.goto(B+'/automations');await Promise.all([p.waitForNavigation(),p.click('form[action$="/automations"] button.p')]);expect(await has(p,'flash ok'),'automations saved');
await p.goto(B+'/ready-replies');await Promise.all([p.waitForNavigation(),p.click('form[action$="/ready-replies"] button.p')]);expect(await has(p,'flash ok'),'ready replies saved');
await p.goto(B+'/campaigns/new');await p.fill('#name','E2E Diwali');await p.fill('#template_name','diwali_offer');await Promise.all([p.waitForNavigation(),p.click('button[name="preview"]')]);
expect(p.url().includes('preview=1'),'campaign count preview works');step();
await logout(p);
// Sohan goes on leave; a client writes in Hindi on his number
await login(p,'sohan@e2e.in');await p.goto(B+'/profile');
const tm=new Date(Date.now()+86400000);await p.selectOption('#away_type','leave');await p.fill('#back_date',tm.toISOString().slice(0,10));
await Promise.all([p.waitForNavigation(),p.click('form[action$="/availability"] button')]);expect(await has(p,'pill warn'),'Sohan marked away');
const sign=b=>'sha256='+crypto.createHmac('sha256','e2e-app-secret').update(b).digest('hex');
const inbound=(text,id)=>JSON.stringify({entry:[{changes:[{value:{metadata:{phone_number_id:'109876543210987'},contacts:[{wa_id:'919811100099',profile:{name:'Clash Client'}}],messages:[{id,from:'919811100099',timestamp:String(Math.floor(Date.now()/1000)),type:'text',text:{body:text}}]}}]}]});
let body=inbound('नमस्ते, कृपया रेट लिस्ट भेजिए','wamid.E2E'+RUN+'a');
r=await p.request.post(B+'/webhook/whatsapp',{headers:{'Content-Type':'application/json','X-Hub-Signature-256':sign(body)},data:body});expect(r.status()===200,'signed webhook accepted '+r.status());
r=await p.request.post(B+'/webhook/whatsapp',{headers:{'Content-Type':'application/json','X-Hub-Signature-256':sign(body)},data:body});expect(r.status()===200,'duplicate webhook accepted (ignored)');
r=await p.request.post(B+'/webhook/whatsapp',{headers:{'Content-Type':'application/json','X-Hub-Signature-256':'sha256=00'},data:inbound('fake','wamid.FAKE'+RUN)});expect(r.status()===403,'unsigned webhook refused '+r.status());
await p.goto(B+'/profile');await Promise.all([p.waitForNavigation(),p.click('form[action$="/availability"] button')]);expect(await has(p,'flash'),'I am back shows welcome');
await p.goto(B+'/inbox');expect(await has(p,'Clash Client')||await has(p,'Clash Firm'),'chat appears in Sohan inbox');
await Promise.all([p.waitForNavigation(),p.click('a.conv')]);
expect(await has(p,'कृपया रेट लिस्ट'),'client message shown');expect((await p.$$('form.sug')).length>0,'ready replies suggested');
expect(/[ऀ-ॿ]/.test(await p.$eval('form.sug .o',e=>e.textContent).catch(()=>'')),'suggestion is in Hindi (client language)');
const bubbles=await p.$$eval('.bubble',e=>e.map(x=>x.className));expect(bubbles.length===2&&bubbles.some(c=>c.includes('auto')),'one inbound + one auto-reply (duplicate ignored), got '+bubbles.join('|'));
await p.fill('#body','Ji, rate list bhej raha hoon');await Promise.all([p.waitForNavigation(),p.click('form.compose button')]);expect(await has(p,'Ji, rate list bhej raha hoon'),'reply recorded (fake token: failed, shown)');
// Quotation with PDF
await p.goto(B+'/quotes/new');await p.selectOption('#lead_id',{label:'Clash Firm, Indore'});await p.selectOption('#p0',{label:'E2E Pan-D'});await p.fill('#q0','1000');
expect((await p.inputValue('#r0'))!=='','rate filled from product');
await Promise.all([p.waitForNavigation(),p.click('form[action$="/quotes"] button.p')]);expect(/\/quotes\/\d+$/.test(p.url()),'quotation created '+p.url());expect(await has(p,'QT-'),'quotation number shown');
const pdfHref=await p.$eval('a[href$="/pdf"]',a=>a.href);r=await p.request.get(pdfHref);const pdf=await r.body();
expect(r.status()===200&&(r.headers()['content-type']||'').includes('pdf')&&pdf.slice(0,4).toString()==='%PDF'&&pdf.length>5000,'quotation PDF downloads');
const sendRes=await Promise.all([p.waitForNavigation(),p.click('form[action$="/send"] button')]);expect(sendRes[0].status()<500&&await has(p,'flash'),'send quote on WhatsApp handled');
expect((await p.goto(B+'/campaigns')).status()===403,'rep blocked from campaigns by default');
expect((await p.goto(B+'/whatsapp')).status()===403,'rep blocked from WhatsApp settings');
{const m=await newPage({width:390,height:844});await login(m.p,'sohan@e2e.in');for(const u of ['/inbox','/quotes','/quotes/new','/profile']){await m.p.goto(B+u);const o=await overflow(m.p);expect(o<=0,'mobile overflow '+u+' '+o)}await m.c.close();}
await p.goto(B+'/');await logout(p);step();
const r404=await p.goto(B+'/nope-page');expect(r404.status()===404&&await has(p,'Solvix Web'),'own 404 page with credit');
await b.close();
console.log(`\nRUN ${RUN}: ${issues.length?'ISSUES':'OK'}`);issues.forEach(i=>console.log('  '+i));process.exit(issues.length?1:0);
})().catch(e=>{console.log('\nCRASH at',globalThis.P&&globalThis.P.url(),e.message);issues.forEach(i=>console.log('  '+i));process.exit(2)});
