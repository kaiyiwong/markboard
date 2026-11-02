<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, useTemplateRef, watch } from 'vue';
import { edit, refusal, type EditResult } from '@/api';
import Icon from '@/Components/Icon.vue';
import TaskLine from '@/Components/TaskLine.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import type { SectionName, TaskGroup, TaskListItem, TaskListProject } from '@/types';

defineOptions({ layout: AppLayout });

// Every open task in active projects as one list, worked from the keyboard (docs/spec.md, Tasks).
// The selection is kept by project and task, so a reload that adds or removes tasks doesn't move
// it; once the selected task leaves the list, the one now in its place is selected.
const props = defineProps<{ projects: TaskListProject[]; tasks: TaskListItem[] }>();

const GROUPS: { key: TaskGroup; label: string; tone: string }[] = [
    { key: 'waiting-you', label: 'Waiting on you', tone: 'warning' },
    { key: 'in-progress', label: 'In progress', tone: 'accent' },
    { key: 'up-next', label: 'Up next', tone: 'empty' },
    { key: 'waiting-others', label: 'Waiting on others', tone: 'muted' },
];
const YOU = 'Kai';

const keyOf = (item: TaskListItem) => `${item.project_id} ${item.task.task_id}`;
const projectsById = computed(() => new Map(props.projects.map((project) => [project.id, project])));
const nameOf = (projectId: string) => projectsById.value.get(projectId)?.name ?? projectId;

const project = ref<string | null>(null);
const query = ref('');
const filterInput = useTemplateRef<HTMLInputElement>('filterInput');

const counts = computed(() => {
    const byProject = new Map<string, number>();
    props.tasks.forEach((item) => byProject.set(item.project_id, (byProject.get(item.project_id) ?? 0) + 1));
    return props.projects.filter((p) => byProject.has(p.id)).map((p) => ({ ...p, count: byProject.get(p.id) ?? 0 }));
});
const waitingOnYou = computed(() => props.tasks.filter((item) => item.group === 'waiting-you').length);

const shown = computed(() => {
    const words = query.value.trim().toLowerCase();
    return props.tasks.filter(
        (item) =>
            (project.value === null || item.project_id === project.value) &&
            (words === '' || `${item.task.title} ${item.task.task_id} ${nameOf(item.project_id)}`.toLowerCase().includes(words)),
    );
});
const groups = computed(() => GROUPS.map((group) => ({ ...group, items: shown.value.filter((item) => item.group === group.key) })).filter((group) => group.items.length));
const flat = computed(() => groups.value.flatMap((group) => group.items));

const selectedKey = ref<string | null>(null);
const selected = computed(() => flat.value.find((item) => keyOf(item) === selectedKey.value) ?? flat.value[0]);
watch(flat, (items, before) => {
    if (selectedKey.value !== null && !items.some((item) => keyOf(item) === selectedKey.value)) {
        const was = before.findIndex((item) => keyOf(item) === selectedKey.value);
        const next = items[Math.min(Math.max(was, 0), items.length - 1)];
        selectedKey.value = next ? keyOf(next) : null;
    }
});

function select(item: TaskListItem | undefined) {
    if (!item) {
        return;
    }
    selectedKey.value = keyOf(item);
    nextTick(() => document.querySelector('.line.is-selected')?.scrollIntoView({ block: 'nearest' }));
}

function step(by: number) {
    const index = selected.value ? flat.value.indexOf(selected.value) : -1;
    select(flat.value[Math.min(Math.max(index + by, 0), flat.value.length - 1)]);
}

function open(item: TaskListItem | undefined) {
    if (item) {
        router.visit(`/projects/${item.project_id}/tasks/${item.task.task_id}`);
    }
}

// One edit at a time: an edit waits for the page to reload after the last one, so each one sends
// the etag of the file it changes as it is now.
const busy = ref(false);
const ticking = ref<string | null>(null);
const lastTick = ref<{ project_id: string; task_id: string } | null>(null);
const status = ref<{ text: string; conflict?: string; undo?: boolean } | null>(null);

