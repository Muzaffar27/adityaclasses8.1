import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import path from 'node:path';

const root = path.resolve(process.argv[2] || 'storage/app/ocr-samples/grade-8-mathematics');
const bank = JSON.parse(await readFile(path.join(root, 'structured/questions.json'), 'utf8'));
const question = (lesson, page, label) => {
    const q = bank.questions.find(q => (q.lesson_id === lesson && q.page === page && q.label === label)
        || q.origins?.some(origin => origin.lesson_id === lesson && origin.page === page && origin.label === label));
    assert.ok(q, `Missing source question: ${lesson}/${page}/${label}`);
    return q;
};
const mixed = question(267, 4, '(c)').math.children;
assert.deepEqual(mixed.map(n => n.type), ['number', 'fraction', 'operator', 'number', 'fraction', 'operator']);
assert.deepEqual([mixed[0].value, mixed[1].numerator.value, mixed[1].denominator.value, mixed[2].value, mixed[3].value, mixed[4].numerator.value, mixed[4].denominator.value], ['6', '1', '3', '-', '5', '5', '6']);
const cube = question(27, 5, '9.').math.children[0];
assert.deepEqual(cube, { type: 'root', radicand: { type: 'number', value: '125' }, index: { type: 'number', value: '3' } });
const powers = question(25, 2, '1.').math.children;
assert.deepEqual([powers[0].base.value, powers[0].exponent.value, powers[1].value, powers[2].base.value, powers[2].exponent.value], ['7', '3', '×', '7', '4']);
assert.equal(question(270, 3, '(n)').math.children.map(n => n.value).join(''), '5×22÷(-2)');
assert.equal(new Set(bank.questions.map(q => `${q.id}:${q.topic}`)).size, bank.questions.length);
assert.ok(bank.questions.every(q => !q.image && !q.context_image && Boolean(q.math) !== Boolean(q.text)));
assert.ok(!bank.questions.some(q => q.topic.includes('Pythagoras')));
console.log('Real-PDF structured checks passed: mixed fractions, cube-root index, powers, arithmetic label removal, unique IDs, no image questions and diagram exclusion.');
