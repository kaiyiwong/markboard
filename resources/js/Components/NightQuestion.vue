<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { edit, fieldError, refusal, useEditing, type EditResult } from '@/api';
import CodeText from '@/Components/CodeText.vue';
import Icon from '@/Components/Icon.vue';
import type { NightQuestion } from '@/types';

// A work order the chief of staff couldn't scope without Kai: its questions and the answers. When the
// order gives each question its choices, they're chips to pick from (with "Other" for a written
// answer) and "why it's asking" a click away; otherwise the questions show as written over a text box.
// Either way the answers go into the order as its `## KAI'S ANSWERS` section, which the next triage
// reads. The form keeps the etag from the first change, so a change to the order meanwhile is refused.
const props = defineProps<{ question: NightQuestion }>();

const OTHER = 'other';
const asks = computed(() => props.question.asks);
const picked = ref<string[]>((props.question.asks ?? []).map(() => ''));
const others = ref<string[]>((props.question.asks ?? []).map(() => ''));
const answers = ref(props.question.asks ? '' : (props.question.answers ?? ''));
const sentEtag = ref<string | null>(null);
const editing = useEditing();
const busy = ref(false);
const result = ref<EditResult | null>(null);
const saved = ref(false);
const id = `answer-${props.question.project}-${props.question.task}`;
const title = computed(() => props.question.headline || (props.question.title?.replace(/^\S+ \S+: /, '') ?? props.question.task));

/** A question counts as answered once it has a choice, or a written answer under "Other". */
const answered = computed(() => picked.value.filter((choice, i) => (choice === OTHER ? (others.value[i] ?? '').trim() !== '' : choice !== '')).length);
const ready = computed(() => (asks.value ? answered.value === asks.value.length : answers.value.trim() !== ''));

/** The answers as the order keeps them: one numbered line per question, its question then the answer. */
function written(): string {
    if (!asks.value) {
        return answers.value;
    }
    return asks.value
        .map((ask, i) => `${i + 1}. ${ask.ask} ${picked.value[i] === OTHER ? (others.value[i] ?? '').trim() : picked.value[i]}`)
        .join('\n');
}

function start() {
    if (!editing.value) {
        sentEtag.value = props.question.etag;
        editing.value = true;
        saved.value = false;
    }
}

async function submit() {
    busy.value = true;
    result.value = await edit('PUT', `/api/v1/night/orders/${props.question.project}/${props.question.task}/answers`, sentEtag.value ?? props.question.etag, {
        answers: written(),
    });
    busy.value = false;
    editing.value = false;
    saved.value = result.value.ok;
}
</script>

<template>
    <article :id="id" class="panel question" :aria-labelledby="id + '-title'">
        <header class="stack gap-3">
            <div class="top">
                <p class="p-label eyebrow">
                    <Link :href="`/projects/${question.project}`" class="link-quiet project">{{ question.project_name }}</Link>
                    <code class="num">{{ question.task }}</code>
                </p>
                <p class="p-caption blocks"><Icon name="blocked" />Blocks the task</p>
            </div>
            <h3 :id="id + '-title'" class="p-headline">{{ title }}</h3>
            <p v-if="question.context" class="p-caption"><CodeText :text="question.context" /></p>
        </header>

        <form class="stack gap-6" @submit.prevent="submit" @focusin="start" @change="start">
            <ol v-if="asks" class="asks stack gap-6">
                <li v-for="(ask, i) in asks" :key="i" class="ask">
                    <span class="p-caption num n">{{ i + 1 }}</span>
                    <fieldset class="stack gap-4">
                        <legend class="p-title">{{ ask.ask }}</legend>
                        <div class="choices">
                            <label v-for="choice in [...ask.choices, OTHER]" :key="choice" class="choice">
                                <input v-model="picked[i]" type="radio" class="visually-hidden" :name="`${id}-${i}`" :value="choice" />
                                <span class="p-body">{{ choice === OTHER ? 'Other' : choice }}</span>
                            </label>
                        </div>
                        <input
                            v-if="picked[i] === OTHER"
                            v-model="others[i]"
                            class="input md other"
                            type="text"
                            :aria-label="`Your answer to: ${ask.ask}`"
                            placeholder="Your answer"
                        />
                        <details v-if="ask.why" class="disclosure why">
                            <summary class="p-caption"><Icon name="chevron" class="turn" />Why it's asking</summary>
                            <p class="p-caption"><CodeText :text="ask.why" /></p>
                        </details>
                    </fieldset>
                </li>
            </ol>

            <template v-else>
                <!-- Rendered on the server: raw HTML escaped, unsafe links dropped. -->
                <div v-if="question.missing" class="content p-body" v-html="question.missing" />
                <div class="field md">
                    <label class="p-field" :for="id + '-text'">Your answers</label>
                    <textarea :id="id + '-text'" v-model="answers" class="input md" rows="3" required :aria-invalid="!!fieldError(result, 'answers')" />
                    <p v-if="fieldError(result, 'answers')" class="p-caption msg"><Icon name="alert" /><span>{{ fieldError(result, 'answers') }}</span></p>
                </div>
            </template>

            <div v-if="asks && question.answers" class="earlier stack gap-2">
                <p class="p-label">Sent earlier</p>
                <p class="p-caption sent">{{ question.answers }}</p>
            </div>

            <div class="foot">
                <p v-if="refusal(result)" class="p-caption msg" role="alert"><Icon name="alert" /><span>{{ refusal(result) }}</span></p>
                <p v-else-if="saved" class="p-caption" role="status">Saved in the work order. The next triage reads it.</p>
                <p v-else-if="asks" class="p-caption num">{{ answered }} of {{ asks.length }} answered</p>
                <button type="submit" class="btn md btn-primary hit" :disabled="busy || !ready">
                    <span class="lbl">{{ question.answers ? 'Update answers' : asks ? 'Send answers' : 'Save answers' }}</span>
                </button>
            </div>
        </form>
    </article>
