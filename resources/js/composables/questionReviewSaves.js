import api from '../api';
import { createQuestionReviewQueue } from './questionReviewQueue.mjs';

const queues = new Map();
export function useQuestionReviewSaves() {
    const tokenId = (localStorage.getItem('auth_token') || '').split('|')[0];
    const key = `question-review-saves:${tokenId}`;
    if (!queues.has(key)) {
        const guardedApi = Object.fromEntries(['get', 'post'].map(method => [method, (...args) => {
            if ((localStorage.getItem('auth_token') || '').split('|')[0] !== tokenId) throw new Error('The signed-in session changed. Review these changes again.');
            return api[method](...args);
        }]));
        const queue = createQuestionReviewQueue({ api: guardedApi, storage: sessionStorage, key });
        queues.set(key, queue);
        window.addEventListener('beforeunload', event => {
            if ((localStorage.getItem('auth_token') || '').split('|')[0] !== tokenId || !queue.jobs.value.length) return;
            event.preventDefault(); event.returnValue = '';
        });
    }
    return queues.get(key);
}
