<script setup lang="ts">
import { computed } from 'vue';
import { daysFrom, dueLabel, useToday } from '@/dates';
import type { TaskListItem } from '@/types';

// One task on the Tasks page, on one line: project, ID, title and the one fact that can need
// action (its due label, otherwise how long it has waited or been in progress).
const props = defineProps<{
    item: TaskListItem;
    projectName: string;
    selected: boolean;
    /** True while a tick sent from this page is on its way to the file. */
    ticking: boolean;
}>();

const today = useToday();
const OLD_AFTER_DAYS = 14;

const fact = computed(() => {
    if (props.item.task.due) {
        const due = dueLabel(today.value, props.item.task.due);
        return { text: due.text, tone: due.tone, title: `Due ${props.item.task.due}` };
    }
    if (props.item.age_from) {
        const days = -daysFrom(today.value, props.item.age_from);
        const since = props.item.group === 'in-progress' ? 'Started' : 'Waiting since';
        return { text: `${days}d`, tone: days >= OLD_AFTER_DAYS ? ('warning' as const) : null, title: `${since} ${props.item.age_from}` };
    }
    return null;
});
</script>

<template>
    <li class="line" :class="{ 'is-selected': selected, 'is-ticking': ticking }" :aria-current="selected ? 'true' : undefined">
        <span class="box" aria-hidden="true" />
        <span class="p-caption project">{{ projectName }}</span>
        <code class="p-caption num id">{{ item.task.task_id }}</code>
        <span class="p-body title" :title="item.task.title">
            <span v-if="ticking" class="visually-hidden">Ticking:</span>
            {{ item.task.title }}
        </span>
        <span v-if="fact" class="p-caption num fact" :class="fact.tone" :title="fact.title">{{ fact.text }}</span>
    </li>
</template>

<style scoped>
.line {
    position: relative;
    display: grid;
    grid-template-columns: var(--space-4) minmax(0, 9rem) 5ch minmax(0, 1fr) auto;
    gap: var(--space-3);
    align-items: center;
    min-block-size: var(--control-h-lg);
    padding-inline: var(--space-4);
    border-block-start: 1px solid var(--border-subtle);
    cursor: pointer;
}

@media (hover: hover) {
    .line:hover {
        background: var(--bg-control-hover);
    }
}

/* The selected task: the accent marks the one selected thing (DESIGN.md, Color quiet). */
.line.is-selected {
    background: var(--accent-bg);
}

.line.is-selected::before {
    content: '';
    position: absolute;
    inset-block: 0;
    inset-inline-start: 0;
    inline-size: var(--space-1);
    background: var(--accent-strong);
}

.box {
    inline-size: var(--space-4);
    block-size: var(--space-4);
    border: 1px solid var(--border-strong);
    border-radius: var(--radius-1);
}

.is-ticking .box {
    border-color: var(--accent-solid);
    background: var(--accent-solid);
}

.is-ticking .title {
    color: var(--fg-muted);
    text-decoration: line-through;
}

.project,
.id,
.fact {
    color: var(--fg-muted);
    white-space: nowrap;
}

.project,
.title {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.id {
    font-family: var(--font-mono);
}

.fact.warning {
    color: var(--warning-fg-strong);
}

.fact.danger {
    color: var(--danger-fg-strong);
}

/* On a phone the project moves out and the title gets the room. */
@media (max-width: 479px) {
    .line {
        grid-template-columns: var(--space-4) 5ch minmax(0, 1fr) auto;
    }

    .project {
        display: none;
    }
}
</style>
