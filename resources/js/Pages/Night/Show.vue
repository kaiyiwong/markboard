<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { edit, refusal, type EditResult } from '@/api';
import Icon from '@/Components/Icon.vue';
import NightQuestion from '@/Components/NightQuestion.vue';
import NightQuickCloses from '@/Components/NightQuickCloses.vue';
import NightReview from '@/Components/NightReview.vue';
import NightTriage from '@/Components/NightTriage.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { bareTitle, plural, thingsToCheck } from '@/night';
import type { NightQuestion as Question, NightQueueItem, NightReview as Review, NightTally, NightTask, NightUnpushed, TriageParts } from '@/types';

defineOptions({ layout: AppLayout });

// The hub's night run (docs/spec.md, Night). The page leads with everything that waits on Kai as one
// list (branches to review, questions to answer, builds stopped for a decision, quick closes), each a
// link to its card below. Then the cards in that order, the rest of the night, and the triage.
const props = defineProps<{
    /** Null for the latest night. */
    date: string | null;
    /** The night shown, or null when there has been no night run. */
    night: string | null;
    nights: string[];
    tallies: Record<string, NightTally>;
    running: boolean;
    stopped: boolean;
    /** What the night's sessions would cost at API prices. Not billed: it counts toward the plan's limits. */
    usage: number;
    tasks: { ready: Review[]; blocked: NightTask[]; stuck: NightTask[]; skipped: NightTask[]; done: NightTask[] };
    questions: Question[];
    /** The chief of staff's triage note, rendered on the server. */
    triage: string | null;
    triage_parts: TriageParts | null;
    triage_etag: string | null;
    queue: NightQueueItem[];
    /** Projects this night merged into whose main isn't pushed yet. */
    unpushed: NightUnpushed[];
}>();

type Tone = 'warning' | 'info' | 'danger' | 'success' | 'neutral';

const day = (date: string) =>
    new Intl.DateTimeFormat('en', { weekday: 'long', month: 'short', day: 'numeric', timeZone: 'UTC' }).format(new Date(`${date}T00:00:00Z`));
const shortDay = (date: string) => new Intl.DateTimeFormat('en', { weekday: 'short', month: 'short', day: 'numeric', timeZone: 'UTC' }).format(new Date(`${date}T00:00:00Z`));
const monthDay = (date: string) => new Intl.DateTimeFormat('en', { month: 'short', day: 'numeric', timeZone: 'UTC' }).format(new Date(`${date}T00:00:00Z`));
const latest = computed(() => props.date === null || props.date === props.nights[0]);
// Questions, blocked builds and quick closes are about the hub now, so only the latest night shows them.
const questions = computed(() => (latest.value ? props.questions : []));
const blocked = computed(() => (latest.value ? props.tasks.blocked : []));
const closes = computed(() => (latest.value ? (props.triage_parts?.quick_closes ?? []) : []));
const unpushed = computed(() => (latest.value ? props.unpushed : []));

/** Everything that waits on Kai, one row each, in the order of the cards below. */
const agenda = computed(() => [
    ...props.tasks.ready.map((task) => {
        const items = task.verdict?.items ?? [];
        const failed = items.filter((item) => item.result === 'FAIL').length;
        const unsure = items.filter((item) => item.result === 'INCONCLUSIVE').length;
        const toCheck = thingsToCheck(task.verdict);
        return {
            key: `review-${task.project}-${task.task}`,
            tone: (failed ? 'danger' : 'warning') as Tone,
            id: task.task,
            title: task.verdict?.headline || bareTitle(task.title, task.task),
            note: failed ? `${plural(failed, 'failed check')}` : unsure ? `${plural(unsure, 'unsure check')}` : toCheck ? `${plural(toCheck, 'thing')} to check` : 'Ready to merge',
        };
    }),
    ...questions.value.map((question) => ({
        key: `answer-${question.project}-${question.task}`,
        tone: 'info' as Tone,
        id: question.task,
        title: question.headline || bareTitle(question.title, question.task),
        note: plural(Math.max(1, question.count), 'question'),
    })),
    ...blocked.value.map((task) => ({
        key: 'blocked',
        tone: 'danger' as Tone,
        id: task.task,
        title: bareTitle(task.title, task.task),
        note: 'Stopped for a decision',
    })),
    ...(closes.value.length
        ? [{ key: 'quick-closes', tone: 'info' as Tone, id: 'Triage', title: `${plural(closes.value.length, 'task')} look done or superseded`, note: plural(closes.value.length, 'quick close') }]
        : []),
    ...unpushed.value.map((project) => ({
        key: 'push',
        tone: 'neutral' as Tone,
        id: 'Push',
        title: `${project.project_name}: ${project.base} isn't pushed yet`,
        note: `${plural(project.ahead, 'commit')} ahead`,
    })),
]);

