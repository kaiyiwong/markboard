<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed, nextTick, onMounted, ref, watch } from 'vue';
import { edit, refusal, useEditing, type EditResult } from '@/api';
import AddTask from '@/Components/AddTask.vue';
import ConflictPanel from '@/Components/ConflictPanel.vue';
import FileErrors from '@/Components/FileErrors.vue';
import Icon from '@/Components/Icon.vue';
import Progress from '@/Components/Progress.vue';
import TaskRow from '@/Components/TaskRow.vue';
import { dueLabel, useToday } from '@/dates';
import AppLayout from '@/Layouts/AppLayout.vue';
import type { Conflict, Project, SectionName, SourceFile, Task } from '@/types';

defineOptions({ layout: AppLayout });

const props = defineProps<{
    project: Project;
    file: SourceFile | null;
    sections: { name: SectionName; tasks: Task[] }[];
    highlight: string | null;
    conflicts: Conflict[];
}>();

// The task route (/projects/{project}/tasks/T12) scrolls to its task once, on arrival.
onMounted(async () => {
    if (props.highlight) {
        await nextTick();
        document.getElementById(props.highlight)?.scrollIntoView({ block: 'center' });
    }
});

const slug = (name: string) => name.toLowerCase().replace(/ /g, '-');

// A task that changed section since the last props (ticked, undone, moved, or changed by an agent)
// arrives in its new section from the direction it came from. Not on first load: nothing moved yet.
const ORDER: SectionName[] = ['Up next', 'In progress', 'Waiting on', 'Done'];
const arrived = ref(new Map<string, 'down' | 'up'>());
watch(
    () => props.sections,
    (now, before) => {
        const was = new Map(before.flatMap((section) => section.tasks.map((task) => [task.task_id, section.name] as const)));
        arrived.value = new Map(
            now.flatMap((section) =>
                section.tasks
                    .filter((task) => was.has(task.task_id) && was.get(task.task_id) !== section.name)
                    .map((task) => [task.task_id, ORDER.indexOf(section.name) > ORDER.indexOf(was.get(task.task_id)!) ? 'down' : 'up'] as const),
            ),
        );
    },
);
const today = useToday();

// The page's key element: how much of the project is done, then the open sections and the next due date.
const progress = computed(() => {
    const count = (name: SectionName) => props.sections.find((section) => section.name === name)?.tasks.length ?? 0;
    const open = props.sections.filter((section) => section.name !== 'Done').flatMap((section) => section.tasks);
    const nextDue = open.map((task) => task.due).filter((due): due is string => due !== null).sort()[0] ?? null;
    return {
        done: count('Done'),
        total: count('Done') + open.length,
        open: (['Up next', 'In progress', 'Waiting on'] as const).map((name) => ({ name, count: count(name) })),
        nextDue: nextDue ? dueLabel(today.value, nextDue) : null,
    };
});
const editableEtag = () => (props.file?.editable ? props.file.etag : null);

// Drag a task by its handle onto another task in the same section to take its position. The etag
// is the one on screen when the drag began; polling waits until the drag ends.
const dragging = ref<{ task: Task; etag: string } | null>(null);
const over = ref<string | null>(null);
const dragEditing = useEditing();
const dropResult = ref<{ section: SectionName; result: EditResult } | null>(null);

function onDragStart(event: DragEvent, task: Task) {
    const etag = editableEtag();
    if (etag === null) {
        return;
    }
    dragging.value = { task, etag };
    dragEditing.value = true;
    event.dataTransfer?.setData('text/plain', task.task_id);
}

function onDragOver(event: DragEvent, target: Task) {
    if (dragging.value && dragging.value.task.section === target.section) {
        event.preventDefault();
        over.value = target.task_id;
    }
}

async function onDrop(target: Task) {
    const drag = dragging.value;
    onDragEnd();
    if (!drag || drag.task.section !== target.section || drag.task.task_id === target.task_id) {
        return;
    }
    const result = await edit('PUT', `/api/v1/projects/${props.project.id}/tasks/${drag.task.task_id}/position`, drag.etag, { position: target.position });
    dropResult.value = { section: target.section, result };
}

function onDragEnd() {
    dragging.value = null;
    over.value = null;
    dragEditing.value = false;
}
</script>

