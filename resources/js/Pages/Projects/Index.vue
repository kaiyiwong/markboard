<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import Icon from '@/Components/Icon.vue';
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

const counts = ['Up next', 'In progress', 'Waiting on'] as const;
</script>

<template>
    <Head title="Projects" />
    <div class="stack gap-7">
        <div class="stack gap-5">
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

        <section v-if="results" class="stack gap-4" aria-labelledby="results" aria-live="polite">
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
                <ul class="ledger cq">
                    <li v-for="project in list" :key="project.id" class="project">
                        <div class="stack gap-2 who">
                            <Link :href="`/projects/${project.id}`" class="p-title link-quiet">{{ project.name }}</Link>
                            <p class="p-caption">
                                {{ project.category }}<template v-if="project.next_milestone"> · Next: {{ project.next_milestone }}</template>
                            </p>
                        </div>
                        <div class="state">
                            <ul class="chips">
                                <li v-if="!project.folder_found" class="chip chip-warning"><span class="p-label">Folder not found</span></li>
                                <li v-else-if="project.tasks_file === 'missing'" class="chip"><span class="p-label">Not migrated: no TASKS.md</span></li>
                                <li v-else-if="project.tasks_file === 'errors'" class="chip chip-warning">
                                    <span class="p-label">Not migrated: {{ project.error_count }} {{ project.error_count === 1 ? 'error' : 'errors' }}</span>
                                </li>
                                <li v-if="project.due === 'overdue'" class="chip chip-danger"><span class="p-label">Overdue</span></li>
                                <li v-else-if="project.due === 'soon'" class="chip chip-warning"><span class="p-label">Due within 7 days</span></li>
                                <li v-if="project.sync_error" class="chip chip-danger"><span class="p-label">Sync failed</span></li>
                            </ul>
                            <dl v-if="project.folder_found && project.tasks_file !== 'missing'" class="counts">
                                <div v-for="section in counts" :key="section" class="count">
                                    <dt class="p-label">{{ section }}</dt>
                                    <dd class="p-body num">{{ project.counts[section] }}</dd>
                                </div>
                            </dl>
                        </div>
                    </li>
                </ul>
            </component>
        </template>

        <section v-if="registryErrors.length" class="stack gap-4" aria-labelledby="registry-errors">
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
.project {
    display: grid;
    gap: var(--space-3);
    padding-block: var(--space-4);
}

/* From 480px of list width, the counts and badges sit to the right of the name. */
@container (min-width: 480px) {
    .project {
        grid-template-columns: minmax(0, 1fr) auto;
        align-items: center;
        gap: var(--space-5);
    }

    .state {
        justify-items: end;
    }
}

.state {
    display: grid;
    gap: var(--space-3);
}

.counts {
    display: flex;
    gap: var(--space-5);
    margin: 0;
}

.count {
    display: flex;
    flex-direction: column-reverse;
    gap: var(--space-2);
}

.count dd {
    margin: 0;
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
