<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { edit, refusal, type EditResult } from '@/api';
import Icon from '@/Components/Icon.vue';
import NightQuestion from '@/Components/NightQuestion.vue';
import NightReview from '@/Components/NightReview.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import type { NightQuestion as Question, NightQueueItem, NightReview as Review, NightTask } from '@/types';

defineOptions({ layout: AppLayout });

// The hub's night run (docs/spec.md, Night): what's ready to review, what waits on Kai, what got
// stuck, and what didn't run. The page leads with the number that needs Kai: tasks ready for review.
const props = defineProps<{
    /** Null for the latest night. */
    date: string | null;
    /** The night shown, or null when there has been no night run. */
    night: string | null;
    nights: string[];
    running: boolean;
    stopped: boolean;
    /** What the night's sessions would cost at API prices. Not billed: it counts toward the plan's limits. */
    usage: number;
    tasks: { ready: Review[]; blocked: NightTask[]; stuck: NightTask[]; skipped: NightTask[]; done: NightTask[] };
    questions: Question[];
    /** The chief of staff's triage note, rendered on the server. */
    triage: string | null;
    queue: NightQueueItem[];
}>();

const day = (date: string) =>
    new Intl.DateTimeFormat('en', { weekday: 'long', month: 'short', day: 'numeric', timeZone: 'UTC' }).format(new Date(`${date}T00:00:00Z`));
