<template>
    <div class="question-studio">
        <header class="studio-header">
            <div>
                <p class="eyebrow">Question bank / Question {{ confirmedCount + 1 }} <span v-if="confirmedCount" class="session-count">· {{ confirmedCount }} confirmed in preview</span></p>
                <h2>Create a question</h2>
                <p>Write once. Link it to all the topics where it belongs.</p>
            </div>
            <span class="preview-badge"><BeakerIcon /> Prototype · nothing saved</span>
        </header>

        <div v-if="confirmation" class="confirmation" role="status">
            <CheckCircleIcon /><span>{{ confirmation }}</span>
            <button type="button" aria-label="Dismiss message" @click="confirmation = ''"><XMarkIcon /></button>
        </div>

        <form class="studio-grid" @submit.prevent="reviewQuestion">
            <div class="editor">
                <div class="editor-content" :inert="previewOpen">
                <section class="studio-card" aria-labelledby="content-title">
                    <div class="section-title"><span class="step">{{ String(confirmedCount + 1).padStart(2, '0') }}</span><div><h3 id="content-title">Build your question</h3><p>Write it, review it, then move to the next one.</p></div></div>
                    <div class="field-grid question-settings">
                        <div class="field-block"><label for="question-type">Question type</label><select id="question-type" v-model="questionType"><option v-for="type in types" :key="type.value" :value="type.value">{{ type.label }}</option></select></div>
                        <div class="field-block"><label for="question-difficulty">Difficulty</label><select id="question-difficulty" v-model="difficulty"><option>Easy</option><option>Medium</option><option>Hard</option></select></div>
                    </div>
                    <div class="field-block question-body"><label for="question-content">Question</label><textarea id="question-content" ref="questionInput" v-model="prompt" maxlength="5000" rows="3" placeholder="Write your question here…"></textarea><div class="editor-footer"><span>Plain text &amp; mathematical symbols</span><span>{{ prompt.length }}/5,000</span></div></div>
                    <p v-if="attempted && !prompt.trim()" class="field-error">Write a question to preview.</p>
                    <div class="symbol-bar" aria-label="Insert a mathematical symbol"><span>Quick insert</span><button v-for="symbol in symbols" :key="symbol" type="button" :aria-label="`Insert ${symbol}`" @click="insertSymbol(symbol)">{{ symbol }}</button></div>

                    <div v-if="questionType === 'multiple_choice'" class="answer-section">
                        <div class="answer-heading"><h4>Answer options</h4><span>Select the correct answer</span></div>
                        <div class="answer-options-grid"><div v-for="(option, index) in options" :key="index" class="answer-option" :class="{ correct: correctOption === index }">
                            <input v-model="correctOption" type="radio" name="correct-option" :value="index" :aria-label="`Option ${optionLetter(index)} is correct`" />
                            <span class="option-letter">{{ optionLetter(index) }}</span><input v-model="options[index]" type="text" :aria-label="`Answer option ${optionLetter(index)}`" :placeholder="`Answer ${optionLetter(index)}`" maxlength="500" />
                            <CheckCircleIcon v-if="correctOption === index" class="correct-icon" /><button v-if="options.length > 2" type="button" :aria-label="`Remove answer ${optionLetter(index)}`" @click="removeOption(index)"><XMarkIcon /></button>
                        </div>
                        </div><button v-if="options.length < 6" type="button" class="text-button add-option" @click="options.push('')"><PlusIcon /> Add an option</button>
                    </div>
                    <div v-else-if="questionType === 'true_false'" class="answer-section"><div class="answer-heading"><h4>Correct answer</h4></div><div class="truth-options"><label v-for="value in ['True', 'False']" :key="value" :class="{ chosen: referenceAnswer === value }"><input v-model="referenceAnswer" type="radio" name="truth-answer" :value="value" />{{ value }}</label></div></div>
                    <div v-else class="field-block answer-section"><label for="reference-answer">Reference answer <span class="optional">Optional for this preview</span></label><textarea id="reference-answer" v-model="referenceAnswer" rows="2" placeholder="Add an expected answer or marking guidance…"></textarea></div>

                    <p v-if="answerError" class="field-error" role="alert">{{ answerError }}</p>
                    <details class="explanation-section"><summary>Explanation / tutor notes <span>Optional</span></summary><label class="sr-only" for="question-explanation">Explanation or tutor notes</label><textarea id="question-explanation" v-model="explanation" rows="3" placeholder="Explain the answer or leave a note for later…"></textarea></details>
                </section>

                <footer class="form-footer"><button type="button" class="secondary-button" @click="resetSample">Reset sample</button><button type="submit" class="primary-button"><EyeIcon /> Review question</button></footer>
                </div>
                <Transition name="preview-door">
                    <section v-if="previewOpen" id="question-preview-door" class="preview-door" aria-label="Question preview" @keydown.esc.prevent.stop="closeReview">
                        <div class="door-scroll">
                <div class="preview-panel review-panel">
                    <div class="preview-heading"><EyeIcon /><h3>Live preview</h3><span>Question {{ confirmedCount + 1 }}</span></div>
                    <div class="student-preview">
                        <div class="preview-meta"><span>{{ subject }}</span><span>Grade {{ grade }}</span></div>
                        <div class="preview-question-heading"><span class="question-number">{{ String(confirmedCount + 1).padStart(2, '0') }}</span><span>{{ typeLabel }}</span><span class="difficulty" :class="difficulty.toLowerCase()">{{ difficulty }}</span></div>
                        <p class="preview-prompt">{{ prompt.trim() || 'Your question will appear here.' }}</p>
                        <div v-if="questionType === 'multiple_choice'" class="preview-answers"><div v-for="(option, index) in options" :key="index"><span>{{ optionLetter(index) }}</span><p>{{ option.trim() || `Answer ${optionLetter(index)}` }}</p><span class="preview-radio"></span></div></div>
                        <div v-else-if="questionType === 'true_false'" class="preview-truth"><span>True</span><span>False</span></div>
                        <div v-else class="preview-answer-placeholder">Student answer area</div>
                        <div class="preview-bottom"><span>{{ selectedTopics.length }} linked {{ selectedTopics.length === 1 ? 'topic' : 'topics' }}</span><span>Question preview</span></div>
                    </div>
                    <div class="review-answer"><strong>Correct / reference answer</strong><p>{{ correctAnswerText }}</p><template v-if="explanation.trim()"><strong>Tutor notes</strong><p>{{ explanation }}</p></template></div>
                    <div class="link-summary"><div class="link-summary-title"><LinkIcon /><strong>Linked topics</strong></div><div v-if="selectedTopics.length" class="preview-topic-chips"><span v-for="topic in selectedTopics" :key="topic">{{ topic }}</span></div><p v-else class="no-selection">Choose topics to see the connections.</p></div>
                    <p class="preview-footnote"><InformationCircleIcon /> Sample data · preview only</p>
                </div>
                        </div>
                <div class="door-actions"><button ref="reviewBackButton" type="button" class="secondary-button" @click="closeReview">Back to editing</button><button type="button" class="primary-button" :disabled="!canConfirm" @click="confirmQuestion"><CheckCircleIcon /> Confirm &amp; next question</button><p v-if="!canConfirm">Complete the question, answers and topics to confirm.</p></div>
                    </section>
                </Transition>
            </div>

            <aside class="placement-column" aria-label="Question placement">
                <details class="studio-card placement-card" :open="placementExpanded" @toggle="placementExpanded = $event.target.open">
                    <summary class="placement-toggle"><LinkIcon /><div><h3>Where does it belong?</h3><p>Grade {{ grade }} · {{ subject }} · {{ selectedTopics.length }} topics</p></div><ChevronDownIcon /></summary><div class="placement-fields">
                    <div class="field-grid">
                        <div class="field-block"><label for="question-grade">Grade</label><select id="question-grade" v-model="grade" @change="clearPlacement"><option v-for="item in grades" :key="item" :value="item">Grade {{ item }}</option></select></div>
                        <div class="field-block"><label for="question-subject">Subject</label><select id="question-subject" v-model="subject" @change="clearPlacement"><option v-for="item in subjects" :key="item">{{ item }}</option></select></div>
                    </div>
                    <div class="topics-heading"><label for="topic-search">Attach to topics <span class="count">{{ selectedTopics.length }} selected</span></label><button v-if="selectedTopics.length" type="button" class="text-button" @click="selectedTopics = []">Clear selection</button></div>
                    <div class="topic-picker">
                        <div class="topic-search"><MagnifyingGlassIcon /><input id="topic-search" v-model="topicSearch" type="search" placeholder="Find a topic…" /></div>
                        <div class="topic-options">
                            <label v-for="topic in filteredTopics" :key="topic" class="topic-option" :class="{ selected: selectedTopics.includes(topic) }">
                                <input v-model="selectedTopics" type="checkbox" :value="topic" /><span>{{ topic }}</span><LinkIcon v-if="selectedTopics.includes(topic)" />
                            </label>
                            <p v-if="!filteredTopics.length" class="empty-topics">No matching topics. Try another search.</p>
                        </div>
                    </div>
                    <div v-if="selectedTopics.length" class="selected-topics" aria-label="Selected topics"><button v-for="topic in selectedTopics" :key="topic" type="button" :aria-label="`Remove ${topic}`" @click="removeTopic(topic)">{{ topic }}<XMarkIcon /></button></div>
                    <p v-if="attempted && !selectedTopics.length" class="field-error">Select at least one topic.</p>
                    <p class="field-help"><LinkIcon /> One question, shared across {{ selectedTopics.length || 'your selected' }} {{ selectedTopics.length === 1 ? 'topic' : 'topics' }}.</p>
                </div></details>
            </aside>
        </form>

    </div>
