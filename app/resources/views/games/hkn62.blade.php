<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#083e3b"><meta name="csrf-token" content="{{ csrf_token() }}">
    <title>62 KM · HKN Run — BRUDEL</title>
    <link rel="icon" href="/brand/favicon.ico"><link rel="stylesheet" href="/games/hkn62/style.css?v=2">
    <script src="/games/hkn62/engine.js?v=2" defer></script><script src="/games/hkn62/game.js?v=2" defer></script>
</head>
<body>
<a class="skip" href="#play">Langsung ke permainan</a>
<header class="top"><a href="/" class="brand"><img src="/brand/logo-mark.svg" alt=""><span>BRUDEL<span class="dot">.</span><small>POLTEKKES KEMENKES MANADO</small></span></a><span class="event-pill"><i></i> HARI KESEHATAN NASIONAL KE-62</span><a class="back" href="/">Kembali ke BRUDEL ↗</a></header>
<main>
    <section class="heading"><div><p class="eyebrow">MINI GAME / EDISI HKN 62</p><h1>Langkah kecil.<br><em>Semangat besar.</em></h1><p class="intro">Satu lintasan, 62 kilometer. Lompati rintangan,<br class="desktop"> jaga semangat, dan raih badge finisher-mu.</p></div><div class="edition"><span>HKN</span><strong>62<span>↗</span></strong><span>BERGERAK BERSAMA</span></div></section>
    <div class="game-layout">
        <section class="play-card" id="play" aria-label="Permainan HKN Run">
            <div class="hud"><div><span class="label">JARAK TEMPUH</span><strong><span id="distance">0,00</span> <small>/ 62 km</small></strong></div><div class="hud-right"><div><span class="label">WAKTU</span><strong id="time">00:00,00</strong></div><div><span class="label">SEMANGAT</span><strong id="hearts" aria-label="3 nyawa">♥ ♥ ♥</strong></div><button class="pause" id="pause" aria-label="Jeda permainan" disabled>Ⅱ</button></div></div>
            <div class="progress" role="progressbar" aria-label="Progres menuju 62 kilometer" aria-valuemin="0" aria-valuemax="62" aria-valuenow="0"><span id="progress"></span></div>
            <div class="stage"><canvas id="canvas" width="1000" height="450" tabindex="0" aria-label="Lintasan lari. Tekan Spasi atau panah atas untuk melompat, P untuk jeda.">Browser Anda perlu mendukung Canvas untuk memainkan game ini.</canvas><span class="stage-tag" id="stage-tag">01 / PESISIR PAGI</span><span class="toast" id="toast" role="status"></span>
                <div class="overlay" id="overlay"><div class="overlay-card"><span class="overline" id="overline">TANTANGAN HKN KE-62</span><h2 id="title">Siap menempuh<br>62 kilometer?</h2><p id="message">Lari otomatis. Lompat tepat waktu.<br>Tiga kesempatan untuk mencapai garis finis.</p><button class="primary" id="start">Mulai berlari <span>→</span></button><button class="secondary" id="retry-save" hidden>Coba simpan hadiah lagi</button><p class="micro" id="micro">± 2 menit • lari cepat + 4 trivia kesehatan</p></div></div>
            <section class="quiz" id="quiz" aria-labelledby="quiz-question" hidden><div class="quiz-top"><span id="quiz-label"></span><strong id="quiz-time"></strong></div><h2 id="quiz-question"></h2><div id="quiz-options"></div><p id="quiz-result" role="status"></p><a id="quiz-source" target="_blank" rel="noopener noreferrer" hidden></a></section>
            </div>
            <div class="controls"><div><kbd>SPASI</kbd> / <kbd>↑</kbd><span> untuk lompat</span><span class="separator">·</span><kbd>P</kbd><span> jeda</span></div><button id="jump" class="jump" disabled>↥ <span>Lompat</span></button></div>
        </section>
        <aside class="reward-card"><p class="eyebrow">BUKAN SEKADAR FINIS</p><h2>Bawa pulang<br>pencapaianmu.</h2><div class="medal" aria-label="Badge Finisher 62 KM"><div class="medal-ring"><span>HKN KE-62</span><strong>62<small>KM</small></strong><span>★ FINISHER ★</span></div></div><h3>Finisher 62 KM</h3><p class="reward-sub">Badge + frame foto profil eksklusif</p><div class="points"><strong>+62</strong><span>POIN<br>BRUDEL</span></div><p class="reward-note" id="reward-note">Finis pertama: 62 poin, badge, dan frame foto profil terbuka. Main lagi untuk memperbaiki waktu, bukan menggandakan hadiah.</p><a href="/profil" class="profile-link">Pasang frame di profil ↗</a></aside>
    </div>
    <section class="hkn-identity"><img class="hkn-logo" src="/games/hkn62/logo-hkn62.png" alt="Logo HKN ke-62, Sehat untuk Semua"><div class="identity-copy"><p class="eyebrow">HADIAH EKSKLUSIF FINISHER</p><h2>Semangat sehat, sampai ke profilmu.</h2><p>Tuntaskan 62 km untuk membuka frame HKN ke-62.<br>Pasang atau lepas kapan saja dari profil BRUDEL.</p></div><div class="frame-demo" aria-label="Contoh frame foto profil HKN ke-62"><span>BR</span><img src="/games/hkn62/frame.svg" alt=""></div></section>
    <section class="bottom-grid"><div class="how"><p class="eyebrow">RITME YANG TEPAT, LANGKAH YANG HEBAT</p><h2>Lari cepat. Pikir tepat.</h2><div class="steps"><div><span>01</span><p><strong>Lompat.</strong>Tekan Spasi, ↑, layar permainan, atau tombol Lompat.</p></div><div><span>02</span><p><strong>Jaga semangat.</strong>Laju meningkat di setiap tahap. Tabrakan mengurangi satu nyawa.</p></div><div><span>03</span><p><strong>Kejar finis.</strong>Jawab 4 trivia dalam 12 detik per soal. Salah atau terlambat: −1 nyawa.</p></div></div><p class="footnote">Jarak virtual, bukan catatan aktivitas fisik. Waktu menjawab trivia dan penjelasan ikut dihitung. Jeda tidak dihitung dalam waktu lomba. Jika waktunya sama, yang lebih dulu mencatatnya berada di atas.</p></div><div class="leaderboard"><div class="board-title"><div><p class="eyebrow">PAPAN PERINGKAT</p><h2>Langkah tercepat.</h2></div><span>TOP 10</span></div><div id="board"><p class="empty">Memuat peringkat…</p></div><p class="board-note">Identitas pemain lain ditampilkan sebagai alias pelari.</p></div></section>
    <footer><span>BRUDEL PLAY / HKN KE-62</span><span>Bergerak. Bertumbuh. Berprestasi.</span></footer>
</main>
<div class="sr-only" role="status" aria-live="polite" id="announcer"></div>
<noscript><div class="noscript">Aktifkan JavaScript untuk memainkan HKN Run.</div></noscript>
</body></html>
