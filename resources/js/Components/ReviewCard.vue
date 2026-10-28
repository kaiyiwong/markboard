<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import type { ReviewItem } from '@/types';

// One task waiting on Kai. With a card: its question, then the images to judge from (each option
// for a pick, old then new for an approve) and the answers. Without one: the task as written.
// The answer buttons are inert until the answer actions land (Markboard T18, step 3).
const props = defineProps<{ item: ReviewItem }>();

const card = computed(() => props.item.card);
const since = computed(() =>
    props.item.since
        ? new Intl.DateTimeFormat('en', { month: 'short', day: 'numeric', timeZone: 'UTC' }).format(new Date(`${props.item.since}T00:00:00Z`))
        : null,
);
const taskLink = computed(() => `/projects/${props.item.project.id}/tasks/${props.item.task.task_id}`);
</script>

<template>
    <article class="panel stack gap-6" aria-labelledby="review-question">
        <div class="stack gap-2">
            <p class="p-caption meta">
                <Link :href="`/projects/${item.project.id}`" class="link-quiet">{{ item.project.name }}</Link>
                <Link :href="taskLink" class="link-quiet num">{{ item.task.task_id }}</Link>
                <span v-if="card">{{ item.task.title }}</span>
                <span v-if="since" class="num">since {{ since }}</span>
            </p>
            <h2 id="review-question" class="p-headline">{{ card?.question ?? item.task.title }}</h2>
        </div>

        <ul v-if="card?.answer === 'pick'" class="options" role="list">
            <li v-for="(image, i) in card.images" :key="image.url" class="stack gap-3">
                <a :href="image.url" target="_blank" rel="noopener" class="shot">
                    <img :src="image.url" :alt="image.label" />
                </a>
                <div class="option-foot">
                    <span class="p-body">{{ image.label }}</span>
                    <button type="button" class="btn btn-secondary md hit" disabled>
                        <span class="lbl">Pick {{ i + 1 }}</span>
                    </button>
                </div>
            </li>
        </ul>

        <div v-else-if="card?.answer === 'approve'" class="compare">
            <figure v-for="(image, i) in card.images" :key="image.url" class="stack gap-3" :class="{ latest: i === card.images.length - 1 }">
                <figcaption class="p-label">{{ image.label }}</figcaption>
                <a :href="image.url" target="_blank" rel="noopener" class="shot">
                    <img :src="image.url" :alt="image.label" />
                </a>
            </figure>
        </div>

        <template v-else-if="card?.answer === 'choose'">
            <ul v-if="card.images.length" class="options" role="list">
                <li v-for="image in card.images" :key="image.url" class="stack gap-3">
                    <a :href="image.url" target="_blank" rel="noopener" class="shot">
                        <img :src="image.url" :alt="image.label" />
                    </a>
                    <span class="p-caption">{{ image.label }}</span>
                </li>
            </ul>
            <div class="choices">
                <button v-for="choice in card.choices" :key="choice" type="button" class="btn btn-secondary md hit" disabled>
                    <span class="lbl">{{ choice }}</span>
                </button>
            </div>
        </template>

        <div v-else class="stack gap-3">
            <ul v-if="item.card_errors.length" class="stack gap-1 errors" aria-label="Card errors">
                <li v-for="error in item.card_errors" :key="error" class="p-caption"><code>review/{{ item.task.task_id }}.json</code> {{ error }}</li>
            </ul>
            <p v-if="item.task.proof" class="p-body">{{ item.task.proof }}</p>
            <ul v-if="item.task.notes.length" class="stack gap-2 notes">
                <li v-for="note in item.task.notes" :key="note" class="p-body">{{ note }}</li>
            </ul>
        </div>

        <footer class="stack gap-4 foot">
            <div v-if="card?.answer === 'approve'" class="choices">
                <button type="button" class="btn btn-primary md hit" disabled><span class="lbl">Approve</span></button>
                <button type="button" class="btn btn-secondary md hit" disabled><span class="lbl">Redo</span></button>
            </div>
            <label class="field">
                <span class="field-head p-caption">Note</span>
                <textarea class="input" rows="2" disabled />
            </label>
            <div class="choices">
                <button type="button" class="btn btn-ghost md hit" disabled><span class="lbl">Done</span></button>
                <button type="button" class="btn btn-ghost md hit" disabled><span class="lbl">Drop</span></button>
            </div>
        </footer>
    </article>
</template>

<style scoped>
.meta {
    display: flex;
    flex-wrap: wrap;
    gap: var(--space-1) var(--space-3);
}

/* Options side by side, as large as the column allows: two across on a desk, one on a phone. */
.options {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(16rem, 1fr));
    gap: var(--space-6) var(--space-5);
    margin: 0;
    padding: 0;
    list-style: none;
}

.option-foot {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: var(--space-3);
}

/* Old and new at the same size, so the change is what the eye compares. */
.compare {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(16rem, 1fr));
    gap: var(--space-5);
}

.compare figure {
    margin: 0;
}

.compare .latest figcaption {
    color: var(--accent-fg-strong);
}

.shot {
    display: block;
    overflow: hidden;
    border: 1px solid var(--border-subtle);
    border-radius: var(--radius-2);
    background: var(--bg-subtle);
}

.shot img {
    display: block;
    inline-size: 100%;
    block-size: auto;
}

.choices {
    display: flex;
    flex-wrap: wrap;
    gap: var(--space-3);
}

.errors {
    margin: 0;
    padding: 0;
    list-style: none;
    color: var(--danger-fg-strong);
}

.notes {
    margin: 0;
    padding-inline-start: var(--space-5);
}

.foot {
    padding-block-start: var(--space-5);
    border-top: 1px solid var(--border-subtle);
}
</style>