/** A pip per thing a night left: the latest night's are its agenda; earlier ones by how each task ended. */
function pips(night: string): Tone[] {
    if (night === props.nights[0]) {
        return agenda.value.map((row) => row.tone);
    }
    const tally = props.tallies[night];
    if (!tally) {
        return [];
    }
    const tones: [keyof NightTally, Tone][] = [['ready', 'warning'], ['blocked', 'warning'], ['stuck', 'danger'], ['done', 'success'], ['skipped', 'neutral']];
    return tones.flatMap(([group, tone]) => Array<Tone>(tally[group]).fill(tone));
}
const open = (night: string) => (night === props.nights[0] ? agenda.value.length : (props.tallies[night]?.ready ?? 0) + (props.tallies[night]?.blocked ?? 0));

const rest = computed(() =>
    [
        { key: 'stuck', title: 'Stuck', tasks: props.tasks.stuck },
        { key: 'skipped', title: "Didn't run", tasks: props.tasks.skipped },
        { key: 'done', title: 'Done', tasks: props.tasks.done },
    ].filter((group) => group.tasks.length),
);
const anchors = computed(() => Object.fromEntries(questions.value.map((question) => [question.task, `#answer-${question.project}-${question.task}`])));
const outcome: Record<string, string> = { stuck: 'Stuck', failed: 'Failed', merged: 'Merged', discarded: 'Discarded', skipped: "Didn't run" };

const stopBusy = ref(false);
const stopResult = ref<EditResult | null>(null);
async function toggleStop() {
    stopBusy.value = true;
    stopResult.value = await edit('PUT', '/api/v1/night/stop', null, { stop: !props.stopped });
    stopBusy.value = false;
}
</script>