<template>
    <Head :title="project.name" />
    <div class="stack gap-6">
        <div class="stack gap-3">
            <p class="p-label"><Link href="/" class="link-quiet">Projects</Link></p>
            <h1 class="p-headline">{{ project.name }}</h1>
            <p class="p-caption">
                {{ project.category }} · {{ project.status }}<template v-if="project.next_milestone"> · Next: {{ project.next_milestone }}</template>
            </p>
        </div>

        <div v-if="!file" class="panel stack gap-3">
            <h2 class="p-title">{{ project.folder_found ? 'No TASKS.md yet' : 'Folder not found' }}</h2>
            <p class="p-body">
                {{
                    project.folder_found
                        ? "This project isn't migrated: its folder has no TASKS.md. Its tasks show here once the file exists."
                        : "The registry's path for this project doesn't exist, or isn't mounted in Sail. Fix the path in projects.md."
                }}
            </p>
        </div>

        <template v-else>
            <section class="panel summary" aria-labelledby="progress-label">
                <div class="stack gap-3 key">
                    <p id="progress-label" class="p-label">Done</p>
                    <p class="p-key">{{ progress.done }} <span class="p-title of">of {{ progress.total }}</span></p>
                    <Progress :done="progress.done" :total="progress.total" size="lg" />
                </div>
                <dl class="stats">
                    <div v-for="section in progress.open" :key="section.name" class="stat">
                        <dt class="p-label">{{ section.name }}</dt>
                        <dd class="p-title num">{{ section.count }}</dd>
                    </div>
                    <div v-if="progress.nextDue" class="stat">
                        <dt class="p-label">Next due</dt>
                        <dd class="p-title" :class="progress.nextDue.tone && `is-${progress.nextDue.tone}`">{{ progress.nextDue.text }}</dd>
                    </div>
                </dl>
            </section>
            <ConflictPanel v-for="conflict in conflicts" :key="conflict.id" :conflict="conflict" name="TASKS.md" />
            <FileErrors :file="file" name="TASKS.md" />
            <section v-for="section in sections" :key="section.name" class="panel stack gap-4 section" :aria-labelledby="`section-${slug(section.name)}`">
                <h2 :id="`section-${slug(section.name)}`" class="p-title heading">
                    {{ section.name }} <span class="p-label num">{{ section.tasks.length }}</span>
                </h2>
                <p v-if="dropResult?.section === section.name && refusal(dropResult.result)" class="p-caption msg" role="alert">
                    <Icon name="alert" /><span>{{ refusal(dropResult.result) }}</span>
                </p>
                <ul v-if="section.tasks.length" class="ledger">
                    <TaskRow
                        v-for="task in section.tasks"
                        :key="task.task_id"
                        :task="task"
                        :highlighted="task.task_id === highlight"
                        :project-id="project.id"
                        :etag="editableEtag()"
                        :section-size="section.tasks.length"
                        :arrived="arrived.get(task.task_id) ?? null"
                        :class="{ 'is-drop-target': over === task.task_id && dragging?.task.task_id !== task.task_id }"
                        @dragstart="onDragStart($event, task)"
                        @dragover="onDragOver($event, task)"
                        @dragleave="over = null"
                        @drop.prevent="onDrop(task)"
                        @dragend="onDragEnd"
                    />
                </ul>
                <p v-else class="p-caption">No tasks in {{ section.name }}.</p>
                <AddTask v-if="section.name === 'Up next' && editableEtag()" :project-id="project.id" :etag="editableEtag()!" />
            </section>
        </template>
    </div>
</template>

<style scoped>
.heading {
    display: flex;
    align-items: center;
    gap: var(--space-2);
}

.summary {
    display: grid;
    gap: var(--space-6);
    grid-template-columns: repeat(auto-fit, minmax(min(100%, 16rem), 1fr));
    align-items: end;
}

.key {
    max-inline-size: 24rem;
}

.of {
    color: var(--fg-muted);
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

.is-danger {
    color: var(--danger-fg-strong);
}

.is-warning {
    color: var(--warning-fg-strong);
}

/* Rows reach the panel's edges, so their hairlines and highlight run the full width. */
.section {
    padding: var(--space-5) 0 0;
}

.section > :not(.ledger) {
    margin-inline: var(--space-5);
}

.section > :last-child:not(.ledger) {
    margin-block-end: var(--space-5);
}

.section > .ledger > :last-child {
    border-bottom: 0;
}
</style>
