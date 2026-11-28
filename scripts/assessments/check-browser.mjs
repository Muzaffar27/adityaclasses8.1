// Local UI fixtures only; backend authorisation/persistence is covered by PHP tests.
import assert from 'node:assert/strict';
import { mkdir } from 'node:fs/promises';
import path from 'node:path';
import { pathToFileURL } from 'node:url';
const { chromium } = await import(process.env.PLAYWRIGHT_MODULE ? pathToFileURL(process.env.PLAYWRIGHT_MODULE).href : 'playwright');
const origin = process.argv[2] || 'http://localhost:8080';
if (!['localhost', '127.0.0.1'].includes(new URL(origin).hostname)) throw Error('Local verification only.');
const browser = await chromium.launch({ headless: true, ...(process.env.TEST_CHROME_PATH ? { executablePath: process.env.TEST_CHROME_PATH } : { channel: 'chrome' }) });
const directory = path.resolve('storage/app/automatic-assessment-verification'); await mkdir(directory, { recursive: true });
const errors = [];
const id = '11111111-1111-4111-8111-111111111111';
async function pressKey(page, name) {
    const key = page.getByRole('button', { name, exact: true });
    if (!await key.isVisible()) {
        const tab = name === 'Pi: π' || name.startsWith('Variable: ') ? 'Vars' : /^(\d|Decimal point|Delete previous character)$/.test(name) ? '123' : 'Maths';
        await page.getByRole('button', { name: tab, exact: true }).click();
    }
    await key.click();
}
async function drawStroke(page, label, type = 'touch') {
    const canvas = page.getByLabel(label, { exact: true });
    await canvas.scrollIntoViewIfNeeded();
    const box = await canvas.boundingBox();
    const points = [[0.2, 0.25], [0.55, 0.25], [0.4, 0.5], [0.25, 0.75]]
        .map(([x, y]) => ({ x: box.x + box.width * x, y: box.y + box.height * y }));
    const session = await page.context().newCDPSession(page);
    try {
        if (type === 'touch') {
            await session.send('Input.dispatchTouchEvent', { type: 'touchStart', touchPoints: [{ ...points[0], id: 1 }] });
            for (const point of points.slice(1)) await session.send('Input.dispatchTouchEvent', { type: 'touchMove', touchPoints: [{ ...point, id: 1 }] });
            await session.send('Input.dispatchTouchEvent', { type: 'touchEnd', touchPoints: [] });
        } else {
            await session.send('Input.dispatchMouseEvent', { type: 'mousePressed', ...points[0], button: 'left', buttons: 1, clickCount: 1, pointerType: 'pen' });
            for (const point of points.slice(1)) await session.send('Input.dispatchMouseEvent', { type: 'mouseMoved', ...point, buttons: 1, pointerType: 'pen' });
            await session.send('Input.dispatchMouseEvent', { type: 'mouseReleased', ...points.at(-1), button: 'left', buttons: 0, clickCount: 1, pointerType: 'pen' });
        }
    } finally { await session.detach(); }
}
async function hasCanvasInk(page, label) {
    return page.getByLabel(label, { exact: true }).evaluate(canvas => {
        const pixels = canvas.getContext('2d').getImageData(0, 0, canvas.width, canvas.height).data;
        return pixels.some((value, index) => index % 4 === 3 && value > 0);
    });
}
async function context(role, handler = async () => ({})) {
    const ctx = await browser.newContext({ viewport: { width: 390, height: 900 }, serviceWorkers: 'block', hasTouch: true });
    await ctx.addInitScript(() => localStorage.setItem('auth_token', 'isolated-browser-fixture'));
    await ctx.route('**/api/**', async route => {
        const url = new URL(route.request().url());
        const output = url.pathname === '/api/me' ? { user: { id: 9001, name: 'Browser fixture', role, student_profile: { must_change_password: false } } } : await handler(url, route.request());
        if (output?.abort) return route.abort('failed');
        await route.fulfill({ status: output?.httpStatus || 200, contentType: 'application/json', body: JSON.stringify(output?.body || output) });
    });
    const page = await ctx.newPage(); page.on('pageerror', e => errors.push(e.message));
    return { ctx, page };
}
try {
    const { ctx, page } = await context('student');
    await page.goto(origin+'/test-papers/demo');
    await page.getByRole('heading', { name: 'Try answering a question' }).waitFor();
    assert.equal(await page.locator('.response-sheet').count(), 1);
    assert.equal(await page.locator('[role=tabpanel]:visible').count(), 1);
    assert.equal(await page.getByRole('button', { name: /Write by hand|Show working/ }).count(), 0);
    assert.equal(await page.locator('input[type=file]').count(), 0);
    assert.equal(await page.getByRole('region', { name: 'Maths keypad', exact: true }).count(), 0);
    await page.locator('math-field[aria-label="Question 1 final answer"]').click();
    assert.equal(await page.getByRole('button', { name: /ABC|normal keyboard|Use maths keypad/ }).count(), 0);
    assert.equal(await page.locator('math-field[aria-label="Question 1 final answer"]').getAttribute('inputmode'), 'none');
    await pressKey(page, 'Space');
    assert.equal(await page.getByRole('button', { name: 'Submit demo', exact: true }).isDisabled(), true);
    await pressKey(page, 'Delete previous character');
    for (const value of ['x', 'y', 'a', 'b', 'c', 'm', 'n', 'p', 'q', 'r', 't']) {
        await pressKey(page, `Variable: ${value}`);
        assert.equal(await page.locator('math-field[aria-label="Question 1 final answer"]').evaluate(element => element.getValue('ascii-math')), value);
        await pressKey(page, 'Delete previous character');
    }
    for (const name of ['Percent: %', 'Pi: π', 'Degrees: °']) { await pressKey(page, name); await pressKey(page, 'Delete previous character'); }
    for (const name of ['3', 'Fraction: /', '4']) await pressKey(page, name);
    assert.equal(await page.locator('math-field[aria-label="Question 1 final answer"]').evaluate(element => element.getValue('ascii-math')), '(3)/(4)');
    await page.getByRole('button', { name: 'Maths', exact: true }).click();
    assert.equal(await page.locator('.fraction-icon span').count(), 2);
    assert.notEqual(await page.locator('.fraction-icon').evaluate(el => getComputedStyle(el, '::after').borderTopStyle), 'none');
    await page.getByRole('tab', { name: 'Working', exact: true }).click();
    assert.equal(await page.getByRole('region', { name: 'Maths keypad', exact: true }).count(), 1);
    await page.locator('math-field[aria-label="Question 1 working"]').click();
    for (const name of ['1', 'Add: +', '2']) await pressKey(page, name);
    assert.equal(await page.locator('math-field[aria-label="Question 1 working"]').evaluate(element => element.getValue('ascii-math')), '1+2');
    await page.getByRole('button', { name: 'New working line', exact: true }).click();
    await pressKey(page, 'Variable: y');
    assert.equal(await page.locator('math-field[aria-label="Question 1 working"]').evaluate(element => element.getValue('ascii-math')), '1+2\ny');
    await page.getByRole('button', { name: 'Maths', exact: true }).click();
    for (const width of [320, 390, 1440]) {
        await page.setViewportSize({ width, height: 900 });
        await page.evaluate(() => scrollTo(0, 0));
        assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth), false, `Overflow at ${width}`);
        const geometry = await page.locator('.maths-keypad').evaluate(element => ({ rows: getComputedStyle(element).gridTemplateRows.split(' ').length, widths: [...element.children].map(key => key.getBoundingClientRect().width) }));
        assert.equal(geometry.rows, 2);
        assert.ok(geometry.widths.every(width => width >= 44));
        if (width <= 640) assert.equal(await page.locator('.answer-keyboard').evaluate(element => Math.round(element.getBoundingClientRect().bottom)), 900);
        await page.screenshot({ path: path.join(directory, `keypad-${width}.png`), fullPage: true });
    }
    await page.getByRole('button', { name: 'Done', exact: true }).click();
    assert.equal(await page.getByRole('region', { name: 'Maths keypad', exact: true }).count(), 0);
    await page.getByRole('tab', { name: 'Working', exact: true }).focus();
    await page.keyboard.press('ArrowRight');
    assert.equal(await page.getByRole('tab', { name: 'Final answer', exact: true }).getAttribute('aria-selected'), 'true');
    assert.equal(await page.locator('math-field[aria-label="Question 1 final answer"]').evaluate(element => element.getValue('ascii-math')), '(3)/(4)');
    await page.keyboard.press('ArrowLeft');
    assert.equal(await page.getByRole('tab', { name: 'Working', exact: true }).getAttribute('aria-selected'), 'true');
    await page.locator('math-field[aria-label="Question 1 working"]').click();
    await pressKey(page, 'Space');
    assert.ok((await page.locator('math-field[aria-label="Question 1 working"]').evaluate(element => element.getValue('latex'))).includes('\\;'));
    await page.getByRole('heading', { name: 'Try answering a question' }).click();
    assert.equal(await page.getByRole('region', { name: 'Maths keypad', exact: true }).count(), 0);
    await page.getByRole('button', { name: 'Next', exact: true }).click();
    await page.locator('math-field[aria-label="Question 2 final answer"]').click();
    for (const name of ['2', 'Power: x superscript', '3']) await pressKey(page, name);
    assert.equal(await page.locator('math-field[aria-label="Question 2 final answer"]').evaluate(element => element.getValue('ascii-math')), '2^3');
    await page.getByRole('button', { name: 'Right', exact: true }).click();
    await page.locator('math-field[aria-label="Question 2 final answer"]').evaluate(element => element.executeCommand('selectAll'));
    await pressKey(page, 'Delete previous character');
    for (const name of ['Root: √', '9']) await pressKey(page, name);
    assert.equal(await page.locator('math-field[aria-label="Question 2 final answer"]').evaluate(element => element.getValue('ascii-math')), 'sqrt(9)');
    await page.getByRole('button', { name: 'Previous', exact: true }).click();
    assert.equal(await page.locator('math-field[aria-label="Question 1 final answer"]').evaluate(element => element.getValue('ascii-math')), '(3)/(4)');
    await page.getByRole('button', { name: 'Submit demo', exact: true }).click();
    await page.getByText('Demo submitted. On a real paper your answers would be saved and await review.').waitFor();
    assert.equal(await page.locator('math-field[aria-label="Question 1 final answer"]').evaluate(element => element.readOnly), true);
    await ctx.close();

    const mixedDemo = await context('student');
    await mixedDemo.page.goto(origin+'/test-papers/demo');
    await mixedDemo.page.setViewportSize({ width: 320, height: 800 });
    const mixedField = mixedDemo.page.locator('math-field').first();
    await mixedField.click();
    assert.deepEqual(await mixedDemo.page.locator('.keyboard-footer button').allTextContents(), ['Space', '← Left', 'Right →']);
    const arrowEntries = [
        { keys: ['3', 'Fraction: /', '4', 'Right', 'Add: +', '2'], expected: '(3)/(4)+2' },
        { keys: ['2', 'Power: x superscript', '3', 'Right', 'Add: +', '1'], expected: '2^3+1' },
        { keys: ['1', '2', '3', 'Left', '4'], expected: '1243' },
    ];
    for (const { keys, expected } of arrowEntries) {
        await mixedField.evaluate(element => element.executeCommand('selectAll'));
        await pressKey(mixedDemo.page, 'Delete previous character');
        for (const key of keys) await pressKey(mixedDemo.page, key);
        assert.equal(await mixedField.evaluate(element => element.getValue('ascii-math')), expected);
    }
    const mixedEntries = [
        ['1', 'Space', 'Fraction: /', '1', 'Right', '4'],
        ['1', 'Space', '1', 'Fraction: /', '4'],
    ];
    for (const keys of mixedEntries) {
        await mixedField.evaluate(element => element.executeCommand('selectAll'));
        await pressKey(mixedDemo.page, 'Delete previous character');
        for (const key of keys) await pressKey(mixedDemo.page, key);
        assert.equal(await mixedField.evaluate(element => element.value), '1\\;\\frac14', `Mixed entry ${keys.join(', ')}`);
    }
    await mixedDemo.page.waitForFunction(() => {
        const field = document.querySelector('math-field');
        const bounds = field?.getBoundingClientRect();
        const keyboard = document.querySelector('.answer-keyboard');
        return bounds && keyboard && bounds.bottom < keyboard.getBoundingClientRect().top - 8;
    });
    await mixedDemo.page.getByRole('tab', { name: 'Working', exact: true }).click();
    await mixedDemo.page.locator('math-field').nth(1).click();
    for (const width of [320, 390, 1440]) {
        await mixedDemo.page.setViewportSize({ width, height: 900 });
        assert.equal(await mixedDemo.page.evaluate(() => document.documentElement.scrollWidth > innerWidth), false);
        const widths = await mixedDemo.page.locator('.keyboard-footer button').evaluateAll(buttons => buttons.map(button => button.getBoundingClientRect().width));
        assert.ok(widths.every(width => width >= 44));
    }
    await mixedDemo.ctx.close();

    const inkDemo = await context('student');
    await inkDemo.page.goto(origin+'/test-papers/demo');
    assert.equal(await inkDemo.page.getByRole('button', { name: 'Handwrite', exact: true }).count(), 0);
    await inkDemo.page.getByRole('tab', { name: 'Working', exact: true }).click();
    const inkLabel = 'Question 1 working handwriting';
    const inkArea = inkDemo.page.getByRole('region', { name: `${inkLabel} area`, exact: true });
    await inkDemo.page.getByRole('button', { name: 'Handwrite', exact: true }).click();
    await drawStroke(inkDemo.page, inkLabel);
    assert.equal(await hasCanvasInk(inkDemo.page, inkLabel), true);
    await inkArea.getByRole('button', { name: 'Undo stroke', exact: true }).click();
    assert.equal(await hasCanvasInk(inkDemo.page, inkLabel), false);
    assert.equal(await inkDemo.page.getByRole('button', { name: 'Submit demo', exact: true }).isDisabled(), true);
    await drawStroke(inkDemo.page, inkLabel, 'pen');
    assert.equal(await hasCanvasInk(inkDemo.page, inkLabel), true);
    await inkArea.getByRole('button', { name: 'Clear', exact: true }).click();
    assert.equal(await hasCanvasInk(inkDemo.page, inkLabel), false);
    await inkArea.getByRole('button', { name: 'Undo stroke', exact: true }).click();
    assert.equal(await hasCanvasInk(inkDemo.page, inkLabel), true);
    await inkDemo.page.getByRole('tab', { name: 'Final answer', exact: true }).click();
    assert.equal(await inkDemo.page.getByRole('button', { name: 'Handwrite', exact: true }).count(), 0);
    await inkDemo.page.getByRole('tab', { name: 'Working', exact: true }).click();
    assert.equal(await hasCanvasInk(inkDemo.page, inkLabel), true);
    assert.equal(await inkArea.getByRole('button', { name: 'Undo stroke', exact: true }).isEnabled(), true);
    await inkDemo.page.getByRole('button', { name: 'Keypad', exact: true }).click();
    for (const width of [320, 390, 1440]) {
        await inkDemo.page.setViewportSize({ width, height: 900 });
        assert.equal(await inkDemo.page.evaluate(() => document.documentElement.scrollWidth > innerWidth), false);
        assert.equal(await hasCanvasInk(inkDemo.page, inkLabel), true);
    }
    await inkDemo.page.getByRole('button', { name: 'Next', exact: true }).click();
    await inkDemo.page.getByRole('button', { name: 'Previous', exact: true }).click();
    await inkDemo.page.getByRole('tab', { name: 'Working', exact: true }).click();
    assert.equal(await hasCanvasInk(inkDemo.page, inkLabel), true);
    await inkDemo.page.getByRole('tab', { name: 'Working', exact: true }).click();
    await inkDemo.page.getByRole('button', { name: 'Handwrite', exact: true }).click();
    await drawStroke(inkDemo.page, 'Question 1 working handwriting');
    assert.equal(await hasCanvasInk(inkDemo.page, 'Question 1 working handwriting'), true);
    await inkDemo.page.getByRole('button', { name: 'Submit demo', exact: true }).click();
    assert.equal(await inkDemo.page.getByRole('button', { name: 'Handwrite', exact: true }).count(), 0);
    await inkDemo.ctx.close();

    let revision = 0, status = 'available', failNextSave = false;
    let holdNextSave = false, releaseSave;
    const questionOrder = [1, 2, 3, 4, 5, 7, 6, 8, 9, 10, 11, 12, 13, 14, 15];
    const answers = Object.fromEntries(questionOrder.filter(number => ![1, 6].includes(number))
        .map(number => [number, { answer: '2', working: '' }]));
    answers[7] = { answer: '3/4', working: 'My working\n1 + 2' };
    const paper = () => ({ id, grade: 'Grade 8', subject: 'Mathematics', status, version: 1,
        answering_available: ['available', 'started'].includes(status), handwriting_available: status === 'started', marking_ready: false,
        questions: questionOrder.map(number => ({ number, content: { instructions: 'Calculate:', text: '1 + 1' } })),
        responses: { revision, answers: Object.entries(answers).map(([number, a]) => ({ number: Number(number), ...a })) } });
    const live = await context('student', async (url, request) => {
        if (url.pathname === '/api/test-papers/attention') return { total: ['available', 'started'].includes(status) ? 1 : 0,
            available_count: status === 'available' ? 1 : 0, started_count: status === 'started' ? 1 : 0,
            next: ['available', 'started'].includes(status) ? { id, status, grade: 'Grade 8', subject: 'Mathematics' } : null };
        if (url.pathname === '/api/student/dashboard') return { student: { name: 'Browser fixture' }, stats: {}, courses: [] };
        if (url.pathname === '/api/test-papers') return { data: [paper()], last_page: 1 };
        if (url.pathname.endsWith('/start')) { status = 'started'; return paper(); }
        if (url.pathname.endsWith('/answers')) {
            if (failNextSave) { failNextSave = false; return { abort: true }; }
            const body = request.postDataJSON();
            if (holdNextSave) { holdNextSave = false; await new Promise(resolve => { releaseSave = resolve; }); }
            if (body.revision !== revision) return { httpStatus: 409, body: { message: 'Another tab saved newer answers.' } };
            body.answers.forEach(({ number, ...a }) => { answers[number] = a; }); revision++; return { revision };
        }
        if (url.pathname.endsWith('/submit')) { assert.equal(request.postDataJSON().revision, revision); status = 'awaiting_review'; return paper(); }
        if (url.pathname === `/api/test-papers/${id}`) return paper();
        return {};
    });
    await live.page.goto(origin+'/dashboard');
    await live.page.getByRole('status', { name: 'Test paper reminder' }).waitFor();
    await live.page.locator('.paper-count').filter({ hasText: '1 to do' }).waitFor();
    for (const width of [320, 390, 1440]) {
        await live.page.setViewportSize({ width, height: 900 });
        assert.equal(await live.page.evaluate(() => document.documentElement.scrollWidth > innerWidth), false, `Dashboard overflow at ${width}`);
        await live.page.screenshot({ path: path.join(directory, `paper-reminder-${width}.png`), fullPage: true });
    }
    await live.page.setViewportSize({ width: 390, height: 900 });
    await live.page.getByRole('status', { name: 'Test paper reminder' }).getByRole('link', { name: 'Open test paper' }).click();
    await live.page.waitForURL(origin+`/test-papers/${id}`);
    assert.equal(new URL(live.page.url()).pathname, `/test-papers/${id}`);
    await live.page.getByRole('button', { name: 'Start answering', exact: true }).click();
    assert.equal(await live.page.getByRole('status', { name: 'Test paper reminder' }).count(), 0);
    await live.page.getByRole('link', { name: 'Dashboard', exact: true }).first().click();
    await live.page.getByRole('status', { name: 'Test paper reminder' }).getByRole('link', { name: 'Resume test paper' }).click();
    await live.page.locator('math-field[aria-label="Question 1 final answer"]').click();
    const firstSave = live.page.waitForResponse(r => r.url().endsWith('/answers') && r.status() === 200);
    await live.page.getByRole('button', { name: '2', exact: true }).click(); await firstSave;
    assert.equal(answers[1].answer, '\\(2\\)');
    await live.page.reload();
    await live.page.locator('math-field[aria-label="Question 1 final answer"]').waitFor();
    assert.equal(await live.page.locator('math-field[aria-label="Question 1 final answer"]').evaluate(element => element.getValue('ascii-math')), '2');
    await live.page.locator('math-field[aria-label="Question 1 final answer"]').click();
    const pageNav = live.page.getByRole('navigation', { name: 'Question pages' });
    failNextSave = true;
    await live.page.getByRole('button', { name: '3', exact: true }).click();
    await pageNav.getByRole('button', { name: /Next/ }).click();
    await live.page.getByRole('button', { name: 'Retry save', exact: true }).waitFor();
    await live.page.getByRole('heading', { name: 'Question 1', exact: true }).waitFor();
    assert.equal(await live.page.locator('math-field[aria-label="Question 1 final answer"]').evaluate(element => element.getValue('ascii-math')), '23');
    const recovered = live.page.waitForResponse(r => r.url().endsWith('/answers') && r.status() === 200);
    await live.page.getByRole('button', { name: 'Retry save', exact: true }).click();
    await recovered;
    await live.page.locator('math-field[aria-label="Question 1 final answer"]').click();
    revision++;
    const conflict = live.page.waitForResponse(r => r.url().endsWith('/answers') && r.status() === 409);
    await live.page.getByRole('button', { name: '4', exact: true }).click(); await conflict;
    assert.equal(await live.page.locator('math-field[aria-label="Question 1 final answer"]').evaluate(element => element.getValue('ascii-math')), '234');
    const retry = live.page.waitForResponse(r => r.url().endsWith('/answers') && r.status() === 200);
    await live.page.getByRole('button', { name: 'Retry using this draft', exact: true }).click(); await retry;
    assert.equal(answers[1].answer, '\\(234\\)');
    await live.page.locator('math-field').first().evaluate(element => {
        element.insert('9'.repeat(1001));
    });
    await live.page.getByText('This field has reached its length limit.', { exact: true }).waitFor();
    assert.equal(await live.page.locator('math-field').first().evaluate(element => element.getValue('ascii-math')), '234');
    // Legacy text renders without rewriting its saved response.
    const questionNumbers = () => live.page.locator('.paper .question h3').allTextContents();
    const expected = first => Array.from({ length: 5 }, (_, i) => 'Question ' + (first + i));
    assert.deepEqual(await questionNumbers(), expected(1));
    for (const width of [320, 390, 1440]) {
        await live.page.setViewportSize({ width, height: 900 });
        assert.equal(await live.page.evaluate(() => document.documentElement.scrollWidth > innerWidth), false);
    }
    await live.page.setViewportSize({ width: 390, height: 900 });
    await pageNav.getByRole('button', { name: /Next/ }).click();
    assert.deepEqual(await questionNumbers(), expected(6));
    assert.equal(await live.page.locator('math-field').first().evaluate(element => element.getValue('ascii-math')), '(3)/(4)');
    assert.equal(answers[7].answer, '3/4');
    await live.page.locator('.question').first().getByRole('tab', { name: 'Working', exact: true }).click();
    assert.ok((await live.page.locator('math-field').nth(1).evaluate(element => element.value)).includes('\\text{My working}'));
    assert.equal(answers[7].working, 'My working\n1 + 2');
    await pageNav.getByRole('button', { name: /Next/ }).click();
    assert.deepEqual(await questionNumbers(), expected(11));
    assert.ok(await pageNav.getByRole('button', { name: /Next/ }).isDisabled());
    await pageNav.getByRole('button', { name: /Previous/ }).click();
    await pageNav.getByRole('button', { name: /Previous/ }).click();
    const entry = live.page.locator('math-field').first();
    await entry.click();
    await entry.evaluate(element => element.executeCommand('selectAll'));
    await pressKey(live.page, 'Delete previous character');
    await pressKey(live.page, 'Fraction: /');
    await pressKey(live.page, '3');
    await live.page.getByText('All changes saved', { exact: true }).waitFor();
    assert.ok(answers[1].answer.includes('placeholder'));
    const unfinished = answers[1].answer;
    await live.page.reload();
    await live.page.locator('math-field').first().waitFor();
    assert.equal(await live.page.locator('math-field').first().evaluate(element => `\\(${element.value}\\)`), unfinished);
    await live.page.locator('math-field').first().click();
    await live.page.getByRole('button', { name: 'Left', exact: true }).click();
    await pressKey(live.page, '4');
    await live.page.getByRole('button', { name: 'Right', exact: true }).click();
    await pressKey(live.page, 'Add: +');
    await pressKey(live.page, '2');
    await pressKey(live.page, 'Power: x superscript');
    await pressKey(live.page, '3');
    await live.page.getByText('All changes saved', { exact: true }).waitFor();
    await live.page.reload();
    await live.page.locator('math-field').first().waitFor();
    assert.equal(await live.page.locator('math-field').first().evaluate(element => element.getValue('ascii-math')), '(3)/(4)+2^3');
    assert.equal(await live.page.getByRole('button', { name: 'Handwrite', exact: true }).count(), 0);
    await live.page.locator('.question').first().getByRole('tab', { name: 'Working', exact: true }).click();
    await live.page.getByRole('button', { name: 'Handwrite', exact: true }).click();
    holdNextSave = true;
    const inkRequest = live.page.waitForRequest(request => request.url().endsWith('/answers'));
    await drawStroke(live.page, inkLabel);
    await inkRequest;
    await drawStroke(live.page, inkLabel, 'pen');
    releaseSave();
    await live.page.getByText('All changes saved', { exact: true }).waitFor();
    assert.equal(answers[1].working_ink.strokes.length, 2);
    assert.equal('answer_ink' in answers[1], false);
    await live.page.reload();
    await live.page.locator('.question').first().getByRole('tab', { name: 'Working', exact: true }).click();
    await live.page.getByLabel(inkLabel, { exact: true }).waitFor();
    assert.equal(await hasCanvasInk(live.page, inkLabel), true);
    await live.page.locator('.question').first().getByRole('tab', { name: 'Working', exact: true }).click();
    await live.page.getByRole('button', { name: 'Keypad', exact: true }).click();
    await live.page.locator('math-field[aria-label="Question 1 working"]').click();
    for (const key of ['1', 'Space', '1', 'Fraction: /', '4']) await pressKey(live.page, key);
    await live.page.getByRole('button', { name: 'Handwrite', exact: true }).click();
    const workingSaved = live.page.waitForResponse(response => response.url().endsWith('/answers') && response.status() === 200);
    await drawStroke(live.page, 'Question 1 working handwriting');
    await workingSaved;
    assert.equal(answers[1].working_ink.strokes.length, 3);
    assert.equal(answers[1].working, '\\(1\\;\\frac14\\)');
    await live.page.reload();
    await live.page.locator('.question').first().getByRole('tab', { name: 'Working', exact: true }).click();
    await live.page.locator('math-field[aria-label="Question 1 working"]').waitFor();
    assert.equal(await live.page.locator('math-field[aria-label="Question 1 working"]').evaluate(element => element.value), '1\\;\\frac14');
    assert.equal(await live.page.locator('math-field').first().evaluate(element => element.getValue('ascii-math')), '(3)/(4)+2^3');
    const submission = live.page.getByRole('region', { name: 'Answer saving and submission' });
    for (const width of [320, 390, 1440]) {
        await live.page.setViewportSize({ width, height: 900 });
        assert.equal(await live.page.evaluate(() => document.documentElement.scrollWidth > innerWidth), false);
        const header = await live.page.locator('.reader-header').boundingBox();
        const actions = await submission.boundingBox();
        const firstQuestion = await live.page.locator('.question').first().boundingBox();
        assert.ok(header.height < (width < 540 ? 120 : 80));
        assert.ok(actions.y < firstQuestion.y);
    }
    await live.page.setViewportSize({ width: 390, height: 900 });
    await submission.getByRole('button', { name: 'Submit answers', exact: true }).click();
    await submission.getByRole('alert').filter({ hasText: 'Final answers missing for questions 7.' }).waitFor();
    assert.equal(await submission.getByRole('button', { name: 'Submit now', exact: true }).count(), 0);
    assert.equal(status, 'started');
    await pageNav.getByRole('button', { name: /Next/ }).click();
    await live.page.locator('math-field[aria-label="Question 7 final answer"]').click();
    await pressKey(live.page, '0');
    await live.page.getByText('All changes saved', { exact: true }).waitFor();
    assert.equal(answers[6].answer, '\\(0\\)');
    await submission.getByRole('button', { name: 'Submit answers', exact: true }).click();
    await submission.getByRole('button', { name: 'Submit now', exact: true }).click();
    await live.page.getByText(/Your answers are saved and awaiting review/).waitFor();
    assert.equal(status, 'awaiting_review');
    assert.equal(await live.page.getByRole('button', { name: 'Handwrite', exact: true }).count(), 0);
    await live.page.locator('.question').first().getByRole('tab', { name: 'Working', exact: true }).click();
    await live.page.waitForFunction(label => {
        const canvas = document.querySelector(`canvas[aria-label="${label}"]`);
        return canvas?.width > 0 && canvas?.height > 0;
    }, inkLabel);
    assert.equal(await hasCanvasInk(live.page, inkLabel), true);
    await live.page.getByRole('link', { name: 'Dashboard', exact: true }).first().click();
    await live.page.getByRole('heading', { name: 'Test papers', exact: true }).waitFor();
    assert.equal(await live.page.getByRole('status', { name: 'Test paper reminder' }).count(), 0);
    assert.equal(await live.page.locator('.paper-count').count(), 0);
    await live.ctx.close();

    let failAttention = true;
    const recovery = await context('student', async url => {
        if (url.pathname === '/api/test-papers/attention') return { total: 1, next: { id, status: 'available', subject: 'Mathematics', grade: 'Grade 8' } };
        if (url.pathname === '/api/test-papers') return failAttention ? { abort: true } : { data: [], current_page: 1, last_page: 1 };
        if (url.pathname === '/api/student/dashboard') return { student: { name: 'Browser fixture' }, stats: {}, courses: [] };
        return {};
    });
    await recovery.page.goto(origin+'/dashboard');
    await recovery.page.locator('.test-card').getByRole('button', { name: 'Try again', exact: true }).waitFor();
    failAttention = false;
    await recovery.page.locator('.test-card').getByRole('button', { name: 'Try again', exact: true }).click();
    await recovery.page.getByRole('status', { name: 'Test paper reminder' }).waitFor();
    await recovery.ctx.close();

    let enabled = null, checked = false;
    const staff = await context('tutor', async (url, request) => {
        if (url.pathname === '/api/getStudents') return [{ id: 9001, name: 'Fixture student' }];
        if (url.pathname === '/api/grades') return [{ id: 2, name: 'Grade 8' }];
        if (url.pathname === '/api/subjects') return [{ id: 1, name: 'Mathematics' }];
        if (['/api/admin/test-papers', '/api/test-papers'].includes(url.pathname)) return { data: [], last_page: 1 };
        if (url.pathname.includes('/students/')) { enabled = true; return { id: 1, enabled: true }; }
        if (url.pathname.endsWith('/reconcile')) { checked = true; return { status: 'waiting_questions' }; }
        if (request.method() === 'PUT') { enabled = request.postDataJSON().enabled; return { enabled }; }
        if (url.pathname === '/api/admin/automatic-test-papers') return { data: enabled === null ? [] : [{ enrolment: { id: 1, grade_id: 2, subject_id: 1, enabled,
            last_status: checked ? 'waiting_questions' : 'waiting_topics' }, batches: checked ? [{ id, sequence: 1, topics: ['A', 'B', 'C'], status: 'waiting_questions', details: { message: 'Approve more questions for topic A.' } }] : [] }] };
        return {};
    });
    await staff.page.goto(origin+'/test-papers');
    assert.equal(await staff.page.getByRole('status', { name: 'Test paper reminder' }).count(), 0);
    await staff.page.getByLabel('Student', { exact: true }).selectOption('9001');
    await staff.page.getByLabel('Grade', { exact: true }).selectOption('2'); await staff.page.getByLabel('Subject', { exact: true }).selectOption('1');
    await staff.page.getByRole('button', { name: 'Check this course', exact: true }).click();
    await staff.page.getByRole('button', { name: 'Check now', exact: true }).click();
    await staff.page.getByText('More approved questions are needed', { exact: true }).first().waitFor();
    await staff.page.getByRole('button', { name: 'Pause', exact: true }).click();
    await staff.page.getByRole('button', { name: 'Resume', exact: true }).waitFor();
    assert.equal(enabled, false);
    for (const width of [320, 390, 1440]) { await staff.page.setViewportSize({ width, height: 900 }); assert.equal(await staff.page.evaluate(() => document.documentElement.scrollWidth > innerWidth), false); }
    await staff.ctx.close();
    assert.deepEqual(errors, []);
    console.log('Browser fixtures passed: mixed numbers/Space, fractions/powers, handwriting, undo/clear, save/reload/conflict/retry/submit, 320/390/1440 layouts, staff controls. No page runtime errors.');
} finally { await browser.close(); }