function nextProps(): Promise<void> {
    return new Promise((resolve) => {
        const stop = watch(
            () => props.tasks,
            () => {
                stop();
                resolve();
            },
        );
        setTimeout(() => {
            stop();
            resolve();
        }, 4_000);
    });
}

async function send(item: TaskListItem | { project_id: string; task_id: string }, action: string, body: object, done: string): Promise<EditResult | null> {
    const projectId = item.project_id;
    const taskId = 'task' in item ? item.task.task_id : item.task_id;
    const etag = projectsById.value.get(projectId)?.etag ?? null;
    if (etag === null) {
        status.value = { text: `${nameOf(projectId)}'s TASKS.md can't be edited here. Open the task to see why.` };
        return null;
    }
    if (busy.value) {
        return null;
    }
    busy.value = true;
    const reloaded = nextProps();
    const result = await edit('POST', `/api/v1/projects/${projectId}/tasks/${taskId}/${action}`, etag, body);
    status.value = result.ok
        ? { text: done }
        : result.status === 412
          ? { text: `${taskId}: ${nameOf(projectId)}'s TASKS.md changed on disk, so nothing was written.`, conflict: `/projects/${projectId}/tasks/${taskId}` }
          : { text: `${taskId}: ${refusal(result) ?? result.message}` };
    await reloaded;
    busy.value = false;
    return result;
}

async function tick(item: TaskListItem | undefined) {
    if (!item) {
        return;
    }
    ticking.value = keyOf(item);
    const result = await send(item, 'tick', {}, `${item.task.task_id} done in ${nameOf(item.project_id)}`);
    ticking.value = null;
    if (result?.ok) {
        lastTick.value = { project_id: item.project_id, task_id: item.task.task_id };
        status.value = { text: `${item.task.task_id} done in ${nameOf(item.project_id)}`, undo: true };
    }
}

async function undo() {
    const last = lastTick.value;
    if (!last) {
        return;
    }
    const result = await send(last, 'undo', {}, `${last.task_id} back in ${nameOf(last.project_id)}`);
    if (result?.ok) {
        lastTick.value = null;
        selectedKey.value = `${last.project_id} ${last.task_id}`;
    }
}

// Move to: a menu beside the selected task, tied to it by anchor positioning like the Project
// page's task menu. "Someone else" asks who in the menu itself.
const menu = useTemplateRef<HTMLElement>('menu');
const askingWho = ref(false);
const who = ref('');
const whoInput = useTemplateRef<HTMLInputElement>('whoInput');
const moveTargets = computed(() => {
    const section = selected.value?.task.section;
    return [
        { key: 'u', label: 'Up next', section: 'Up next' as SectionName, waiting: null, tone: 'empty' },
        { key: 'i', label: 'In progress', section: 'In progress' as SectionName, waiting: null, tone: 'accent' },
        { key: 'w', label: 'Waiting on you', section: 'Waiting on' as SectionName, waiting: YOU, tone: 'warning' },
        { key: 'o', label: 'Waiting on someone else', section: 'Waiting on' as SectionName, waiting: '', tone: 'muted' },
    ].filter((target) => target.section !== section);
});
const menuOpen = ref(false);

function openMenu() {
    if (!selected.value || !menu.value) {
        return;
    }
    askingWho.value = false;
    who.value = '';
    menu.value.showPopover();
    nextTick(() => menu.value?.querySelector<HTMLElement>('[role="menuitem"]')?.focus());
}

function closeMenu() {
    menu.value?.hidePopover();
}

function chooseTarget(target: (typeof moveTargets.value)[number]) {
    if (target.waiting === '') {
        askingWho.value = true;
        nextTick(() => whoInput.value?.focus());
        return;
    }
    closeMenu();
    move(target.section, target.waiting);
}

function move(section: SectionName, waiting: string | null) {
    const item = selected.value;
    if (!item) {
        return;
    }
    send(item, 'move', waiting === null ? { section } : { section, waiting }, `${item.task.task_id} moved to ${waiting === YOU ? 'Waiting on you' : waiting ? `Waiting on ${waiting}` : section}`);
}

