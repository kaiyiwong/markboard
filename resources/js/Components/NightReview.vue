<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { edit, refusal, type EditResult } from '@/api';
import CodeText from '@/Components/CodeText.vue';
import Icon from '@/Components/Icon.vue';
import NightChanges from '@/Components/NightChanges.vue';
import NightToCheck from '@/Components/NightToCheck.vue';
import { bareTitle, plural, thingsToCheck } from '@/night';
import type { NightReview } from '@/types';

// One task the night run left ready, as a decision: what changed in a line, how much and whether it
// stayed in scope, what to check by eye (with a tick per row), then the checks and the changes folded,
// and Merge or Discard. The verifier's short fields (headline, short check names) are optional; without
// them the card shows its long text. Merge and Discard send back the branch head this card shows, so a
// branch that moved since is refused rather than acted on blind.
const props = defineProps<{ task: NightReview }>();

const busy = ref(false);
const result = ref<EditResult | null>(null);
const dialog = ref<HTMLDialogElement | null>(null);
const id = `review-${props.task.project}-${props.task.task}`;
const branch = computed(() => (props.task.branch.state === 'present' ? props.task.branch : null));
const verdict = computed(() => props.task.verdict);
const added = computed(() => branch.value?.files.reduce((sum, file) => sum + (file.added ?? 0), 0) ?? 0);
const removed = computed(() => branch.value?.files.reduce((sum, file) => sum + (file.removed ?? 0), 0) ?? 0);
const checks = computed(() => verdict.value?.items ?? []);
const passed = computed(() => checks.value.filter((item) => item.result === 'PASS').length);
const short = computed(() => checks.value.length > 0 && checks.value.every((item) => item.short !== ''));
const toCheck = computed(() => thingsToCheck(verdict.value));
const outside = computed(() => verdict.value?.scope_problems.length ?? 0);

const title = computed(() => verdict.value?.headline || bareTitle(props.task.title, props.task.task));
const status = computed(() => {
    switch (verdict.value?.result) {
        case 'PASS':
            return { word: 'Ready', tone: 'warning' };
        case 'FAIL':
            return { word: 'Verifier failed', tone: 'danger' };
        case 'INCONCLUSIVE':
            return { word: 'Verifier unsure', tone: 'warning' };
        default:
            return { word: 'No verdict', tone: 'warning' };
    }
});
/** The run in one line: the commit judged, the verifier's model and what the task's sessions would cost. */
const meta = computed(() => {
    const model = /verifier \w+ \(([^)]+)\)/.exec(props.task.reason)?.[1];
    const cost = /\$(\d+(?:\.\d+)?)/.exec(props.task.reason)?.[1];
    return [(verdict.value?.commit || props.task.commit).slice(0, 7), model, cost && `$${cost}`].filter(Boolean).join(' · ');
});

const checkLabel: Record<string, string> = { PASS: 'Pass', FAIL: 'Fail', INCONCLUSIVE: 'Unsure' };
const numbered = (text: string) => {
    const match = /^(\d+)\.\s+/.exec(text);
    return match ? { n: match[1], text: text.slice(match[0].length) } : { n: '', text };
};
const missingBranch: Record<string, string> = {
    gone: 'The branch is gone: it was merged or discarded.',
    'no-repo': "The project isn't a git repo here, so there's no branch to show.",
    'no-project': "The project isn't registered, or its folder isn't found.",
};

async function act(action: 'merge' | 'discard') {
    if (!branch.value) {
        return;
    }
    if (action === 'discard' && !window.confirm(`Discard ${props.task.task}? Its branch and worktree are deleted, and the work with them.`)) {
        return;
    }
    busy.value = true;
    result.value = await edit('POST', `/api/v1/night/branches/${props.task.project}/${props.task.task}/${action}`, null, { head: branch.value.head });
    busy.value = false;
}
</script>

