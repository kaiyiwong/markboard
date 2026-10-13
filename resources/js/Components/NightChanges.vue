<script setup lang="ts">
import { computed } from 'vue';
import Icon from '@/Components/Icon.vue';
import type { NightBranch, NightFile } from '@/types';

type Branch = Extract<NightBranch, { state: 'present' }>;

// The branch's changes, by area: files grouped by their first two folders, each with a bar of lines
// added and removed (scaled to the largest, so size reads at a glance) and its diff a click away.
// Then the commits, without the task ID each one starts with.
const props = defineProps<{ branch: Branch; task: string }>();

interface Row extends NightFile {
    name: string;
    total: number;
    tag: '' | 'new' | 'deleted';
}

const lines = (file: NightFile) => (file.added ?? 0) + (file.removed ?? 0);

const areas = computed(() => {
    const byArea = new Map<string, Row[]>();
    for (const file of props.branch.files) {
        const folders = file.path.split('/').slice(0, -1);
        const area = folders.slice(0, 2).join('/') || '.';
        const tag = /^new file mode/m.test(file.patch) ? 'new' : /^deleted file mode/m.test(file.patch) ? 'deleted' : '';
        const name = area === '.' ? file.path : file.path.slice(area.length + 1);
        byArea.set(area, [...(byArea.get(area) ?? []), { ...file, name, total: lines(file), tag }]);
    }
    return [...byArea]
        .map(([name, files]) => ({
            name,
            files: files.sort((a, b) => b.total - a.total),
            added: files.reduce((sum, file) => sum + (file.added ?? 0), 0),
            removed: files.reduce((sum, file) => sum + (file.removed ?? 0), 0),
        }))
        .sort((a, b) => b.added + b.removed - (a.added + a.removed));
});
const areaScale = computed(() => Math.max(1, ...areas.value.map((area) => area.added + area.removed)));
const fileScale = computed(() => Math.max(1, ...props.branch.files.map(lines)));

const plural = (count: number, word: string) => `${count} ${word}${count === 1 ? '' : 's'}`;
/** A bar's width: its share of the largest, never so thin it disappears. */
const width = (total: number, scale: number) => `${Math.max(4, Math.round((total / scale) * 100))}%`;
/** "0048: the refusals move…" reads as "the refusals move…": the card already names the task. */
const subject = (text: string) => {
    const id = /^(\S+): /.exec(text)?.[1];
    return id && props.task.startsWith(id) ? text.slice(id.length + 2) : text;
};

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
    <details class="disclosure changes">
        <summary class="top">
            <Icon name="chevron" class="turn" />
            <span class="p-body strong">Changes</span>
            <span class="p-caption">{{ plural(branch.commits.length, 'commit') }} in {{ plural(areas.length, 'area') }}</span>
        </summary>
        <div class="body stack gap-6">
            <ul class="areas">
                <li v-for="(area, i) in areas" :key="area.name">
                    <details class="disclosure" :open="i === 0">
                        <summary class="crow">
                            <Icon name="chevron" class="turn" />
                            <code class="p-caption name">{{ area.name }}</code>
                            <span class="p-caption meta">{{ plural(area.files.length, 'file') }}</span>
                            <span class="track" aria-hidden="true">
                                <span class="bar" :style="{ inlineSize: width(area.added + area.removed, areaScale) }">
                                    <span v-if="area.added" class="add" :style="{ flexGrow: area.added }" />
                                    <span v-if="area.removed" class="del" :style="{ flexGrow: area.removed }" />
                                </span>
                            </span>
                            <span class="p-caption num nums"><span class="plus">+{{ area.added }}</span> <span class="minus">−{{ area.removed }}</span></span>
                        </summary>
                        <ul class="files">
                            <li v-for="file in area.files" :key="file.path">
                                <details class="disclosure">
                                    <summary class="crow">
                                        <span />
                                        <code class="p-caption name file">{{ file.name }}</code>
                                        <span class="p-caption meta" :class="file.tag">{{ file.tag }}</span>
                                        <span class="track" aria-hidden="true">
                                            <span v-if="file.added !== null" class="bar" :style="{ inlineSize: width(file.total, fileScale) }">
                                                <span v-if="file.added" class="add" :style="{ flexGrow: file.added }" />
                                                <span v-if="file.removed" class="del" :style="{ flexGrow: file.removed }" />
                                            </span>
                                        </span>
                                        <span v-if="file.added !== null" class="p-caption num nums"><span class="plus">+{{ file.added }}</span> <span class="minus">−{{ file.removed }}</span></span>
                                        <span v-else class="p-caption nums">binary</span>
                                    </summary>
                                    <pre class="diff"><code><span v-for="(line, l) in file.patch.split('\n')" :key="l" :class="kind(line)">{{ line }}