</template>

<script setup>
import { computed, nextTick, ref } from 'vue';
import { ChevronDownIcon, BeakerIcon, CheckCircleIcon, EyeIcon, InformationCircleIcon, LinkIcon, MagnifyingGlassIcon, PlusIcon, XMarkIcon } from '@heroicons/vue/24/outline';
defineProps({ previewOpen: { type: Boolean, default: false } });
const emit = defineEmits(['update:previewOpen']);

const grades = [7, 8, 9, 10, 11, 12, 13];
const subjects = ['Mathematics', 'Additional Mathematics', 'Accounts'];
const types = [
    { value: 'multiple_choice', label: 'Multiple choice' }, { value: 'true_false', label: 'True / false' },
    { value: 'short_answer', label: 'Short answer' }, { value: 'numerical', label: 'Numerical answer' },
    { value: 'mathematical', label: 'Mathematical exercise' }, { value: 'written', label: 'Written answer' },
];
const symbols = ['²', '³', '√', 'π', '×', '÷', '½', '≤', '≥'];
const grade = ref(7);
const subject = ref('Mathematics');
const selectedTopics = ref(['Decimal Numbers', 'Fractions']);
const topicSearch = ref('');
const questionType = ref('multiple_choice');
const difficulty = ref('Easy');
const prompt = ref('Express 0.75 as a fraction in its simplest form.');
const options = ref(['3/4', '1/4', '7/5', '75/10']);
const correctOption = ref(0);
const referenceAnswer = ref('');
const explanation = ref('');
const attempted = ref(false);
const confirmation = ref('');
const questionInput = ref(null);
const reviewBackButton = ref(null);
const confirmedCount = ref(0);
const answerError = ref('');
const placementExpanded = ref(window.matchMedia('(min-width: 1100px)').matches);
const topics = computed(() => {
    if (subject.value === 'Accounts') return ['Introduction to Accounting', 'Double Entry', 'Cash Book', 'Trial Balance', 'Financial Statements'];
    if (subject.value === 'Additional Mathematics') return ['Functions', 'Quadratic Equations', 'Polynomials', 'Trigonometry', 'Differentiation'];
    return grade.value <= 8
        ? ['Decimal Numbers', 'Fractions', 'Integers', 'Percentages', 'Ratio & Proportion', 'Basic Geometry']
        : ['Algebra', 'Equations', 'Geometry', 'Trigonometry', 'Statistics', 'Probability'];
});
const filteredTopics = computed(() => topics.value.filter(topic => topic.toLowerCase().includes(topicSearch.value.trim().toLowerCase())));
const typeLabel = computed(() => types.find(type => type.value === questionType.value)?.label);
const correctAnswerText = computed(() => questionType.value === 'multiple_choice'
    ? correctOption.value === null ? 'Choose a correct answer.' : `${optionLetter(correctOption.value)}. ${options.value[correctOption.value] || ''}`
    : referenceAnswer.value.trim() || 'No reference answer supplied.');
