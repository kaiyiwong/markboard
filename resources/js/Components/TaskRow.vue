<script setup lang="ts">
import Icon from '@/Components/Icon.vue';
import type { Task } from '@/types';

// The signature element (DESIGN.md): a task row with its ID, title, metadata chips and proof line.
defineProps<{ task: Task; highlighted: boolean }>();
</script>

<template>
    <li :id="task.task_id" class="task" :class="{ 'is-highlighted': highlighted }" :aria-current="highlighted ? 'true' : undefined">
        <code class="id">{{ task.task_id }}</code>
        <div class="stack gap-3">
            <p class="p-body title" :class="{ 'is-checked': task.checked }">
                <Icon v-if="task.checked" name="check" class="mark" />
                <span v-if="task.checked" class="visually-hidden">Done:</span>
                <span>{{ task.title }}</span>
            </p>
            <ul v-if="task.metadata.length" class="chips" aria-label="Details">
                <li
                    v-for="([key, value], i) in task.metadata"
                    :key="i"
                    class="chip"
                    :class="{ 'chip-danger': key === 'due' && task.overdue }"
                >
                    <span class="p-caption"><span class="key">{{ key === 'due' && task.overdue ? 'overdue' : key }}</span> <span class="num">{{ value }}</span></span>
                </li>
            </ul>
            <p v-if="task.proof" class="p-caption proof"><span class="key">Proof:</span> {{ task.proof }}</p>
            <p v-for="(note, i) in task.notes" :key="i" class="p-caption note"><span class="key">Note:</span> {{ note }}</p>
        </div>
    </li>
</template>

<style scoped>
.task {
    display: grid;
    grid-template-columns: 5ch minmax(0, 1fr);
    gap: var(--space-3);
    padding: var(--space-4) var(--space-3);
}

/* The selected task: the accent marks it, with a 2px bar that reaches 3:1 (SPEC 3.5 rule 2). */
.is-highlighted {
    background: var(--accent-bg);
    box-shadow: inset 2px 0 0 var(--accent-strong);
}

.id {
    color: var(--fg-muted);
    text-box: trim-both cap alphabetic;
}

/* Muted text on the accent tint would sit close to 4.5:1, so the highlighted row uses the default. */
.is-highlighted :is(.id, .is-checked) {
    color: var(--fg-default);
}

.title {
    display: flex;
    align-items: center;
    gap: var(--space-2);
    overflow-wrap: anywhere;
}

.is-checked {
    color: var(--fg-muted);
}

.mark {
    color: var(--success-fg);
}

.proof,
.note {
    color: var(--fg-default);
    overflow-wrap: anywhere;
}
</style>
