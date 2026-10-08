<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { edit, refusal, useEditing, type EditResult } from '@/api';
import ConflictPanel from '@/Components/ConflictPanel.vue';
import FileErrors from '@/Components/FileErrors.vue';
import Icon from '@/Components/Icon.vue';
import PipelineRowForm from '@/Components/PipelineRowForm.vue';
import { relativeDate, useToday } from '@/dates';
import AppLayout from '@/Layouts/AppLayout.vue';
import type { Board, PipelineRow, Stage } from '@/types';

defineOptions({ layout: AppLayout });

const props = defineProps<{
    boards: Board[];
    stages: { open: Stage[]; closed: Stage[] };
}>();

const inStage = (rows: PipelineRow[], stage: Stage) => rows.filter((row) => row.stage === stage);
const closed = (rows: PipelineRow[], stages: Stage[]) => rows.filter((row) => row.stage !== null && stages.includes(row.stage));
const unknown = (rows: PipelineRow[]) => rows.filter((row) => row.stage === null);
const label = (stage: string) => stage.charAt(0).toUpperCase() + stage.slice(1);
const etagOf = (board: Board) => (board.file.editable ? board.file.etag : null);
const allStages = () => [...props.stages.open, ...props.stages.closed];
const today = useToday();
const when = (date: string) => relativeDate(today.value, date);

// The page's key element: open applications across every board, then the next action that's due.
const summary = computed(() => {
    const open = props.boards.flatMap((board) => board.rows.filter((row) => row.stage !== null && props.stages.open.includes(row.stage)));
    const next = open.filter((row) => row.date !== null).sort((a, b) => a.date!.localeCompare(b.date!))[0] ?? null;
    return { open: open.length, next };
});

// One form open at a time: a card being edited (project and row position), or a board's Add row.
const open = ref<{ project: string; position: number | null } | null>(null);
const isOpen = (board: Board, position: number | null) => open.value?.project === board.project.id && open.value.position === position;

// Drag a card to another column to change its stage. The etag is the one on screen when the drag
// began; polling waits until it ends.
const dragging = ref<{ project: string; row: PipelineRow; etag: string } | null>(null);
const over = ref<string | null>(null);
const dragEditing = useEditing();
const dropResult = ref<{ project: string; result: EditResult } | null>(null);

function onDragStart(event: DragEvent, board: Board, row: PipelineRow) {
    const etag = etagOf(board);
    if (etag === null) {
        return;
    }
    dragging.value = { project: board.project.id, row, etag };
    dragEditing.value = true;
    event.dataTransfer?.setData('text/plain', row.company);
}

function onDragOver(event: DragEvent, board: Board, stage: Stage) {
    if (dragging.value?.project === board.project.id && dragging.value.row.stage !== stage) {
        event.preventDefault();
        over.value = `${board.project.id}:${stage}`;
    }
}

async function onDrop(board: Board, stage: Stage) {
    const drag = dragging.value;
    onDragEnd();
    if (!drag || drag.project !== board.project.id || drag.row.stage === stage) {
        return;
    }
    const result = await edit('PATCH', `/api/v1/projects/${board.project.id}/pipeline/rows/${drag.row.position}`, drag.etag, { stage });
    dropResult.value = { project: board.project.id, result };
}

function onDragEnd() {
    dragging.value = null;
    over.value = null;
    dragEditing.value = false;
}
</script>

