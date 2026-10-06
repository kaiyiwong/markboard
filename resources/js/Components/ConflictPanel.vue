<script setup lang="ts">
import { computed, ref } from 'vue';
import { edit, refusal, type EditResult } from '@/api';
import Icon from '@/Components/Icon.vue';
import type { Conflict, DiffLine } from '@/types';

// An edit the file refused because it changed on disk after the page loaded (a 412). Nothing was
// written; the panel shows what changed since, and offers Apply only when the edit's own lines
// are unchanged. Apply sends the etag shown here, so it lands on exactly this version.
const props = defineProps<{ conflict: Conflict; name: string }>();

const busy = ref(false);
const result = ref<EditResult | null>(null);

async function run(action: 'apply' | 'discard') {
    busy.value = true;
    result.value = await edit('POST', `/api/v1/conflicts/${props.conflict.id}/${action}`, action === 'apply' ? props.conflict.etag : null);
    busy.value = false;
}

/** The diff's lines, with a gap marker wherever unchanged lines were left out. */
const rows = computed(() => {
    const out: (DiffLine | 'gap')[] = [];
    let last: DiffLine | null = null;
    for (const line of props.conflict.diff ?? []) {
        if (last && line.old !== null && last.old !== null && line.old > last.old + 1) {
            out.push('gap');
        }
        out.push(line);
        if (line.old !== null) {
            last = line;
        }
    }
    return out;
});
const marks = { same: ' ', removed: '−', added: '+' } as const;
const labels = { same: 'Unchanged', removed: 'Removed', added: 'Added' } as const;
</script>

<template>
    <section class="panel stack gap-5" :aria-labelledby="`conflict-${conflict.id}`">
        <div class="stack gap-3">
            <h2 :id="`conflict-${conflict.id}`" class="p-title head">
                <Icon name="alert" class="alert" />
                <span>Not saved: {{ conflict.summary }}</span>
            </h2>
            <p class="p-body">
                {{ name }} changed on disk after this page loaded, so Markboard wrote nothing.
                <template v-if="conflict.applicable">What your edit changes is the same in the new version, so it can be applied to it.</template>
                <template v-else-if="conflict.diff">What your edit changes is different now, so it can only be discarded. Make it again if you still want it.</template>
                <template v-else>The version you edited is no longer stored, so the change can't be shown or applied.</template>
            </p>
        </div>

        <div v-if="conflict.diff" class="stack gap-2">
            <h3 class="p-label">Changed on disk since you loaded the page</h3>
            <ol class="diff" aria-label="Line changes">
                <template v-for="(row, i) in rows" :key="i">
                    <li v-if="row === 'gap'" class="line gap p-caption" aria-hidden="true"><span class="num" /><span class="num" /><span /><span>⋯</span></li>
                    <li v-else class="line p-caption" :class="row.op">
                        <span class="num">{{ row.old ?? '' }}</span>
                        <span class="num">{{ row.new ?? '' }}</span>
                        <span class="mark" aria-hidden="true">{{ marks[row.op] }}</span>
                        <span class="text"><span class="visually-hidden">{{ labels[row.op] }}: </span>{{ row.text }}</span>
                    </li>
                </template>
            </ol>
        </div>

        <p v-if="refusal(result)" class="p-caption msg" role="alert"><Icon name="alert" /><span>{{ refusal(result) }}</span></p>
        <div class="actions md">
            <button type="button" class="btn md btn-secondary hit" :disabled="busy" @click="run('discard')"><span class="lbl">Discard my edit</span></button>
            <button v-if="conflict.applicable" type="button" class="btn md btn-primary hit" :disabled="busy" @click="run('apply')">
                <span class="lbl">Apply to the new version</span>
            </button>
        </div>
    </section>
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

.diff {
    margin: 0;
    padding: 0;
    list-style: none;
    border: 1px solid var(--border-subtle);
    border-radius: var(--container-radius);
    overflow-x: auto;
}

/* Old number, new number, marker, text: the numbers and marker mean the diff reads without color. */
.line {
    display: grid;
    grid-template-columns: 4ch 4ch 2ch minmax(0, 1fr);
    gap: var(--space-2);
    padding: var(--space-1) var(--space-3);
    font-family: var(--font-mono);
    color: var(--fg-default);
}

.line .num {
    color: var(--fg-muted);
    text-align: end;
}

/* Muted text on a status tint would sit near 4.5:1, so changed lines keep the default color. */
:is(.removed, .added) .num {
    color: var(--fg-default);
}

.text {
    white-space: pre-wrap;
    overflow-wrap: anywhere;
}

.removed {
    background: var(--danger-bg);
}

.added {
    background: var(--success-bg);
}

.gap {
    color: var(--fg-muted);
}
</style>
