import test from 'node:test';
import assert from 'node:assert/strict';
import { questionRegions, selectTest, cleanInstructions, joinPdfText } from '../../scripts/questions/pdf-question-core.mjs';
import { renderTest } from '../../scripts/questions/render-local-test.mjs';

const item = (str, x, y) => ({ str, transform: [1, 0, 0, 12, x, 840 - y], width: str.length * 6, height: 12 });
test('instruction fragments join by PDF spacing instead of inserting spaces inside words', () => {
    assert.equal(joinPdfText([{ text: 'Simpl', x: 54, width: 30, height: 12 }, { text: 'if', x: 84.1, width: 7.3, height: 12 }, { text: 'y the following', x: 91.4, width: 80, height: 12 }]), 'Simplify the following');
    assert.equal(cleanInstructions('E XER CISE 1\nSimplify:'), 'Simplify:');
});
test('two columns retain shared instructions and isolate numbered question regions', () => {
    const { regions } = questionRegions([item('EXERCISE 1', 50, 90), item('Prices decrease by 20%.', 50, 115), item('1.', 50, 150), item('2.', 50, 350), item('3.', 320, 150), item('4.', 320, 350)], 595, 840);
    assert.equal(regions.length, 4);
    assert.match(regions[0].contextText, /20%/);
    assert.ok(regions[0].rect.x + regions[0].rect.width < regions[2].rect.x);
    assert.ok(regions[0].rect.y + regions[0].rect.height < regions[1].rect.y);
});
test('worked solutions and pages without exercise headings are explicitly skipped', () => {
    assert.equal(questionRegions([item('EXERCISE 1', 50, 90), item('Solution:', 50, 120), item('1.', 50, 150)], 595, 840).reason, 'worked_examples_or_solutions');
    assert.equal(questionRegions([item('Agreement', 50, 90)], 595, 840).reason, 'no_exercise_heading');
    assert.equal(questionRegions([item('REVISION EXERCISE', 50, 90), item('1.', 50, 150)], 595, 840).reason, 'mixed_revision_exercise');
});
test('parent questions keep their subparts and fragmented letter markers are recognised', () => {
    const parent = questionRegions([item('EXERCISE 1', 50, 90), item('1.', 50, 140), item('(a)', 70, 170), item('(b)', 70, 300)], 595, 840);
    assert.equal(parent.regions.length, 1);
    const letters = questionRegions([item('EXERCISE 1', 50, 90), item('(', 50, 150), item('a', 56, 150), item(')', 62, 150), item('(b)', 50, 350)], 595, 840);
    assert.equal(letters.regions.length, 2);
    assert.equal(letters.regions[0].label, '(a)');
});
test('subquestions referring to one shared diagram remain one complete exercise', () => {
    const result = questionRegions([item('EXERCISE 3', 50, 90), item('Consider the following triangle.', 50, 120), item('(a)', 50, 155), item('(b)', 50, 185), item('(c)', 50, 215), item('x', 500, 270)], 595, 840);
    assert.equal(result.regions.length, 1);
    assert.ok(result.regions[0].rect.x + result.regions[0].rect.width > 500);
    assert.ok(result.regions[0].rect.y + result.regions[0].rect.height > 270);
});
test('root indices above a first-row question stay with the maths rather than shared instructions', () => {
    const index = { ...item('3', 352, 143), height: 7 };
    const result = questionRegions([item('EXERCISE 10', 54, 85), item('Find the value of each of the following', 54, 117), index, item('1.', 72, 150), item('√64', 90, 150), item('9.', 333, 150), item('√125', 352, 150)], 595, 840);
    assert.equal(result.regions.length, 2);
    for (const q of result.regions) assert.equal(cleanInstructions(q.contextText), 'Find the value of each of the following');
    const cubeRoot = result.regions.find(q => q.label === '9.');
    assert.ok(cubeRoot.rect.y < 143 - index.height);
    assert.ok(cubeRoot.rect.x < index.transform[4]);
});
const bank = ['A', 'B', 'C', 'D'].flatMap(topic => Array.from({ length: 10 }, (_, n) => ({ id: `${topic}${n}`, topic, grade_id: 2, subject_id: 1 })));
test('explicit Question headings override equation results and preserve full-width crops', () => {
    const result = questionRegions([item('Exercise 4', 57, 90), item('Question 1', 57, 115), item('.', 117, 115), item('Solve the equation', 57, 136), item('6.', 217, 136), item('Question', 58, 350), item('2.', 114, 350), item('Solve the equation', 58, 375), item('7.', 225, 375)], 595, 840);
    assert.deepEqual(result.regions.map(q => q.label), ['1.', '2.']);
    assert.ok(result.regions.every(q => q.rect.x <= 58 && q.rect.x + q.rect.width > 225));
    assert.ok(result.regions.every(q => q.rect.height < 100));
});
test('inline equation results are not interpreted as extra columns', () => {
    const result = questionRegions([item('Exercise 1', 50, 90), item('1.', 50, 150), item('x + 2 =', 75, 150), item('7.', 170, 150), item('2.', 50, 350), item('x + 3 =', 75, 350), item('9.', 170, 350)], 595, 840);
    assert.deepEqual(result.regions.map(q => q.label), ['1.', '2.']);
});
test('clean presentation hides source labels and exercise titles but preserves instructions safely', () => {
    assert.equal(cleanInstructions('EXERCISE 1 – Calculate sale price\nAll items decrease by 20%.'), 'Calculate sale price\nAll items decrease by 20%.');
    assert.equal(cleanInstructions('EXERCISE 4'), '');
    const html = renderTest({ count: 1, questions: [{ topic: 'Secret topic', lesson_id: 20, page: 2, context_text: 'EXERCISE 1\nSolve <x>.', image: 'q.png' }] });
    assert.ok(!html.includes('Secret topic') && !html.includes('Lesson 20') && !html.includes('Source page') && !html.includes('EXERCISE'));
    assert.ok(html.includes('Solve &lt;x&gt;.'));
    assert.ok(html.includes('Open question 1 at full size'));
});
test('plain word problems can reflow while equations retain their original maths image', () => {
    const result = questionRegions([item('Exercise 1', 50, 90), item('1.', 50, 150), item('If an item costs Rs 500, calculate its sale price after the discount.', 72, 150)], 595, 840);
    assert.match(result.regions[0].questionText, /^If an item/);
    const html = renderTest({ count: 1, questions: [{ question_text: result.regions[0].questionText, image: 'q.png' }] });
    assert.ok(html.includes('class="question-prose"'));
    assert.ok(!html.includes('<img'));
});
test('tests include only the selected three topics, balanced without repeated questions', () => {
    for (const count of [10, 11, 15]) {
        const selected = selectTest(bank, ['C', 'A', 'B'], count);
        assert.equal(selected.length, count);
        assert.equal(new Set(selected.map(q => q.id)).size, count);
        assert.ok(selected.every(q => ['C', 'A', 'B'].includes(q.topic)));
        const sizes = ['C', 'A', 'B'].map(topic => selected.filter(q => q.topic === topic).length);
        assert.ok(Math.max(...sizes) - Math.min(...sizes) <= 1);
    }
    assert.notDeepEqual(selectTest(bank, ['A', 'B', 'C'], 15, () => 0), selectTest(bank, ['A', 'B', 'C'], 15, n => n - 1));
});
test('insufficient pools and mixed courses fail instead of including unrelated questions', () => {
    assert.throws(() => selectTest(bank.filter(q => q.topic !== 'C'), ['A', 'B', 'C']), /Insufficient/);
    assert.throws(() => selectTest(bank, ['A', 'A', 'B']), /three different/);
    assert.throws(() => selectTest(bank.map(q => ({ ...q, grade_id: q.topic === 'C' ? 3 : 2 })), ['A', 'B', 'C']), /one grade/);
    assert.throws(() => selectTest(bank, ['A', 'B', 'C'], 9), /between 10 and 15/);
    const repeated = [...bank, ...bank];
    assert.equal(new Set(selectTest(repeated, ['A', 'B', 'C']).map(q => q.id)).size, 15);
});
