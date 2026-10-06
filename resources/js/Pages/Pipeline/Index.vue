<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import FileErrors from '@/Components/FileErrors.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import type { Board, PipelineRow, Stage } from '@/types';

defineOptions({ layout: AppLayout });

defineProps<{
    boards: Board[];
    stages: { open: Stage[]; closed: Stage[] };
}>();

const inStage = (rows: PipelineRow[], stage: Stage) => rows.filter((row) => row.stage === stage);
const closed = (rows: PipelineRow[], stages: Stage[]) => rows.filter((row) => row.stage !== null && stages.includes(row.stage));
const unknown = (rows: PipelineRow[]) => rows.filter((row) => row.stage === null);
const label = (stage: string) => stage.charAt(0).toUpperCase() + stage.slice(1);
</script>

<template>
    <Head title="Pipeline" />
    <div class="stack gap-8">
        <h1 class="p-headline">Pipeline</h1>

        <div v-if="!boards.length" class="stack gap-3">
            <h2 class="p-title">No pipelines yet</h2>
            <p class="p-body">A board appears here for every registered project whose folder has a pipeline.md.</p>
        </div>

        <section v-for="board in boards" :key="board.project.id" class="stack gap-5" :aria-labelledby="`board-${board.project.id}`">
            <h2 :id="`board-${board.project.id}`" class="p-title">
                <Link :href="`/projects/${board.project.id}`" class="link-quiet">{{ board.project.name }}</Link>
            </h2>
            <FileErrors :file="board.file" name="pipeline.md" />

            <div class="grid columns">
                <section v-for="stage in stages.open" :key="stage" class="column stack gap-3" :aria-label="label(stage)">
                    <h3 class="p-label heading">{{ label(stage) }} <span class="num">{{ inStage(board.rows, stage).length }}</span></h3>
                    <ul class="cards stack gap-3">
                        <li v-for="row in inStage(board.rows, stage)" :key="row.position" class="card stack gap-3">
                            <p class="p-body"><strong>{{ row.company }}</strong></p>
                            <p class="p-caption">{{ row.role }}</p>
                            <p class="p-body">{{ row.next_action }}</p>
                            <p v-if="row.date" class="p-label num">{{ row.date }}</p>
                        </li>
                    </ul>
                    <p v-if="!inStage(board.rows, stage).length" class="p-caption">Nothing {{ stage }}.</p>
                </section>
            </div>

            <details v-if="closed(board.rows, stages.closed).length" class="stack gap-3">
                <summary class="p-label">Closed <span class="num">{{ closed(board.rows, stages.closed).length }}</span></summary>
                <ul class="ledger">
                    <li v-for="row in closed(board.rows, stages.closed)" :key="row.position" class="closed">
                        <span class="p-body"><strong>{{ row.company }}</strong> · {{ row.role }}</span>
                        <span class="chip"><span class="p-label">{{ row.stage }}</span></span>
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
        </section>
    </div>
</template>

<style scoped>
/* Four open stages: one column on a phone, two from 640, four from 1024 (spans of the page grid). */
.columns {
    row-gap: var(--space-5);
}

.column {
    grid-column: span 4;
    min-width: 0;
}

@media (min-width: 1024px) {
    .column {
        grid-column: span 3;
    }
}

.heading {
    display: flex;
    gap: var(--space-2);
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
</style>
