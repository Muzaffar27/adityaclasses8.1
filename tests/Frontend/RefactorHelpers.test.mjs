import assert from 'node:assert/strict';
import test from 'node:test';
import { compareTopics, uniqueSorted } from '../../resources/js/utils/lessonTopics.js';
import { useLessonCollection } from '../../resources/js/composables/lessonCollection.js';

test('topic ordering keeps unnumbered topics first and numbered topics in numeric order', () => {
    const topics = ['10 Geometry', '2 Algebra', 'Revision', 'Basics', '1 Numbers'];
    assert.deepEqual(topics.sort(compareTopics), ['Basics', 'Revision', '1 Numbers', '2 Algebra', '10 Geometry']);
    assert.equal(compareTopics('2 Algebra', '2 Geometry'), 0);
});

test('topic suggestions trim, deduplicate and naturally sort without changing case handling', () => {
    assert.deepEqual(uniqueSorted([' Topic 10 ', '', null, 'Topic 2', 'Topic 2', 'topic 2']), ['Topic 2', 'topic 2', 'Topic 10']);
});

test('lesson loading stays active until post-load pagination work completes', async () => {
    let finish;
    const collection = useLessonCollection(async () => ({ data: [2, 1] }), {
        transform: lessons => lessons.toSorted(),
        onLoaded: () => new Promise(resolve => { finish = resolve; }),
    });
    const pending = collection.loadLessons();
    assert.equal(collection.loading.value, true);
    await Promise.resolve();
    assert.deepEqual(collection.lessons.value, [1, 2]);
    assert.equal(collection.loading.value, true);
    finish();
    assert.equal(await pending, true);
    assert.equal(collection.loading.value, false);
});

test('course-list errors retain loaded lessons and support retry', async () => {
    let fail = false;
    const errors = [];
    const collection = useLessonCollection(async () => {
        if (fail) throw new Error('Offline');
        return { data: [{ id: 1 }] };
    }, { onError: error => errors.push(error.message) });
    await collection.loadLessons();
    fail = true;
    assert.equal(await collection.loadLessons(), false);
    assert.deepEqual(collection.lessons.value, [{ id: 1 }]);
    assert.equal(collection.loading.value, false);
    assert.deepEqual(errors, ['Offline']);
    fail = false;
    assert.equal(await collection.loadLessons(), true);
});

test('the all-lessons view clears stale lessons on failure and accepts empty responses', async () => {
    let response = [{ id: 1 }];
    const collection = useLessonCollection(async () => {
        if (response instanceof Error) throw response;
        return { data: response };
    }, { clearOnError: true, transform: lessons => lessons || [], onError: () => {} });
    await collection.loadLessons();
    response = new Error('Offline');
    assert.equal(await collection.loadLessons(), false);
    assert.deepEqual(collection.lessons.value, []);
    response = null;
    assert.equal(await collection.loadLessons(), true);
    assert.deepEqual(collection.lessons.value, []);
});
