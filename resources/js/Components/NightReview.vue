<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { edit, refusal, type EditResult } from '@/api';
import Icon from '@/Components/Icon.vue';
import type { NightReview } from '@/types';

// One task the night run left ready: what the verifier found, what to look at, the commits and the
// diff, the builder's report, and the two decisions. Merge and Discard send back the branch head this
// card shows, so a branch that moved since is refused rather than acted on blind.
const props = defineProps<{ task: NightReview }>();

const busy = ref(false);
const result = ref<EditResult | null>(null);
const branch = computed(() => (props.task.branch.state === 'present' ? props.task.branch : null));
const lines = computed(() => branch.value?.files.reduce((sum, file) => sum + (file.added ?? 0) + (file.removed ?? 0), 0) ?? 0);

const tone = (result: string) => (result === 'PASS' ? 'chip-success' : result === 'FAIL' ? 'chip-danger' : 'chip-warning');
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

/** A diff line's kind, for its colour. */
function kind(line: string): string {
    if (line.startsWith('@@')) return 'hunk';
    if (line.startsWith('+++') || line.startsWith('---') || line.startsWith('diff ') || line.startsWith('index ')) return 'meta';
    if (line.startsWith('+')) return 'add';
    if (line.startsWith('-')) return 'del';
    return '';
}
</script>

<template>
    <article class="panel review stack gap-5" :aria-labelledby="`review-${task.project}-${task.task}`">
        <header class="stack gap-2">
            <p class="p-label">
                <Link :href="`/projects/${task.project}`">{{ task.project_name }}</Link>
                <span aria-hidden="true"> · </span><span class="num">{{ task.task }}</span>
            </p>
            <h3 :id="`review-${task.project}-${task.task}`" class="p-title">{{ task.title?.replace(/^\S+ \S+: /, '') ?? task.task }}</h3>
            <ul class="chips">
                <li v-if="task.verdict" class="chip" :class="tone(task.verdict.result)">
                    <span class="p-label">Verifier {{ task.verdict.result.toLowerCase() }}</span>
                </li>
                <li v-else class="chip chip-warning"><span class="p-label">No verdict</span></li>
                <li v-if="task.rounds > 1" class="chip chip-warning"><span class="p-label">{{ task.rounds }} rounds</span></li>
                <li v-if="branch" class="chip"><span class="p-label num">{{ branch.commits.length }} commits · {{ branch.files.length }} files · {{ lines }} lines</span></li>
            </ul>
        </header>

        <section v-if="task.verdict" class="stack gap-3" aria-label="Verdict">
            <p v-if="task.verdict.summary" class="p-body">{{ task.verdict.summary }}</p>
            <ul class="items">
                <li v-for="(item, i) in task.verdict.items" :key="i" class="item" :class="item.result.toLowerCase()">
                    <span class="mark p-label">{{ item.result === 'PASS' ? 'Pass' : item.result === 'FAIL' ? 'Fail' : 'Unsure' }}</span>
                    <span class="stack gap-1">
                        <span class="p-body">{{ item.item }}</span>
                        <span v-if="item.evidence" class="p-caption">{{ item.evidence }}</span>
                    </span>
                </li>
            </ul>
            <div v-if="task.verdict.scope_problems.length" class="callout danger stack gap-2">
                <p class="p-label">Outside the task's scope</p>
                <ul class="p-body"><li v-for="(problem, i) in task.verdict.scope_problems" :key="i">{{ problem }}</li></ul>
            </div>
            <div v-if="task.verdict.look_at.length" class="callout stack gap-2">
                <p class="p-label">Look at</p>
                <ul class="p-body"><li v-for="(look, i) in task.verdict.look_at" :key="i">{{ look }}</li></ul>
            </div>
        </section>

        <section v-if="branch" class="stack gap-3" aria-label="The branch">
            <p class="p-caption">
                <code>{{ branch.name }}</code> in <code class="path">{{ branch.worktree }}</code>
            </p>
            <ol class="commits">
                <li v-for="commit in branch.commits" :key="commit.sha" class="p-body"><code class="num">{{ commit.sha }}</code> {{ commit.subject }}</li>
            </ol>
            <details v-for="file in branch.files" :key="file.path" class="file">
                <summary class="p-body">
                    <code class="path">{{ file.path }}</code>
                    <span v-if="file.added !== null" class="p-caption num"><span class="plus">+{{ file.added }}</span> <span class="minus">−{{ file.removed }}</span></span>
                    <span v-else class="p-caption">binary</span>
                </summary>
                <pre class="diff"><code><span v-for="(line, i) in file.patch.split('\n')" :key="i" :class="kind(line)">{{ line }}
</span></code></pre>
            </details>
            <p v-if="branch.truncated" class="p-caption">The diff is long, so it's cut here. See the rest with <code>git diff {{ branch.base }}...{{ branch.name }}</code>.</p>
        </section>
        <p v-else class="p-caption">{{ missingBranch[task.branch.state] }}</p>

        <details v-if="task.report" class="report">
            <summary class="p-label">The builder's report</summary>
            <!-- Rendered on the server: raw HTML escaped, unsafe links dropped. -->
            <div class="content p-body" v-html="task.report" />
        </details>

        <p class="p-caption">{{ task.reason }}</p>

        <footer v-if="branch" class="stack gap-3">
            <p v-if="refusal(result)" class="p-caption msg" role="alert"><Icon name="alert" /><span>{{ refusal(result) }}</span></p>
            <div class="actions md">
                <button type="button" class="btn md btn-secondary hit" :disabled="busy" @click="act('discard')"><span class="lbl">Discard</span></button>
                <button type="button" class="btn md btn-primary hit" :disabled="busy" @click="act('merge')">
                    <Icon name="check" /><span class="lbl">Merge into {{ branch.base }}</span>
                </button>
            </div>
        </footer>
    </article>
</template>

<style scoped>
.chip-success {
    background: var(--success-bg);
    color: var(--success-fg-strong);
}

.items,
.commits {
    display: grid;
    gap: var(--space-3);
    padding: 0;
    list-style: none;
}

.item {
    display: grid;
    grid-template-columns: 5rem minmax(0, 1fr);
    gap: var(--space-3);
}

.item .mark {
    color: var(--fg-muted);
}

.item.pass .mark {
    color: var(--success-fg-strong);
}

.item.fail .mark {
    color: var(--danger-fg-strong);
}

.callout {
    padding: var(--space-4);
    border-radius: var(--radius-2);
    background: var(--bg-subtle);
}

.callout.danger {
    background: var(--danger-bg);
}

.callout ul {
    padding-inline-start: var(--space-5);
}

.file summary,
.report summary {
    display: flex;
    flex-wrap: wrap;
    gap: var(--space-3);
    align-items: baseline;
    cursor: pointer;
}

.diff {
    max-height: 32rem;
    margin-block-start: var(--space-3);
    padding: var(--space-3);
    overflow: auto;
    border-radius: var(--radius-2);
    background: var(--bg-subtle);
}

.diff span {
    display: block;
    white-space: pre;
}

.diff .add,
.plus {
    color: var(--success-fg-strong);
}

.diff .add {
    background: var(--success-bg);
}

.diff .del,
.minus {
    color: var(--danger-fg-strong);
}

.diff .del {
    background: var(--danger-bg);
}

.diff .hunk,
.diff .meta {
    color: var(--fg-muted);
}

.path {
    overflow-wrap: anywhere;
}
</style>
