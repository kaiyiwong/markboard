<script setup lang="ts">
import { ref } from 'vue';
import { edit, fieldError, refusal, useEditing, type EditResult } from '@/api';
import Icon from '@/Components/Icon.vue';

// The "Add task" form at the bottom of Up next. It keeps the etag from the moment you start typing,
// so a change on disk while you type is a conflict, not an overwrite.
const props = defineProps<{ projectId: string; etag: string }>();

const blank = () => ({ title: '', due: '', proof: '' });
const fields = ref(blank());
const sentEtag = ref<string | null>(null);
const editing = useEditing();
const busy = ref(false);
const result = ref<EditResult | null>(null);

function start() {
    if (!editing.value) {
        sentEtag.value = props.etag;
        editing.value = true;
    }
}

async function submit() {
    const { title, due, proof } = fields.value;
    busy.value = true;
    result.value = await edit('POST', `/api/v1/projects/${props.projectId}/tasks`, sentEtag.value ?? props.etag, {
        title,
        ...(due === '' ? {} : { due }),
        ...(proof.trim() === '' ? {} : { proof }),
    });
    busy.value = false;
    if (result.value.ok || result.value.status === 412) {
        fields.value = blank();
        editing.value = false;
    }
}

function reset() {
    fields.value = blank();
    result.value = null;
    editing.value = false;
}
</script>

<template>
    <form class="add stack gap-4" aria-label="Add task" @submit.prevent="submit" @focusin="start" @keydown.esc="reset">
        <div class="fields">
            <div class="field md title">
                <label class="p-field" :for="`add-${projectId}-title`">New task</label>
                <input :id="`add-${projectId}-title`" v-model="fields.title" class="input md" required :aria-invalid="!!fieldError(result, 'title')" />
                <p v-if="fieldError(result, 'title')" class="p-caption msg"><Icon name="alert" /><span>{{ fieldError(result, 'title') }}</span></p>
            </div>
            <div class="field md">
                <label class="p-field" :for="`add-${projectId}-due`">Due date <span class="key">(optional)</span></label>
                <input :id="`add-${projectId}-due`" v-model="fields.due" type="date" class="input md" :aria-invalid="!!fieldError(result, 'due')" />
                <p v-if="fieldError(result, 'due')" class="p-caption msg"><Icon name="alert" /><span>{{ fieldError(result, 'due') }}</span></p>
            </div>
            <div class="field md proof">
                <label class="p-field" :for="`add-${projectId}-proof`">Proof <span class="key">(optional)</span></label>
                <input :id="`add-${projectId}-proof`" v-model="fields.proof" class="input md" placeholder="How you'll know it's done" :aria-invalid="!!fieldError(result, 'proof')" />
                <p v-if="fieldError(result, 'proof')" class="p-caption msg"><Icon name="alert" /><span>{{ fieldError(result, 'proof') }}</span></p>
            </div>
        </div>
        <p v-if="refusal(result)" class="p-caption msg" role="alert"><Icon name="alert" /><span>{{ refusal(result) }}</span></p>
        <div class="actions md">
            <button v-if="editing" type="button" class="btn md btn-secondary hit" @click="reset"><span class="lbl">Clear</span></button>
            <button type="submit" class="btn md btn-primary hit" :disabled="busy"><span class="lbl">Add task</span></button>
        </div>
    </form>
</template>

<style scoped>
.add {
    padding-block: var(--space-4);
}

.fields {
    display: flex;
    flex-wrap: wrap;
    gap: var(--space-4);
}

.fields .field {
    flex: 0 1 auto;
}

.fields .title,
.fields .proof {
    flex: 1 1 30ch;
}
</style>
