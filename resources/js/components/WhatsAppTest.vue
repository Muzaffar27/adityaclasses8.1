<template>
    <section class="report-card">
        <div class="cost-banner">
            <div>
                <span>Cost per delivered utility message</span>
                <small>Meta Cloud API · Mauritius utility message</small>
            </div>
            <strong>US$0.0077</strong>
        </div>

        <div class="card-heading">
            <div class="whatsapp-mark">
                <WhatsAppIcon />
            </div>
            <div>
                <p class="eyebrow">WhatsApp report sample</p>
                <h2>Sample testing</h2>
                <p class="subtitle">Keep the message brief while giving parents the results and next step.</p>
            </div>
            <span class="draft-badge">Draft preview</span>
        </div>

        <div class="preview-notice">
            This is a visual template only. Sending will be connected after the Meta template and secure parent report page are ready.
        </div>

        <div class="report-workspace">
            <form class="report-builder" @submit.prevent>
                <div class="section-heading">
                    <div>
                        <p class="section-kicker">Report details</p>
                        <h3>Weekly summary</h3>
                    </div>
                </div>

                <div class="field-grid">
                    <label>
                        <span>Student name</span>
                        <input v-model.trim="report.student" maxlength="80" type="text" placeholder="Student name">
                    </label>
                    <label>
                        <span>Week</span>
                        <input v-model.trim="report.week" maxlength="50" type="text" placeholder="21–27 Sep 2026">
                    </label>
                </div>

                <div class="tests-heading">
                    <div>
                        <p class="section-kicker">Results</p>
                        <h3>Tests completed</h3>
                    </div>
                    <button class="add-test" type="button" :disabled="report.tests.length >= 6" @click="addTest">
                        + Add test
                    </button>
                </div>

                <div v-if="report.tests.length" class="test-list">
                    <div v-for="(test, index) in report.tests" :key="test.id" class="test-row">
                        <label>
                            <span>Subject</span>
                            <input v-model.trim="test.subject" maxlength="50" type="text" placeholder="Mathematics">
                        </label>
                        <label>
                            <span>Topic</span>
                            <input v-model.trim="test.topic" maxlength="80" type="text" placeholder="Linear Equations">
                        </label>
                        <label class="score-field">
                            <span>Score</span>
                            <input v-model.number="test.score" min="0" max="100" type="number" inputmode="numeric">
                        </label>
                        <button class="remove-test" type="button" :aria-label="`Remove test ${index + 1}`"
                            @click="removeTest(index)">×</button>
                    </div>
                </div>
                <p v-else class="empty-tests">Add at least one test to preview the student's results.</p>

                <label class="wide-field">
                    <span>Focus area</span>
                    <textarea v-model.trim="report.focus" maxlength="180" rows="2"
                        placeholder="What needs more attention?"></textarea>
                </label>

                <label class="wide-field">
                    <span>Recommended next step</span>
                    <textarea v-model.trim="report.nextStep" maxlength="220" rows="2"
                        placeholder="What should the student do next?"></textarea>
                </label>
            </form>

            <aside class="preview-panel">
                <div class="preview-heading">
                    <div>
                        <p class="section-kicker">Parent view</p>
                        <h3>WhatsApp preview</h3>
                    </div>
                    <span>Live</span>
                </div>

                <div class="chat-window">
                    <div class="chat-header">
                        <div class="chat-avatar">
                            <img :src="logoUrl" alt="Aditya Classes">
                        </div>
                        <div>
                            <strong>ADITYACLASSES</strong>
                            <span>Student progress update</span>
                        </div>
                    </div>

                    <div class="chat-body">
                        <article class="message-bubble">
                            <p class="message-cost">
                                <span>Message cost</span>
                                <strong>US$0.0077</strong>
                            </p>
                            <div class="message-intro">
                                <div>
                                    <p class="message-brand">ADITYACLASSES</p>
                                    <h4>Sample testing</h4>
                                </div>
                            </div>
                            <p class="student-line">
                                <strong>{{ report.student || 'Student name' }}</strong>
                                <span>{{ report.week || 'Week' }}</span>
                            </p>

                            <div class="message-section">
                                <strong><span aria-hidden="true">📊</span> Results</strong>
                                <ul v-if="report.tests.length">
                                    <li v-for="test in report.tests" :key="`preview-${test.id}`">
                                        <span>{{ test.subject || 'Subject' }} · {{ test.topic || 'Topic' }}</span>
                                        <b>{{ normaliseScore(test.score) }}%</b>
                                    </li>
                                </ul>
                                <p v-else class="preview-empty">No tests recorded this week.</p>
                            </div>

                            <div v-if="report.focus || report.nextStep" class="message-section compact guidance-section">
                                <strong><span aria-hidden="true">🎯</span> What to work on</strong>
                                <p v-if="report.focus">{{ report.focus }}</p>
                                <p v-if="report.nextStep" class="next-step"><b>Next:</b> {{ report.nextStep }}</p>
                            </div>

                            <p class="disclaimer">
                                Automated weekly update. Replies aren't monitored; contact the tutor directly for questions.
                            </p>

                            <div class="report-link">
                                <span aria-hidden="true">↗</span>
                                View detailed report
                            </div>

                            <time>09:00 <span aria-hidden="true">✓✓</span></time>
                        </article>
                    </div>
                </div>

                <p class="link-note">The detailed-report button will use a secure, parent-specific link when that page is built.</p>
            </aside>
        </div>
    </section>
