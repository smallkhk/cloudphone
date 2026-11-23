// Renders the iPhone app's built-in artwork (backgrounds + illustrations) with
// headless Chromium into the asset catalog. Run: node ios-app/art/generate.cjs
// Everything ships inside the app — nothing is downloaded at runtime.
const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');

const OUT = path.join(__dirname, '..', 'Vhonix', 'Resources', 'Assets.xcassets');

const grain = `<svg xmlns='http://www.w3.org/2000/svg' width='300' height='300'><filter id='n'><feTurbulence type='fractalNoise' baseFrequency='.9' numOctaves='3' stitchTiles='stitch'/><feColorMatrix values='0 0 0 0 1  0 0 0 0 1  0 0 0 0 1  0 0 0 .55 0'/></filter><rect width='100%' height='100%' filter='url(%23n)'/></svg>`;

const backgrounds = {
  indigo: ['#6C5CE7', '#B26BFF', '#2FB6E8'],
  pink: ['#FF5FA2', '#FF9F43', '#9B5CFF'],
  emerald: ['#14C38E', '#2FB6E8', '#5B6CFA'],
  blue: ['#3B82F6', '#7B6CFF', '#22D3EE'],
  violet: ['#8B5CF6', '#EC4899', '#4F46E5'],
};

function backgroundHTML([a, b, c]) {
  const blob = (x, y, w, h, color, op) =>
    `<div style="position:absolute;left:${x}px;top:${y}px;width:${w}px;height:${h}px;border-radius:50%;background:${color};opacity:${op};filter:blur(160px)"></div>`;
  return `<html><body style="margin:0;width:1290px;height:2796px;overflow:hidden;background:#05060B;position:relative">
  ${blob(-420, -380, 1500, 1300, a, .62)}
  ${blob(560, 260, 1200, 1300, b, .45)}
  ${blob(-300, 1300, 1100, 1000, c, .30)}
  ${blob(500, 1900, 1200, 1100, a, .30)}
  ${blob(-200, 2300, 1000, 900, b, .25)}
  <div style="position:absolute;left:-30%;top:8%;width:160%;height:420px;transform:rotate(-22deg);
    background:linear-gradient(90deg,transparent,rgba(255,255,255,.06),transparent);filter:blur(60px)"></div>
  <div style="position:absolute;inset:0;
    background-image:linear-gradient(rgba(255,255,255,.04) 1px,transparent 1px),linear-gradient(90deg,rgba(255,255,255,.04) 1px,transparent 1px);
    background-size:86px 86px;
    -webkit-mask-image:radial-gradient(1000px 1000px at 50% 4%, #000 15%, transparent 72%)"></div>
  <div style="position:absolute;inset:0;background:url(&quot;data:image/svg+xml;utf8,${grain}&quot;);opacity:.08;mix-blend-mode:overlay"></div>
  <div style="position:absolute;inset:0;background:linear-gradient(180deg,rgba(5,6,11,.30) 0%,rgba(5,6,11,.05) 18%,rgba(5,6,11,.15) 50%,rgba(5,6,11,.6) 100%)"></div>
  </body></html>`;
}

const base = `margin:0;width:1024px;height:1024px;background:transparent;display:grid;place-items:center;font-family:Inter,sans-serif;overflow:hidden`;
const glow = (color, size = 620, op = .55) =>
  `<div style="position:absolute;width:${size}px;height:${size}px;border-radius:50%;background:${color};filter:blur(120px);opacity:${op}"></div>`;
const sparkle = (x, y, s, c = '#fff') =>
  `<div style="position:absolute;left:${x}px;top:${y}px;width:${s}px;height:${s}px;background:${c};
   clip-path:polygon(50% 0,60% 40%,100% 50%,60% 60%,50% 100%,40% 60%,0 50%,40% 40%);filter:drop-shadow(0 0 12px ${c})"></div>`;
const glassChip = (x, y, html, extra = '') =>
  `<div style="position:absolute;left:${x}px;top:${y}px;padding:20px 28px;border-radius:28px;
   background:linear-gradient(135deg,rgba(255,255,255,.28),rgba(255,255,255,.08));border:2px solid rgba(255,255,255,.35);
   backdrop-filter:blur(20px);box-shadow:0 30px 60px rgba(0,0,0,.35);color:#fff;font-weight:700;font-size:34px;${extra}">${html}</div>`;