</span></code></pre>
                                </details>
                            </li>
                        </ul>
                    </details>
                </li>
            </ul>
            <p v-if="branch.truncated" class="p-caption">The diff is long, so it's cut here. See the rest with <code>git diff {{ branch.base }}...{{ branch.name }}</code>.</p>
            <ol class="commits">
                <li v-for="commit in branch.commits" :key="commit.sha">
                    <code class="p-caption num sha">{{ commit.sha }}</code>
                    <span class="p-body">{{ subject(commit.subject) }}</span>
                </li>
            </ol>
            <p class="p-caption path"><code>{{ branch.name }}</code> in <code>{{ branch.worktree }}</code></p>
        </div>
    </details>
</template>

<style scoped>
.top {
    padding-block: var(--space-4);
}

.strong {
    font-weight: var(--weight-medium);
}

.body {
    padding-block: var(--space-2) var(--space-5);
}

.areas,
.files,
.commits {
    display: grid;
    margin: 0;
    padding: 0;
    list-style: none;
}

.areas {
    gap: var(--space-2);
}

/* One row for an area or a file, in shared columns so names, bars and counts line up. */
.crow {
    display: grid;
    grid-template-columns: var(--space-4) minmax(0, 1fr) 4rem 8rem 7rem;
    gap: var(--space-4);
    align-items: center;
    padding-block: var(--space-3);
}

.name {
    color: var(--fg-default);
    overflow-wrap: anywhere;
}

.file {
    padding-inline-start: var(--space-5);
}

.meta {
    color: var(--fg-muted);
}

.meta.new {
    color: var(--success-fg-strong);
}

.meta.deleted {
    color: var(--danger-fg-strong);
}

.track {
    display: flex;
}

.bar {
    display: flex;
    gap: calc(var(--space-1) / 2);
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

.diff {
    max-height: 32rem;
    margin: 0 0 var(--space-3);
    padding: var(--space-3);
    overflow: auto;
    border-radius: var(--radius-2);
    background: var(--bg-control);
}

.diff span {
    display: block;
    white-space: pre;
}

.diff .add {
    background: var(--success-bg);
    color: var(--success-fg-strong);
}

.diff .del {
    background: var(--danger-bg);
    color: var(--danger-fg-strong);
}

.diff .hunk,
.diff .meta {
    color: var(--fg-muted);
}

.commits {
    gap: var(--space-4);
}

.commits > li {
    display: grid;
    grid-template-columns: auto minmax(0, 1fr);
    gap: var(--space-4);
    align-items: baseline;
}

.sha {
    color: var(--fg-muted);
}

.path {
    overflow-wrap: anywhere;
}

/* On a phone the bars go: the counts carry the same news. */
@media (max-width: 599px) {
    .crow {
        grid-template-columns: var(--space-4) minmax(0, 1fr) auto auto;
        gap: var(--space-3);
    }

    .track {
        display: none;
    }

    .file {
        padding-inline-start: 0;
    }
}
</style>