<template>
    <article class="panel review" :aria-labelledby="id">
        <header class="stack gap-4">
            <div class="top">
                <p class="p-label eyebrow">
                    <Link :href="`/projects/${task.project}`" class="link-quiet project">{{ task.project_name }}</Link>
                    <code class="num">{{ task.task }}</code>
                </p>
                <p class="p-caption">Built <span class="num">{{ task.time.slice(11) }}</span></p>
            </div>
            <h3 :id="id" class="p-headline">{{ title }}</h3>
            <p class="p-body status">
                <span class="dot" :class="status.tone" aria-hidden="true" />
                <span class="strong">{{ status.word }}</span>
                <span class="muted">{{ toCheck ? `${plural(toCheck, 'thing')} to check` : 'Nothing to check by eye' }}</span>
            </p>
            <ul class="facts">
                <li v-if="branch" class="p-caption fact"><Icon name="file" />{{ plural(branch.files.length, 'file') }}</li>
                <li v-if="outside" class="p-caption fact danger"><Icon name="alert" />{{ outside }} outside scope</li>
                <li v-else-if="verdict" class="p-caption fact"><Icon name="scope" />All in scope</li>
                <li v-if="task.rounds > 1" class="p-caption fact">{{ task.rounds }} rounds</li>
                <li v-if="branch" class="p-caption fact num">
                    <span class="bar" aria-hidden="true">
                        <span v-if="added" class="add" :style="{ flexGrow: added }" />
                        <span v-if="removed" class="del" :style="{ flexGrow: removed }" />
                    </span>
                    <span><span class="plus">+{{ added }}</span> <span class="minus">−{{ removed }}</span></span>
                </li>
            </ul>
        </header>

        <div v-if="verdict?.scope_problems.length" class="callout stack gap-4">
            <p class="p-label">Outside the task's scope</p>
            <ul class="points stack gap-3">
                <li v-for="(problem, i) in verdict.scope_problems" :key="i" class="p-body"><CodeText :text="problem" /></li>
            </ul>
        </div>

        <NightToCheck
            v-if="toCheck"
            :storage-key="`markboard:night-checked:${task.project}/${task.task}/${branch?.head ?? task.commit}`"
            :confirm="verdict?.confirm ?? []"
            :look-at="verdict?.look_at ?? []"
        />

        <div class="evidence">
            <details v-if="verdict" class="disclosure" :open="passed < checks.length">
                <summary class="row">
                    <Icon name="chevron" class="turn" />
                    <span class="p-body strong">Checks</span>
                    <span v-if="checks.length" class="pips" role="img" :aria-label="`${passed} of ${checks.length} checks pass`">
                        <span v-for="(item, i) in checks" :key="i" :class="item.result.toLowerCase()" />
                    </span>
                    <span v-if="checks.length" class="p-caption">{{ passed }} of {{ checks.length }} pass</span>
                </summary>
                <div class="body stack gap-5">
                    <p v-if="verdict.summary" class="p-body muted"><CodeText :text="verdict.summary" /></p>

                    <!-- With short names: a grid of cells, each opening to the check in full. -->
                    <ul v-if="short" class="grid">
                        <li v-for="(item, i) in checks" :key="i" :class="item.result.toLowerCase()">
                            <details class="cell" :open="item.result !== 'PASS'">
                                <summary>
                                    <Icon :name="item.result === 'PASS' ? 'check' : 'alert'" class="mark" />
                                    <span class="stack gap-2">
                                        <span class="p-body strong"><span class="visually-hidden">{{ checkLabel[item.result] }}: </span>{{ item.short }}</span>
                                        <span v-if="item.says" class="p-caption"><CodeText :text="item.says" /></span>
                                    </span>
                                </summary>
                                <div class="full stack gap-2">
                                    <p class="p-caption strong-fg"><CodeText :text="item.item" /></p>
                                    <p v-if="item.evidence" class="p-caption"><CodeText :text="item.evidence" /></p>
                                </div>
                            </details>
                        </li>
                    </ul>

                    <!-- Without: one row per check, its evidence a click away. -->
                    <ol v-else class="list">
                        <li v-for="(item, i) in checks" :key="i" :class="item.result.toLowerCase()">
                            <component :is="item.evidence ? 'details' : 'div'" :open="item.evidence && item.result !== 'PASS' ? true : undefined" class="disclosure">
                                <component :is="item.evidence ? 'summary' : 'div'" class="check-row">
                                    <Icon :name="item.result === 'PASS' ? 'check' : 'alert'" class="mark" />
                                    <span class="p-caption num n">{{ numbered(item.item).n }}</span>
                                    <span class="p-body"><span class="visually-hidden">{{ checkLabel[item.result] }}: </span><CodeText :text="numbered(item.item).text" /></span>
                                    <span v-if="item.result !== 'PASS'" class="p-label verdict">{{ checkLabel[item.result] }}</span>
                                    <Icon v-if="item.evidence" name="chevron" class="turn" />
                                </component>
                                <p v-if="item.evidence" class="p-caption proof"><CodeText :text="item.evidence" /></p>
                            </component>
                        </li>
                    </ol>
                </div>
            </details>

            <NightChanges v-if="branch" :branch="branch" :task="task.task" />
        </div>

        <footer class="foot">
            <button v-if="task.report" type="button" class="btn md btn-ghost hit" @click="dialog?.showModal()">
                <Icon name="report" /><span class="lbl">Full report</span>
            </button>
            <code class="p-caption num meta" :title="task.reason">{{ meta }}</code>
            <p v-if="refusal(result)" class="p-caption msg" role="alert"><Icon name="alert" /><span>{{ refusal(result) }}</span></p>
            <p v-else-if="!branch" class="p-caption">{{ missingBranch[task.branch.state] }}</p>
            <div v-if="branch" class="actions md">
                <button type="button" class="btn md btn-ghost hit" :disabled="busy" @click="act('discard')"><span class="lbl">Discard</span></button>
                <button type="button" class="btn md btn-primary hit" :disabled="busy" @click="act('merge')">
                    <Icon name="merge" /><span class="lbl">Merge into {{ branch.base }}</span>
                </button>
            </div>
        </footer>

        <dialog v-if="task.report" ref="dialog" class="panel report" :aria-labelledby="`${id}-report`">
            <div class="stack gap-5">
                <div class="report-head">
                    <h4 :id="`${id}-report`" class="p-title">The builder's report</h4>
                    <button type="button" class="btn md btn-secondary hit" @click="dialog?.close()"><span class="lbl">Close</span></button>
                </div>
                <!-- Rendered on the server: raw HTML escaped, unsafe links dropped. -->
                <div class="content p-body" v-html="task.report" />
            </div>
        </dialog>
    </article>