<template>
    <Head :title="night ? `Night, ${night}` : 'Night'" />
    <div class="layout">
        <div class="stack gap-7 main-col">
            <section class="panel summary stack gap-6" aria-labelledby="night-key">
                <div class="stack gap-3">
                    <p class="p-label">{{ night ? `Night of ${day(night)}` : 'Night run' }}</p>
                    <h1 id="night-key" class="p-key">
                        <template v-if="!night">No night run yet</template>
                        <template v-else-if="agenda.length">{{ agenda.length }} waiting on you</template>
                        <template v-else>Nothing waiting on you</template>
                    </h1>
                    <p v-if="!night" class="p-caption">The hub's night run writes what it did to its <code>orders/</code> folder. Once it has run, its tasks show here.</p>
                </div>
                <ul v-if="agenda.length" class="agenda">
                    <li v-for="row in agenda" :key="`${row.key}-${row.id}`">
                        <a :href="`#${row.key}`" class="agenda-row">
                            <span class="dot" :class="row.tone" aria-hidden="true" />
                            <code class="p-caption num id">{{ row.id }}</code>
                            <span class="p-body title">{{ row.title }}</span>
                            <span class="p-caption note" :class="row.tone">{{ row.note }}</span>
                        </a>
                    </li>
                </ul>
            </section>

            <section v-if="tasks.ready.length" class="stack gap-4" aria-labelledby="ready">
                <h2 id="ready" class="p-title sec-head">Review and merge <span class="p-caption num">{{ tasks.ready.length }}</span></h2>
                <NightReview v-for="task in tasks.ready" :key="`${task.project}-${task.task}`" :task="task" />
            </section>

            <section v-if="questions.length || blocked.length" id="questions" class="stack gap-4" aria-labelledby="answer">
                <h2 id="answer" class="p-title sec-head">Questions for you <span class="p-caption num">{{ questions.length + blocked.length }}</span></h2>
                <NightQuestion v-for="question in questions" :key="`${question.project}-${question.task}`" :question="question" />
                <div v-if="blocked.length" id="blocked" class="panel stack gap-4">
                    <h3 class="p-label">The builder stopped and needs a decision</h3>
                    <ul class="ledger rows">
                        <li v-for="task in blocked" :key="`${task.project}-${task.task}`" class="row">
                            <p class="p-body line">
                                <Link :href="`/projects/${task.project}`" class="link-quiet">{{ task.project_name }}</Link>
                                <code class="p-caption num id">{{ task.task }}</code>
                                <span>{{ bareTitle(task.title, task.task) }}</span>
                            </p>
                            <p class="p-caption">{{ task.reason }}</p>
                        </li>
                    </ul>
                </div>
            </section>

            <section v-if="closes.length && night && triage_etag" id="quick-closes" class="stack gap-4" aria-labelledby="closes">
                <h2 id="closes" class="p-title sec-head">Quick closes <span class="p-caption num">{{ closes.length }} to decide</span></h2>
                <NightQuickCloses :closes="closes" :date="night" :etag="triage_etag" />
            </section>

            <section v-if="unpushed.length" id="push" class="panel stack gap-4" aria-labelledby="to-push">
                <div class="stack gap-2">
                    <h2 id="to-push" class="p-title">Not pushed yet</h2>
                    <p class="p-caption">Merge stays on this machine. Push when you're ready; the count is as of your last fetch.</p>
                </div>
                <ul class="ledger rows">
                    <li v-for="project in unpushed" :key="project.project" class="row">
                        <p class="p-body line">
                            <Link :href="`/projects/${project.project}`" class="link-quiet">{{ project.project_name }}</Link>
                            <span>{{ project.base }} is {{ plural(project.ahead, 'commit') }} ahead of {{ project.remote }}</span>
                        </p>
                        <code class="p-caption num command">{{ project.command }}</code>
                    </li>
                </ul>
            </section>

            <section v-if="rest.length" class="panel stack gap-5" aria-labelledby="rest">
                <h2 id="rest" class="p-title">The rest of the night</h2>
                <div v-for="group in rest" :key="group.key" class="stack gap-3">
                    <h3 class="p-label">{{ group.title }} <span class="num">{{ group.tasks.length }}</span></h3>
                    <ul class="ledger rows">
                        <li v-for="task in group.tasks" :key="`${task.project}-${task.task}`" class="row">
                            <p class="p-body line">
                                <Link :href="`/projects/${task.project}`" class="link-quiet">{{ task.project_name }}</Link>
                                <code class="p-caption num id">{{ task.task }}</code>
                                <span>{{ bareTitle(task.title, task.task) }}</span>
                                <span v-if="group.key === 'stuck'" class="chip chip-danger"><span class="p-label">{{ outcome[task.outcome] }}</span></span>
                                <span v-else-if="group.key === 'done'" class="p-caption">{{ outcome[task.outcome] }}</span>
                            </p>
                            <p class="p-caption">{{ task.reason }}</p>
                        </li>
                    </ul>
                </div>
            </section>

            <section v-if="triage_parts && night" class="stack gap-4" aria-labelledby="triage">
                <h2 id="triage" class="p-title sec-head">Triage <span class="p-caption">{{ monthDay(night) }}</span></h2>
                <NightTriage :parts="triage_parts" :note="triage" :anchors="anchors" />
            </section>
            <details v-else-if="triage" class="panel disclosure triage-note">
                <summary class="p-title"><Icon name="chevron" class="turn" />The chief of staff's triage</summary>
                <!-- Rendered on the server: raw HTML escaped, unsafe links dropped. -->
                <div class="content p-body" v-html="triage" />
            </details>
        </div>

        <aside class="stack gap-4 side">
            <section class="panel stack gap-5 side-panel" aria-labelledby="next-run">
                <div class="side-head">
                    <h2 id="next-run" class="p-title">{{ stopped ? 'Next run' : 'Next run, tonight' }}</h2>
                    <span v-if="running" class="chip chip-warning"><span class="p-label">Running now</span></span>
                    <span v-else-if="stopped" class="chip chip-danger"><span class="p-label">Skipped</span></span>
                </div>
                <ol v-if="queue.length" class="plain stack gap-4">
                    <li v-for="item in queue" :key="`${item.project}-${item.task}`" class="stack gap-2">
                        <p class="p-body">{{ item.project_name }} <code class="p-caption num">{{ item.task }}</code></p>
                        <p class="p-caption">{{ item.model }} · {{ item.effort }}{{ item.last ? ` · last run ${item.last}` : '' }}</p>
                    </li>
                </ol>
                <p v-else class="p-caption">The queue is empty. The chief of staff fills it at the start of the run.</p>
                <p v-if="refusal(stopResult)" class="p-caption msg" role="alert"><Icon name="alert" /><span>{{ refusal(stopResult) }}</span></p>
                <div>
                    <button type="button" class="btn md hit skip-run" :class="stopped ? 'btn-primary' : 'btn-ghost'" :disabled="stopBusy" @click="toggleStop">
                        <span class="lbl">{{ stopped ? 'Run tonight after all' : 'Skip tonight' }}</span>
                    </button>
                </div>
            </section>

            <nav class="panel stack gap-4 side-panel" aria-labelledby="past-nights">
                <h2 id="past-nights" class="p-title">Nights</h2>
                <ul v-if="nights.length" class="past-list">
                    <li v-for="(past, i) in nights" :key="past">
                        <Link :href="i === 0 ? '/night' : `/night/${past}`" class="past" :aria-current="past === night ? 'page' : undefined">
                            <span class="p-body">{{ shortDay(past) }}</span>
                            <span class="pips" aria-hidden="true"><span v-for="(tone, p) in pips(past).slice(0, 8)" :key="p" :class="tone" /></span>
                            <span class="p-caption num">{{ open(past) }} open</span>
                        </Link>
                    </li>
                </ul>
                <p v-else class="p-caption">No nights yet.</p>
                <p v-if="night && usage" class="p-caption">
                    {{ shortDay(night) }}: worth <span class="num">${{ usage.toFixed(2) }}</span> at API prices. Not billed; it counts toward the plan's limits.
                </p>
            </nav>
        </aside>
    </div>