</template>

<style scoped>
.question {
    display: grid;
    gap: var(--space-6);
}

.top {
    display: flex;
    flex-wrap: wrap;
    align-items: baseline;
    justify-content: space-between;
    gap: var(--space-2) var(--space-4);
}

.eyebrow {
    display: flex;
    flex-wrap: wrap;
    align-items: baseline;
    gap: var(--space-1) var(--space-3);
}

/* The project as an all-caps label, +0.06em (SPEC 4.5 rule 5), in the accent: it links. */
.project {
    color: var(--accent-fg);
    text-transform: uppercase;
    letter-spacing: 0.06em;
}

.eyebrow code {
    color: var(--fg-muted);
}

.blocks {
    display: inline-flex;
    align-items: center;
    gap: var(--space-1);
    color: var(--warning-fg-strong);
}

.asks {
    margin: 0;
    padding: 0;
    list-style: none;
}

/* A question: its number in the margin, then the question, its choices and why it's asking. */
.ask {
    display: grid;
    grid-template-columns: var(--space-4) minmax(0, 1fr);
    gap: var(--space-3);
    align-items: baseline;
}

.n {
    color: var(--fg-muted);
}

fieldset {
    min-inline-size: 0;
    margin: 0;
    padding: 0;
    border: 0;
}

legend {
    float: left;
    inline-size: 100%;
    padding: 0;
    font-weight: var(--weight-medium);
}

legend + * {
    clear: both;
}

.choices {
    display: flex;
    flex-wrap: wrap;
    gap: var(--space-2);
}

/* A choice is a radio as a chip: picked, it fills with the accent (a marker well over 3:1). */
.choice {
    display: inline-flex;
    align-items: center;
    min-block-size: var(--control-h-md);
    padding-inline: var(--space-4);
    border-radius: var(--control-radius);
    background: var(--bg-control);
    cursor: pointer;
}

.choice:has(:checked) {
    background: var(--accent-solid);
    color: var(--fg-on-accent);
}

.choice:has(:focus-visible) {
    outline: 2px solid var(--focus-ring);
    outline-offset: 2px;
}

@media (hover: hover) {
    .choice:not(:has(:checked)):hover {
        background: var(--bg-control-hover);
    }
}

.other {
    max-inline-size: 32rem;
}

.why > summary {
    gap: var(--space-1);
}

.why > p {
    margin-block-start: var(--space-3);
    padding-inline-start: calc(var(--space-4) + var(--space-1));
}

.sent {
    white-space: pre-line;
}

/* The order's questions as written, when it gives no choices: one numbered row each. */
.content :deep(> * + *) {
    margin-block-start: var(--space-4);
}

.content :deep(:is(p, ul, ol)) {
    margin-block: 0;
}

.content :deep(:is(ul, ol)) {
    padding-inline-start: var(--space-5);
}

.content :deep(li + li) {
    margin-block-start: var(--space-4);
}

.content :deep(li > :is(ul, ol, p)) {
    margin-block-start: var(--space-3);
}

.content :deep(li li + li) {
    margin-block-start: var(--space-2);
}

.foot {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: flex-end;
    gap: var(--space-3) var(--space-4);
}

.foot > p {
    margin-inline-end: auto;
}
</style>
