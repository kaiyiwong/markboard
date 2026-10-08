<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import Icon from '@/Components/Icon.vue';
import Progress from '@/Components/Progress.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import type { FormatError, ProjectSummary, SearchResult } from '@/types';

defineOptions({ layout: AppLayout });

const props = defineProps<{
    projects: ProjectSummary[];
    registryErrors: FormatError[];
    hubSyncErrors: string[];
    filters: { category: string | null; status: string | null; q: string };
    categories: string[];
    statuses: string[];
    results: SearchResult[] | null;
}>();

const groups = computed(() => ({
    active: props.projects.filter((project) => project.status === 'active'),
    paused: props.projects.filter((project) => project.status === 'paused'),
    done: props.projects.filter((project) => project.status === 'done'),
}));

// Filters and search live in the query string, so every view has its own URL.
const query = ref(props.filters.q);
function visit(changes: Partial<typeof props.filters>) {
    const params = { ...props.filters, ...changes };
    router.get('/', Object.fromEntries(Object.entries(params).filter(([, value]) => value)), {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}
function onSelect(key: 'category' | 'status', event: Event) {
    visit({ [key]: (event.target as HTMLSelectElement).value || null });
}
function clearSearch() {
    query.value = '';
    visit({ q: '' });
}

const OPEN = ['Up next', 'In progress', 'Waiting on'] as const;
const open = (project: ProjectSummary) => OPEN.reduce((sum, section) => sum + project.counts[section], 0);
const total = (project: ProjectSummary) => open(project) + project.counts.Done;
const hasTasks = (project: ProjectSummary) => project.folder_found && project.tasks_file !== 'missing';

// The page's key element: open tasks across the active projects, then what needs attention first.
const summary = computed(() => {
    const active = groups.value.active.filter(hasTasks);
    const sum = (section: (typeof OPEN)[number] | 'Done') => active.reduce((n, project) => n + project.counts[section], 0);
    return {
        open: active.reduce((n, project) => n + open(project), 0),
        projects: active.length,
        stats: (['In progress', 'Waiting on', 'Done'] as const).map((section) => ({ section, count: sum(section) })),
        attention: groups.value.active.filter((project) => project.due !== null),
    };
});
</script>

<template>
    <Head title="Projects" />
    <div class="stack gap-7">
        <div class="head">
            <h1 class="p-headline">Projects</h1>
            <div class="controls auto">
                <form class="cgroup" role="search" @submit.prevent="visit({ q: query.trim() })">
                    <span class="select search">
                        <Icon name="search" class="lead" />
                        <input v-model="query" class="input" type="search" name="q" aria-label="Search tasks" placeholder="Search tasks" />
                    </span>
                </form>
                <div class="cgroup">
                    <label class="ctl-label" for="category">Category</label>
                    <span class="select">
                        <select id="category" class="input" :value="filters.category ?? ''" @change="onSelect('category', $event)">
                            <option value="">All</option>
                            <option v-for="category in categories" :key="category" :value="category">{{ category }}</option>
                        </select>
                        <Icon name="chevron" />
                    </span>
                </div>
                <div class="cgroup">
                    <label class="ctl-label" for="status">Status</label>
                    <span class="select">
                        <select id="status" class="input" :value="filters.status ?? ''" @change="onSelect('status', $event)">
                            <option value="">All</option>
                            <option v-for="status in statuses" :key="status" :value="status">{{ status }}</option>
                        </select>
                        <Icon name="chevron" />
                    </span>
                </div>
            </div>
        </div>

        <div v-for="(error, i) in hubSyncErrors" :key="i" class="panel" role="status">
            <p class="p-body"><strong>A hub file failed to sync:</strong> {{ error }}. The next request tries again.</p>
        </div>

        <section v-if="results" class="panel stack gap-4" aria-labelledby="results" aria-live="polite">
            <h2 id="results" class="p-title">
                {{ results.length ? `Tasks matching “${filters.q}”` : `No tasks match “${filters.q}”` }}
            </h2>
            <ul v-if="results.length" class="ledger">
                <li v-for="result in results" :key="`${result.project_id}-${result.task_id}`" class="result">
                    <code class="id">{{ result.task_id }}</code>
                    <Link :href="`/projects/${result.project_id}/tasks/${result.task_id}`" class="p-body link-quiet">{{ result.title }}</Link>
                    <span class="p-caption">{{ result.project_name }} · {{ result.section }}</span>
                </li>
            </ul>
            <div class="row">
                <button type="button" class="btn md btn-secondary hit" @click="clearSearch"><span class="lbl">Clear search</span></button>
            </div>
        </section>

        <section v-else-if="summary.projects" class="panel summary" aria-labelledby="summary-label">
            <div class="stack gap-3">
                <p id="summary-label" class="p-label">Open tasks</p>
                <p class="p-key">{{ summary.open }}</p>
                <p class="p-caption">across {{ summary.projects }} active {{ summary.projects === 1 ? 'project' : 'projects' }}</p>
            </div>
            <dl class="stats">
                <div v-for="stat in summary.stats" :key="stat.section" class="stat">
                    <dt class="p-label">{{ stat.section }}</dt>
                    <dd class="p-title num">{{ stat.count }}</dd>
                </div>
            </dl>
            <div v-if="summary.attention.length" class="stack gap-3 attention">
                <p class="p-label">Needs attention</p>
                <ul class="stack gap-2 plain">
                    <li v-for="project in summary.attention" :key="project.id" class="flag">
                        <span class="dot" :class="project.due === 'overdue' ? 'is-danger' : 'is-warning'" aria-hidden="true" />
                        <Link :href="`/projects/${project.id}`" class="p-body link-quiet">{{ project.name }}</Link>
                        <span class="p-caption">{{ project.due === 'overdue' ? 'a task is overdue' : 'a task is due within 7 days' }}</span>
                    </li>
                </ul>
            </div>
        </section>

        <p v-if="!projects.length" class="p-body">No projects match these filters.</p>

        <template v-for="(list, status) in groups" :key="status">
            <component
                :is="status === 'done' ? 'details' : 'section'"
                v-if="list.length"
                class="stack gap-4 group"
                :aria-labelledby="status === 'done' ? undefined : `group-${status}`"
            >
                <component :is="status === 'done' ? 'summary' : 'h2'" :id="`group-${status}`" class="p-title">
                    {{ status === 'active' ? 'Active' : status === 'paused' ? 'Paused' : 'Done' }}
                    <span class="p-label num">{{ list.length }}</span>
                </component>
                <ul class="tiles">
                    <li v-for="project in list" :key="project.id" class="panel tile">
                        <div class="tile-head">
                            <Link :href="`/projects/${project.id}`" class="p-title link-quiet name">{{ project.name }}</Link>
                            <ul class="chips">
                                <li v-if="!project.folder_found" class="chip chip-warning"><span class="p-label">Folder not found</span></li>
                                <li v-else-if="project.tasks_file === 'errors'" class="chip chip-warning">
                                    <span class="p-label">Not migrated: {{ project.error_count }} {{ project.error_count === 1 ? 'error' : 'errors' }}</span>
                                </li>
                                <li v-if="project.due === 'overdue'" class="chip chip-danger"><span class="p-label">Overdue</span></li>
                                <li v-else-if="project.due === 'soon'" class="chip chip-warning"><span class="p-label">Due within 7 days</span></li>
                                <li v-if="project.sync_error" class="chip chip-danger"><span class="p-label">Sync failed</span></li>
                            </ul>
                        </div>
                        <p class="p-caption">
                            {{ project.category }}<template v-if="project.next_milestone"> · Next: {{ project.next_milestone }}</template>
                        </p>
                        <div v-if="hasTasks(project)" class="stack gap-3 progress-block">
                            <Progress :done="project.counts.Done" :total="total(project)" />
                            <p class="p-caption num tally">
                                <span><strong>{{ open(project) }}</strong> open</span>
                                <span>{{ project.counts['In progress'] }} in progress</span>
                                <span>{{ project.counts['Waiting on'] }} waiting</span>
                                <span>{{ project.counts.Done }} done</span>
                            </p>
                        </div>
                        <p v-else-if="project.folder_found" class="p-caption progress-block">Not migrated: no TASKS.md</p>
                    </li>
                </ul>
            </component>
        </template>

        <section v-if="registryErrors.length" class="panel stack gap-4" aria-labelledby="registry-errors">
            <h2 id="registry-errors" class="p-title">Rows skipped in projects.md</h2>
            <p class="p-body">These rows break a rule of the registry, so their projects aren't listed. Fix them in projects.md.</p>
            <ul class="ledger">
                <li v-for="(error, i) in registryErrors" :key="i" class="result">
                    <span class="p-label num">{{ error.line === 0 ? 'File' : `Line ${error.line}` }}</span>
                    <span class="p-body">{{ error.message }}</span>
                </li>
            </ul>
        </section>
    </div>
</template>

<style scoped>
.head {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: var(--space-4) var(--space-6);
}

/* The key number, its stats, then what needs attention: side by side when there's room. */
.summary {
    display: grid;
    gap: var(--space-6);
    grid-template-columns: repeat(auto-fit, minmax(min(100%, 14rem), 1fr));
    align-items: start;
}

.stats {
    display: flex;
    flex-wrap: wrap;
    gap: var(--space-6);
    margin: 0;
}

.stat {
    display: flex;
    flex-direction: column-reverse;
    gap: var(--space-3);
}

.stat dd {
    margin: 0;
}

.plain {
    margin: 0;
    padding: 0;
    list-style: none;
}

.flag {
    display: flex;
    flex-wrap: wrap;
    align-items: baseline;
    gap: var(--space-1) var(--space-2);
}

/* A status dot: the color repeats the words beside it, never replaces them. */
.dot {
    flex: none;
    inline-size: var(--space-2);
    block-size: var(--space-2);
    border-radius: var(--radius-bar);
    align-self: center;
}

.dot.is-danger {
    background: var(--danger-fg);
}

.dot.is-warning {
    background: var(--warning-fg);
}

.tiles {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(min(100%, 20rem), 1fr));
    gap: var(--space-4);
    margin: 0;
    padding: 0;
    list-style: none;
}

/* A tile is one link to its project: the name's hit area covers the whole tile. */
.tile {
    position: relative;
    display: grid;
    grid-template-rows: auto auto 1fr;
    gap: var(--space-3);
    padding: var(--space-5);
}

.tile-head {
    min-block-size: var(--control-h-sm);
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: var(--space-2) var(--space-3);
}

.name::after {
    content: '';
    position: absolute;
    inset: 0;
    border-radius: inherit;
}

.tile .chips {
    position: relative;
}

.progress-block {
    align-self: end;
    padding-block-start: var(--space-2);
}

.tally {
    display: flex;
    flex-wrap: wrap;
    gap: var(--space-1) var(--space-3);
}

.tally strong {
    color: var(--fg-default);
}

@media (hover: hover) {
    .tile:hover {
        box-shadow: var(--shadow-raised);
    }

    .tile:hover .name {
        text-decoration: underline;
    }
}

.tile:has(.name:focus-visible) {
    outline: 2px solid var(--focus-ring);
    outline-offset: 2px;
}

.name:focus-visible {
    outline: none;
}

.group > :is(h2, summary) {
    display: flex;
    align-items: center;
    gap: var(--space-2);
}

summary {
    cursor: pointer;
}

.result {
    display: flex;
    flex-wrap: wrap;
    align-items: baseline;
    gap: var(--space-2) var(--space-3);
    padding-block: var(--space-3);
}

.id {
    color: var(--fg-muted);
}
</style>