</template>

<style scoped>
/* The night first, the queue and past nights beside it from 1024, under it on smaller screens. */
.layout {
    display: grid;
    gap: var(--space-6);
}

@media (min-width: 1024px) {
    .layout {
        grid-template-columns: minmax(0, 1fr) minmax(14rem, 20rem);
        align-items: start;
    }
}

.plain {
    margin: 0;
    padding: 0;
    list-style: none;
}

/* The agenda: one row per thing that waits, a link to its card. */
.agenda {
    display: grid;
    margin: 0 calc(-1 * var(--space-3));
    padding: 0;
    list-style: none;
}

.agenda-row {
    display: grid;
    grid-template-columns: var(--space-2) 6rem minmax(0, 1fr) auto;
    gap: var(--space-4);
    align-items: center;
    padding: var(--space-3);
    border-radius: var(--control-radius);
    color: var(--fg-default);
    text-decoration: none;
}

@media (hover: hover) {
    .agenda-row:hover {
        background: var(--bg-control-hover);
    }
}

.agenda-row .id {
    color: var(--fg-muted);
    overflow-wrap: anywhere;
}

.note {
    text-align: end;
}

/* A push command is one long path with no spaces: break it on a phone. */
.command {
    overflow-wrap: anywhere;
}

.note.warning {
    color: var(--warning-fg-strong);
}

