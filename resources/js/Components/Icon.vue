<script setup lang="ts">
// The few icons the pages use, drawn on a 24 grid with the system's 1.5px stroke (.icon).
defineProps<{ name: 'search' | 'chevron' | 'check' | 'alert' | 'more' | 'grip' | 'plus' | 'file' | 'scope' | 'merge' | 'report' | 'blocked' }>();
</script>

<template>
    <svg class="icon" viewBox="0 0 24 24" aria-hidden="true">
        <template v-if="name === 'search'"><circle cx="11" cy="11" r="6" /><path d="M20 20l-4.5-4.5" /></template>
        <path v-else-if="name === 'chevron'" d="M6 9l6 6 6-6" />
        <path v-else-if="name === 'check'" d="M5 12.5l4.5 4.5L19 7.5" />
        <path v-else-if="name === 'plus'" d="M12 5v14M5 12h14" />
        <path v-else-if="name === 'file'" d="M7 3h7l4 4v14H7zM14 3v4h4" />
        <template v-else-if="name === 'scope'"><circle cx="12" cy="12" r="8" /><circle cx="12" cy="12" r="3" /></template>
        <template v-else-if="name === 'merge'"><circle cx="6" cy="5.5" r="2.5" /><circle cx="18" cy="17" r="2.5" /><path d="M6 8v13M6 9a8 8 0 0 0 9.5 8" /></template>
        <path v-else-if="name === 'report'" d="M4 6h16M4 12h16M4 18h10" />
        <template v-else-if="name === 'blocked'"><circle cx="12" cy="12" r="8" /><path d="M8.5 12h7" /></template>
        <g v-else-if="name === 'more'" fill="currentColor" stroke="none"><circle cx="5" cy="12" r="1.5" /><circle cx="12" cy="12" r="1.5" /><circle cx="19" cy="12" r="1.5" /></g>
        <g v-else-if="name === 'grip'" fill="currentColor" stroke="none">
            <circle cx="9" cy="6" r="1.5" /><circle cx="15" cy="6" r="1.5" /><circle cx="9" cy="12" r="1.5" /><circle cx="15" cy="12" r="1.5" /><circle cx="9" cy="18" r="1.5" /><circle cx="15" cy="18" r="1.5" />
        </g>
        <template v-else><circle cx="12" cy="12" r="9" /><path d="M12 7.5v5.5M12 16.5v.01" /></template>
    </svg>
</template>
