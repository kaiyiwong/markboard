<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { nextTick, onMounted } from 'vue';
import FileErrors from '@/Components/FileErrors.vue';
import TaskRow from '@/Components/TaskRow.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import type { Project, SectionName, SourceFile, Task } from '@/types';

defineOptions({ layout: AppLayout });

const props = defineProps<{
    project: Project;
    file: SourceFile | null;
    sections: { name: SectionName; tasks: Task[] }[];
    highlight: string | null;
}>();

// The task route (/projects/{project}/tasks/T12) scrolls to its task once, on arrival.
onMounted(async () => {
    if (props.highlight) {
        await nextTick();
        document.getElementById(props.highlight)?.scrollIntoView({ block: 'center' });
    }
});

const slug = (name: string) => name.toLowerCase().replace(/ /g, '-');
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
            <FileErrors :file="file" name="TASKS.md" />
            <section v-for="section in sections" :key="section.name" class="stack gap-4" :aria-labelledby="`section-${slug(section.name)}`">
                <h2 :id="`section-${slug(section.name)}`" class="p-title heading">
                    {{ section.name }} <span class="p-label num">{{ section.tasks.length }}</span>
                </h2>
                <ul v-if="section.tasks.length" class="ledger">
                    <TaskRow v-for="task in section.tasks" :key="task.task_id" :task="task" :highlighted="task.task_id === highlight" />
                </ul>
                <p v-else class="p-caption">No tasks in {{ section.name }}.</p>
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
