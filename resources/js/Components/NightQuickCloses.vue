<script setup lang="ts">
import { ref } from 'vue';
import { edit, refusal, type EditResult } from '@/api';
import CodeText from '@/Components/CodeText.vue';
import Icon from '@/Components/Icon.vue';
import type { QuickClose } from '@/types';

// Tasks the chief of staff thinks are done or superseded, each a one-click decision. Close ticks the
// task into Done in its project's TASKS.md, through the same write path as the Project page's tick.
// Keep writes a `kept:` line under it in the triage note, so the next triage stops suggesting it.
const props = defineProps<{ closes: QuickClose[]; date: string; etag: string }>();

const busy = ref<string | null>(null);
const result = ref<{ key: string; result: EditResult } | null>(null);
const key = (close: QuickClose) => `${close.project}/${close.task}`;

async function act(close: QuickClose, action: 'close' | 'keep') {
    busy.value = key(close);
    const answer =
        action === 'close'
            ? await edit('POST', `/api/v1/projects/${close.project}/tasks/${close.task}/tick`, close.etag)
            : await edit('POST', `/api/v1/night/triage/${props.date}/keep`, props.etag, { project: close.project, task: close.task });
    result.value = { key: key(close), result: answer };
    busy.value = null;
}

/** What went wrong, beside the row: a changed file reloads the page, so it can be tried again. */
function message(close: QuickClose): string | null {
    if (result.value?.key !== key(close) || result.value.result.ok) {
        return null;
    }
    return result.value.result.status === 412 ? 'The file changed since the page showed it. The page has the current one now: try again.' : refusal(result.value.result);
}
</script>

<template>
    <ul class="panel closes">
        <li v-for="close in closes" :key="key(close)" class="close">
            <code class="p-caption num id">{{ close.task }}</code>
            <div class="stack gap-2">
                <p class="p-body strong">{{ close.short }}</p>
                <p v-if="close.why" class="p-caption"><CodeText :text="close.why" /></p>
                <p v-if="message(close)" class="p-caption msg" role="alert"><Icon name="alert" /><span>{{ message(close) }}</span></p>
            </div>
            <div class="actions md">
                <button
                    type="button"
                    class="btn md btn-secondary hit"
                    :disabled="busy !== null || close.etag === null"
                    :title="close.etag === null ? `${close.project_name}'s TASKS.md can't be edited here` : undefined"
                    @click="act(close, 'close')"
                >
                    <span class="lbl">Close</span>
                </button>
                <button type="button" class="btn md btn-secondary hit" :disabled="busy !== null" @click="act(close, 'keep')"><span class="lbl">Keep</span></button>
            </div>
        </li>
    </ul>
</template>

<style scoped>
.closes {
    display: grid;
    margin: 0;
    list-style: none;
}

/* A row: the ID, what it is and why it looks closable, then the two buttons. */
.close {
    display: grid;
    grid-template-columns: auto minmax(0, 1fr) auto;
    gap: var(--space-4);
    align-items: center;
    padding-block: var(--space-4);
}

.close:first-child {
    padding-block-start: 0;
}

.close:last-child {
    padding-block-end: 0;
}

.close + .close {
    border-top: 1px solid var(--border-subtle);
}

.id {
    align-self: start;
    color: var(--fg-muted);
}

.strong {
    font-weight: var(--weight-medium);
}

@media (max-width: 599px) {
    .close {
        grid-template-columns: auto minmax(0, 1fr);
    }

    .close > .actions {
        grid-column: 2;
    }
}
</style>