<template>
    <Head title="Pipeline" />
    <div class="stack gap-6">
        <h1 class="p-headline">Pipeline</h1>

        <section v-if="boards.length" class="panel summary" aria-labelledby="pipeline-key">
            <div class="stack gap-3">
                <p id="pipeline-key" class="p-label">Open applications</p>
                <p class="p-key">{{ summary.open }}</p>
            </div>
            <div v-if="summary.next" class="stack gap-3">
                <p class="p-label">Next action</p>
                <p class="p-title">{{ summary.next.next_action }}</p>
                <p class="p-caption">
                    {{ summary.next.company }} ·
                    <span :class="when(summary.next.date!).tone && `is-${when(summary.next.date!).tone}`">{{ when(summary.next.date!).text }}</span>
                </p>
            </div>
        </section>

        <div v-if="!boards.length" class="stack gap-3">
            <h2 class="p-title">No pipelines yet</h2>
            <p class="p-body">A board appears here for every registered project whose folder has a pipeline.md.</p>
        </div>

        <section v-for="board in boards" :key="board.project.id" class="panel stack gap-5 board" :aria-labelledby="`board-${board.project.id}`">
            <h2 :id="`board-${board.project.id}`" class="p-title">
                <Link :href="`/projects/${board.project.id}`" class="link-quiet">{{ board.project.name }}</Link>
            </h2>
            <ConflictPanel v-for="conflict in board.conflicts" :key="conflict.id" :conflict="conflict" name="pipeline.md" />
            <FileErrors :file="board.file" name="pipeline.md" />
            <p v-if="dropResult?.project === board.project.id && refusal(dropResult.result)" class="p-caption msg" role="alert">
                <Icon name="alert" /><span>{{ refusal(dropResult.result) }}</span>
            </p>

            <div class="grid columns">
                <section
                    v-for="stage in stages.open"
                    :key="stage"
                    class="column stack gap-3"
                    :class="{ 'is-drop-target': over === `${board.project.id}:${stage}` }"
                    :aria-label="label(stage)"
                    @dragover="onDragOver($event, board, stage)"
                    @dragleave="over = null"
                    @drop.prevent="onDrop(board, stage)"
                >
                    <h3 class="p-label heading">{{ label(stage) }} <span class="num count">{{ inStage(board.rows, stage).length }}</span></h3>
                    <ul class="cards stack gap-3">
                        <li
                            v-for="row in inStage(board.rows, stage)"
                            :key="row.position"
                            class="card stack gap-3"
                            :class="{ 'is-editable': etagOf(board) !== null && !isOpen(board, row.position) }"
                            :draggable="etagOf(board) !== null && !isOpen(board, row.position)"
                            @dragstart="onDragStart($event, board, row)"
                            @dragend="onDragEnd"
                            @click="etagOf(board) !== null && !isOpen(board, row.position) && (open = { project: board.project.id, position: row.position })"
                        >
                            <PipelineRowForm
                                v-if="isOpen(board, row.position)"
                                :project-id="board.project.id"
                                :etag="etagOf(board)!"
                                :stages="allStages()"
                                :row="row"
                                @close="open = null"
                            />
                            <template v-else>
                                <p class="p-body">
                                    <button v-if="etagOf(board) !== null" type="button" class="company" :aria-label="`Edit ${row.company}`">
                                        <strong>{{ row.company }}</strong>
                                    </button>
                                    <strong v-else>{{ row.company }}</strong>
                                </p>
                                <p class="p-caption">{{ row.role }}</p>
                                <p class="p-body">{{ row.next_action }}</p>
                                <div v-if="row.date" class="when">
                                    <span
                                        class="chip"
                                        :class="{ 'chip-danger': when(row.date).tone === 'danger', 'chip-warning': when(row.date).tone === 'warning' }"
                                        :title="row.date"
                                    >
                                        <span class="p-label">{{ when(row.date).text }}</span>
                                    </span>
                                </div>
                            </template>
                        </li>
                    </ul>
                    <p v-if="!inStage(board.rows, stage).length" class="p-caption">Nothing {{ stage }}.</p>
                </section>
            </div>

            <details v-if="closed(board.rows, stages.closed).length" class="stack gap-3">
                <summary class="p-label">Closed <span class="num">{{ closed(board.rows, stages.closed).length }}</span></summary>
                <ul class="ledger">
                    <li v-for="row in closed(board.rows, stages.closed)" :key="row.position" class="closed">
                        <PipelineRowForm
                            v-if="isOpen(board, row.position)"
                            class="closed-form"
                            :project-id="board.project.id"
                            :etag="etagOf(board)!"
                            :stages="allStages()"
                            :row="row"
                            @close="open = null"
                        />
                        <template v-else>
                            <span class="p-body">
                                <button
                                    v-if="etagOf(board) !== null"
                                    type="button"
                                    class="company"
                                    :aria-label="`Edit ${row.company}`"
                                    @click="open = { project: board.project.id, position: row.position }"
                                >
                                    <strong>{{ row.company }}</strong>
                                </button>
                                <strong v-else>{{ row.company }}</strong>
                                · {{ row.role }}
                            </span>
                            <span class="chip"><span class="p-label">{{ row.stage }}</span></span>
                        </template>
                    </li>
                </ul>
            </details>

            <div v-if="unknown(board.rows).length" class="stack gap-3">
                <h3 class="p-label">Stage not recognised</h3>
                <ul class="ledger">
                    <li v-for="row in unknown(board.rows)" :key="row.position" class="closed">
                        <span class="p-body"><strong>{{ row.company }}</strong> · {{ row.role }}</span>
                    </li>
                </ul>
            </div>

            <template v-if="etagOf(board) !== null">
                <div v-if="isOpen(board, null)" class="panel add">
                    <PipelineRowForm :project-id="board.project.id" :etag="etagOf(board)!" :stages="allStages()" @close="open = null" />
                </div>
                <div v-else class="actions md start">
                    <button type="button" class="btn md btn-secondary hit" @click="open = { project: board.project.id, position: null }">
                        <span class="lbl">Add row</span>
                    </button>
                </div>
            </template>
        </section>
    </div>