const canConfirm = computed(() => Boolean(prompt.value.trim() && selectedTopics.value.length)
    && (questionType.value !== 'multiple_choice' || (correctOption.value !== null && options.value.every(option => option.trim())))
    && (questionType.value !== 'true_false' || ['True', 'False'].includes(referenceAnswer.value)));

function clearPlacement() {
    selectedTopics.value = [];
    topicSearch.value = '';
    confirmation.value = '';
}

function removeTopic(topic) {
    selectedTopics.value = selectedTopics.value.filter(selected => selected !== topic);
}

function optionLetter(index) {
    return String.fromCharCode(65 + index);
}

function removeOption(index) {
    options.value.splice(index, 1);
    if (correctOption.value === index) correctOption.value = null;
    else if (correctOption.value > index) correctOption.value--;
}

async function insertSymbol(symbol) {
    const input = questionInput.value;
    const start = input?.selectionStart ?? prompt.value.length;
    const end = input?.selectionEnd ?? start;
    if (prompt.value.length - (end - start) + symbol.length > 5000) return;
    prompt.value = prompt.value.slice(0, start) + symbol + prompt.value.slice(end);
    await nextTick();
    input?.focus();
    input?.setSelectionRange(start + symbol.length, start + symbol.length);
}

async function reviewQuestion() {
    attempted.value = true;
    answerError.value = '';
    confirmation.value = '';
    if (!prompt.value.trim()) {
        questionInput.value?.focus();
        return;
    }
    if (!selectedTopics.value.length) {
        placementExpanded.value = true;
        await nextTick();
        document.getElementById('topic-search')?.focus();
        return;
    }
    if (questionType.value === 'multiple_choice' && (options.value.some(option => !option.trim()) || correctOption.value === null)) {
        answerError.value = 'Fill in the answer options and select the correct answer before reviewing.';
        return;
    }
    if (questionType.value === 'true_false' && !['True', 'False'].includes(referenceAnswer.value)) {
        answerError.value = 'Select True or False before reviewing.';
        return;
    }
    emit('update:previewOpen', true);
    await nextTick();
    reviewBackButton.value?.focus({ preventScroll: true });
    if (window.matchMedia('(max-width: 768px)').matches) {
        document.getElementById('question-preview-door')?.scrollIntoView({ block: 'start', behavior: 'smooth' });
    }
}