const shortDay = (date: string) => new Intl.DateTimeFormat('en', { weekday: 'short', month: 'short', day: 'numeric', timeZone: 'UTC' }).format(new Date(`${date}T00:00:00Z`));
const latest = computed(() => props.date === null || props.date === props.nights[0]);
const waiting = computed(() => props.questions.length + props.tasks.blocked.length);
const label = (task: NightTask) => task.title?.replace(/^\S+ \S+: /, '') ?? task.task;
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
        <div class="stack gap-6 main-col">
            <div class="stack gap-3">
                <p class="p-label">{{ night ? `Night of ${day(night)}` : 'Night run' }}</p>
                <h1 class="p-key">
                    <template v-if="night">{{ tasks.ready.length }} ready for review</template>
                    <template v-else>No night run yet</template>
                </h1>
                <ul class="chips">
                    <li v-if="running" class="chip chip-warning"><span class="p-label">Running now</span></li>
                    <li v-if="waiting" class="chip chip-warning"><span class="p-label num">{{ waiting }} waiting on you</span></li>
                    <li v-if="tasks.stuck.length" class="chip chip-danger"><span class="p-label num">{{ tasks.stuck.length }} stuck</span></li>
                    <li v-if="stopped" class="chip chip-danger"><span class="p-label">Next run skipped</span></li>
                </ul>
                <p v-if="night && usage" class="p-caption">
                    Plan usage this night: worth <span class="num">${{ usage.toFixed(2) }}</span> at API prices. Not billed; it counts toward the plan's limits.
                </p>
            </div>

            <div v-if="!night" class="panel stack gap-3">
                <p class="p-body">The hub's night run writes what it did to its <code>orders/</code> folder. Once it has run, its tasks show here.</p>
            </div>

            <section v-if="tasks.ready.length" class="stack gap-4" aria-labelledby="ready">
                <h2 id="ready" class="p-headline">Ready for review</h2>
                <NightReview v-for="task in tasks.ready" :key="`${task.project}-${task.task}`" :task="task" />
            </section>

            <section v-if="latest && waiting" class="stack gap-4" aria-labelledby="waiting">
                <h2 id="waiting" class="p-headline">Waiting on you</h2>
                <NightQuestion v-for="question in questions" :key="`${question.project}-${question.task}`" :question="question" />
                <div v-if="tasks.blocked.length" class="panel stack gap-3">
                    <p class="p-label">The builder stopped and needs a decision</p>
                    <ul class="rows">
                        <li v-for="task in tasks.blocked" :key="`${task.project}-${task.task}`" class="stack gap-1">
                            <span class="p-body"><Link :href="`/projects/${task.project}`">{{ task.project_name }}</Link> <span class="num">{{ task.task }}</span> {{ label(task) }}</span>
                            <span class="p-caption">{{ task.reason }}</span>
                        </li>
                    </ul>
                </div>
            </section>

            <section v-for="group in (['stuck', 'skipped', 'done'] as const)" v-show="tasks[group].length" :key="group" class="panel stack gap-3" :aria-labelledby="`group-${group}`">
                <h2 :id="`group-${group}`" class="p-title">{{ group === 'stuck' ? 'Stuck' : group === 'skipped' ? "Didn't run" : 'Done this night' }}</h2>
                <ul class="rows">
                    <li v-for="task in tasks[group]" :key="`${task.project}-${task.task}`" class="stack gap-1">
                        <span class="p-body">
                            <Link :href="`/projects/${task.project}`">{{ task.project_name }}</Link> <span class="num">{{ task.task }}</span> {{ label(task) }}
                            <span class="chip" :class="{ 'chip-danger': group === 'stuck' }"><span class="p-label">{{ outcome[task.outcome] }}</span></span>
                        </span>
                        <span class="p-caption">{{ task.reason }}</span>
                    </li>
                </ul>
            </section>

            <details v-if="triage" class="panel">
                <summary class="p-title">The chief of staff's triage</summary>
                <!-- Rendered on the server: raw HTML escaped, unsafe links dropped. -->
                <div class="content p-body triage" v-html="triage" />
            </details>
        </div>

        <aside class="stack gap-4 side">
            <section class="panel stack gap-3" aria-labelledby="next-run">
                <h2 id="next-run" class="p-label">Next run</h2>
                <ol v-if="queue.length" class="rows">
                    <li v-for="item in queue" :key="`${item.project}-${item.task}`" class="stack gap-1">
                        <span class="p-body">{{ item.project_name }} <span class="num">{{ item.task }}</span></span>
                        <span class="p-caption">{{ item.model }}, {{ item.effort }} effort{{ item.last ? `, last: ${item.last}` : '' }}</span>
                    </li>
                </ol>
                <p v-else class="p-caption">The queue is empty. The chief of staff fills it at the start of the run.</p>
                <p v-if="refusal(stopResult)" class="p-caption msg" role="alert"><Icon name="alert" /><span>{{ refusal(stopResult) }}</span></p>
                <button type="button" class="btn md hit" :class="stopped ? 'btn-primary' : 'btn-secondary'" :disabled="stopBusy" @click="toggleStop">
                    <span class="lbl">{{ stopped ? 'Run tonight after all' : 'Skip the next run' }}</span>
                </button>
            </section>

            <nav class="panel stack gap-3" aria-labelledby="past-nights">
                <h2 id="past-nights" class="p-label">Nights</h2>
                <ul v-if="nights.length" class="past-list">
                    <li v-for="(past, i) in nights" :key="past">
                        <Link :href="i === 0 ? '/night' : `/night/${past}`" class="p-body past" :aria-current="past === night ? 'page' : undefined">{{ shortDay(past) }}</Link>
                    </li>
                </ul>
                <p v-else class="p-caption">No nights yet.</p>
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
        grid-template-columns: minmax(0, 1fr) minmax(12rem, 18rem);
        align-items: start;
    }
}

.rows {
    display: grid;
    gap: var(--space-3);
    margin: 0;
    padding: 0;
    list-style: none;
}

/* Past nights, as the Brief page lists past briefs. */
.past-list {
    display: grid;
    gap: var(--space-1);
    margin: 0;
    padding: 0;
    list-style: none;
}

.past {
    display: block;
    padding: var(--space-2) var(--space-3);
    border-radius: var(--control-radius);
    color: var(--fg-default);
    text-decoration: none;
}

/* The night on screen: the accent marks it, with a 2px bar that reaches 3:1 (SPEC 3.5 rule 2). */
.past[aria-current='page'] {
    position: relative;
    background: var(--accent-bg);
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
    .past:not([aria-current='page']):hover {
        background: var(--bg-control-hover);
    }
}

details > summary {
    cursor: pointer;
}

.triage {
    margin-block-start: var(--space-4);
}
</style>
