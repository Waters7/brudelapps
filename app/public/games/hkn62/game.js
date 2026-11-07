/* BRUDEL HKN Run: presentation and authenticated reward transport. */
(() => {
    'use strict';
    const $ = id => document.getElementById(id), E = window.HknEngine;
    const canvas = $('canvas'), ctx = canvas.getContext('2d');
    const preview = document.body.dataset.preview === 'true';
    let run = E.create(6262026), state = 'ready', token = null, pending = false, saving = false;
    let accumulator = 0, last = 0, jump = false, answer = null, quizKey = '', toastUntil = 0, milestone = 0;
    let W = 1000, H = 450, ground = 350, raf = 0, lifeNotice = 3;
    const reduced = matchMedia('(prefers-reduced-motion: reduce)').matches;
    const format = ticks => { const cs = Math.floor(ticks * 100 / 60); return `${String(Math.floor(cs / 6000)).padStart(2,'0')}:${String(Math.floor(cs / 100) % 60).padStart(2,'0')},${String(cs % 100).padStart(2,'0')}`; };
    function say(message) { $('announcer').textContent = message; }
    function toast(message) { $('toast').textContent = message; $('toast').classList.add('show'); toastUntil = performance.now() + 2200; say(message); }
    async function api(path, body) {
        const response = await fetch(`/games/hkn-62/${path}`, {
            method: body ? 'POST' : 'GET', credentials: 'same-origin',
            headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
            ...(body ? { body: JSON.stringify(body) } : {})
        });
        if (response.redirected || response.status === 401 || response.status === 419) throw new Error('Sesi masuk berakhir. Buka BRUDEL dan masuk kembali.');
        const data = await response.json().catch(() => ({}));
        if (!response.ok) throw new Error(Object.values(data.errors || {}).flat()[0] || data.message || 'Belum tersambung. Periksa koneksi lalu coba lagi.');
        return data;
    }
    function overlay(title, message, button, overline = 'HKN KE-62 / BRUDEL RUN') {
        $('quiz').hidden = true; quizKey = '';
        $('overlay').hidden = false; $('title').textContent = title; $('message').textContent = message;
        $('overline').textContent = overline; $('start').textContent = button;
        $('start').disabled = false; $('pause').disabled = true; $('jump').disabled = true;
    }
    function controls(active) { $('overlay').hidden = active; $('pause').disabled = !active; $('jump').disabled = !active; }
    async function start() {
        if (state === 'loading' || saving) return;
        if (state === 'paused') { state = 'running'; last = 0; accumulator = 0; controls(true); canvas.focus({preventScroll:true}); return; }
        if (pending && !confirm('Hasil finis belum tersimpan. Mulai ulang akan mengganti sesi ini. Lanjutkan?')) return;
        state = 'loading'; $('start').disabled = true; $('start').textContent = 'Menyiapkan lintasan…';
        $('retry-save').hidden = true;
        try {
            const data = preview ? { token: null, seed: 6262026 } : await api('mulai', {});
            const response = await fetch(new URL('trivia.json', document.querySelector('script[src*="engine.js"]').src));
            if (!response.ok && !preview) throw new Error('Pertanyaan belum termuat. Coba lagi.');
            const questions = await response.json();
            token = data.token; run = E.create(data.seed, questions); pending = false; jump = false; answer = null; quizKey = '';
            milestone = 0; lifeNotice = 3; state = 'running'; last = 0; accumulator = 0;
            controls(true); hud(); canvas.focus({preventScroll:true}); say('Mulai! Lompati rintangan menuju 62 kilometer.');
        } catch (error) {
            state = 'ready'; overlay('Belum bisa mulai', error.message, 'Coba lagi');
        }
    }
    function pause() {
        if (state !== 'running') return;
        state = 'paused'; jump = false; accumulator = 0;
        overlay('Ambil napas dulu.', 'Waktu berhenti selama jeda. Sesi hadiah berlaku satu jam sejak mulai.', 'Lanjut berlari');
        $('micro').textContent = 'Tekan P atau tombol Lanjut berlari'; say('Permainan dijeda.');
    }
    async function save() {
        if (!pending || saving) return;
        saving = true; $('retry-save').hidden = true; $('start').disabled = true;
        $('message').textContent = 'Memeriksa lintasan dan menyimpan hadiah ke akunmu…';
        try {
            const data = await api('finis', { token, ticks: run.tick, jumps: run.jumps, answers: run.answers });
            pending = false;
            $('message').textContent = data.awarded ? '62 poin, badge, dan frame HKN ke-62 sudah terbuka! Pasang frame dari halaman profil BRUDEL.' : 'Badge dan frame sudah kamu miliki. Waktu terbaikmu diperbarui jika lebih cepat; hadiah tetap satu kali.';
            $('micro').textContent = `Waktu kali ini ${format(run.tick)} · Terbaik ${format(data.best_ticks)}`;
            say($('message').textContent); await standings();
        } catch (error) {
            $('message').textContent = `Hadiah belum tersimpan. ${error.message}`;
            $('retry-save').hidden = false; say('Finis tercapai, tetapi hadiah belum tersimpan. Coba simpan lagi.');
        } finally { saving = false; $('start').disabled = false; }
    }
    function finish() {
        state = run.status;
        if (state === 'won') {
            overlay('62 km. Kamu berhasil!', preview ? 'Mode latihan: kamu menuntaskan lintasan. Masuk ke BRUDEL untuk memperoleh 62 poin dan badge.' : 'Menyiapkan hadiah…', 'Lari lagi', 'FINISHER / HKN KE-62');
            $('micro').textContent = `Waktu finis ${format(run.tick)}`; say('Selamat! Kamu mencapai finis 62 kilometer.');
            if (!preview) { pending = true; save(); }
        } else {
            overlay('Semangat, coba lagi!', `Kamu sudah menempuh ${(run.distance / 1000).toLocaleString('id-ID',{maximumFractionDigits:2})} km. Perhatikan rintangan dan lompat sedikit lebih awal.`, 'Coba lagi');
            $('micro').textContent = 'Poin dan badge diberikan setelah mencapai 62 km.'; say('Permainan selesai. Coba lagi untuk mencapai finis.');
        }
        $('start').focus({preventScroll:true});
    }
    async function standings() {
        const board = $('board');
        if (preview) { board.textContent = 'Mode latihan · peringkat dan hadiah tersedia setelah masuk BRUDEL.'; board.className = 'empty'; return; }
        try {
            const data = await api('peringkat'); board.replaceChildren();
            if (!data.rows.length) { const p = document.createElement('p'); p.className = 'empty'; p.textContent = 'Belum ada finisher. Jadilah yang pertama!'; board.append(p); }
            for (const row of data.rows) {
                const div = document.createElement('div'); div.className = 'board-row' + (row.mine ? ' mine' : '');
                for (const [className, value] of [['rank',String(row.rank).padStart(2,'0')],['name',row.name],['clock',format(row.ticks)]]) {
                    const span = document.createElement('span'); span.className = className; span.textContent = value; div.append(span);
                }
                board.append(div);
            }
            if (data.mine) $('reward-note').textContent = `Badge sudah diraih · ${data.mine.points} poin tersimpan. ${data.mine.best_ticks ? `Waktu terbaikmu ${format(data.mine.best_ticks)}.` : 'Selesaikan tantangan trivia untuk masuk peringkat baru.'} Main lagi untuk memperbaiki peringkat.`;
        } catch (error) { board.textContent = `Peringkat belum tersedia. ${error.message}`; board.className = 'empty'; }
    }
    function hud() {
        quizHud();
        $('distance').textContent = (run.distance / 1000).toLocaleString('id-ID',{minimumFractionDigits:2,maximumFractionDigits:2});
        $('time').textContent = format(run.tick);
        $('hearts').textContent = '♥ '.repeat(run.lives) + '♡ '.repeat(3-run.lives);
        $('hearts').setAttribute('aria-label',`${run.lives} nyawa`);
        $('progress').style.width = `${run.distance / E.FINISH * 100}%`;
        $('progress').parentElement.setAttribute('aria-valuenow',(run.distance / 1000).toFixed(2));
        const phase = Math.min(2, Math.floor(run.distance / 21000));
        $('stage-tag').textContent = ['01 / PESISIR PAGI','02 / JALUR HIJAU','03 / MENUJU FINIS'][phase];
    }
    function resize() {
        const rect = canvas.getBoundingClientRect(), dpr = Math.min(devicePixelRatio || 1, 2);
        canvas.width = Math.round(rect.width * dpr); canvas.height = Math.round(rect.height * dpr);
        W = rect.width < 600 ? 600 : 1000; H = W * rect.height / rect.width; ground = H * .77;
        ctx.setTransform(canvas.width / W, 0, 0, canvas.height / H, 0, 0); draw();
    }
    function rounded(x,y,w,h,r,color) { ctx.fillStyle=color; ctx.beginPath(); ctx.roundRect(x,y,w,h,r); ctx.fill(); }
    function line(points,color,width) { ctx.strokeStyle=color; ctx.lineWidth=width; ctx.lineCap='round'; ctx.lineJoin='round'; ctx.beginPath(); points.forEach(([x,y],i)=>i?ctx.lineTo(x,y):ctx.moveTo(x,y)); ctx.stroke(); }
    function hill(x,base,width,height,color) { ctx.fillStyle=color;ctx.beginPath();ctx.moveTo(x,base);ctx.bezierCurveTo(x+width*.3,base-height,x+width*.55,base-height,x+width,base);ctx.closePath();ctx.fill(); }
    function cloud(x,y,scale) {ctx.save();ctx.translate(x,y);ctx.scale(scale,scale);ctx.fillStyle='#ffffffa0';ctx.beginPath();ctx.ellipse(0,0,40,12,0,0,Math.PI*2);ctx.ellipse(-15,-10,20,18,0,0,Math.PI*2);ctx.ellipse(10,-14,24,21,0,0,Math.PI*2);ctx.fill();ctx.restore();}
    function tree(x,y,scale) {ctx.save();ctx.translate(x,y);ctx.scale(scale,scale);line([[0,0],[3,-65]],'#588573',6);hill(-35,-42,76,58,'#77ae8c');hill(-28,-60,60,51,'#91be97');ctx.restore();}
    function runner(x,y) {
        const stride = state === 'running' && run.y === 0 && run.freeze === 0 ? Math.sin(run.tick*.34) : .45;
        ctx.save();ctx.translate(x,y);
        if (run.invincible>0 && Math.floor(run.tick/6)%2===0) ctx.globalAlpha=.4;
        ctx.fillStyle='#174d3d22';ctx.beginPath();ctx.ellipse(0,run.y+4,23,5,0,0,Math.PI*2);ctx.fill();
        // Limbs, jersey, head and a lime headband; vector art stays crisp at any size.
        line([[-1,-29],[-13*stride,-14],[-22*stride,0]],'#174b48',10);
        line([[3,-30],[14*stride,-16],[24*stride,0]],'#236a60',10);
        line([[-22*stride-3,0],[-22*stride+8,0]],'#f6f4da',6);
        line([[24*stride-3,0],[24*stride+8,0]],'#d9ee74',6);
        line([[0,-59],[-12*stride-3,-47],[-22*stride+5,-50]],'#bf805a',7);
        rounded(-11,-64,25,37,8,'#008f80');
        line([[10,-57],[21*stride+7,-45],[27*stride+15,-53]],'#d59c72',7);
        ctx.fillStyle='#dea77b';ctx.beginPath();ctx.arc(6,-78,13,0,Math.PI*2);ctx.fill();
        ctx.fillStyle='#213e36';ctx.beginPath();ctx.arc(4,-83,12,Math.PI,Math.PI*2);ctx.fill();
        rounded(-8,-86,26,5,2,'#d9ee74');
        ctx.fillStyle='#173e36';ctx.fillRect(14,-80,2,3);
        rounded(-5,-56,12,13,2,'#e9f4dc');ctx.fillStyle='#126759';ctx.font='bold 8px sans-serif';ctx.fillText('62',-4,-46);
        ctx.restore();
    }
    function draw() {
        const phase = Math.min(2,Math.floor(run.distance/21000));
        const sky = ctx.createLinearGradient(0,0,0,H);sky.addColorStop(0,['#d1ece5','#dcebd7','#f8e9c8'][phase]);sky.addColorStop(1,'#f0f1d9');ctx.fillStyle=sky;ctx.fillRect(0,0,W,H);
        const offset = reduced ? 0 : run.distance;
        ctx.fillStyle='#fcf5cb';ctx.beginPath();ctx.arc(W*.78,H*.22,34,0,Math.PI*2);ctx.fill();
        for(let i=0;i<5;i++){const x=((i*310-offset*.035)%(W+310)+(W+310))%(W+310)-100;cloud(x,H*.18+(i%2)*27,.65+(i%3)*.14);}
        ctx.fillStyle='#b5dcd0';ctx.fillRect(0,ground-72,W,100);
        for(let i=-1;i<5;i++){const x=i*450-(offset*.09)%450;hill(x,ground-36,530,150,'#9ac7b5');hill(x+90,ground-13,340,105,'#b5d3af');}
        line([[0,ground-30],[W,ground-30]],'#d9edda',3);
        for(let i=-1;i<7;i++){const x=i*230-(offset*.3)%230;tree(x,ground-5,.6+(i%2)*.2);}
        ctx.fillStyle='#b4c68b';ctx.fillRect(0,ground-4,W,12);ctx.fillStyle='#426f59';ctx.fillRect(0,ground+8,W,H-ground);ctx.fillStyle='#64836b';ctx.fillRect(0,ground+17,W,3);
        for(let i=-1;i<W/75+1;i++){rounded(i*75-run.distance%75,ground+55,33,3,1,'#afbf902f');}
        const px = W < 800 ? 105 : 150;
        // Kilometer markers move with the course, not with wall-clock time.
        const marker = Math.floor(run.distance/1000)*1000+1000, mx = px+marker-run.distance;
        if(mx<W+50 && marker<62000){line([[mx,ground],[mx,ground-102]],'#4a7562',3);rounded(mx-25,ground-124,50,27,4,'#f6f4dd');ctx.fillStyle='#3b6856';ctx.font='bold 12px sans-serif';ctx.textAlign='center';ctx.fillText(`${marker/1000} KM`,mx,ground-106);ctx.textAlign='left';}
        for(let i=run.next;i<run.obstacles.length;i++){const o=run.obstacles[i], x=px+o.at-run.distance;if(x>W+80)break;if(x+o.width<0)continue;
            ctx.globalAlpha=o.hit?.38:1;rounded(x,ground-o.height,o.width,o.height,4,'#d9854f');rounded(x-4,ground-7,o.width+8,7,2,'#a85d3c');rounded(x,ground-o.height+9,o.width,8,0,'#fff2cf');ctx.globalAlpha=1;
        }
        const fx=px+E.FINISH-run.distance;
        if(fx<W+80){line([[fx,ground],[fx,ground-190]],'#174d43',6);rounded(fx,ground-190,130,35,0,'#153e36');ctx.fillStyle='#eff5dc';ctx.font='bold 17px sans-serif';ctx.fillText('FINIS 62 KM',fx+9,ground-166);for(let i=0;i<7;i++)for(let j=0;j<2;j++){ctx.fillStyle=(i+j)%2?'#edf2d5':'#264b3d';ctx.fillRect(fx+i*18,ground-155+j*12,18,12);}}
        runner(px,ground-run.y);
        if(state==='won'&&!reduced){for(let i=0;i<36;i++){ctx.fillStyle=['#007e73','#d9ee74','#d9854f'][i%3];ctx.fillRect((i*83+19)%W,20+(i*53)%(H*.6),6,9);}}
    }
    function frame(now) {
        if(state==='running') {
            if(!last) last=now;
            accumulator+=Math.min((now-last)/1000,.15);
            while(accumulator>=1/E.HZ && state==='running') {
                E.step(run,jump,answer);jump=false;answer=null;accumulator-=1/E.HZ;
                if(run.lives<lifeNotice){lifeNotice=run.lives;toast(`${run.lives} nyawa tersisa. Kamu masih bisa!`);}
                if(run.status!=='running'){hud();finish();break;}
                const km=Math.floor(run.distance/10000);
                if(km>milestone){milestone=km;toast(`${km*10} km! Teruskan langkahmu.`);}
            }
            hud();draw();
        }
        if(state==='won')draw();
        if(toastUntil && now>toastUntil){$('toast').classList.remove('show');toastUntil=0;}
        last=now;raf=requestAnimationFrame(frame);
    }
    function doJump(){if(state==='running' && !run.quiz && !run.feedbackTicks && run.y===0)jump=true;}
    function quizHud() {
        const visible = state === 'running' && (run.quiz || run.feedbackTicks > 0);
        $('quiz').hidden = !visible;
        if (!visible) { quizKey = ''; $('jump').disabled = state !== 'running'; return; }
        $('jump').disabled = true;
        const index = run.quiz ? run.quizIndex : run.feedback.index, q = run.questions[index];
        const key = `${index}:${run.quiz}`;
        $('quiz-time').textContent = run.quiz ? `${Math.ceil((720-run.quizTicks)/60)} detik` : 'Lanjut sebentar lagi…';
        if (key === quizKey) return;
        quizKey = key;
        $('quiz-label').textContent = `POS KESEHATAN ${index+1}/4 · KM ${q.km}`;
        $('quiz-question').textContent = q.question;
        $('quiz-options').replaceChildren();
        $('quiz-result').textContent = '';
        $('quiz-source').hidden = run.quiz;
        if (run.quiz) {
            jump = false;
            q.options.forEach((option, choice) => {
                const button = document.createElement('button'); button.type = 'button';
                button.textContent = `${choice+1}. ${option}`;
                button.addEventListener('click', () => { if (state === 'running' && run.quiz && answer === null) answer = choice; });
                $('quiz-options').append(button);
            });
            $('quiz-options').firstElementChild.focus({preventScroll:true});
            say(`Pertanyaan ${index+1}. ${q.question} Waktu 12 detik.`);
        } else {
            $('quiz-result').textContent = `${run.feedback.correct ? 'Benar!' : run.feedback.timeout ? 'Waktu habis. −1 nyawa.' : 'Belum tepat. −1 nyawa.'} ${q.explanation}`;
            $('quiz-source').textContent = q.source; $('quiz-source').href = q.url;
            say($('quiz-result').textContent);
        }
    }
    $('start').addEventListener('click',start);$('retry-save').addEventListener('click',save);
    $('pause').addEventListener('click',pause);$('jump').addEventListener('pointerdown',event=>{event.preventDefault();doJump();});
    $('jump').addEventListener('click',doJump);canvas.addEventListener('pointerdown',doJump);
    document.addEventListener('keydown',event=>{
        if(event.repeat || event.ctrlKey || event.metaKey || event.altKey)return;
        if(state === 'running' && run.quiz && ['Digit1','Digit2','Digit3'].includes(event.code)) { event.preventDefault(); if(answer === null) answer = Number(event.code.slice(-1))-1; return; }
        if(['Space','ArrowUp'].includes(event.code) && state==='running' && !event.target.closest('a,button,input,textarea')){event.preventDefault();doJump();}
        if(event.code==='KeyP'||event.code==='Escape'){if(state==='running'){event.preventDefault();pause();}else if(state==='paused'){event.preventDefault();start();}}
    });
    document.addEventListener('visibilitychange',()=>{if(document.hidden)pause();});
    window.addEventListener('blur',pause);
    window.addEventListener('beforeunload',event=>{if(state==='running'||pending){event.preventDefault();event.returnValue='';}});
    new ResizeObserver(resize).observe(canvas);resize();hud();standings();raf=requestAnimationFrame(frame);
    if(preview){$('micro').textContent='MODE LATIHAN · hadiah tidak disimpan';$('reward-note').textContent='Ini pratinjau game. Hadiah nyata hanya diberikan saat bermain melalui akun BRUDEL.';}
})();
