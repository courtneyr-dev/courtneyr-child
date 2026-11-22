import { chromium } from '@playwright/test';
const b = await chromium.launch({ executablePath: '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome' });
for (const [n,u,sel] of [['favorite','http://localhost:8887/2026/08/16/favorite-favorited-a-post/','.cr-fav-sheet'],['repost','http://localhost:8887/2026/08/17/repost-reposted-a-post/','.cr-repost-clip--single']]) {
 const p = await b.newPage({viewport:{width:1448,height:900}}); await p.goto(u,{waitUntil:'networkidle'});
 console.log(n, JSON.stringify(await p.evaluate((s)=>{const q=(e)=>{const r=document.querySelector(e)?.getBoundingClientRect();return r&&[Math.round(r.left*10)/10,Math.round(r.width*10)/10]};return {content:q('.single-post__content'),entry:q('.pkiw-singular-entry'),record:q(s),wide:document.querySelector('.single-post__content').classList.contains('cr-wide-column')}},sel)));
}
await b.close();
