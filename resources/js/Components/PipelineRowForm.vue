<script setup lang="ts">
import { nextTick, onMounted, ref, useTemplateRef } from 'vue';
import { edit, fieldError, refusal, useEditing, type EditResult } from '@/api';
import Icon from '@/Components/Icon.vue';
import type { PipelineRow, Stage } from '@/types';

// Edits one pipeline row, or adds one when no row is given. The etag is the one on screen when the
// form opened; an edit sends only the fields that changed.
const props = defineProps<{ projectId: string; etag: string; stages: Stage[]; row?: PipelineRow }>();
const emit = defineEmits<{ close: [] }>();

const sentEtag = props.etag;
const editing = useEditing();
editing.value = true;
const busy = ref(false);
const result = ref<EditResult | null>(null);
const values = (row?: PipelineRow) => ({
    company: row?.company ?? '',
    role: row?.role ?? '',
    stage: row?.stage ?? 'applied',
    next_action: row?.next_action ?? '',
    date: row?.date ?? '',
});
const fields = ref(values(props.row));
const formEl = useTemplateRef<HTMLFormElement>('formEl');
const id = (field: string) => `${props.projectId}-${props.row?.position ?? 'new'}-${field}`;
const label = (stage: string) => stage.charAt(0).toUpperCase() + stage.slice(1);

onMounted(() => nextTick(() => formEl.value?.querySelector<HTMLElement>('input')?.focus()));

async function submit() {
    const before = values(props.row);
    const changed = props.row
        ? Object.fromEntries(Object.entries(fields.value).filter(([key, value]) => value !== before[key as keyof typeof before]))
        : fields.value;
    busy.value = true;
    result.value = props.row
        ? await edit('PATCH', `/api/v1/projects/${props.projectId}/pipeline/rows/${props.row.position}`, sentEtag, changed)
        : await edit('POST', `/api/v1/projects/${props.projectId}/pipeline/rows`, sentEtag, changed);
    busy.value = false;
    if (result.value.ok || result.value.status === 412) {
        editing.value = false;
        emit('close');
    }
}

function close() {
    editing.value = false;
    emit('close');
}
</script>

<template>
    <form ref="formEl" class="stack gap-4" :aria-label="row ? `Edit ${row.company}` : 'Add row'" @submit.prevent="submit" @keydown.esc="close">
        <div v-for="field in ['company', 'role', 'next_action'] as const" :key="field" class="field md">
            <label class="p-field" :for="id(field)">{{ field === 'next_action' ? 'Next action' : label(field) }}</label>
            <input :id="id(field)" v-model="fields[field]" class="input md" required :aria-invalid="!!fieldError(result, field)" />
            <p v-if="fieldError(result, field)" class="p-caption msg"><Icon name="alert" /><span>{{ fieldError(result, field) }}</span></p>
        </div>
        <div class="field md">
            <label class="p-field" :for="id('stage')">Stage</label>
            <span class="select">
                <select :id="id('stage')" v-model="fields.stage" class="input md">
                    <option v-for="stage in stages" :key="stage" :value="stage">{{ label(stage) }}</option>
                </select>
                <Icon name="chevron" />
            </span>
        </div>
        <div class="field md">
            <label class="p-field" :for="id('date')">Date <span class="key">(optional)</span></label>
            <input :id="id('date')" v-model="fields.date" type="date" class="input md" :aria-invalid="!!fieldError(result, 'date')" />
            <p v-if="fieldError(result, 'date')" class="p-caption msg"><Icon name="alert" /><span>{{ fieldError(result, 'date') }}</span></p>
        </div>
        <p v-if="refusal(result)" class="p-caption msg" role="alert"><Icon name="alert" /><span>{{ refusal(result) }}</span></p>
        <div class="actions md">
            <button type="button" class="btn md btn-secondary hit" @click="close"><span class="lbl">Close</span></button>
            <button type="submit" class="btn md btn-primary hit" :disabled="busy"><span class="lbl">{{ row ? 'Save changes' : 'Add row' }}</span></button>
        </div>
    </form>
</template>
