<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { edit, fieldError, refusal, useEditing, type EditResult } from '@/api';
import type { ReviewItem } from '@/types';

// One task waiting on Kai. With a card: its question, then the images to judge from (each option
// for a pick, old then new for an approve) and the answers. Without one: the task as written.
// Every answer is one write to the project's TASKS.md (docs/spec.md, Review); the note goes with it.
const props = defineProps<{ item: ReviewItem }>();

type Answer = 'pick' | 'choose' | 'approve' | 'redo' | 'reply' | 'done' | 'drop';

const note = ref('');
const busy = ref(false);
const result = ref<EditResult | null>(null);
const typing = useEditing();
watch(note, (text) => (typing.value = text.trim() !== ''));
const off = computed(() => busy.value || props.item.etag === null);

async function send(answer: Answer, value: string | null = null) {
    busy.value = true;
    result.value = await edit('POST', `/api/v1/review/${props.item.project.id}/${props.item.task.task_id}/answer`, props.item.etag, {
        answer,
        value,
        note: note.value.trim() === '' ? null : note.value,
    });
    busy.value = false;
    if (result.value.ok) {
        note.value = '';
    }
}

/** Why the answer didn't go through. A changed file reloads the page, so it can be tried again. */
const problem = computed(() => {
    const r = result.value;
    if (props.item.etag === null) {
        return "This project's TASKS.md isn't in the strict format, so Markboard can't write the answer.";
    }
    if (!r || r.ok) {
        return null;
    }
    if (r.status === 412) {
        return 'TASKS.md changed since the page showed it. The page has the current one now: try again.';
    }
    return fieldError(r, 'note') ?? fieldError(r, 'value') ?? fieldError(r, 'answer') ?? refusal(r);
});

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
                    <button type="button" class="btn btn-secondary md hit" :disabled="off" @click="send('pick', image.label)">
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
                <button v-for="choice in card.choices" :key="choice" type="button" class="btn btn-secondary md hit" :disabled="off" @click="send('choose', choice)">
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
                <button type="button" class="btn btn-primary md hit" :disabled="off" @click="send('approve')"><span class="lbl">Approve</span></button>
                <button type="button" class="btn btn-secondary md hit" :disabled="off" @click="send('redo')"><span class="lbl">Redo</span></button>
            </div>
            <label class="field">
                <span class="field-head p-caption">Note</span>
                <textarea v-model="note" class="input" rows="2" :disabled="off" />
            </label>
            <div class="choices">
                <button type="button" class="btn md hit" :class="card ? 'btn-secondary' : 'btn-primary'" :disabled="off || note.trim() === ''" @click="send('reply')">
                    <span class="lbl">Send</span>
                </button>
                <button type="button" class="btn btn-ghost md hit" :disabled="off" @click="send('done')"><span class="lbl">Done</span></button>
                <button type="button" class="btn btn-ghost md hit" :disabled="off" @click="send('drop')"><span class="lbl">Drop</span></button>
            </div>
            <p v-if="problem" class="p-caption problem" role="alert">{{ problem }}</p>
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

.problem {
    color: var(--danger-fg-strong);
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