</template>

<style scoped>
.summary {
    display: grid;
    gap: var(--space-6);
    grid-template-columns: repeat(auto-fit, minmax(min(100%, 16rem), 1fr));
    align-items: start;
}

.is-danger {
    color: var(--danger-fg-strong);
}

.is-warning {
    color: var(--warning-fg-strong);
}

/* Four open stages: one column on a phone, two from 640, four from 1024 (spans of the page grid). */
.columns {
    row-gap: var(--space-4);
}

/* A lane: the canvas tint inside the white board, so the white cards stand out. */
.column {
    grid-column: span 4;
    min-width: 0;
    padding: var(--space-3);
    border-radius: var(--container-radius);
    background: var(--bg-canvas);
}

@media (min-width: 1024px) {
    .column {
        grid-column: span 3;
    }
}

/* A column a dragged card can drop into: the accent outline shows where it will land. */
.is-drop-target {
    outline: 2px solid var(--accent-strong);
    outline-offset: var(--space-1);
}

.heading {
    display: flex;
    justify-content: space-between;
    gap: var(--space-2);
    padding: var(--space-1) var(--space-1) 0;
}

.count {
    color: var(--fg-muted);
}

.when {
    display: flex;
}

.cards {
    margin: 0;
    padding: 0;
    list-style: none;
}

.card {
    padding: var(--space-4);
    border: 1px solid var(--panel-border);
    border-radius: var(--container-radius);
    background: var(--bg-surface);
    overflow-wrap: anywhere;
}

.card.is-editable {
    cursor: grab;
}

@media (hover: hover) {
    .card.is-editable:hover {
        border-color: var(--border-control);
    }
}

/* The company name is the card's keyboard way in; a click anywhere on the card does the same. */
.company {
    padding: 0;
    border: 0;
    background: none;
    font: inherit;
    color: inherit;
    text-align: start;
    cursor: pointer;
}

summary {
    cursor: pointer;
}

.closed {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: var(--space-2) var(--space-3);
    padding-block: var(--space-3);
}

.closed-form {
    flex: 1 1 100%;
}

.add {
    max-inline-size: 65ch;
}

.start {
    justify-content: flex-start;
}
</style>
