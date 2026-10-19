<script setup lang="ts">
import { computed } from 'vue';
import CodeText from '@/Components/CodeText.vue';
import Icon from '@/Components/Icon.vue';
import type { TriageItem, TriageParts } from '@/types';

// The chief of staff's triage, laid out: what it picked and carried over (with the run each gets),
// what it sent back (answered in Questions above), and what it passed over, grouped by reason with
// the reasons in full a click away. The note as written is at the end.
const props = defineProps<{ parts: TriageParts; note: string | null; anchors: Record<string, string> }>();

const columns = computed(() =>
    [
        { label: 'Picked', items: props.parts.picked },
        { label: 'Carried over', items: props.parts.carried },
    ].filter((column) => column.items.length),
);
const sentBack = computed(() => props.parts.sent_back.flatMap((item) => item.tasks));
const passed = computed(() => {
    const groups = new Map<string, TriageItem[]>();
    for (const item of props.parts.passed_over) {
        const reason = item.fields.reason ?? 'Other reasons';
        groups.set(reason, [...(groups.get(reason) ?? []), item]);
    }
    return [...groups].map(([reason, items]) => ({ reason, items, tasks: items.flatMap((item) => item.tasks) }));
});
const seen = computed(() => props.parts.seen.filter((item) => item.text !== 'None.'));
const held = computed(() => props.parts.held.filter((item) => item.text !== 'None.'));
</script>

<template>
    <div class="panel triage stack gap-6">
        <div class="columns">
            <section v-for="column in columns" :key="column.label" class="stack gap-4" :aria-label="column.label">
                <h3 class="p-label caps">{{ column.label }}</h3>
                <ul class="plain stack gap-4">
                    <li v-for="(item, i) in column.items" :key="i" class="stack gap-2">
                        <p class="p-body">
                            <code class="p-caption num id">{{ item.tasks.join(' ') }}</code>
                            <span class="strong">{{ item.fields.short ?? item.text }}</span>
                        </p>
                        <p v-if="item.fields.run" class="p-caption">{{ item.fields.run }}</p>
                    </li>
                </ul>
            </section>
            <section v-if="sentBack.length" class="stack gap-4" aria-label="Sent back">
                <h3 class="p-label caps">Sent back</h3>
                <p class="p-body ids">
                    <a v-for="task in sentBack" :key="task" :href="anchors[task] ?? '#questions'" class="num">{{ task }}</a>
                </p>
                <p class="p-caption">In Questions above</p>
            </section>
        </div>

        <section v-if="passed.length || seen.length || held.length" class="stack gap-2" aria-label="Passed over">
            <h3 class="p-label caps">Passed over</h3>
            <div class="groups">
                <details v-for="group in passed" :key="group.reason" class="disclosure">
                    <summary class="group">
                        <Icon name="chevron" class="turn" />
                        <span class="p-body strong">{{ group.reason }}</span>
                        <code class="p-caption num id">{{ group.tasks.join(' ') }}</code>
                    </summary>
                    <ul class="plain reasons stack gap-3">
                        <li v-for="(item, i) in group.items" :key="i" class="p-caption"><CodeText :text="item.text" /></li>
                    </ul>
                </details>
                <details v-for="list in [{ label: 'Seen, not a task', items: seen }, { label: 'Held', items: held }].filter((l) => l.items.length)" :key="list.label" class="disclosure">
                    <summary class="group">
                        <Icon name="chevron" class="turn" />
                        <span class="p-body strong">{{ list.label }}</span>
                        <span class="p-caption num">{{ list.items.length }}</span>
                    </summary>
                    <ul class="plain reasons stack gap-3">
                        <li v-for="(item, i) in list.items" :key="i" class="p-caption"><CodeText :text="item.text" /></li>
                    </ul>
                </details>
            </div>
        </section>

        <details v-if="note" class="disclosure whole">
            <summary class="p-caption"><Icon name="chevron" class="turn" />The note as written</summary>
            <!-- Rendered on the server: raw HTML escaped, unsafe links dropped. -->
            <div class="content p-body" v-html="note" />
        </details>
    </div>
</template>

<style scoped>
.columns {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(min(100%, 14rem), 1fr));
    gap: var(--space-6);
}

.plain {
    margin: 0;
    padding: 0;
    list-style: none;
}

/* All-caps section labels get +0.06em (SPEC 4.5 rule 5). */
.caps {
    margin: 0;
    text-transform: uppercase;
    letter-spacing: 0.06em;
}

.id {
    margin-inline-end: var(--space-2);
    color: var(--fg-muted);
}

.strong {
    font-weight: var(--weight-medium);
}

.ids {
    display: flex;
    flex-wrap: wrap;
    gap: var(--space-3);
    font-family: var(--font-mono);
}

.group {
    padding-block: var(--space-3);
}

.group > .id {
    margin-inline-start: var(--space-4);
}

.reasons {
    padding-block: var(--space-1) var(--space-4);
    padding-inline-start: calc(var(--space-4) + var(--space-3));
}

.whole > summary {
    gap: var(--space-1);
}

.content {
    margin-block-start: var(--space-4);
}

.content :deep(> * + *) {
    margin-block-start: var(--space-4);
}

.content :deep(:is(p, ul, ol, h1, h2, h3)) {
    margin-block-end: 0;
}

.content :deep(li + li) {
    margin-block-start: var(--space-2);
}
</style>