</template>

<style scoped>
.review {
    display: grid;
    gap: var(--space-6);
    /* The verifier's text is full of paths and identifiers with no spaces: break them on a phone. */
    overflow-wrap: anywhere;
}

.top,
.report-head {
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

.status {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: var(--space-2) var(--space-3);
}

.strong {
    font-weight: var(--weight-medium);
}

.muted {
    color: var(--fg-muted);
}

/* A status dot: the word beside it says the same. */
.dot {
    flex: none;
    inline-size: var(--space-2);
    block-size: var(--space-2);
    border-radius: var(--radius-full);
    background: var(--warning-fg);
}

.dot.danger {
    background: var(--danger-fg);
}

.facts {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: var(--space-3) var(--space-6);
    margin: 0;
    padding: 0;
    list-style: none;
}

.fact {
    display: inline-flex;
    align-items: center;
    gap: var(--space-2);
    color: var(--fg-default);
}

.fact .icon {
    color: var(--fg-muted);
}

.fact.danger,
.fact.danger .icon {
    color: var(--danger-fg-strong);
}

/* Lines added and removed, as one bar split in proportion. */
.bar {
    display: flex;
    gap: calc(var(--space-1) / 2);
    inline-size: 6.5rem;
    block-size: calc(var(--space-1) * 1.5);
}

.bar > span {
    min-inline-size: calc(var(--space-1) / 2);
    border-radius: var(--radius-full);
}

.add {
    background: var(--success-fg);
}

.del {
    background: var(--danger-fg);
}

.plus {
    color: var(--success-fg-strong);
}

.minus {
    color: var(--danger-fg-strong);
}

.callout {
    padding: var(--space-5);
    border-radius: var(--radius-2);
    background: var(--danger-bg);
}

.callout .p-label {
    color: var(--danger-fg-strong);
}

.points {
    margin: 0;
    padding-inline-start: var(--space-5);
}

.evidence > details > .row {
    padding-block: var(--space-4);
}

.body {
    padding-block: var(--space-2) var(--space-5);
}

/* A pip per check, coloured by its result: the count beside it says the same. */
.pips {
    display: flex;
    gap: var(--space-1);
}

.pips > span {
    inline-size: var(--space-2);
    block-size: var(--space-2);
    border-radius: var(--radius-full);
    background: var(--warning-fg);
}

.pips > .pass {
    background: var(--success-fg);
}

.pips > .fail {
    background: var(--danger-fg);
}

.grid,
.list {
    margin: 0;
    padding: 0;
    list-style: none;
}

.grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(min(100%, 14rem), 1fr));
    gap: var(--space-5);
}

