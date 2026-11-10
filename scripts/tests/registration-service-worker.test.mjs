import { readFileSync } from 'node:fs';
import vm from 'node:vm';
import test from 'node:test';
import assert from 'node:assert/strict';

const source = readFileSync(new URL('../../public/sw.js', import.meta.url), 'utf8');
const version = source.match(/const CACHE_VERSION = "([^"]+)"/)[1];
function worker(overrides = {}) {
    const listeners = new Map();
    const context = {
        URL, console,
        self: {location: {origin: 'https://example.test'}, addEventListener: (name, callback) => listeners.set(name, callback),
            clients: {claim: async () => {}}, skipWaiting: async () => {}},
        fetch: async () => {throw new Error('offline');},
        caches: {open: async () => {throw new Error('Private endpoint touched cache');}, match: async () => ({privateCachedRecord: true})},
        ...overrides,
    };
    vm.runInNewContext(source, context);
    return listeners;
}

test('private registration list, details and CSV never use offline cache', async () => {
    const listeners = worker();
    for (const path of ['/api/admin/registrations?status=pending', '/api/admin/registrations/12', '/api/admin/registrations/export']) {
        let response;
        listeners.get('fetch')({request: {method: 'GET', mode: 'cors', url: 'https://example.test'+path}, respondWith: promise => {response = promise;}});
        await assert.rejects(response, /offline/);
    }
});

test('online private requests preserve authentication and explicitly bypass browser cache', async () => {
    const expected = {ok: true};
    const request = {method: 'GET', mode: 'cors', url: 'https://example.test/api/admin/registrations/export', headers: {'X-Auth-Token': 'test-fixture'}};
    const listeners = worker({fetch: async (actual, options) => {
        assert.equal(actual, request);
        assert.equal(options.cache, 'no-store');
        return expected;
    }});
    let response;
    listeners.get('fetch')({request, respondWith: promise => {response = promise;}});
    assert.equal(await response, expected);
});

test('activation removes previously cached registration responses and preserves other current data', async () => {
    const removed = [];
    const paths = ['/api/admin/registrations', '/api/admin/registrations/12', '/api/admin/registrations/export', '/api/student/dashboard', '/logo.png'];
    const listeners = worker({caches: {
        keys: async () => [version+'-runtime', version+'-shell'],
        delete: async () => {throw new Error('Unrelated current cache removed');},
        open: async () => ({keys: async () => paths.map(path => ({url: 'https://example.test'+path})),
            delete: async request => {removed.push(new URL(request.url).pathname);}}),
    }});
    let activated;
    listeners.get('activate')({waitUntil: promise => {activated = promise;}});
    await activated;
    assert.deepEqual(removed.sort(), paths.slice(0, 3).sort());
});

test('other API offline behavior remains unchanged', async () => {
    const listeners = worker();
    let response;
    listeners.get('fetch')({request: {method: 'GET', mode: 'cors', url: 'https://example.test/api/student/dashboard'}, respondWith: promise => {response = promise;}});
    assert.deepEqual(await response, {privateCachedRecord: true});
});

test('cold cache-only resume requests can reach the network instead of ERR_CACHE_MISS', async () => {
    const cached = [];
    const expected = {ok: true, clone() {return this;}};
    const request = {method: 'GET', mode: 'same-origin', cache: 'only-if-cached',
        url: 'https://example.test/lessons/1/1?resume=387&video=lesson'};
    const listeners = worker({
        caches: {open: async () => ({match: async () => undefined, put: (key, value) => {cached.push([key, value]);}})},
        fetch: async (actual, options) => {
            assert.equal(actual, request);
            if (options?.cache !== 'no-store') throw new Error('net::ERR_CACHE_MISS');
            return expected;
        },
    });
    let response;
    listeners.get('fetch')({request, respondWith: promise => {response = promise;}});
    assert.equal(await response, expected);
    assert.deepEqual(cached, [[request, expected]]);
});

test('ordinary asset requests preserve their browser cache policy and offline fallback', async () => {
    const expected = {cachedAsset: true};
    const request = {method: 'GET', mode: 'cors', cache: 'reload', url: 'https://example.test/build/assets/app.js'};
    let fetched;
    const listeners = worker({
        caches: {open: async () => ({match: async () => expected})},
        fetch: async (actual, options) => {assert.equal(actual, request); assert.equal(options, undefined); fetched = true; throw new Error('offline');},
    });
    let response;
    listeners.get('fetch')({request, respondWith: promise => {response = promise;}});
    assert.equal(await response, expected);
    assert.equal(fetched, true);
});