</template>

<script setup>
import { reactive } from 'vue'
import WhatsAppIcon from './common/WhatsAppIcon.vue'

let nextTestId = 3
const logoUrl = '/logo.png'

const report = reactive({
    student: 'Maya Sharma',
    week: '21–27 Sep 2026',
    tests: [
        { id: 1, subject: 'Mathematics', topic: 'Linear Equations', score: 80 },
        { id: 2, subject: 'Mathematics', topic: 'HCM', score: 50 }
    ],
    focus: 'HCM needs more practice.',
    nextStep: 'Review the HCM notes, then retake the test.'
})

function normaliseScore(score) {
    const value = Number(score)
    if (!Number.isFinite(value)) return 0
    return Math.min(100, Math.max(0, Math.round(value)))
}

function addTest() {
    if (report.tests.length >= 6) return
    report.tests.push({ id: nextTestId++, subject: '', topic: '', score: 0 })
}

function removeTest(index) {
    report.tests.splice(index, 1)
}
</script>

<style scoped>
.report-card {
    background: linear-gradient(145deg, rgba(22, 101, 52, 0.15), rgba(15, 23, 42, 0.88));
    border: 1px solid rgba(74, 222, 128, 0.2);
    border-radius: 18px;
    box-shadow: 0 18px 42px rgba(2, 6, 23, 0.22);
    padding: 1.4rem;
}

.cost-banner {
    align-items: center;
    background: linear-gradient(90deg, rgba(37, 211, 102, 0.14), rgba(20, 184, 166, 0.06));
    border: 1px solid rgba(74, 222, 128, 0.24);
    border-radius: 12px;
    display: flex;
    justify-content: space-between;
    margin-bottom: 1rem;
    padding: 0.7rem 0.8rem;
}

.cost-banner span,
.cost-banner small {
    display: block;
}

.cost-banner span {
    color: #dcfce7;
    font-size: 0.7rem;
    font-weight: 800;
}

.cost-banner small {
    color: rgba(203, 213, 225, 0.58);
    font-size: 0.58rem;
    margin-top: 0.12rem;
}

.cost-banner strong {
    background: rgba(37, 211, 102, 0.13);
    border: 1px solid rgba(74, 222, 128, 0.22);
    border-radius: 999px;
    color: #86efac;
    flex: 0 0 auto;
    font-size: 0.78rem;
    margin-left: 0.8rem;
    padding: 0.38rem 0.62rem;
}

.card-heading {
    align-items: center;
    display: grid;
    gap: 0.9rem;
    grid-template-columns: auto minmax(0, 1fr) auto;
}

.whatsapp-mark {
    align-items: center;
    background: #25d366;
    border: 1px solid rgba(255, 255, 255, 0.18);
    border-radius: 50%;
    color: #fff;
    display: flex;
    height: 46px;
    justify-content: center;
    width: 46px;
}

.whatsapp-mark svg {
    height: 25px;
    width: 25px;
}

.eyebrow,
.section-kicker {
    color: #86efac;
    font-size: 0.62rem;
    font-weight: 850;
    letter-spacing: 0.1em;
    margin: 0;
    text-transform: uppercase;
}

h2,
h3,
h4,
p {
    margin-top: 0;
}

h2 {
    color: #f8fafc;
    font-size: 1.08rem;
    font-weight: 800;
    margin-bottom: 0;
}

.subtitle {
    color: rgba(203, 213, 225, 0.62);
    font-size: 0.72rem;
    margin: 0.18rem 0 0;
}

.draft-badge {
    background: rgba(250, 204, 21, 0.1);
    border: 1px solid rgba(250, 204, 21, 0.23);
    border-radius: 999px;
    color: #fde68a;
    font-size: 0.64rem;
    font-weight: 800;
    padding: 0.36rem 0.58rem;
}

.preview-notice {
    background: rgba(59, 130, 246, 0.07);
    border: 1px solid rgba(96, 165, 250, 0.15);
    border-radius: 10px;
    color: rgba(191, 219, 254, 0.72);
    font-size: 0.68rem;
    margin-top: 1rem;
    padding: 0.65rem 0.75rem;
}

