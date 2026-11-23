// Local browser fixtures; persisted ordering/ownership is tested in SavedTestPaperTest.
import assert from 'node:assert/strict';
import { mkdir } from 'node:fs/promises';
import path from 'node:path';
import { pathToFileURL } from 'node:url';
const { chromium } = await import(process.env.PLAYWRIGHT_MODULE ? pathToFileURL(process.env.PLAYWRIGHT_MODULE).href : 'playwright');
const origin = 'http://localhost:8080';
const browser = await chromium.launch({ headless: true, ...(process.env.TEST_CHROME_PATH ? { executablePath: process.env.TEST_CHROME_PATH } : { channel: 'chrome' }) });
const directory = path.resolve('storage/app/automatic-assessment-verification');
await mkdir(directory, { recursive: true });
const errors = [];
try {
    const context = await browser.newContext({ viewport: { width: 390, height: 900 }, serviceWorkers: 'block', reducedMotion: 'reduce' });
    await context.addInitScript(() => localStorage.setItem('auth_token', 'dashboard-list-fixture'));
    const page = await context.newPage();
    page.on('pageerror', e => errors.push(e.message));
    const papers = ['available', 'started', 'available', 'available', 'awaiting_review', 'marked', 'cancelled'].map((status, i) => ({
        id: `11111111-1111-4111-8111-11111111110${i}`, status, subject: i === 0 ? 'Mathematics' : `Subject ${i + 1}`,
        grade: 'Grade 8', available_at: `2026-11-${String(i + 1).padStart(2, '0')}T10:00:00Z`,
    }));
    let failList = false, empty = false;
    const unfinished = () => empty ? [] : papers.filter(p => ['available', 'started'].includes(p.status));
    await context.route('**/api/**', async route => {
        const url = new URL(route.request().url());
        let data = {};
        if (url.pathname === '/api/me') data = { user: { id: 9001, name: 'Dashboard preview', role: 'student' } };
        if (url.pathname === '/api/student/dashboard') data = { student: { name: 'Dashboard preview' }, stats: {}, courses: [] };
        if (url.pathname === '/api/test-papers/attention') data = { total: unfinished().length, next: unfinished()[0] || null };
        if (url.pathname === '/api/test-papers') {
            if (failList) return route.abort('failed');
            const all = empty ? [] : [...unfinished(), ...papers.filter(p => !['available', 'started'].includes(p.status))];
            const current = Number(url.searchParams.get('page') || 1);
            const size = Number(url.searchParams.get('per_page') || 20);
            data = { data: all.slice((current - 1) * size, current * size), current_page: current, last_page: Math.max(1, Math.ceil(all.length / size)) };
        }
        if (url.pathname.startsWith('/api/test-papers/111')) data = { ...papers.find(p => url.pathname.endsWith(p.id)), version: 1, questions: [], responses: { revision: 0, answers: [] }, attempt: {} };
        await route.fulfill({ json: data });
    });
    await page.clock.install();
    await page.goto(origin + '/dashboard');
    const card = page.getByRole('article', { name: 'Test papers' });
    const reminder = page.getByRole('status', { name: 'Test paper reminder' });
    await card.locator('li').nth(4).waitFor();
    assert.equal(await card.locator('li').count(), 5);
    assert.equal(await reminder.getByRole('link').getAttribute('href'), '/test-papers/' + papers[0].id);
    assert.equal(await card.locator('li').first().getByText('Up next', { exact: true }).count(), 1);
    await card.getByRole('link', { name: 'Resume Subject 2 test paper', exact: true }).waitFor();
    await card.getByRole('link', { name: 'View Subject 5 test paper', exact: true }).waitFor();
    for (const width of [320, 390, 768, 1440]) {
        await page.setViewportSize({ width, height: 900 });
        await page.evaluate(() => Promise.all(document.getAnimations().filter(a => a.effect?.getComputedTiming().iterations !== Infinity).map(a => a.finished.catch(() => {}))));
        assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth), false, `Overflow at ${width}`);
        assert.ok((await card.locator('li a').first().boundingBox()).height >= 44);
        assert.equal(await reminder.evaluate(el => getComputedStyle(el).borderRadius), '14px');
        await page.screenshot({ path: path.join(directory, `pink-dashboard-papers-${width}.png`), fullPage: true });
    }
    await card.getByRole('button', { name: 'Next', exact: true }).click();
    await card.getByRole('link', { name: 'View Subject 7 test paper', exact: true }).waitFor();
    assert.equal(await card.locator('li').count(), 2);
    await card.getByRole('button', { name: 'Previous', exact: true }).click();
    await card.getByRole('link', { name: 'Start Mathematics test paper', exact: true }).waitFor();
    papers[0].status = 'awaiting_review';
    await page.clock.fastForward(31000);
    await reminder.getByRole('link', { name: 'Resume test paper', exact: true }).waitFor();
    assert.equal(await reminder.getByRole('link').getAttribute('href'), '/test-papers/' + papers[1].id);
    await card.locator('li').first().getByText('Subject 2', { exact: true }).waitFor();
    assert.equal(await card.locator('.paper-count').innerText(), '3 to do');
    await card.getByRole('link', { name: 'Resume Subject 2 test paper', exact: true }).click();
    await page.waitForURL(origin + '/test-papers/' + papers[1].id);
    await reminder.waitFor({ state: 'hidden' });
    assert.equal(await reminder.count(), 0);
    failList = true;
    await page.goto(origin + '/dashboard');
    await card.getByRole('button', { name: 'Try again', exact: true }).waitFor();
    failList = false;
    await card.getByRole('button', { name: 'Try again', exact: true }).click();
    await card.locator('li').first().getByText('Subject 2', { exact: true }).waitFor();
    empty = true;
    await page.reload();
    await card.getByText(/Your first paper will appear/).waitFor();
    assert.equal(await reminder.count(), 0);
    assert.deepEqual(errors, []);
    await context.close();
    console.log('Dashboard paper fixtures passed: pink announcement-style reminder, oldest-first list, Start/Resume/View, pagination, readiness refresh, retry/empty states, correct links and 320/390/768/1440 layouts. No page errors.');
} finally { await browser.close(); }
