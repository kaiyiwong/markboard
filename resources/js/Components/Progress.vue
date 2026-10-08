<script setup lang="ts">
import { computed } from 'vue';

// How much of a project is done: one series in accent-strong on a quiet track (SPEC 9 rule 1,
// focus by default). The numbers beside it carry the meaning; the bar is for scanning.
const props = defineProps<{ done: number; total: number; size?: 'md' | 'lg' }>();

const share = computed(() => (props.total === 0 ? 0 : props.done / props.total));
</script>

<template>
    <div class="progress" :class="size ?? 'md'" role="img" :aria-label="`${done} of ${total} done`">
        <span class="fill" :style="{ inlineSize: `${share * 100}%` }" />
    </div>
</template>

<style scoped>
.progress {
    overflow: hidden;
    block-size: var(--space-1);
    border-radius: var(--radius-bar);
    background: var(--bg-control);
}

.progress.lg {
    block-size: var(--space-2);
}

.fill {
    display: block;
    block-size: 100%;
    border-radius: inherit;
    background: var(--accent-strong);
}
</style>