.report-workspace {
    display: grid;
    gap: 1rem;
    grid-template-columns: minmax(0, 1.12fr) minmax(320px, 0.88fr);
    margin-top: 1rem;
}

.report-builder,
.preview-panel {
    background: rgba(2, 6, 23, 0.23);
    border: 1px solid rgba(148, 163, 184, 0.1);
    border-radius: 14px;
    min-width: 0;
    padding: 1rem;
}

.section-heading,
.tests-heading,
.preview-heading {
    align-items: center;
    display: flex;
    justify-content: space-between;
}

.section-heading h3,
.tests-heading h3,
.preview-heading h3 {
    color: #f8fafc;
    font-size: 0.9rem;
    margin: 0.15rem 0 0;
}

.field-grid {
    display: grid;
    gap: 0.7rem;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    margin-top: 0.9rem;
}

label span,
.test-row label span {
    color: rgba(203, 213, 225, 0.58);
    display: block;
    font-size: 0.64rem;
    margin-bottom: 0.3rem;
}

input,
textarea {
    background: rgba(15, 23, 42, 0.76);
    border: 1px solid rgba(148, 163, 184, 0.14);
    border-radius: 9px;
    box-sizing: border-box;
    color: #f8fafc;
    font: inherit;
    font-size: 0.72rem;
    outline: none;
    padding: 0.6rem 0.65rem;
    transition: border-color 0.2s ease, box-shadow 0.2s ease;
    width: 100%;
}

textarea {
    line-height: 1.45;
    resize: vertical;
}

input:focus,
textarea:focus {
    border-color: rgba(74, 222, 128, 0.55);
    box-shadow: 0 0 0 3px rgba(34, 197, 94, 0.09);
}

.tests-heading {
    border-top: 1px solid rgba(148, 163, 184, 0.1);
    margin-top: 1rem;
    padding-top: 1rem;
}

.add-test {
    background: rgba(34, 197, 94, 0.1);
    border: 1px solid rgba(74, 222, 128, 0.22);
    border-radius: 8px;
    color: #bbf7d0;
    cursor: pointer;
    font-size: 0.68rem;
    font-weight: 750;
    min-height: 34px;
    padding: 0.42rem 0.65rem;
}

.add-test:disabled {
    cursor: not-allowed;
    opacity: 0.45;
}

.test-list {
    display: grid;
    gap: 0.55rem;
    margin-top: 0.7rem;
}

.test-row {
    align-items: end;
    background: rgba(15, 23, 42, 0.42);
    border: 1px solid rgba(148, 163, 184, 0.08);
    border-radius: 10px;
    display: grid;
    gap: 0.55rem;
    grid-template-columns: minmax(0, 0.8fr) minmax(0, 1.2fr) 72px 34px;
    padding: 0.65rem;
}

.score-field input {
    text-align: center;
}

.remove-test {
    align-items: center;
    background: rgba(239, 68, 68, 0.08);
    border: 1px solid rgba(248, 113, 113, 0.16);
    border-radius: 8px;
    color: #fecaca;
    cursor: pointer;
    display: flex;
    font-size: 1rem;
    height: 34px;
    justify-content: center;
    width: 34px;
}

.empty-tests {
    color: rgba(203, 213, 225, 0.5);
    font-size: 0.68rem;
    margin: 0.75rem 0 0;
}

.wide-field {
    display: block;
    margin-top: 0.8rem;
}

.preview-heading > span {
    background: rgba(34, 197, 94, 0.1);
    border-radius: 999px;
    color: #86efac;
    font-size: 0.61rem;
    font-weight: 800;
    padding: 0.3rem 0.5rem;
}

.chat-window {
    background: #0b141a;
    border: 1px solid rgba(148, 163, 184, 0.12);
    border-radius: 13px;
    margin-top: 0.85rem;
    overflow: hidden;
}

.chat-header {
    align-items: center;
    background: #202c33;
    display: flex;
    gap: 0.55rem;
    padding: 0.65rem 0.75rem;
}

.chat-avatar {
    align-items: center;
    background: #25d366;
    border-radius: 50%;
    color: #062e1b;
    display: flex;
    font-size: 0.63rem;
    font-weight: 900;
    height: 32px;
    justify-content: center;
    width: 32px;
}

.chat-avatar img {
    height: 72%;
    object-fit: contain;
    width: 72%;
}

.chat-header strong,
.chat-header span {
    display: block;
}

.chat-header strong {
    color: #f8fafc;
    font-size: 0.72rem;
}

.chat-header span {
    color: rgba(203, 213, 225, 0.55);
    font-size: 0.58rem;
}

