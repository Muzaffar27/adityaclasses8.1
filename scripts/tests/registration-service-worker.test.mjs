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
