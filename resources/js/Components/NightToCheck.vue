<script setup lang="ts">
import { computed, ref } from 'vue';
import CodeText from '@/Components/CodeText.vue';
import Icon from '@/Components/Icon.vue';
import type { NightConfirm } from '@/types';

// What the verifier asks a human to check by eye before merging: its `## Confirm:` blocks (rows of
// cells, such as an English term and its translation) and its "Look at" points. Each row has a
// "Looks right" tick. The ticks are a reviewer's notes, not the hub's data, so they stay in this
// browser, keyed by the branch head: a branch that moves starts unticked.
const props = defineProps<{ storageKey: string; confirm: NightConfirm[]; lookAt: string[] }>();

function load(): Set<string> {
    try {
        const saved: unknown = JSON.parse(localStorage.getItem(props.storageKey) ?? '[]');
        return new Set(Array.isArray(saved) ? saved.filter((key): key is string => typeof key === 'string') : []);
    } catch {
        return new Set();
    }
}

const ticked = ref(load());

function toggle(key: string) {
    const next = new Set(ticked.value);
    if (!next.delete(key)) {
        next.add(key);
    }
    ticked.value = next;
    try {
        localStorage.setItem(props.storageKey, JSON.stringify([...next]));
    } catch {
        // Storage blocked (a private window): the ticks last until the page reloads.
    }
}

const blocks = computed(() =>
    props.confirm.map((block, b) => {
        const groups = block.groups.map((group, g) => ({
            label: group.label,
            rows: group.rows.map((cells, r) => ({ key: `c${b}.${g}.${r}`, first: cells[0] ?? '', rest: cells.slice(1).join(' · ') })),
        }));
        const keys = groups.flatMap((group) => group.rows.map((row) => row.key));
        return { ...block, groups, keys, done: keys.filter((key) => ticked.value.has(key)).length };
    }),
);
const looks = computed(() => props.lookAt.map((text, i) => ({ key: `l${i}`, text })));
const looksDone = computed(() => looks.value.filter((look) => ticked.value.has(look.key)).length);
</script>

<template>
    <div class="stack gap-6">
        <section v-for="(block, b) in blocks" :key="b" class="stack gap-4" :aria-label="block.title">
            <h4 class="block-head">
                <span class="p-title">{{ block.title }}</span>
                <span class="p-caption num">{{ block.done }} of {{ block.keys.length }} confirmed</span>
            </h4>
            <div class="table" :class="{ pairs: block.groups.some((group) => group.rows.some((row) => row.rest)) }">
                <div v-if="block.columns.length" class="row cols" aria-hidden="true">
                    <span v-for="column in block.columns.slice(0, 2)" :key="column" class="p-label caps">{{ column }}</span>
                </div>
                <template v-for="(group, g) in block.groups" :key="g">
                    <p v-if="group.label" class="p-caption group">{{ group.label }}</p>
                    <div v-for="row in group.rows" :key="row.key" class="row">
                        <span class="p-body first" :class="{ wide: !row.rest }"><CodeText :text="row.first" /></span>
                        <span v-if="row.rest" class="p-title value"><CodeText :text="row.rest" /></span>
                        <button
                            type="button"
                            role="checkbox"
                            class="look"
                            :aria-checked="ticked.has(row.key)"
                            :aria-label="`Looks right: ${row.first}${row.rest ? `, ${row.rest}` : ''}`"
                            @click="toggle(row.key)"
                        >
                            <span class="ring"><Icon v-if="ticked.has(row.key)" name="check" /></span>
                            <span class="p-caption">Looks right</span>
                        </button>
                    </div>
                </template>
            </div>
            <p v-if="block.note" class="p-caption"><CodeText :text="block.note" /></p>
        </section>

        <section v-if="looks.length" class="stack gap-4" aria-label="Look at">
            <h4 class="block-head">
                <span class="p-title">{{ blocks.length ? 'Also look at' : 'Look at before merging' }}</span>
                <span class="p-caption num">{{ looksDone }} of {{ looks.length }} confirmed</span>
            </h4>
            <div class="table">
                <div v-for="look in looks" :key="look.key" class="row">
                    <span class="p-body first"><CodeText :text="look.text" /></span>
                    <button type="button" role="checkbox" class="look" :aria-checked="ticked.has(look.key)" :aria-label="`Looks right: ${look.text}`" @click="toggle(look.key)">
                        <span class="ring"><Icon v-if="ticked.has(look.key)" name="check" /></span>
                        <span class="p-caption">Looks right</span>
                    </button>
                </div>
            </div>
        </section>
    </div>
</template>

<style scoped>
.block-head {
    display: flex;
    flex-wrap: wrap;
    align-items: baseline;
    gap: var(--space-2) var(--space-3);
    margin: 0;
}

.block-head > .p-caption {
    font-weight: var(--weight-regular);
}

/* Rows: the first cell, the value (larger, as it's the thing to judge), then the tick at the end. */
.table {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    column-gap: var(--space-5);
}

.table.pairs {
    grid-template-columns: minmax(6rem, 12rem) minmax(0, 1fr) auto;
}

.row {
    display: grid;
    grid-column: 1 / -1;
    grid-template-columns: subgrid;
    align-items: center;
    padding-block: var(--space-3);
}

.table.pairs .wide {
    grid-column: span 2;
}

.row > .look {
    grid-column: -2;
}

.cols {
    padding-block: 0 var(--space-2);
}

/* All-caps column headings get +0.06em (SPEC 4.5 rule 5). */
.caps {
    text-transform: uppercase;
    letter-spacing: 0.06em;
}

.group {
    grid-column: 1 / -1;
    padding-block: var(--space-4) var(--space-1);
}

.value {
    font-weight: var(--weight-medium);
}

.look {
    display: inline-flex;
    align-items: center;
    gap: var(--space-2);
    min-block-size: var(--control-h-sm);
    padding-inline: var(--space-2);
    border: 0;
    border-radius: var(--control-radius);
    background: none;
    color: var(--fg-default);
    cursor: pointer;
}

.look .p-caption {
    color: var(--fg-default);
}

/* The tick's ring uses the control border (3:1); ticked, it fills with the accent. */
.ring {
    display: inline-grid;
    place-items: center;
    inline-size: var(--space-4);
    block-size: var(--space-4);
    border: 1.5px solid var(--border-control);
    border-radius: var(--radius-full);
    --icon: var(--space-3);
}

.look[aria-checked='true'] .ring {
    border-color: var(--accent-solid);
    background: var(--accent-solid);
    color: var(--fg-on-accent);
}

.look[aria-checked='true'] .p-caption {
    color: var(--accent-fg-strong);
}

@media (hover: hover) {
    .look:hover {
        background: var(--bg-control-hover);
    }
}

/* On a phone the tick drops under the row's text. */
@media (max-width: 599px) {
    .table,
    .table.pairs {
        grid-template-columns: minmax(0, 1fr);
    }

    .row {
        gap: var(--space-2);
    }

    .row > .look {
        grid-column: 1;
        justify-self: start;
        margin-inline-start: calc(-1 * var(--space-2));
    }
}
</style>
