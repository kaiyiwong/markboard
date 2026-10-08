<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { nextTick, onMounted, ref } from 'vue';
import { edit, refusal, useEditing, type EditResult } from '@/api';
import AddTask from '@/Components/AddTask.vue';
import ConflictPanel from '@/Components/ConflictPanel.vue';
import FileErrors from '@/Components/FileErrors.vue';
import Icon from '@/Components/Icon.vue';
import TaskRow from '@/Components/TaskRow.vue';
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
    <div class="stack gap-7">
        <div class="stack gap-3">
            <p class="p-label"><Link href="/" class="link-quiet">Projects</Link></p>
            <h1 class="p-headline">{{ project.name }}</h1>
            <p class="p-caption">
                {{ project.category }} · {{ project.status }}<template v-if="project.next_milestone"> · Next: {{ project.next_milestone }}</template>
            </p>
        </div>

        <div v-if="!file" class="stack gap-3">
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
            <ConflictPanel v-for="conflict in conflicts" :key="conflict.id" :conflict="conflict" name="TASKS.md" />
            <FileErrors :file="file" name="TASKS.md" />
            <section v-for="section in sections" :key="section.name" class="stack gap-4" :aria-labelledby="`section-${slug(section.name)}`">
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
</style>
