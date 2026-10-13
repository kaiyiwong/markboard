<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ref } from 'vue';
import { edit, fieldError, refusal, useEditing, type EditResult } from '@/api';
import Icon from '@/Components/Icon.vue';
import type { NightQuestion } from '@/types';

// A work order the chief of staff couldn't scope without Kai: its questions and an answer form. The
// answers go into the order as its `## KAI'S ANSWERS` section; the next triage reads them, and an
// order whose questions are all answered becomes work for the night. The form keeps the etag from the
// moment you start typing, so a change to the order meanwhile is refused, not overwritten.
const props = defineProps<{ question: NightQuestion }>();

const answers = ref(props.question.answers ?? '');
const sentEtag = ref<string | null>(null);
const editing = useEditing();
const busy = ref(false);
const result = ref<EditResult | null>(null);
const saved = ref(false);
const id = `answer-${props.question.project}-${props.question.task}`;

function start() {
    if (!editing.value) {
        sentEtag.value = props.question.etag;
        editing.value = true;
        saved.value = false;
    }
}

async function submit() {
    busy.value = true;
    result.value = await edit('PUT', `/api/v1/night/orders/${props.question.project}/${props.question.task}/answers`, sentEtag.value ?? props.question.etag, {
        answers: answers.value,
    });
    busy.value = false;
    editing.value = false;
    saved.value = result.value.ok;
}
</script>

<template>
    <article class="panel stack gap-4" :aria-labelledby="id + '-title'">
        <header class="stack gap-2">
            <p class="p-label">
                <Link :href="`/projects/${question.project}`">{{ question.project_name }}</Link>
                <span aria-hidden="true"> · </span><span class="num">{{ question.task }}</span>
            </p>
            <h3 :id="id + '-title'" class="p-title">{{ question.title?.replace(/^\S+ \S+: /, '') ?? question.task }}</h3>
        </header>
        <!-- Rendered on the server: raw HTML escaped, unsafe links dropped. -->
        <div v-if="question.missing" class="content p-body" v-html="question.missing" />
        <form class="stack gap-3" @submit.prevent="submit" @focusin="start">
            <div class="field md">
                <label class="p-field" :for="id">Your answers</label>
                <textarea :id="id" v-model="answers" class="input md" rows="4" required :aria-invalid="!!fieldError(result, 'answers')" />
                <p v-if="fieldError(result, 'answers')" class="p-caption msg"><Icon name="alert" /><span>{{ fieldError(result, 'answers') }}</span></p>
            </div>
            <p v-if="refusal(result)" class="p-caption msg" role="alert"><Icon name="alert" /><span>{{ refusal(result) }}</span></p>
            <p v-else-if="saved" class="p-caption" role="status">Saved in the work order. The next triage reads it.</p>
            <div class="actions md">
                <button type="submit" class="btn md btn-primary hit" :disabled="busy || answers.trim() === ''">
                    <span class="lbl">{{ question.answers ? 'Update answers' : 'Save answers' }}</span>
                </button>
            </div>
        </form>
    </article>
</template>