function submitWho() {
    if (who.value.trim() === '') {
        return;
    }
    closeMenu();
    move('Waiting on', who.value.trim());
}

function onMenuKey(event: KeyboardEvent) {
    if (askingWho.value) {
        return;
    }
    const target = moveTargets.value.find((t) => t.key === event.key);
    if (target) {
        event.preventDefault();
        chooseTarget(target);
        return;
    }
    if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
        event.preventDefault();
        const items = [...(menu.value?.querySelectorAll<HTMLElement>('[role="menuitem"]') ?? [])];
        const at = items.indexOf(document.activeElement as HTMLElement);
        items[(at + (event.key === 'ArrowDown' ? 1 : -1) + items.length) % items.length]?.focus();
    }
}

function onKey(event: KeyboardEvent) {
    if (menuOpen.value || event.metaKey || event.ctrlKey || event.altKey) {
        return;
    }
    if (event.target instanceof HTMLElement && event.target.closest('input, textarea, select, [contenteditable]')) {
        if (event.target === filterInput.value && (event.key === 'Escape' || event.key === 'Enter')) {
            filterInput.value.blur();
        }
        return;
    }
    const keys: Record<string, () => void> = {
        j: () => step(1),
        ArrowDown: () => step(1),
        k: () => step(-1),
        ArrowUp: () => step(-1),
        Enter: () => open(selected.value),
        x: () => tick(selected.value),
        m: openMenu,
        z: undo,
        '/': () => filterInput.value?.focus(),
    };
    const action = keys[event.key];
    if (action) {
        event.preventDefault();
        action();
    }
}
onMounted(() => window.addEventListener('keydown', onKey));
onBeforeUnmount(() => window.removeEventListener('keydown', onKey));
</script>

<template>
    <Head title="Tasks" />
    <div class="stack gap-6">
        <div class="head">
            <header class="stack gap-3">
                <p class="p-label">Tasks</p>
                <h1 class="p-key">
                    <template v-if="waitingOnYou">{{ waitingOnYou }} waiting on you</template>
                    <template v-else>Nothing waiting on you</template>
                </h1>
                <p class="p-caption">{{ tasks.length }} open across {{ counts.length }} {{ counts.length === 1 ? 'project' : 'projects' }}</p>
            </header>
            <div class="controls auto">
                <div class="cgroup">
                    <label class="ctl-label" for="project">Project</label>
                    <span class="select">
                        <select id="project" v-model="project" class="input">
                            <option :value="null">All</option>
                            <option v-for="p in counts" :key="p.id" :value="p.id">{{ p.name }} ({{ p.count }})</option>
                        </select>
                        <Icon name="chevron" />
                    </span>
                </div>
                <span class="select search">
                    <Icon name="search" class="lead" />
                    <input ref="filterInput" v-model="query" class="input" type="search" aria-label="Filter tasks" placeholder="Filter" />
                </span>
            </div>
        </div>

        <section v-for="group in groups" :key="group.key" class="panel group" :aria-labelledby="`group-${group.key}`">
            <h2 :id="`group-${group.key}`" class="p-label group-head">
                <span class="dot" :class="group.tone" aria-hidden="true" />
                {{ group.label }}
                <span class="num">{{ group.items.length }}</span>
            </h2>
            <ul class="lines">
                <TaskLine
                    v-for="item in group.items"
                    :key="keyOf(item)"
                    :item="item"
                    :project-name="nameOf(item.project_id)"
                    :selected="selected !== undefined && keyOf(item) === keyOf(selected)"
                    :ticking="ticking === keyOf(item)"
                    :style="selected !== undefined && keyOf(item) === keyOf(selected) ? 'anchor-name: --selected-task' : undefined"
                    @click="select(item)"
                    @dblclick="open(item)"
                />
            </ul>
        </section>
        <p v-if="!groups.length" class="panel p-body">{{ tasks.length ? 'No tasks match.' : 'No open tasks in active projects.' }}</p>

        <div class="foot">
            <p class="p-caption" role="status">
                <template v-if="status">
                    {{ status.text }}
                    <Link v-if="status.conflict" :href="status.conflict" class="link">See the conflict</Link>
                    <template v-if="status.undo"> · <kbd>z</kbd> undo</template>
                </template>
            </p>
            <p class="p-caption keys" aria-label="Keyboard">
                <span><kbd>j</kbd><kbd>k</kbd> select</span>
                <span><kbd>↵</kbd> open</span>
                <span><kbd>x</kbd> done</span>
                <span><kbd>m</kbd> move</span>
                <span><kbd>z</kbd> undo</span>
                <span><kbd>/</kbd> filter</span>
            </p>
        </div>
    </div>

    <div
        ref="menu"
        popover
        class="menu listbox product"
        role="menu"
        aria-label="Move to"
        @toggle="menuOpen = ($event as ToggleEvent).newState === 'open'"
        @keydown="onMenuKey"
    >
        <form v-if="askingWho" class="stack gap-2 who" @submit.prevent="submitWho">
            <label class="ctl-label" for="who">Waiting on</label>
            <input id="who" ref="whoInput" v-model="who" class="input" type="text" autocomplete="off" />
        </form>
        <template v-else>
            <button v-for="target in moveTargets" :key="target.key" type="button" role="menuitem" class="option hit" @click="chooseTarget(target)">
                <span class="p-body target"><span class="dot" :class="target.tone" aria-hidden="true" />{{ target.label }}</span>
                <kbd>{{ target.key }}</kbd>
            </button>
        </template>
    </div>
