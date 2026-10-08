<script setup lang="ts">
import Icon from '@/Components/Icon.vue';
import type { SourceFile } from '@/types';

// A file that isn't editable is shown read-only, with its errors and line numbers, never hidden
// behind an empty state (DESIGN.md, Never). A failed sync is shown too: the rows on screen are
// then from the last version that synced.
defineProps<{ file: SourceFile; name: string }>();
</script>

<template>
    <div v-if="!file.editable || file.sync_error" class="panel stack gap-4" role="status">
        <div v-if="!file.editable" class="stack gap-3">
            <h2 class="p-title head">
                <Icon name="alert" class="alert" />
                <span>{{ name }} is read-only: {{ file.errors.length }} {{ file.errors.length === 1 ? 'error' : 'errors' }}</span>
            </h2>
            <p class="p-body">Markboard doesn't edit a file it can't read exactly. Fix these lines in the file and this page updates on its own.</p>
            <ul class="ledger errors">
                <li v-for="(error, i) in file.errors" :key="i" class="error">
                    <span class="p-label num">{{ error.line === 0 ? 'File' : `Line ${error.line}` }}</span>
                    <span class="p-body">{{ error.message }}</span>
                </li>
            </ul>
        </div>
        <p v-if="file.sync_error" class="p-body">
            <strong>The last sync of {{ name }} failed:</strong> {{ file.sync_error }}. What's shown is the last version that synced; the next request tries again.
        </p>
    </div>
</template>

<style scoped>
.head {
    display: flex;
    align-items: center;
    gap: var(--space-2);
}

.alert {
    color: var(--danger-fg);
}

.error {
    display: grid;
    grid-template-columns: 8ch minmax(0, 1fr);
    align-items: baseline;
    gap: var(--space-3);
    padding-block: var(--space-3);
}
</style>