.cell > summary {
    display: grid;
    grid-template-columns: auto minmax(0, 1fr);
    gap: var(--space-3);
    align-items: start;
    cursor: pointer;
    list-style: none;
}

.cell > summary::-webkit-details-marker {
    display: none;
}

.full {
    margin-block-start: var(--space-3);
    padding-inline-start: calc(var(--space-4) + var(--space-3));
}

.strong-fg {
    color: var(--fg-default);
}

@media (hover: hover) {
    .cell > summary:hover .strong,
    summary.check-row:hover .p-body {
        text-decoration: underline;
        text-decoration-color: var(--fg-muted);
        text-underline-offset: 0.2em;
    }
}

/* Icons sit on the first line: the text is trimmed to its cap height (about 12px), so a 16px icon
   rises by half the difference to centre on it. */
.mark,
.check-row > .icon {
    margin-block-start: calc(var(--space-1) / -2);
}

.mark {
    color: var(--success-fg-strong);
}

.inconclusive .mark,
.inconclusive .verdict {
    color: var(--warning-fg-strong);
}

.fail .mark,
.fail .verdict {
    color: var(--danger-fg-strong);
}

.list > li + li {
    border-top: 1px solid var(--border-subtle);
}

.check-row {
    display: grid;
    grid-template-columns: auto var(--space-4) minmax(0, 1fr) auto auto;
    gap: var(--space-3);
    align-items: start;
    padding-block: var(--space-3);
}

summary.check-row {
    cursor: pointer;
}

.check-row .n {
    color: var(--fg-muted);
    text-align: end;
}

.check-row .turn {
    grid-column: 5;
}

/* The evidence, under the check's text, with a bar in the check's colour when it didn't pass. */
.proof {
    margin: 0 0 var(--space-4);
    margin-inline-start: calc(2 * var(--space-4) + 2 * var(--space-3));
    padding-inline-start: var(--space-4);
    border-inline-start: 2px solid var(--border-subtle);
}

.inconclusive .proof {
    border-color: var(--warning-fg);
}

.fail .proof {
    border-color: var(--danger-fg);
}

/* The report and the run on the left, the decision on the right. */
.foot {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: var(--space-3) var(--space-4);
}

.foot > .btn-ghost {
    margin-inline-start: calc(-1 * var(--space-3));
}

.meta {
    color: var(--fg-muted);
}

.foot > .actions {
    margin-inline-start: auto;
}

.report {
    inline-size: min(100% - 2 * var(--space-5), 48rem);
    max-block-size: min(100% - 2 * var(--space-7), 48rem);
    overflow: auto;
    color: var(--fg-default);
}

.report::backdrop {
    background: var(--scrim);
}

.content :deep(> * + *) {
    margin-block-start: var(--space-4);
}

.content :deep(:is(p, ul, ol, h1, h2, h3, h4)) {
    margin-block-end: 0;
}

.content :deep(li + li) {
    margin-block-start: var(--space-2);
}
</style>
