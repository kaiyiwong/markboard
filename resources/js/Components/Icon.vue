<script setup lang="ts">
// The few icons the pages use, drawn on a 24 grid with the system's 1.5px stroke (.icon).
defineProps<{ name: 'search' | 'chevron' | 'check' | 'alert' }>();
</script>

<template>
    <svg class="icon" viewBox="0 0 24 24" aria-hidden="true">
        <template v-if="name === 'search'"><circle cx="11" cy="11" r="6" /><path d="M20 20l-4.5-4.5" /></template>
        <path v-else-if="name === 'chevron'" d="M6 9l6 6 6-6" />
        <path v-else-if="name === 'check'" d="M5 12.5l4.5 4.5L19 7.5" />
        <template v-else><circle cx="12" cy="12" r="9" /><path d="M12 7.5v5.5M12 16.5v.01" /></template>
    </svg>
</template>
