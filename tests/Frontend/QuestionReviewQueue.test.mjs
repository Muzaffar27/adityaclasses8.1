import test from 'node:test';
import assert from 'node:assert/strict';
import { setImmediate } from 'node:timers/promises';
import { createQuestionReviewQueue } from '../../resources/js/composables/questionReviewQueue.mjs';

const id = value => value.repeat(64);
const flag = value => ({ kind: 'flag', sourceId: 1, questionId: id(value), revision: 1, reason: null });
const approval = { kind: 'approve', sourceId: 1, snapshot: id('a'), questionIds: [id('c')] };
function storage(initial = []) {
    let saved = JSON.stringify(initial);
    return { getItem: () => saved, setItem: (_, value) => { saved = value; } };
}
async function until(check) {
    for (let i = 0; i < 100; i++) { if (check()) return; await setImmediate(); }
    assert.fail('Queue did not settle');
}

test('rapid flags are serialized and approval uses their acknowledged snapshot and explicit IDs', async () => {
    const calls = [], releases = [];
    const queue = createQuestionReviewQueue({ key: 'test', storage: storage(), api: {
        post: (url, body) => { calls.push({ url, body }); return new Promise(resolve => releases.push(resolve)); },
    } });
    queue.enqueue(flag('a')); queue.enqueue(flag('b')); queue.enqueue(approval);
    assert.equal(calls.length, 1);
    assert.deepEqual([...queue.hiddenIds.value].sort(), [id('a'), id('b'), id('c')]);
    releases[0]({ data: { pdf: { snapshot: id('d') } } });
    await until(() => calls.length === 2);
    releases[1]({ data: { pdf: { snapshot: id('e') } } });
    await until(() => calls.length === 3);
    assert.deepEqual(calls[2].body, { snapshot: id('e'), flagged: [], question_ids: [id('c')] });
    releases[2]({ data: { approved_count: 1 } });
    await until(() => queue.jobs.value.length === 0);
});

test('failed flags remain recoverable without hiding the card; explicit retry clears the journal', async () => {
    let fail = true;
    const journal = storage();
    const queue = createQuestionReviewQueue({ key: 'test', storage: journal, api: {
        post: async () => { if (fail) throw Error('offline'); return { data: { pdf: { snapshot: id('d') } } }; },
    } });
    queue.enqueue(flag('a'));
    await until(() => queue.failures.value.length === 1);
    assert.equal(queue.hiddenIds.value.has(id('a')), false);
    assert.equal(queue.flaggedIds.value.has(id('a')), true);
    assert.equal(JSON.parse(journal.getItem())[0].status, 'failed');
    fail = false; queue.retry(queue.failures.value[0].id);
    await until(() => queue.jobs.value.length === 0);
    assert.deepEqual(JSON.parse(journal.getItem()), []);
});

test('refresh recovery confirms an already committed approval without adopting a new snapshot', async () => {
    let posts = 0;
    const queue = createQuestionReviewQueue({ key: 'test', storage: storage([{ ...approval, id: 'restored', status: 'saving' }]), api: {
        post: async (_, body) => { posts++; assert.equal(body.snapshot, id('a')); throw Error('acknowledgement lost'); },
        get: async () => ({ data: { snapshot: id('f'), questions: [{ id: id('c'), status: 'approved' }] } }),
    } });
    await until(() => queue.result.value !== null);
    assert.equal(posts, 1);
    assert.equal(queue.jobs.value.length, 0);
    assert.equal(queue.result.value.data.approved_count, 1);
});

test('a conflicting approval stays failed; retry never silently replaces its snapshot', async () => {
    const snapshots = [];
    const queue = createQuestionReviewQueue({ key: 'test', storage: storage(), api: {
        post: async (_, body) => { snapshots.push(body.snapshot); throw Error('conflict'); },
        get: async () => ({ data: { snapshot: id('f'), questions: [{ id: id('c'), status: 'pending' }] } }),
    } });
    queue.enqueue(approval);
    await until(() => queue.failures.value.length === 1);
    queue.retry(queue.failures.value[0].id);
    await until(() => snapshots.length === 2 && queue.failures.value.length === 1);
    assert.deepEqual(snapshots, [id('a'), id('a')]);
    queue.reviewAgain(queue.failures.value[0].id);
    assert.equal(queue.jobs.value.length, 0);
});

test('a malformed journal cannot fall back to approving a whole PDF', async () => {
    let calls = 0;
    const queue = createQuestionReviewQueue({ key: 'test', storage: storage([{ ...approval, questionIds: undefined, id: 'broken', status: 'queued' }]), api: { post: async () => { calls++; } } });
    await setImmediate();
    assert.equal(calls, 0);
    assert.equal(queue.jobs.value.length, 0);
    assert.throws(() => queue.enqueue({ ...approval, questionIds: [] }), /incomplete/);
});
