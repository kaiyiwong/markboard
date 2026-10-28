<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import ReviewCard from '@/Components/ReviewCard.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import type { ReviewItem } from '@/types';

defineOptions({ layout: AppLayout });

// Every task waiting on Kai, one at a time (docs/spec.md, Review). The queue beside the card lists
// them all; arrow keys move through it. The item shown is kept by its project and task, so a
// reload that adds or removes items doesn't move Kai off what he's looking at; once he answers
// it and it leaves the list, the next one takes its place.
const props = defineProps<{ items: ReviewItem[] }>();

const keyOf = (item: ReviewItem) => `${item.project.id} ${item.task.task_id}`;
const currentKey = ref<string | null>(props.items[0] ? keyOf(props.items[0]) : null);
const index = computed(() => Math.max(0, props.items.findIndex((item) => keyOf(item) === currentKey.value)));
const current = computed<ReviewItem | undefined>(() => props.items[index.value]);

watch(
    () => props.items,
    (items, before) => {
        if (!items.some((item) => keyOf(item) === currentKey.value)) {
            const was = before.findIndex((item) => keyOf(item) === currentKey.value);
            const next = items[Math.min(Math.max(was, 0), items.length - 1)];
            currentKey.value = next ? keyOf(next) : null;
        }
    },
);

function go(step: number) {
    const next = props.items[index.value + step];
    if (next) {
        currentKey.value = keyOf(next);
    }
}

function onKey(event: KeyboardEvent) {
    if (event.target instanceof HTMLElement && event.target.closest('input, textarea, select, [contenteditable]')) {
        return;
    }
    if (event.key === 'ArrowRight' || event.key === 'ArrowLeft') {
        go(event.key === 'ArrowRight' ? 1 : -1);
    }
}
onMounted(() => window.addEventListener('keydown', onKey));
onBeforeUnmount(() => window.removeEventListener('keydown', onKey));
</script>

<template>
    <Head title="Review" />
    <div class="layout">
        <div class="stack gap-6 main-col">
            <header class="stack gap-3">
                <p class="p-label">Review</p>
                <h1 class="p-key">
                    <template v-if="items.length">{{ items.length }} to decide</template>
                    <template v-else>Nothing to decide</template>
                </h1>
            </header>
            <ReviewCard v-if="current" :key="keyOf(current)" :item="current" />
        </div>

        <nav v-if="items.length" class="panel stack gap-4 queue" aria-label="Review queue">
            <div class="queue-head">
                <span class="p-caption num">{{ index + 1 }} of {{ items.length }}</span>
                <div class="seg">
                    <button type="button" class="btn btn-ghost md hit" aria-label="Previous" :disabled="index === 0" @click="go(-1)">←</button>
                    <button type="button" class="btn btn-ghost md hit" aria-label="Next" :disabled="index === items.length - 1" @click="go(1)">→</button>
                </div>
            </div>
            <ol class="rows">
                <li v-for="item in items" :key="keyOf(item)">
                    <button type="button" class="row" :aria-current="keyOf(item) === currentKey ? 'true' : undefined" @click="currentKey = keyOf(item)">
                        <span class="dot" :class="{ empty: !item.card }" aria-hidden="true" />
                        <span class="stack gap-1">
                            <span class="p-caption">{{ item.project.name }} <code class="num">{{ item.task.task_id }}</code></span>
                            <span class="p-body">{{ item.card?.question ?? item.task.title }}</span>
                        </span>
                    </button>
                </li>
            </ol>
        </nav>
    </div>
</template>

<style scoped>
/* The card first, the queue beside it from 1024, under it on smaller screens. */
.layout {
    display: grid;
    gap: var(--space-6);
}

@media (min-width: 1024px) {
    .layout {
        grid-template-columns: minmax(0, 1fr) minmax(14rem, 20rem);
        align-items: start;
    }

    .queue {
        position: sticky;
        top: var(--space-5);
    }
}

.queue-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.seg {
    display: flex;
    gap: var(--space-1);
}

.rows {
    display: grid;
    margin: 0 calc(-1 * var(--space-3));
    padding: 0;
    list-style: none;
}

.row {
    display: grid;
    grid-template-columns: var(--space-2) minmax(0, 1fr);
    gap: var(--space-3);
    align-items: start;
    inline-size: 100%;
    padding: var(--space-3);
    border: 0;
    border-radius: var(--control-radius);
    background: none;
    color: var(--fg-default);
    font: inherit;
    text-align: start;
    cursor: pointer;
}

@media (hover: hover) {
    .row:hover {
        background: var(--bg-control-hover);
    }
}

/* The item shown: the accent marks the one selected thing (DESIGN.md, Color quiet). */
.row[aria-current='true'] {
    background: var(--accent-bg);
}

.row code {
    color: var(--fg-muted);
}

/* A filled dot: the task has a card. A ring: it shows as text only. */
.dot {
    inline-size: var(--space-2);
    block-size: var(--space-2);
    margin-block-start: calc(var(--space-1) * 1.5);
    border-radius: var(--radius-full);
    background: var(--accent-strong);
}

.dot.empty {
    background: none;
    box-shadow: inset 0 0 0 1px var(--fg-muted);
}
</style>
