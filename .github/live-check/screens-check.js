// The every-screen check (Celestine, 31 Oct / 1 Nov): every public page and app screen of a test Stubli
// filled with awkward content (awkward-seed.sh), at 360 and 390 px (phones) and 1280 px (computer),
// as a visitor and as the owner. Fails on: a page wider than the screen, codes like &amp; or &#039;
// showing as text, a collection tab that opens empty, broken images, script errors, and pages that error.
// Usage: node screens-check.js <base-url> <user> <password> <stage new|full|live> [shots-dir]
// Stage "live" (the Monday health check on stubli.app): visitor pages only, plus stubli.app's own pages; pass - - for user and password.
// Read-only: it opens pages and changes nothing (Setup is opened but never saved).
const { chromium } = require('playwright');
(async () => {
  const [,, BASE, USER, PASS, STAGE, SHOTS] = process.argv;
  const WIDTHS = [360, 390, 1280];
  let pass = 0, fail = 0; const fails = [];
  const ok = (c, m) => { if (c) { pass++; } else { fail++; fails.push(m); console.log('FAIL ' + m); if (process.env.GITHUB_ACTIONS && STAGE === 'live') { console.log('::error title=Out of step::stubli.app page: ' + m.replace(/[\r\n]+/g, ' ')); } } };
  const b = await chromium.launch();
  const ENT = /&(amp|quot|lt|gt|apos|nbsp|#0*39|#\d{2,6}|#x[0-9a-f]{2,6});/i;

  async function look(p, label, w, errs, bad) {
    await p.waitForTimeout(400);
    const r = await p.evaluate(() => {
      const W = window.innerWidth, sw = document.documentElement.scrollWidth;
      let wide = '';
      if (sw > W + 1) {
        for (const el of document.querySelectorAll('body *')) {
          const cs = getComputedStyle(el); if (cs.position === 'fixed' || cs.display === 'none') { continue; }
          let p = el.parentElement, clipped = false;
          while (p && p !== document.body) { const o = getComputedStyle(p).overflowX; if (o === 'auto' || o === 'scroll' || o === 'hidden') { clipped = true; break; } p = p.parentElement; }
          if (!clipped && el.getBoundingClientRect().right > W + 1) { wide = el.tagName.toLowerCase() + (el.className && typeof el.className === 'string' ? '.' + el.className.trim().split(/\s+/).join('.') : ''); break; }
        }
      }
      const text = document.body ? document.body.innerText : '';
      const broken = [...document.images].filter(i => i.complete && i.getAttribute('src') && i.naturalWidth === 0).map(i => i.getAttribute('src').slice(-50));
      return { W, sw, wide, text, broken, title: document.title };
    });
    ok(r.sw <= r.W + 1, `${label} @${w}: page is ${r.sw}px wide on a ${r.W}px screen${r.wide ? ' (first thing sticking out: ' + r.wide + ')' : ''}`);
    const m = r.text.match(ENT) || r.title.match(ENT);
    ok(!m, `${label} @${w}: shows a code as text: "${m ? (r.text + ' ' + r.title).slice(Math.max(0, (r.text + ' ' + r.title).indexOf(m[0]) - 30), (r.text + ' ' + r.title).indexOf(m[0]) + 20).replace(/\s+/g, ' ') : ''}"`);
    // A first name taken from the site's name must not keep its "'s" ("Zoë's is still choosing", "Zoë's's journal").
    const g = r.text.match(/\S+['’]s['’]s\b|\S+['’]s (is|was|has) (still|probably)\b/);
    ok(!g, `${label} @${w}: a name reads wrong: "${g ? g[0] : ''}"`);
    ok(!r.broken.length, `${label} @${w}: broken image(s) ${r.broken.join(', ')}`);
    ok(!errs.length, `${label} @${w}: script error ${errs.join(' | ')}`);
    ok(!bad.length, `${label} @${w}: failed to load ${bad.join(', ')}`);
    errs.length = 0; bad.length = 0;
    if (SHOTS && w !== 360) { await p.screenshot({ path: `${SHOTS}/${STAGE}-${w}-${label.replace(/[^a-z0-9]+/gi, '_').slice(0, 60)}.png`, fullPage: w === 390 }); }
    return r;
  }
  function watch(p) {
    const errs = [], bad = [];
    p.on('pageerror', e => errs.push(e.message.slice(0, 120)));
    p.on('response', r => { const u = r.url(); if (u.startsWith(BASE) && r.status() >= 400 && !/favicon\.ico/.test(u) && r.request().resourceType() !== 'fetch' && r.request().resourceType() !== 'xhr') { bad.push(r.status() + ' ' + u.replace(BASE, '')); } });
    return { errs, bad };
  }

  // Public pages, as a visitor: the front page, every tab, and one post and one journal entry.
  const v = await b.newContext({ viewport: { width: 390, height: 844 } }); const vp = await v.newPage();
  await vp.goto(BASE + '/', { waitUntil: 'networkidle' });
  const tabs = await vp.$$eval('a.st-tab', as => as.map(a => [a.textContent.trim(), a.getAttribute('href')]));
  const firstPost = await vp.$eval('a[href*="?p="], .st-tile a, a.st-tile', a => a.getAttribute('href')).catch(() => '');
  const entry = await vp.goto(BASE + '/journal/', { waitUntil: 'networkidle' }).then(() => vp.$eval('.st-journal-grid a.st-tile-entry, .st-entry a[href], .st-entry-title a', a => a.getAttribute('href')).catch(() => ''));
  const pages = [['front page', '/']];
  for (const [name, href] of tabs) { if (href && !pages.some(x => x[1] === href)) { pages.push(['tab ' + name, href]); } }
  for (const extra of ['/journal/', '/favourites/', '/guest-book/'].concat(STAGE === 'live' ? ['/welcome/', '/get-started/', '/how-it-works/', '/help/', '/feedback/', '/privacy/'] : [])) { if (!pages.some(x => x[1].replace(BASE, '') === extra)) { pages.push([extra, extra]); } }
  if (firstPost) { pages.push(['a post', firstPost]); }
  if (entry) { pages.push(['a journal entry', entry]); }
  if (STAGE !== 'new') { ok(tabs.length >= 3, `the front page has tabs for the collections (found ${tabs.length}: ${tabs.map(t => t[0]).join(' | ')})`); }
  await v.close();

  for (const w of WIDTHS) {
    const c = await b.newContext({ viewport: { width: w, height: w > 500 ? 900 : 800 } }); const p = await c.newPage(); const { errs, bad } = watch(p);
    for (const [name, href] of pages) {
      const url = href.startsWith('http') ? href : BASE + href;
      const res = await p.goto(url, { waitUntil: 'networkidle' });
      ok(res && res.status() < 400, `visitor ${name} @${w}: opens (${res && res.status()})`);
      await look(p, 'visitor ' + name, w, errs, bad);
      if (STAGE !== 'new' && name.startsWith('tab ')) { ok(!(await p.$('div.st-empty')), `visitor ${name} @${w}: the tab opens empty`); }
    }
    await c.close();
  }

  // The owner: the same pages (with owner buttons) and every app screen.
  if (USER === '-') { await b.close(); if (process.env.GITHUB_ACTIONS) { console.log(`::notice title=Live pages::stubli.app pages at 360, 390 and 1280 px: ${pass} passed, ${fail} failed`); } console.log(`screens (${STAGE}): ${pass} passed, ${fail} failed`); process.exit(fail ? 1 : 0); }
  const ids = await (async () => {
    const c = await b.newContext(); const p = await c.newPage();
    await p.goto(BASE + '/wp-login.php'); await p.fill('#user_login', USER); await p.fill('#user_pass', PASS);
    await Promise.all([p.waitForNavigation(), p.click('#wp-submit')]);
    const st = await c.storageState(); await c.close(); return st;
  })();
  const postId = firstPost ? ((firstPost.match(/[?&]p=(\d+)/) || [])[1] || '') : '';
  let pid = postId;
  for (const w of WIDTHS) {
    const c = await b.newContext({ viewport: { width: w, height: w > 500 ? 900 : 800 }, storageState: ids }); const p = await c.newPage(); const { errs, bad } = watch(p);
    for (const [name, href] of pages.slice(0, 3)) {
      await p.goto(href.startsWith('http') ? href : BASE + href, { waitUntil: 'networkidle' });
      await look(p, 'owner ' + name, w, errs, bad);
    }
    if (!pid) { pid = await p.evaluate(async () => { try { const r = await fetch('/wp-json/wp/v2/posts?per_page=1&_fields=id'); const j = await r.json(); return j[0] ? String(j[0].id) : ''; } catch (e) { return ''; } }); }
    const screens = STAGE === 'new'
      ? ['#setup', '#home', '#you', '#more', '#whatsnew']
      : ['#home', '#discover', '#whatsnew', '#letters', '#you', '#more', '#about', '#look', '#journals', '#journal', '#guestbook', '#links', '#links/new', '#links/groups', '#arrange', '#feedback', '#import', '#new'].concat(pid ? ['#edit/' + pid, '#comments/' + pid] : []);
    await p.goto(BASE + '/app/', { waitUntil: 'networkidle' });
    for (const h of screens) {
      await p.evaluate(x => { location.hash = x; }, h);
      await p.waitForLoadState('networkidle'); await p.waitForTimeout(500);
      await look(p, 'app ' + h, w, errs, bad);
    }
    await c.close();
  }
  await b.close();
  console.log(`screens (${STAGE}): ${pass} passed, ${fail} failed`);
  process.exit(fail ? 1 : 0);
})();