async function closeReview() {
    emit('update:previewOpen', false);
    await nextTick();
    questionInput.value?.focus({ preventScroll: true });
}

async function confirmQuestion() {
    if (!canConfirm.value) return;
    emit('update:previewOpen', false);
    confirmedCount.value++;
    prompt.value = '';
    options.value = ['', '', '', ''];
    correctOption.value = null;
    referenceAnswer.value = '';
    explanation.value = '';
    attempted.value = false;
    answerError.value = '';
    confirmation.value = `Question ${confirmedCount.value} confirmed in this preview. Same course and topics kept for the next question. Nothing saved.`;
    await nextTick();
    questionInput.value?.focus({ preventScroll: true });
    questionInput.value?.scrollIntoView({ block: 'center', behavior: 'smooth' });
}

function resetSample() {
    emit('update:previewOpen', false);
    confirmedCount.value = 0;
    answerError.value = '';
    grade.value = 7;
    subject.value = 'Mathematics';
    selectedTopics.value = ['Decimal Numbers', 'Fractions'];
    topicSearch.value = '';
    questionType.value = 'multiple_choice';
    difficulty.value = 'Easy';
    prompt.value = 'Express 0.75 as a fraction in its simplest form.';
    options.value = ['3/4', '1/4', '7/5', '75/10'];
    correctOption.value = 0;
    referenceAnswer.value = '';
    explanation.value = '';
    attempted.value = false;
    confirmation.value = '';
}
</script>