</template>

<style scoped>
.head {
    display: flex;
    flex-wrap: wrap;
    gap: var(--space-5);
    align-items: end;
    justify-content: space-between;
}

.group {
    padding: 0;
    overflow: hidden;
}

/* The label sits closer to its rows than to the group above (DESIGN.md: groups, not ledgers). */
.group-head {
    display: flex;
    gap: var(--space-3);
    align-items: center;
    padding: var(--space-5) var(--space-4) var(--space-2);
}

.lines {
    margin: 0;
    padding: 0;
    list-style: none;
}

.dot {
    flex: none;
    inline-size: var(--space-2);
    block-size: var(--space-2);
    border-radius: var(--radius-full);
}

.dot.warning {
    background: var(--warning-fg);
}

.dot.accent {
    background: var(--accent-strong);
}

.dot.empty {
    border: 1px solid var(--fg-muted);
}

.dot.muted {
    background: var(--fg-muted);
}

.foot {
    display: flex;
    flex-wrap: wrap;
    gap: var(--space-4);
    justify-content: space-between;
    min-block-size: var(--control-h-md);
}

.keys {
    display: flex;
    flex-wrap: wrap;
    gap: var(--space-4);
    color: var(--fg-muted);
}

.keys span {
    display: inline-flex;
    gap: var(--space-1);
    align-items: center;
}

kbd {
    padding: 0 var(--space-1);
    border: 1px solid var(--border-default);
    border-block-end-width: 2px;
    border-radius: var(--radius-1);
    background: var(--bg-surface);
    color: var(--fg-muted);
    font-family: var(--font-mono);
}

/* Move to sits in the top layer, tied to the selected task by anchor positioning. */
.menu {
    position: fixed;
    position-anchor: --selected-task;
    position-area: block-end span-inline-end;
    position-try-fallbacks: flip-block;
    inset: auto;
    margin: var(--space-1) 0 0;
    background: var(--bg-surface);
    color: var(--fg-default);
    box-shadow: var(--shadow-overlay);
}

.menu:not(:popover-open) {
    display: none;
}

.menu .option {
    border: 0;
    background: none;
    inline-size: 100%;
    font: inherit;
    color: inherit;
    text-align: start;
}

@media (hover: hover) {
    .menu .option:hover {
        background: var(--bg-control-hover);
    }
}

.menu .option:focus-visible {
    background: var(--bg-control-hover);
}

.target {
    display: inline-flex;
    gap: var(--space-3);
    align-items: center;
}

.who {
    padding: var(--space-2);
}
</style>