.chat-body {
    background-color: #0b141a;
    background-image: radial-gradient(rgba(134, 239, 172, 0.035) 1px, transparent 1px);
    background-size: 14px 14px;
    padding: 0.85rem;
}

.message-bubble {
    background: #005c4b;
    border-radius: 10px 10px 2px 10px;
    box-sizing: border-box;
    box-shadow: 0 3px 10px rgba(0, 0, 0, 0.18);
    color: #edf7f5;
    margin-left: auto;
    max-width: 94%;
    padding: 0.75rem 0.75rem 0.45rem;
    position: relative;
    width: 100%;
}

.message-cost {
    align-items: center;
    background: rgba(0, 0, 0, 0.14);
    border-radius: 7px;
    display: flex;
    font-size: 0.6rem;
    justify-content: space-between;
    margin: 0 0 0.55rem;
    padding: 0.38rem 0.45rem;
}

.message-cost span {
    color: rgba(237, 247, 245, 0.72);
}

.message-cost strong {
    color: #a7f3d0;
}

.message-intro {
    border-bottom: 1px solid rgba(255, 255, 255, 0.11);
    margin-bottom: 0.55rem;
    padding-bottom: 0.55rem;
}

.message-brand {
    color: #9ff2c4;
    font-size: 0.56rem;
    font-weight: 900;
    letter-spacing: 0.09em;
    margin-bottom: 0.1rem;
}

.message-bubble h4 {
    color: #fff;
    font-size: 0.84rem;
    margin: 0;
}

.student-line {
    align-items: flex-start;
    border-bottom: 1px solid rgba(255, 255, 255, 0.11);
    display: flex;
    justify-content: space-between;
    margin-bottom: 0.55rem;
    padding-bottom: 0.55rem;
}

.student-line strong,
.student-line span {
    font-size: 0.67rem;
}

.student-line span {
    color: rgba(237, 247, 245, 0.66);
    margin-left: 0.6rem;
    text-align: right;
}

.message-section {
    margin-top: 0.55rem;
}

.message-section > strong {
    display: block;
    font-size: 0.65rem;
    margin-bottom: 0.3rem;
}

.message-section ul {
    display: grid;
    gap: 0.28rem;
    list-style: none;
    margin: 0;
    padding: 0;
}

.message-section li {
    align-items: flex-start;
    display: flex;
    font-size: 0.65rem;
    gap: 0.5rem;
    justify-content: space-between;
    line-height: 1.35;
}

.message-section li b {
    color: #fff;
    flex: 0 0 auto;
}

.compact p,
.preview-empty {
    color: rgba(237, 247, 245, 0.82);
    font-size: 0.64rem;
    line-height: 1.4;
    margin: 0;
}

.guidance-section {
    background: rgba(0, 0, 0, 0.1);
    border-radius: 8px;
    padding: 0.5rem;
}

.guidance-section .next-step {
    margin-top: 0.25rem;
}

.guidance-section .next-step b {
    color: #fff;
}

.disclaimer {
    border-top: 1px solid rgba(255, 255, 255, 0.11);
    color: rgba(237, 247, 245, 0.58);
    font-size: 0.56rem;
    line-height: 1.4;
    margin: 0.65rem 0 0;
    padding-top: 0.55rem;
}

.report-link {
    align-items: center;
    background: rgba(255, 255, 255, 0.96);
    border-radius: 7px;
    color: #087a5a;
    display: flex;
    font-size: 0.65rem;
    font-weight: 850;
    gap: 0.3rem;
    justify-content: center;
    margin-top: 0.55rem;
    min-height: 34px;
}

.message-bubble time {
    color: rgba(237, 247, 245, 0.48);
    display: block;
    font-size: 0.5rem;
    margin-top: 0.35rem;
    text-align: right;
}

.message-bubble time span {
    color: #53bdeb;
}

.link-note {
    color: rgba(203, 213, 225, 0.48);
    font-size: 0.62rem;
    line-height: 1.4;
    margin: 0.65rem 0 0;
}

@media (max-width: 1050px) {
    .report-workspace {
        grid-template-columns: 1fr;
    }

    .message-bubble {
        max-width: min(420px, 94%);
    }
}

@media (max-width: 700px) {
    .report-card {
        padding: 1rem;
    }

    .card-heading {
        grid-template-columns: auto minmax(0, 1fr);
    }

    .cost-banner {
        align-items: flex-start;
    }

    .draft-badge {
        grid-column: 1 / -1;
        justify-self: start;
    }

    .field-grid {
        grid-template-columns: 1fr;
    }

    .test-row {
        grid-template-columns: minmax(0, 1fr) 72px 34px;
    }

    .test-row label:first-child {
        grid-column: 1 / -1;
    }

    .chat-body {
        padding: 0.65rem;
    }

    .message-bubble {
        max-width: 100%;
    }
}
</style>
