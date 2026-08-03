<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Verifikasi Email Berhasil</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@500;600;700;800&family=Nunito:wght@400;600;700;800&display=swap" rel="stylesheet">
<style>
  :root{
    --sky-top:#cfeaf5;
    --sky-bottom:#eef8fb;
    --sky-mid:#a9d9ec;
    --sand:#efdcb8;
    --sand-deep:#e2c795;
    --brown:#a9784e;
    --brown-deep:#7a5636;
    --brown-text:#5b4127;
    --gold:#f0b94a;
    --white:#fffdf8;
  }

  *{box-sizing:border-box;}

  body{
    margin:0;
    min-height:100vh;
    display:flex;
    align-items:center;
    justify-content:center;
    font-family:'Nunito', sans-serif;
    background:linear-gradient(180deg, var(--sky-top) 0%, var(--sky-mid) 45%, var(--sky-bottom) 100%);
    overflow:hidden;
    position:relative;
  }

  @media (prefers-reduced-motion: reduce){
    *{animation-duration:0.001ms !important; animation-iteration-count:1 !important; transition-duration:0.001ms !important;}
  }

  /* ---------- Sun rays ---------- */
  .sun-rays{
    position:absolute;
    top:-220px;
    left:50%;
    transform:translateX(-50%);
    width:600px;
    height:600px;
    background:conic-gradient(from 0deg, transparent 0deg, rgba(240,185,74,0.16) 8deg, transparent 16deg, transparent 40deg, rgba(240,185,74,0.16) 48deg, transparent 56deg, transparent 80deg, rgba(240,185,74,0.16) 88deg, transparent 96deg, transparent 120deg, rgba(240,185,74,0.16) 128deg, transparent 136deg, transparent 160deg, rgba(240,185,74,0.16) 168deg, transparent 176deg, transparent 200deg, rgba(240,185,74,0.16) 208deg, transparent 216deg, transparent 240deg, rgba(240,185,74,0.16) 248deg, transparent 256deg, transparent 280deg, rgba(240,185,74,0.16) 288deg, transparent 296deg, transparent 320deg, rgba(240,185,74,0.16) 328deg, transparent 336deg);
    border-radius:50%;
    animation:spin-slow 60s linear infinite;
    z-index:0;
    pointer-events:none;
  }
  @keyframes spin-slow{ to{ transform:translateX(-50%) rotate(360deg); } }

  /* ---------- Clouds ---------- */
  .cloud{
    position:absolute;
    background:#ffffff;
    border-radius:100px;
    opacity:0.8;
    filter:blur(0.3px);
    z-index:1;
  }
  .cloud::before, .cloud::after{
    content:"";
    position:absolute;
    background:#ffffff;
    border-radius:100px;
  }
  .cloud-1{ width:90px; height:32px; top:12%; left:-140px; animation:drift 26s linear infinite; }
  .cloud-1::before{ width:50px; height:50px; top:-24px; left:12px; }
  .cloud-1::after{ width:36px; height:36px; top:-16px; left:46px; }
  .cloud-2{ width:70px; height:26px; top:22%; left:-140px; animation:drift 34s linear infinite; animation-delay:-10s; }
  .cloud-2::before{ width:38px; height:38px; top:-18px; left:8px; }
  .cloud-2::after{ width:28px; height:28px; top:-12px; left:34px; }
  .cloud-3{ width:110px; height:36px; top:8%; left:-160px; animation:drift 42s linear infinite; animation-delay:-22s; }
  .cloud-3::before{ width:58px; height:58px; top:-28px; left:16px; }
  .cloud-3::after{ width:40px; height:40px; top:-18px; left:56px; }
  @keyframes drift{ to{ transform:translateX(calc(100vw + 200px)); } }

  /* ---------- Distant mountains (brown) ---------- */
  .mountains{
    position:absolute;
    bottom:0; left:0; right:0;
    height:160px;
    z-index:1;
    pointer-events:none;
  }
  .mountains svg{ width:100%; height:100%; display:block; }

  /* ---------- Card ---------- */
  .card{
    position:relative;
    z-index:5;
    background:var(--white);
    border:3px dashed var(--brown);
    border-radius:24px;
    padding:2.6rem 2.2rem 2.2rem;
    max-width:420px;
    width:90%;
    text-align:center;
    box-shadow:0 20px 50px -18px rgba(122,86,54,0.35), 0 2px 0 rgba(255,255,255,0.6) inset;
    animation:card-in 0.7s cubic-bezier(.2,.9,.25,1.2) both;
  }
  @keyframes card-in{
    0%{ opacity:0; transform:translateY(24px) scale(0.96); }
    100%{ opacity:1; transform:translateY(0) scale(1); }
  }

  .eyebrow{
    display:inline-flex;
    align-items:center;
    gap:6px;
    font-family:'Baloo 2', sans-serif;
    font-weight:700;
    font-size:0.72rem;
    letter-spacing:0.14em;
    text-transform:uppercase;
    color:var(--brown-deep);
    background:var(--sand);
    padding:5px 14px;
    border-radius:999px;
    margin-bottom:1.1rem;
  }

  /* ---------- Compass badge ---------- */
  .badge-wrap{
    position:relative;
    width:112px;
    height:112px;
    margin:0 auto 1rem;
  }
  .badge-glow{
    position:absolute;
    inset:-14px;
    border-radius:50%;
    background:radial-gradient(circle, rgba(240,185,74,0.55) 0%, rgba(240,185,74,0) 70%);
    animation:pulse-glow 2.4s ease-in-out infinite;
  }
  @keyframes pulse-glow{
    0%,100%{ transform:scale(0.9); opacity:0.7; }
    50%{ transform:scale(1.12); opacity:1; }
  }
  .badge-ring{
    position:absolute;
    inset:0;
    border-radius:50%;
    background:linear-gradient(145deg, var(--brown) 0%, var(--brown-deep) 100%);
    display:flex;
    align-items:center;
    justify-content:center;
    box-shadow:0 10px 20px -8px rgba(122,86,54,0.5);
    animation:pop-in 0.6s 0.15s cubic-bezier(.2,.9,.25,1.3) both;
  }
  @keyframes pop-in{
    0%{ transform:scale(0.3) rotate(-30deg); opacity:0; }
    100%{ transform:scale(1) rotate(0deg); opacity:1; }
  }
  .badge-inner{
    width:88px;
    height:88px;
    border-radius:50%;
    background:linear-gradient(145deg, #f6ecd8, var(--sand));
    display:flex;
    align-items:center;
    justify-content:center;
    position:relative;
  }
  .compass-needle{
    width:44px;
    height:44px;
    animation:needle-settle 1.3s 0.5s cubic-bezier(.2,.7,.2,1) both;
    transform-origin:50% 50%;
  }
  @keyframes needle-settle{
    0%{ transform:rotate(280deg); opacity:0; }
    60%{ transform:rotate(-18deg); opacity:1; }
    80%{ transform:rotate(8deg); }
    100%{ transform:rotate(0deg); }
  }

  /* sparkles around badge */
  .sparkle{
    position:absolute;
    width:8px;
    height:8px;
    background:var(--gold);
    clip-path:polygon(50% 0%, 61% 39%, 100% 50%, 61% 61%, 50% 100%, 39% 61%, 0% 50%, 39% 39%);
    opacity:0;
    animation:sparkle-pop 1.8s ease-in-out infinite;
  }
  .sparkle:nth-child(1){ top:-2px; left:8px; animation-delay:0.9s; }
  .sparkle:nth-child(2){ top:14px; right:-6px; animation-delay:1.2s; width:6px; height:6px; }
  .sparkle:nth-child(3){ bottom:2px; left:-8px; animation-delay:1.5s; width:5px; height:5px; }
  @keyframes sparkle-pop{
    0%,100%{ opacity:0; transform:scale(0.4) rotate(0deg); }
    50%{ opacity:1; transform:scale(1) rotate(90deg); }
  }

  h2{
    font-family:'Baloo 2', sans-serif;
    font-weight:800;
    font-size:1.5rem;
    color:var(--brown-text);
    margin:0.3rem 0 0.5rem;
  }

  .xp-line{
    display:inline-flex;
    align-items:center;
    gap:6px;
    font-weight:800;
    font-size:0.85rem;
    color:#8a6a1f;
    background:linear-gradient(90deg, #fff3d6, #ffe6a8);
    border:1px solid #f0d99a;
    padding:4px 12px;
    border-radius:999px;
    margin-bottom:1rem;
  }

  p.desc{
    color:#8a7458;
    font-size:0.95rem;
    line-height:1.5;
    margin:0 0 1.4rem;
  }

  /* ---------- Trail hint ---------- */
  .trail-hint{
    font-size:0.85rem;
    font-weight:700;
    color:var(--brown-deep);
    background:var(--sand);
    display:inline-block;
    padding:6px 14px;
    border-radius:999px;
    margin:0 0 1.4rem;
  }

  /* ---------- CTA ---------- */
  .cta{
    display:inline-flex;
    align-items:center;
    gap:8px;
    font-family:'Baloo 2', sans-serif;
    font-weight:700;
    font-size:1rem;
    color:#fff;
    text-decoration:none;
    background:linear-gradient(135deg, #6fb8d8, #4c98ba);
    padding:0.85rem 1.8rem;
    border-radius:14px;
    box-shadow:0 10px 22px -10px rgba(76,152,186,0.6), inset 0 1px 0 rgba(255,255,255,0.35);
    transition:transform 0.15s ease, box-shadow 0.15s ease;
    animation:cta-nudge 2.2s ease-in-out 1.4s infinite;
  }
  @keyframes cta-nudge{
    0%,80%,100%{ transform:translateY(0); }
    90%{ transform:translateY(-4px); }
  }
  .cta:hover{
    transform:translateY(-2px);
    box-shadow:0 14px 26px -10px rgba(76,152,186,0.7), inset 0 1px 0 rgba(255,255,255,0.35);
  }
  .cta:focus-visible{
    outline:3px solid var(--gold);
    outline-offset:3px;
  }
  .cta:active{ transform:translateY(0); }

  .cta svg{ width:18px; height:18px; }

  .footnote{
    margin-top:1.1rem;
    font-size:0.75rem;
    color:#b4a488;
  }
</style>
</head>
<body>

  <div class="sun-rays"></div>
  <div class="cloud cloud-1"></div>
  <div class="cloud cloud-2"></div>
  <div class="cloud cloud-3"></div>

  <div class="mountains" aria-hidden="true">
    <svg viewBox="0 0 1200 200" preserveAspectRatio="none">
      <polygon points="0,200 0,120 180,40 340,140 520,60 700,150 900,70 1080,150 1200,90 1200,200" fill="#cdb083" opacity="0.55"/>
      <polygon points="0,200 0,150 220,90 420,170 640,100 860,175 1080,110 1200,160 1200,200" fill="#b98f5f" opacity="0.75"/>
    </svg>
  </div>

  <main class="card">
    <span class="eyebrow">🧭 Quest Selesai</span>

    <div class="badge-wrap">
      <div class="badge-glow"></div>
      <div class="sparkle"></div>
      <div class="sparkle"></div>
      <div class="sparkle"></div>
      <div class="badge-ring">
        <div class="badge-inner">
          <svg class="compass-needle" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg">
            <circle cx="24" cy="24" r="21" stroke="#a9784e" stroke-width="2.5"/>
            <circle cx="24" cy="24" r="2.5" fill="#7a5636"/>
            <path d="M24 6L29 24L24 22L19 24L24 6Z" fill="#5fa8d3"/>
            <path d="M24 42L19 24L24 26L29 24L24 42Z" fill="#a9784e"/>
          </svg>
        </div>
      </div>
    </div>

    <h2>{{ $message }}</h2>
    <div class="xp-line">⭐ +50 XP &middot; Level Naik!</div>

    <p class="desc">
      Selamat, Penjelajah! Emailmu berhasil diverifikasi. Petualanganmu berlanjut ke basecamp berikutnya.
    </p>

    <p class="trail-hint">🚩 Basecamp berikutnya sudah menunggu</p>

    <a href="{{ $redirectUrl }}" class="cta">
      Lanjutkan Perjalanan
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
    </a>

    <p class="footnote">Halaman ini tidak akan mengalihkanmu otomatis — klik tombol di atas untuk melanjutkan.</p>
  </main>
</body>
</html>