const appColors = [
  ['#FF6B6B', '#E0457B'], ['#FFB443', '#FF7A45'], ['#2FD3A1', '#14A37F'], ['#3EC6FF', '#3B7BFF'],
  ['#B26BFF', '#7B4BFF'], ['#FF6BB5', '#D9468F'], ['#F7D774', '#E8A93B'], ['#7CF0E0', '#2FB6E8'],
  ['#9DA8FF', '#6366F1'], ['#FF8E72', '#F0525F'], ['#6EE7B7', '#10B981'], ['#C4B5FD', '#8B5CF6'],
  ['#FDBA74', '#F97316'], ['#93C5FD', '#3B82F6'], ['#F9A8D4', '#EC4899'], ['#A7F3D0', '#34D399'],
];

const phone = (w, h, extra = '') => `
  <div style="position:relative;width:${w}px;height:${h}px;border-radius:${w * .17}px;padding:${w * .035}px;box-sizing:border-box;
    background:linear-gradient(145deg,#3a3e52,#0c0d14 55%,#1d2030);border:3px solid rgba(255,255,255,.28);
    box-shadow:0 60px 120px rgba(0,0,0,.55), inset 0 2px 0 rgba(255,255,255,.25);${extra}">
    <div style="position:relative;width:100%;height:100%;border-radius:${w * .14}px;overflow:hidden;
      background:linear-gradient(160deg,#4B3BD8,#8A45E6 45%,#2A8FD6)">
      <div style="position:absolute;top:${w * .04}px;left:50%;transform:translateX(-50%);width:${w * .3}px;height:${w * .075}px;border-radius:99px;background:#05060B"></div>
      <div style="position:absolute;left:${w * .07}px;right:${w * .07}px;top:${h * .13}px;display:grid;grid-template-columns:repeat(4,1fr);gap:${w * .055}px">
        ${appColors.map(([a, b], i) => `<div style="aspect-ratio:1;border-radius:28%;background:linear-gradient(145deg,${a},${b});
          box-shadow:0 6px 14px rgba(0,0,0,.25), inset 0 2px 0 rgba(255,255,255,.35);display:grid;place-items:center">
          <div style="width:38%;height:38%;border-radius:${i % 3 === 0 ? '50%' : i % 3 === 1 ? '22%' : '4px'};background:rgba(255,255,255,.85)"></div></div>`).join('')}
      </div>
      <div style="position:absolute;left:${w * .06}px;right:${w * .06}px;bottom:${w * .06}px;height:${w * .24}px;border-radius:${w * .08}px;
        background:rgba(255,255,255,.18);border:1px solid rgba(255,255,255,.3);display:flex;justify-content:space-around;align-items:center">
        ${[0, 1, 2, 3].map(i => `<div style="width:${w * .14}px;height:${w * .14}px;border-radius:28%;background:linear-gradient(145deg,${appColors[i * 4][0]},${appColors[i * 4][1]})"></div>`).join('')}
      </div>
      <div style="position:absolute;inset:0;background:linear-gradient(115deg,rgba(255,255,255,.38),rgba(255,255,255,0) 38%)"></div>
    </div>
  </div>`;

