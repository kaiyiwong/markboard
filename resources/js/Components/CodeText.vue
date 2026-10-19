<script setup lang="ts">
import { computed } from 'vue';

// The night run's agents write plain text with `backticks` around code: show those parts as code.
const props = defineProps<{ text: string }>();

const parts = computed(() => props.text.split('`').map((part, i) => ({ part, code: i % 2 === 1 })));
</script>

<template>
    <span><template v-for="(bit, i) in parts" :key="i"><code v-if="bit.code">{{ bit.part }}</code><template v-else>{{ bit.part }}</template></template></span>
</template>
