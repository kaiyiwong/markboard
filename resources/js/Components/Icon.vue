<script setup lang="ts">
// The few icons the pages use, drawn on a 24 grid with the system's 1.5px stroke (.icon).
defineProps<{ name: 'search' | 'chevron' | 'check' | 'alert' | 'more' | 'grip' }>();
</script>

<template>
    <svg class="icon" viewBox="0 0 24 24" aria-hidden="true">
        <template v-if="name === 'search'"><circle cx="11" cy="11" r="6" /><path d="M20 20l-4.5-4.5" /></template>
        <path v-else-if="name === 'chevron'" d="M6 9l6 6 6-6" />
        <path v-else-if="name === 'check'" d="M5 12.5l4.5 4.5L19 7.5" />
        <g v-else-if="name === 'more'" fill="currentColor" stroke="none"><circle cx="5" cy="12" r="1.5" /><circle cx="12" cy="12" r="1.5" /><circle cx="19" cy="12" r="1.5" /></g>
        <g v-else-if="name === 'grip'" fill="currentColor" stroke="none">
            <circle cx="9" cy="6" r="1.5" /><circle cx="15" cy="6" r="1.5" /><circle cx="9" cy="12" r="1.5" /><circle cx="15" cy="12" r="1.5" /><circle cx="9" cy="18" r="1.5" /><circle cx="15" cy="18" r="1.5" />
        </g>
        <template v-else><circle cx="12" cy="12" r="9" /><path d="M12 7.5v5.5M12 16.5v.01" /></template>
    </svg>
</template>