const art = {
  'art-phone': `<div style="position:relative;display:grid;place-items:center;perspective:1600px">
      ${glow('linear-gradient(135deg,#8B5CF6,#22D3EE)', 640, .7)}
      <div style="transform:rotateY(-24deg) rotateX(12deg) rotateZ(5deg)">${phone(330, 680)}</div>
      ${glassChip(-210, 150, '<span style="display:inline-block;width:20px;height:20px;border-radius:50%;background:#34D399;box-shadow:0 0 14px #34D399;margin-right:14px"></span>Online')}
      ${glassChip(190, 470, 'Android 14')}
      ${sparkle(-160, -330, 46)}${sparkle(260, -250, 30, '#B9B0FF')}${sparkle(230, 300, 38, '#8FE6FF')}
    </div>`,

  'art-shop': `<div style="position:relative;display:grid;place-items:center">
      ${glow('linear-gradient(135deg,#FF5FA2,#FF9F43)', 640, .65)}
      <div style="position:absolute;top:95px;width:230px;height:200px;border-radius:120px 120px 0 0;border:34px solid #FFD0A8;border-bottom:none;box-sizing:border-box;opacity:.95"></div>
      <div style="position:relative;margin-top:180px;width:520px;height:500px;border-radius:40px 40px 70px 70px;
        background:linear-gradient(150deg,#FF7AB0,#FF5F7E 45%,#FF9F43);box-shadow:0 60px 100px rgba(0,0,0,.45), inset 0 3px 0 rgba(255,255,255,.45);
        display:grid;place-items:center;overflow:hidden">
        <div style="position:absolute;inset:0;background:linear-gradient(115deg,rgba(255,255,255,.4),transparent 40%)"></div>
        <div style="transform:rotate(-8deg) translateY(40px)">${phone(170, 330)}</div>
      </div>
      ${sparkle(-260, -180, 54)}${sparkle(250, -260, 36, '#FFE0B2')}${sparkle(270, 200, 44, '#FFC2DB')}
    </div>`,

  'art-wallet': `<div style="position:relative;display:grid;place-items:center">
      ${glow('linear-gradient(135deg,#14C38E,#2FB6E8)', 640, .65)}
      <div style="position:absolute;width:560px;height:350px;border-radius:44px;transform:rotate(-14deg) translate(-40px,-60px);
        background:linear-gradient(135deg,#2FB6E8,#5B6CFA);box-shadow:0 40px 80px rgba(0,0,0,.4);opacity:.9"></div>
      <div style="position:relative;width:580px;height:360px;border-radius:44px;transform:rotate(6deg);padding:44px;box-sizing:border-box;
        background:linear-gradient(135deg,rgba(255,255,255,.42),rgba(255,255,255,.12));border:3px solid rgba(255,255,255,.5);
        backdrop-filter:blur(30px);box-shadow:0 60px 110px rgba(0,0,0,.45);color:#fff">
        <div style="font-weight:800;font-size:34px;letter-spacing:6px;opacity:.9">WALLET</div>
        <div style="margin-top:70px;font-weight:800;font-size:76px">$1,250</div>
        <div style="position:absolute;right:44px;bottom:44px;width:90px;height:66px;border-radius:14px;background:linear-gradient(180deg,#F7D774,#D9A13B)"></div>
      </div>
      ${[['#F7D774', '#D99A10', '$', -250, 250, 170], ['#34D399', '#0E9F6E', '₮', 250, 220, 150], ['#C7D2FE', '#6B8CCE', 'Ł', 220, -280, 120]].map(([a, b, s, x, y, d]) =>
        `<div style="position:absolute;transform:translate(${x}px,${y}px);width:${d}px;height:${d}px;border-radius:50%;
          background:radial-gradient(circle at 35% 30%,#fff8,transparent 45%),linear-gradient(145deg,${a},${b});
          border:6px solid rgba(255,255,255,.55);box-shadow:0 30px 50px rgba(0,0,0,.4);display:grid;place-items:center;
          color:rgba(255,255,255,.95);font-weight:900;font-size:${d * .5}px">${s}</div>`).join('')}
    </div>`,

  'art-email': `<div style="position:relative;display:grid;place-items:center">
      ${glow('linear-gradient(135deg,#2FB6E8,#5B7CFA)', 640, .65)}
      <div style="position:absolute;transform:translateY(-215px);width:420px;height:300px;border-radius:26px;background:#fff;
        box-shadow:0 20px 40px rgba(0,0,0,.25);padding:40px;box-sizing:border-box">
        <div style="height:22px;width:70%;border-radius:11px;background:#E2E8F0"></div>
        <div style="margin-top:20px;height:22px;width:90%;border-radius:11px;background:#EDF2F7"></div>
        <div style="margin-top:34px;display:inline-block;padding:16px 28px;border-radius:18px;background:linear-gradient(135deg,#5B4BE8,#8A45E6);
          color:#fff;font-weight:800;font-size:44px;letter-spacing:8px">482 913</div>
      </div>
      <div style="position:relative;margin-top:180px;width:620px;height:400px;border-radius:44px;overflow:hidden;
        background:linear-gradient(150deg,#3EC6FF,#3B7BFF);box-shadow:0 60px 100px rgba(0,0,0,.45)">
        <div style="position:absolute;inset:0;background:linear-gradient(150deg,#5ED3FF,#4F6BFF);clip-path:polygon(0 0,50% 58%,100% 0,100% 100%,0 100%)"></div>
        <div style="position:absolute;inset:0;background:linear-gradient(115deg,rgba(255,255,255,.35),transparent 40%)"></div>
      </div>
      ${sparkle(-280, -260, 46)}${sparkle(270, -200, 34, '#BAE6FD')}
    </div>`,

  'art-sms': `<div style="position:relative;display:grid;place-items:center">
      ${glow('linear-gradient(135deg,#14C38E,#2FB6E8)', 640, .65)}
      <div style="position:absolute;transform:translate(-70px,-150px);padding:44px 54px;border-radius:52px 52px 52px 14px;
        background:linear-gradient(135deg,rgba(255,255,255,.4),rgba(255,255,255,.14));border:3px solid rgba(255,255,255,.5);
        backdrop-filter:blur(24px);box-shadow:0 40px 80px rgba(0,0,0,.35);color:#fff;font-size:40px;font-weight:600;white-space:nowrap">
        Your code is <b style="font-size:52px;letter-spacing:4px">731 204</b></div>
      <div style="position:absolute;transform:translate(120px,120px);padding:40px 54px;border-radius:52px 52px 14px 52px;
        background:linear-gradient(135deg,#2FD3A1,#14A37F);box-shadow:0 40px 80px rgba(0,0,0,.35);color:#fff;font-size:44px;font-weight:700">Verified ✓</div>
      ${sparkle(-300, 160, 46)}${sparkle(300, -260, 36, '#A7F3D0')}
    </div>`,

  'art-globe': `<div style="position:relative;display:grid;place-items:center">
      ${glow('linear-gradient(135deg,#FF9F43,#FF5F7E)', 640, .6)}
      <div style="position:relative;width:500px;height:500px;border-radius:50%;
        background:radial-gradient(circle at 32% 28%,rgba(255,255,255,.55),transparent 38%),linear-gradient(150deg,#FFB443,#FF6B6B 55%,#C2407A);
        box-shadow:0 60px 110px rgba(0,0,0,.45), inset -30px -40px 80px rgba(0,0,0,.25);overflow:hidden">
        ${[0, 1, 2].map(i => `<div style="position:absolute;inset:0;margin:auto;width:${500 - i * 160}px;height:500px;border-radius:50%;border:4px solid rgba(255,255,255,.35)"></div>`).join('')}
        ${[1, 2, 3].map(i => `<div style="position:absolute;left:0;right:0;top:${i * 125}px;height:4px;background:rgba(255,255,255,.3)"></div>`).join('')}
      </div>
      <div style="position:absolute;width:780px;height:240px;border-radius:50%;border:5px solid rgba(255,255,255,.45);transform:rotate(-18deg)"></div>
      ${[[-370, 40, '#FFD0A8'], [360, -90, '#fff'], [180, 230, '#FFC2DB']].map(([x, y, c]) =>
        `<div style="position:absolute;transform:translate(${x}px,${y}px);width:46px;height:46px;border-radius:50%;background:${c};box-shadow:0 0 30px ${c}"></div>`).join('')}
      ${sparkle(-250, -300, 44)}
    </div>`,

  'art-number': `<div style="position:relative;display:grid;place-items:center">
      ${glow('linear-gradient(135deg,#FF6BB5,#A66BFF)', 640, .65)}
      ${[0, 1, 2].map(i => `<div style="position:absolute;width:${380 + i * 150}px;height:${380 + i * 150}px;border-radius:50%;
        border:${6 - i}px solid rgba(255,255,255,${.4 - i * .1})"></div>`).join('')}
      <div style="position:relative;width:300px;height:300px;border-radius:84px;display:grid;place-items:center;
        background:linear-gradient(145deg,#FF6BB5,#A66BFF);box-shadow:0 50px 90px rgba(0,0,0,.45), inset 0 3px 0 rgba(255,255,255,.45)">
        <svg width="150" height="150" viewBox="0 0 24 24" fill="#fff"><path d="M6.6 10.8a15.1 15.1 0 0 0 6.6 6.6l2.2-2.2a1 1 0 0 1 1-.25 11.4 11.4 0 0 0 3.6.57 1 1 0 0 1 1 1V20a1 1 0 0 1-1 1A17 17 0 0 1 3 4a1 1 0 0 1 1-1h3.5a1 1 0 0 1 1 1c0 1.25.2 2.45.57 3.57a1 1 0 0 1-.25 1z"/></svg>
      </div>
      ${glassChip(-330, 300, '+1 555 0182', 'font-size:40px')}
      ${sparkle(250, -300, 46)}
    </div>`,

  'art-drive': `<div style="position:relative;display:grid;place-items:center">
      ${glow('linear-gradient(135deg,#6C5CE7,#2FB6E8)', 640, .65)}
      <div style="position:relative;width:640px;height:380px;filter:drop-shadow(0 50px 70px rgba(0,0,0,.45))">
        <div style="position:absolute;left:60px;bottom:0;width:520px;height:200px;border-radius:100px;background:linear-gradient(150deg,#8B7CFF,#5B6CFA)"></div>
        <div style="position:absolute;left:120px;top:40px;width:260px;height:260px;border-radius:50%;background:linear-gradient(150deg,#A99CFF,#6C5CE7)"></div>
        <div style="position:absolute;left:300px;top:0;width:300px;height:300px;border-radius:50%;background:linear-gradient(150deg,#9DB8FF,#5B7CFA)"></div>
        <div style="position:absolute;left:140px;top:60px;width:150px;height:70px;border-radius:50%;background:rgba(255,255,255,.35);filter:blur(18px)"></div>
        <svg style="position:absolute;left:250px;top:120px" width="160" height="160" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M12 19V7"/><path d="M6 12l6-6 6 6"/></svg>
      </div>
      ${sparkle(-280, -230, 46)}${sparkle(290, 220, 36, '#C4B5FD')}
    </div>`,

  'art-orders': `<div style="position:relative;display:grid;place-items:center">
      ${glow('linear-gradient(135deg,#6B7489,#5B6CFA)', 640, .6)}
      <div style="position:relative;width:440px;height:600px;background:#F8FAFC;transform:rotate(-6deg);padding:56px;box-sizing:border-box;
        clip-path:polygon(0 0,100% 0,100% 96%,90% 100%,80% 96%,70% 100%,60% 96%,50% 100%,40% 96%,30% 100%,20% 96%,10% 100%,0 96%);
        filter:drop-shadow(0 40px 60px rgba(0,0,0,.4))">
        ${[80, 60, 70, 50, 65].map(w => `<div style="height:22px;width:${w}%;border-radius:11px;background:#E2E8F0;margin-bottom:30px"></div>`).join('')}
        <div style="margin-top:30px;height:4px;background:#CBD5E1"></div>
        <div style="margin-top:30px;display:flex;justify-content:space-between;font-weight:800;font-size:40px;color:#1E293B"><span>Total</span><span>$24</span></div>
      </div>
      <div style="position:absolute;transform:translate(190px,200px);width:170px;height:170px;border-radius:50%;
        background:linear-gradient(145deg,#34D399,#10B981);box-shadow:0 30px 60px rgba(0,0,0,.4);display:grid;place-items:center;border:6px solid rgba(255,255,255,.6)">
        <svg width="90" height="90" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="3.4" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12.5l4.5 4.5L19 7"/></svg>
      </div>
    </div>`,

  'art-orb': `<div style="position:relative;display:grid;place-items:center">
      ${glow('linear-gradient(135deg,#8B5CF6,#22D3EE)', 600, .6)}
      <div style="width:460px;height:460px;border-radius:50%;
        background:radial-gradient(circle at 30% 25%,rgba(255,255,255,.75),rgba(255,255,255,0) 30%),
          radial-gradient(circle at 70% 80%,rgba(34,211,238,.7),transparent 50%),linear-gradient(150deg,#8B5CF6,#4F46E5);
        box-shadow:0 60px 120px rgba(0,0,0,.45), inset -40px -50px 90px rgba(0,0,0,.3), inset 20px 20px 60px rgba(255,255,255,.25)"></div>
      ${sparkle(-240, -220, 50)}${sparkle(230, 180, 36, '#8FE6FF')}
    </div>`,
};

function writeImageset(name, file) {
  const dir = path.join(OUT, `${name}.imageset`);
  fs.mkdirSync(dir, { recursive: true });
  fs.writeFileSync(path.join(dir, 'Contents.json'), JSON.stringify({
    images: [{ filename: file, idiom: 'universal' }],
    info: { author: 'xcode', version: 1 },
  }, null, 2));
  return path.join(dir, file);
}

(async () => {
  const browser = await chromium.launch();
  const page = await browser.newPage({ viewport: { width: 1290, height: 2796 } });

  for (const [name, colors] of Object.entries(backgrounds)) {
    await page.setViewportSize({ width: 1290, height: 2796 });
    await page.setContent(backgroundHTML(colors));
    await page.screenshot({ path: writeImageset(`bg-${name}`, `bg-${name}.jpg`), type: 'jpeg', quality: 90 });
    console.log('bg', name);
  }

  for (const [name, html] of Object.entries(art)) {
    await page.setViewportSize({ width: 1024, height: 1024 });
    await page.setContent(`<html><head><style>*{box-sizing:border-box}</style></head><body style="${base}">${html}</body></html>`);
    await page.screenshot({ path: writeImageset(name, `${name}.png`), omitBackground: true });
    console.log('art', name);
  }

  await browser.close();
})();