<style scoped>
.question-studio { --studio-border: var(--app-border); color: var(--app-text); min-width: 0; }
.question-studio *, .question-studio *::before, .question-studio *::after { box-sizing: border-box; }
.studio-header { align-items: flex-start; display: flex; flex-wrap: wrap; gap: 1rem; justify-content: space-between; margin-bottom: 1.2rem; }
.eyebrow { color: var(--app-accent); font-size: .65rem; font-weight: 750; letter-spacing: .12em; margin-bottom: .4rem; text-transform: uppercase; }
.studio-header h2 { color: #fff; font-size: clamp(1.5rem, 2.5vw, 2rem); font-weight: 800; letter-spacing: -.04em; line-height: 1.2; }
.studio-header h2 + p { color: var(--app-muted); font-size: .8rem; margin-top: .55rem; }
.preview-badge { align-items: center; background: rgba(99,102,241,.1); border: 1px solid rgba(165,180,252,.2); border-radius: 999px; color: #c7d2fe; display: inline-flex; font-size: .65rem; font-weight: 650; gap: .4rem; padding: .45rem .7rem; }
svg { flex: 0 0 auto; height: 17px; width: 17px; }
.prototype-notice, .confirmation { align-items: center; border: 1px solid var(--studio-border); border-radius: 12px; display: flex; font-size: .72rem; gap: .6rem; margin-bottom: 1.2rem; padding: .8rem 1rem; }
.prototype-notice { background: var(--app-inset); color: var(--app-muted); }
.prototype-notice svg { color: var(--app-accent); }
.confirmation { background: rgba(16,185,129,.09); border-color: rgba(52,211,153,.25); color: #a7f3d0; }
.confirmation button { margin-left: auto; }
.studio-grid { align-items: start; display: grid; gap: 1rem; grid-template-columns: minmax(0, 1.8fr) minmax(255px, 1fr); }
.editor, .placement-column { min-width: 0; }
.editor { border-radius: 18px; isolation: isolate; overflow: clip; position: relative; }
.preview-door { background: var(--app-panel); border: 1px solid var(--studio-border); border-radius: 18px; display: flex; flex-direction: column; inset: 0; position: absolute; z-index: 8; }
.door-scroll { flex: 1; min-height: 0; overflow-y: auto; overscroll-behavior: contain; }
.preview-door .preview-panel { border: 0; border-radius: 0; }
.preview-door-enter-active, .preview-door-leave-active { transition: transform .42s cubic-bezier(.22,1,.36,1); }
.preview-door-enter-from, .preview-door-leave-to { transform: translateX(-100%); }
@media (prefers-reduced-motion: reduce) { .preview-door-enter-active, .preview-door-leave-active { transition: none; } }
.studio-card { background: var(--app-glass); border: 1px solid var(--studio-border); border-radius: 18px; margin-bottom: 1rem; padding: 1.35rem; }
.section-title { align-items: flex-start; display: flex; gap: .7rem; margin-bottom: 1.25rem; }
.step { align-items: center; background: rgba(99,102,241,.13); border: 1px solid rgba(165,180,252,.15); border-radius: 10px; color: var(--app-accent); display: flex; flex-shrink: 0; font-size: .65rem; font-weight: 800; height: 30px; justify-content: center; width: 30px; }
.section-title h3 { color: #f8fafc; font-size: .9rem; font-weight: 750; }
.section-title p { color: var(--app-muted); font-size: .7rem; margin-top: .15rem; }
.field-grid { display: grid; gap: .85rem; grid-template-columns: repeat(2, minmax(0,1fr)); }
.field-block { min-width: 0; }
.field-block > label, .topics-heading > label { color: #dbe3f1; display: block; font-size: .72rem; font-weight: 650; margin-bottom: .5rem; }
select, textarea, .answer-option > input[type=text] { background: var(--app-input); border: 1px solid var(--studio-border); border-radius: 10px; color: #f1f5f9; font: inherit; font-size: .78rem; outline: none; width: 100%; }
select { min-height: 42px; padding: .65rem .75rem; }
select option { background: var(--app-panel); }
textarea { line-height: 1.7; min-height: 90px; padding: .85rem; resize: vertical; }
select:focus-visible, textarea:focus-visible, .answer-option > input[type=text]:focus-visible { border-color: #818cf8; box-shadow: 0 0 0 3px rgba(99,102,241,.12); }
button { cursor: pointer; font: inherit; }
button:focus-visible, input:focus-visible, summary:focus-visible { outline: 2px solid #a5b4fc; outline-offset: 3px; }
.topics-heading { align-items: center; display: flex; flex-wrap: wrap; gap: .5rem; justify-content: space-between; margin-top: 1.2rem; }
.topics-heading > label { margin: 0; }
.count { background: rgba(99,102,241,.13); border-radius: 6px; color: #c7d2fe; display: inline-block; font-size: .6rem; margin-left: .4rem; padding: .2rem .4rem; }
.text-button { align-items: center; background: none; border: 0; color: var(--app-accent); display: inline-flex; font-size: .68rem; gap: .3rem; padding: .3rem 0; }
.topic-picker { background: var(--app-inset); border: 1px solid var(--studio-border); border-radius: 12px; margin-top: .65rem; overflow: hidden; }
.topic-search { align-items: center; border-bottom: 1px solid var(--studio-border); color: #64748b; display: flex; gap: .5rem; padding: .6rem .8rem; }
.topic-search input { background: transparent; border: 0; color: #e2e8f0; font: inherit; font-size: .74rem; min-height: 26px; min-width: 0; outline: none; width: 100%; }
.topic-options { display: grid; gap: .3rem; grid-template-columns: repeat(2,minmax(0,1fr)); max-height: 220px; overflow-y: auto; padding: .5rem; }
.topic-option { align-items: center; border: 1px solid transparent; border-radius: 8px; color: #a6b3c9; cursor: pointer; display: flex; font-size: .7rem; gap: .5rem; min-height: 40px; padding: .5rem; }
.topic-option span { overflow-wrap: anywhere; }
.topic-option.selected { background: rgba(99,102,241,.12); border-color: rgba(129,140,248,.2); color: #e0e7ff; }
.topic-option svg { color: #818cf8; height: 13px; margin-left: auto; width: 13px; }
input[type=checkbox], input[type=radio] { accent-color: #818cf8; flex-shrink: 0; height: 16px; width: 16px; }
.empty-topics { color: var(--app-muted); font-size: .75rem; grid-column: 1/-1; padding: 1rem; text-align: center; }
.selected-topics, .preview-topic-chips { display: flex; flex-wrap: wrap; gap: .4rem; margin-top: .75rem; }
.selected-topics button { align-items: center; background: rgba(99,102,241,.1); border: 1px solid rgba(129,140,248,.2); border-radius: 7px; color: #c7d2fe; display: inline-flex; font-size: .63rem; gap: .3rem; max-width: 100%; overflow-wrap: anywhere; padding: .3rem .45rem; text-align: left; }
.selected-topics svg { height: 12px; width: 12px; }
.field-help { align-items: center; color: #7e8ca5; display: flex; font-size: .65rem; gap: .35rem; margin-top: .8rem; }
.field-help svg { height: 13px; width: 13px; }
.field-error { color: #fda4af; font-size: .72rem; margin-top: .6rem; }
.question-body { margin-top: 1.1rem; }
.question-body textarea { border-bottom-left-radius: 0; border-bottom-right-radius: 0; display: block; }
.editor-footer { background: var(--app-inset); border: 1px solid var(--studio-border); border-radius: 0 0 10px 10px; border-top: 0; color: #72819c; display: flex; font-size: .6rem; gap: .5rem; justify-content: space-between; padding: .45rem .7rem; }
.symbol-bar { align-items: center; display: flex; flex-wrap: wrap; gap: .3rem; margin-top: .65rem; }
.symbol-bar > span { color: #7e8ca5; font-size: .62rem; margin-right: .25rem; }
.symbol-bar button { background: var(--app-glass); border: 1px solid var(--studio-border); border-radius: 6px; color: #cbd5e1; font-size: .82rem; min-height: 30px; min-width: 30px; }
.symbol-bar button:hover { background: rgba(99,102,241,.18); }
.answer-section { border-top: 1px solid var(--studio-border); margin-top: 1.2rem; padding-top: 1rem; }
.answer-heading { align-items: center; display: flex; flex-wrap: wrap; gap: .4rem; justify-content: space-between; margin-bottom: .75rem; }
.answer-heading h4 { color: #dbe3f1; font-size: .75rem; font-weight: 650; }
.answer-heading > span { color: #7e8ca5; font-size: .63rem; }
.answer-option { align-items: center; background: var(--app-inset); border: 1px solid var(--studio-border); border-radius: 10px; display: flex; gap: .55rem; margin-bottom: .45rem; min-height: 45px; padding: .4rem .65rem; }
.answer-option.correct { background: rgba(16,185,129,.045); border-color: rgba(52,211,153,.3); }
.option-letter { color: #7e8ca5; font-size: .65rem; font-weight: 700; }
.answer-option > input[type=text] { background: transparent; border: 0; border-radius: 4px; min-height: 30px; min-width: 0; padding: .2rem; }
.correct-icon { color: #6ee7b7; }
.answer-option button, .confirmation button { background: none; border: 0; color: #94a3b8; display: flex; padding: .2rem; }
.truth-options { display: flex; gap: .6rem; }
.truth-options label { align-items: center; border: 1px solid var(--studio-border); border-radius: 10px; display: flex; font-size: .8rem; gap: .5rem; padding: .7rem 1rem; }
.truth-options .chosen { border-color: #818cf8; }
.optional { color: #7e8ca5; font-size: .6rem; font-weight: 400; }
.explanation-section { border-top: 1px solid var(--studio-border); margin-top: 1.1rem; padding-top: .9rem; }
summary { color: #cbd5e1; cursor: pointer; font-size: .72rem; }
summary span { color: #72819c; font-size: .62rem; margin-left: .5rem; }
.explanation-section textarea { margin-top: .8rem; }
.form-footer { display: flex; flex-wrap: wrap; gap: .6rem; justify-content: space-between; padding: .1rem 0 .8rem; }
.primary-button, .secondary-button { align-items: center; border: 1px solid var(--studio-border); border-radius: 10px; display: inline-flex; font-size: .75rem; font-weight: 650; gap: .4rem; justify-content: center; min-height: 43px; padding: .7rem 1rem; }
.primary-button { background: var(--app-primary-gradient); border-color: #6366f1; box-shadow: 0 4px 16px rgba(79,70,229,.15); color: white; }
.secondary-button { background: var(--app-glass); color: #cbd5e1; }
.placement-column { min-width: 0; position: sticky; top: 105px; }
.preview-panel { background: rgba(15,23,42,.35); border: 1px solid var(--studio-border); border-radius: 18px; overflow: hidden; }
.preview-panel, .preview-panel * { box-sizing: border-box; }
.review-panel { --studio-border: var(--app-border); }
.review-panel .student-preview { padding: .85rem; }
.review-panel .preview-prompt { font-size: .85rem; margin: .65rem 0 .8rem; }
.review-panel .preview-answers > div { min-height: 38px; padding: .5rem .6rem; }
.door-actions { background: var(--app-panel); border-top: 1px solid var(--app-border); display: grid; flex-shrink: 0; gap: .5rem; padding: .75rem; }
.door-actions button { width: 100%; }
.door-actions p { color: var(--app-muted); font-size: .62rem; line-height: 1.5; margin: 0; }
.door-actions button:disabled { cursor: not-allowed; opacity: .45; }
.preview-heading { align-items: center; border-bottom: 1px solid var(--studio-border); display: flex; gap: .5rem; padding: .9rem 1rem; }
.preview-heading svg { color: var(--app-accent); }
.preview-heading h3 { color: #dbe3f1; font-size: .75rem; font-weight: 650; }
.preview-heading > span { color: #7e8ca5; font-size: .6rem; margin-left: auto; }
.student-preview { background: linear-gradient(145deg,rgba(99,102,241,.065),transparent); padding: 1.15rem; }
.preview-meta { color: #8291ac; display: flex; flex-wrap: wrap; font-size: .63rem; gap: .35rem; justify-content: space-between; margin-bottom: 1.1rem; }
.preview-question-heading { align-items: center; display: flex; flex-wrap: wrap; font-size: .6rem; gap: .45rem; }
.question-number { background: rgba(129,140,248,.16); border-radius: 7px; color: #c7d2fe; font-size: .65rem; font-weight: 800; padding: .3rem .4rem; }
.preview-question-heading > span:nth-child(2) { color: #94a3b8; }
.difficulty { background: rgba(16,185,129,.08); border: 1px solid rgba(52,211,153,.2); border-radius: 999px; color: #6ee7b7; margin-left: auto; padding: .2rem .4rem; }
.difficulty.medium { background: rgba(245,158,11,.08); border-color: rgba(245,158,11,.2); color: #fcd34d; }
.difficulty.hard { background: rgba(244,63,94,.08); border-color: rgba(244,63,94,.2); color: #fda4af; }
.preview-prompt { color: #f1f5f9; font-size: .95rem; font-weight: 600; line-height: 1.8; margin: .85rem 0 1.1rem; overflow-wrap: anywhere; white-space: pre-wrap; }
.preview-answers { display: grid; gap: .5rem; }
.preview-answers > div { align-items: center; background: var(--app-inset); border: 1px solid var(--studio-border); border-radius: 10px; display: flex; font-size: .75rem; gap: .65rem; min-height: 43px; padding: .6rem .7rem; }
.preview-answers > div > span:first-child { color: #72819c; font-size: .62rem; }
.preview-answers p { color: #cbd5e1; min-width: 0; overflow-wrap: anywhere; }
.preview-radio { border: 1px solid #46516a; border-radius: 50%; flex-shrink: 0; height: 13px; margin-left: auto; width: 13px; }
.preview-bottom { border-top: 1px solid var(--studio-border); color: #72819c; display: flex; flex-wrap: wrap; font-size: .58rem; gap: .4rem; justify-content: space-between; margin-top: 1.1rem; padding-top: .8rem; }
.link-summary { border-top: 1px solid var(--studio-border); padding: 1rem 1.15rem; }
.link-summary-title { align-items: center; color: #c7d2fe; display: flex; font-size: .7rem; gap: .4rem; }
.link-summary-title svg { height: 15px; width: 15px; }
.link-summary p { color: #8291ac; font-size: .68rem; line-height: 1.7; margin-top: .45rem; }
.preview-topic-chips span { background: rgba(99,102,241,.1); border: 1px solid rgba(129,140,248,.15); border-radius: 6px; color: #a5b4fc; font-size: .6rem; max-width: 100%; overflow-wrap: anywhere; padding: .25rem .4rem; }
.preview-footnote { align-items: center; background: rgba(2,6,23,.2); color: #6b7a94; display: flex; font-size: .6rem; gap: .35rem; padding: .7rem 1.15rem; }
.preview-footnote svg { height: 13px; width: 13px; }
.preview-truth { display: flex; gap: .5rem; }
.preview-truth span, .preview-answer-placeholder { border: 1px solid var(--studio-border); border-radius: 8px; color: #7e8ca5; font-size: .75rem; padding: .7rem 1rem; }
.preview-answer-placeholder { min-height: 75px; }
.sr-only { clip: rect(0,0,0,0); height: 1px; overflow: hidden; position: absolute; width: 1px; }
@media (max-width: 1100px) { .studio-grid { grid-template-columns: minmax(0,1fr); } .placement-column { grid-row: 1; position: static; } .placement-card { margin-bottom: 0; } }
@media (max-width: 600px) { .studio-card { padding: 1rem; } .topic-options { grid-template-columns: minmax(0,1fr); } .field-grid { gap: .6rem; } select, textarea, .answer-option > input[type=text], .topic-search input { font-size: 16px; } .form-footer .primary-button { flex: 1; } .editor-footer { flex-wrap: wrap; } .studio-header { gap: .75rem; } .truth-options label { flex: 1; } }

.session-count { color: #6ee7b7; letter-spacing: 0; text-transform: none; }
.studio-card { padding: 1rem; }
.studio-header { margin-bottom: .85rem; }
.section-title { margin-bottom: .85rem; }
.placement-card { padding: 0; }
.placement-toggle { align-items: center; display: flex; gap: .6rem; list-style: none; padding: 1rem; }
.placement-toggle::-webkit-details-marker { display: none; }
.placement-toggle > svg:first-child { color: #a5b4fc; }
.placement-toggle > svg:last-child { color: #8291ac; height: 14px; margin-left: auto; transition: transform .15s ease; width: 14px; }
.placement-card[open] .placement-toggle > svg:last-child { transform: rotate(180deg); }
.placement-toggle h3 { color: #e2e8f0; font-size: .76rem; font-weight: 750; }
.placement-toggle p { color: #8291ac; font-size: .62rem; line-height: 1.5; margin-top: .2rem; }
.placement-fields { border-top: 1px solid var(--studio-border); padding: 1rem; }
.placement-fields .field-grid, .placement-fields .topic-options { grid-template-columns: minmax(0,1fr); }
.placement-fields .topic-options { max-height: 205px; }
.topics-heading { gap: .3rem; }
.topic-option { min-height: 35px; }
.answer-options-grid { display: grid; gap: .5rem; grid-template-columns: repeat(2,minmax(0,1fr)); }
.answer-option { gap: .4rem; margin-bottom: 0; min-width: 0; padding: .35rem .5rem; }
.answer-section { margin-top: .8rem; padding-top: .75rem; }
.question-body { margin-top: .8rem; }
.explanation-section { margin-top: .7rem; padding-top: .65rem; }
.form-footer { background: var(--app-panel); border: 1px solid var(--studio-border); border-radius: 12px; bottom: .75rem; padding: .6rem; position: sticky; z-index: 5; }
.review-panel { text-align: left; }
.review-answer { border-top: 1px solid var(--studio-border); font-size: .75rem; padding: .85rem 1.15rem; }
.review-answer strong { color: #a5b4fc; display: block; font-size: .65rem; margin-bottom: .3rem; }
.review-answer p { color: #e2e8f0; margin-bottom: .6rem; overflow-wrap: anywhere; white-space: pre-wrap; }
@media (max-width: 1100px) { .placement-fields .field-grid { grid-template-columns: repeat(2,minmax(0,1fr)); } .placement-fields .topic-options { grid-template-columns: repeat(2,minmax(0,1fr)); } }
@media (max-width: 600px) { .answer-options-grid { grid-template-columns: minmax(0,1fr); } .placement-fields .topic-options { grid-template-columns: minmax(0,1fr); } .placement-toggle { padding: .75rem; } .studio-header h2 + p { display: none; } }
</style>