.note.info {
    color: var(--info-fg-strong);
}

.note.danger {
    color: var(--danger-fg-strong);
}

/* Status dots and pips: the words beside them say the same. */
.dot,
.pips > span {
    flex: none;
    inline-size: var(--space-2);
    block-size: var(--space-2);
    border-radius: var(--radius-full);
    background: var(--fg-muted);
}

:is(.dot, .pips > span).warning {
    background: var(--warning-fg);
}

:is(.dot, .pips > span).info {
    background: var(--info-fg);
}

:is(.dot, .pips > span).danger {
    background: var(--danger-fg);
}

:is(.dot, .pips > span).success {
    background: var(--success-fg);
}

@media (max-width: 599px) {
    .agenda-row {
        grid-template-columns: var(--space-2) minmax(0, 1fr);
        row-gap: var(--space-2);
    }

    .agenda-row .id,
    .agenda-row .note {
        grid-column: 2;
        text-align: start;
    }

    .agenda-row .id {
        grid-row: 2;
    }

    /* The dot sits on the title's first line, not the middle of a wrapped title. */
    .agenda-row .dot {
        align-self: start;
        margin-block-start: calc(var(--space-1) / 2);
    }
}

.sec-head {
    display: flex;
    align-items: baseline;
    gap: var(--space-2);
}

.sec-head > .p-caption {
    font-weight: var(--weight-regular);
}

/* A task as a row: project, ID and title on one line, the reason under it. */
.rows > .row {
    display: grid;
    gap: var(--space-2);
    padding-block: var(--space-4);
}

.rows > .row:first-child {
    border-top: 0;
    padding-block-start: 0;
}

.rows > .row:last-child {
    border-bottom: 0;
    padding-block-end: 0;
}

.line {
    display: flex;
    flex-wrap: wrap;
    align-items: baseline;
    gap: var(--space-1) var(--space-2);
}

.line .chip {
    align-self: center;
}

.id {
    color: var(--fg-muted);
}

.side-panel {
    padding: var(--space-5);
}

.side-head {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: var(--space-2);
}

.side-panel code {
    color: var(--fg-muted);
}

.skip-run.btn-ghost {
    margin-inline-start: calc(-1 * var(--space-3));
    color: var(--accent-fg);
}

/* Past nights: the day, a pip per thing it left, and how many are open. */
.past-list {
    display: grid;
    gap: var(--space-1);
    margin: 0 calc(-1 * var(--space-3));
    padding: 0;
    list-style: none;
}

.past {
    position: relative;
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto auto;
    gap: var(--space-3);
    align-items: center;
    padding: var(--space-2) var(--space-3);
    border-radius: var(--control-radius);
    color: var(--fg-default);
    text-decoration: none;
}

.pips {
    display: flex;
    gap: var(--space-1);
}

/* The night on screen: the accent marks it, with a 2px bar that reaches 3:1 (SPEC 3.5 rule 2). */
.past[aria-current='page'] .p-body {
    color: var(--accent-fg-strong);
    font-weight: var(--weight-medium);
}

.past[aria-current='page']::before {
    content: '';
    position: absolute;
    inset-block: var(--space-2);
    inset-inline-start: 0;
    inline-size: 2px;
    background: var(--accent-strong);
}

@media (hover: hover) {
    .past:hover {
        background: var(--bg-control-hover);
    }
}

.triage-note .content {
    margin-block-start: var(--space-5);
}

.content :deep(> * + *) {
    margin-block-start: var(--space-4);
}

.content :deep(:is(p, ul, ol, h1, h2, h3)) {
    margin-block-end: 0;
}

.content :deep(li + li) {
    margin-block-start: var(--space-2);
}
</style>
