<script setup lang="ts">
import { computed, nextTick, onMounted, ref, useTemplateRef, watch } from 'vue';
import { edit, fieldError, refusal, useEditing, type EditResult } from '@/api';
import Icon from '@/Components/Icon.vue';
import { dueLabel, shortDate, useToday } from '@/dates';
import type { SectionName, Task } from '@/types';

// The signature element (DESIGN.md): a task row with its ID, title, metadata chips and proof line.
// With an editable file (etag not null) it also carries the edits: a checkbox that ticks it (with
// optional evidence), a menu with Edit, Move to and Cancel, a drag handle (arrow keys move it too),
// and Undo for a task in Done. Every form keeps the etag from the moment it opened.
const props = defineProps<{
    task: Task;
    highlighted: boolean;
    projectId: string;
    /** The TASKS.md etag on screen; null when the file is read-only. */
    etag: string | null;
    /** How many tasks share the section, for the arrow-key reorder. */
    sectionSize: number;
    /** Set when the task just moved here from another section: down from one above, up from one below. */
    arrived?: 'down' | 'up' | null;
}>();

// The signature moment: a ticked task lands in Done. It starts offset toward where it came from and
// tinted, and transitions into place on the next frames (a transition, so a second tick can interrupt it).
const arriving = ref<'down' | 'up' | null>(null);
onMounted(() => {
    if (props.arrived) {
        arriving.value = props.arrived;
        requestAnimationFrame(() => requestAnimationFrame(() => (arriving.value = null)));
    }
});

type Form = 'tick' | 'edit' | 'waiting' | 'undo-waiting';
const OPEN_SECTIONS: SectionName[] = ['Up next', 'In progress', 'Waiting on'];

const form = ref<Form | null>(null);
const editing = useEditing();
watch(form, (now) => (editing.value = now !== null));
const sentEtag = ref<string | null>(null);
const busy = ref(false);
const result = ref<EditResult | null>(null);
const dragReady = ref(false);

const fields = ref({ evidence: '', waiting: '', title: '', due: '', proof: '', notes: '' });
const formEl = useTemplateRef<HTMLFormElement>('formEl');
const menu = useTemplateRef<HTMLElement>('menu');

const isOpen = computed(() => props.task.section !== 'Done');
const today = useToday();

// The due date is the one fact that can need action, so it's the one chip, toned by how close it is
// (only while the task is open). The rest is plain text in the file's order: "Started Oct 1 · Waiting on Ana".
const DATE_KEYS = ['due', 'started', 'since', 'done', 'cancelled'];
const LABELS: Record<string, string> = { due: 'Due', started: 'Started', since: 'since', done: 'Done', cancelled: 'Cancelled', waiting: 'Waiting on', evidence: 'Evidence', from: 'from' };
const due = computed(() => (props.task.due && isOpen.value ? dueLabel(today.value, props.task.due) : null));
const facts = computed(() =>
    props.task.metadata
        .filter(([key]) => !(key === 'due' && due.value))
        .map(([key, value]) => ({
            key: LABELS[key] ?? key,
            value: DATE_KEYS.includes(key) && /^\d{4}-\d{2}-\d{2}$/.test(value) ? shortDate(today.value, value) : value,
            title: value,
        })),
);
const moveTargets = computed(() => OPEN_SECTIONS.filter((section) => section !== props.task.section));
const hasWaiting = computed(() => props.task.metadata.some(([key]) => key === 'waiting'));
const menuId = computed(() => `menu-${props.task.task_id}`);
const url = (action = '') => `/api/v1/projects/${props.projectId}/tasks/${props.task.task_id}${action}`;

function open(which: Form) {
    sentEtag.value = props.etag;
    result.value = null;
    fields.value = {
        evidence: '',
        waiting: '',
        title: props.task.title,
        due: props.task.due ?? '',
        proof: props.task.proof ?? '',
        notes: props.task.notes.join('\n'),
    };
    form.value = which;
    nextTick(() => formEl.value?.querySelector<HTMLElement>('input, textarea')?.focus());
}

function close() {
    form.value = null;
    result.value = null;
}

/** Sends one edit; a form stays open on a refusal it can fix (422), and closes otherwise. */
async function send(method: 'POST' | 'PATCH' | 'PUT', path: string, body: object = {}, etag = sentEtag.value ?? props.etag) {
    busy.value = true;
    result.value = await edit(method, url(path), etag, body);
    busy.value = false;
    if (result.value.ok || result.value.status === 412) {
        form.value = null;
    }
}

function submit() {
    const f = fields.value;
    switch (form.value) {
        case 'tick':
            return send('POST', '/tick', f.evidence.trim() === '' ? {} : { evidence: f.evidence });
        case 'waiting':
            return send('POST', '/move', { section: 'Waiting on', waiting: f.waiting });
        case 'undo-waiting':
            return send('POST', '/undo', { waiting: f.waiting });
        case 'edit':
            return send('PATCH', '', editedFields());
    }
}

/** Only what changed, so an untouched proof or note keeps its exact line. */
function editedFields(): Record<string, unknown> {
    const f = fields.value;
    const changes: Record<string, unknown> = {};
    const notes = f.notes.split('\n').map((note) => note.trim()).filter((note) => note !== '');
    if (f.title !== props.task.title) changes.title = f.title;
    if (f.due !== (props.task.due ?? '')) changes.due = f.due === '' ? null : f.due;
    if (f.proof !== (props.task.proof ?? '')) changes.proof = f.proof.trim() === '' ? null : f.proof;
    if (notes.join('\n') !== props.task.notes.join('\n')) changes.notes = notes;
    return changes;
}

function choose(action: () => void) {
    menu.value?.hidePopover();
    action();
}

function move(section: SectionName) {
    if (section === 'Waiting on') {
        open('waiting');
    } else {
        send('POST', '/move', { section }, props.etag);
    }
}

function undo() {
    if (props.task.from_section === 'Waiting on' && !hasWaiting.value) {
        open('undo-waiting');
    } else {
        send('POST', '/undo', {}, props.etag);
    }
}

function reorderBy(step: number) {
    const position = props.task.position + step;
    if (position >= 0 && position < props.sectionSize) {
        send('PUT', '/position', { position }, props.etag);
    }
}

/** The menu takes focus when it opens, and arrow keys, Home and End move through its items. */
function onMenuToggle(event: Event) {
    if ((event as ToggleEvent).newState === 'open') {
        menu.value?.querySelector<HTMLElement>('[role="menuitem"]')?.focus();
    }
}

function onMenuKey(event: KeyboardEvent) {
    const items = [...(menu.value?.querySelectorAll<HTMLElement>('[role="menuitem"]') ?? [])];
    const at = items.indexOf(document.activeElement as HTMLElement);
    const next = { ArrowDown: at + 1, ArrowUp: at - 1, Home: 0, End: items.length - 1 }[event.key];
    if (next !== undefined && items.length) {
        event.preventDefault();
        items[(next + items.length) % items.length]?.focus();
    }
}
</script>

<template>
    <li
        :id="task.task_id"
        class="task"
        :class="{ 'is-highlighted': highlighted, 'is-editable': etag !== null, [`is-arriving-${arriving}`]: arriving }"
        :aria-current="highlighted ? 'true' : undefined"
        :draggable="dragReady"
        @dragend="dragReady = false"
    >
        <span v-if="etag !== null" class="lead">
            <span v-if="isOpen" class="choice hit">
                <input
                    type="checkbox"
                    class="cb"
                    :checked="form === 'tick'"
                    :aria-label="`Tick ${task.task_id}`"
                    :disabled="busy"
                    @click.prevent="form === 'tick' ? close() : open('tick')"
                />
            </span>
        </span>
        <code class="id">{{ task.task_id }}</code>

        <div class="stack gap-3 body">
            <form v-if="form === 'edit'" ref="formEl" class="stack gap-4" :aria-label="`Edit ${task.task_id}`" @submit.prevent="submit" @keydown.esc="close">
                <div class="field md">
                    <label class="p-field" :for="`${task.task_id}-title`">Title</label>
                    <input :id="`${task.task_id}-title`" v-model="fields.title" class="input md" required :aria-invalid="!!fieldError(result, 'title')" />
                    <p v-if="fieldError(result, 'title')" class="p-caption msg"><Icon name="alert" /><span>{{ fieldError(result, 'title') }}</span></p>
                </div>
                <div class="field md">
                    <label class="p-field" :for="`${task.task_id}-due`">Due date</label>
                    <input :id="`${task.task_id}-due`" v-model="fields.due" type="date" class="input md date" :aria-invalid="!!fieldError(result, 'due')" />
                    <p v-if="fieldError(result, 'due')" class="p-caption msg"><Icon name="alert" /><span>{{ fieldError(result, 'due') }}</span></p>
                </div>
                <div class="field md">
                    <label class="p-field" :for="`${task.task_id}-proof`">Proof</label>
                    <input :id="`${task.task_id}-proof`" v-model="fields.proof" class="input md" :aria-invalid="!!fieldError(result, 'proof')" />
                    <p v-if="fieldError(result, 'proof')" class="p-caption msg"><Icon name="alert" /><span>{{ fieldError(result, 'proof') }}</span></p>
                </div>
                <div class="field md">
                    <div class="field-head">
                        <label class="p-field" :for="`${task.task_id}-notes`">Notes</label>
                        <span class="p-caption">One per line</span>
                    </div>
                    <textarea :id="`${task.task_id}-notes`" v-model="fields.notes" class="input md" rows="3" />
                    <p v-if="fieldError(result, 'notes')" class="p-caption msg"><Icon name="alert" /><span>{{ fieldError(result, 'notes') }}</span></p>
                </div>
                <p v-if="refusal(result)" class="p-caption msg" role="alert"><Icon name="alert" /><span>{{ refusal(result) }}</span></p>
                <div class="actions md">
                    <button type="button" class="btn md btn-secondary hit" @click="close"><span class="lbl">Close</span></button>
                    <button type="submit" class="btn md btn-primary hit" :disabled="busy"><span class="lbl">Save changes</span></button>
                </div>
            </form>

            <template v-else>
                <p class="p-body title" :class="{ 'is-checked': task.checked }">
                    <Icon v-if="task.checked" name="check" class="mark" />
                    <span v-if="task.checked" class="visually-hidden">Done:</span>
                    <span>{{ task.title }}</span>
                </p>
                <div v-if="due || facts.length" class="facts" aria-label="Details">
                    <span v-if="due" class="chip" :class="{ 'chip-danger': due.tone === 'danger', 'chip-warning': due.tone === 'warning' }" :title="task.due ?? undefined">
                        <span class="p-label">{{ due.text }}</span>
                    </span>
                    <p v-if="facts.length" class="p-caption meta">
                        <template v-for="(fact, i) in facts" :key="i">
                            <span v-if="i" aria-hidden="true"> · </span><span :title="fact.title"><span class="key">{{ fact.key }}</span> {{ fact.value }}</span>
                        </template>
                    </p>
                </div>
                <p v-if="task.proof" class="p-caption proof"><span class="key">Proof:</span> {{ task.proof }}</p>
                <p v-for="(note, i) in task.notes" :key="i" class="p-caption note"><span class="key">Note:</span> {{ note }}</p>
            </template>

            <form
                v-if="form === 'tick' || form === 'waiting' || form === 'undo-waiting'"
                ref="formEl"
                class="inline-form"
                :aria-label="form === 'tick' ? `Tick ${task.task_id}` : `Waiting on, for ${task.task_id}`"
                @submit.prevent="submit"
                @keydown.esc="close"
            >
                <div class="field md">
                    <template v-if="form === 'tick'">
                        <label class="p-field" :for="`${task.task_id}-evidence`">Evidence <span class="key">(optional)</span></label>
                        <input :id="`${task.task_id}-evidence`" v-model="fields.evidence" class="input md" placeholder="A commit, a link, a file name" :aria-invalid="!!fieldError(result, 'evidence')" />
                    </template>
                    <template v-else>
                        <label class="p-field" :for="`${task.task_id}-waiting`">Waiting on whom or what?</label>
                        <input :id="`${task.task_id}-waiting`" v-model="fields.waiting" class="input md" required :aria-invalid="!!fieldError(result, 'waiting')" />
                    </template>
                    <p v-if="fieldError(result, form === 'tick' ? 'evidence' : 'waiting')" class="p-caption msg">
                        <Icon name="alert" /><span>{{ fieldError(result, form === 'tick' ? 'evidence' : 'waiting') }}</span>
                    </p>
                </div>
                <div class="actions md">
                    <button type="button" class="btn md btn-secondary hit" @click="close"><span class="lbl">Close</span></button>
                    <button type="submit" class="btn md btn-primary hit" :disabled="busy">
                        <span class="lbl">{{ form === 'tick' ? `Tick ${task.task_id}` : form === 'waiting' ? 'Move to Waiting on' : `Undo ${task.task_id}` }}</span>
                    </button>
                </div>
            </form>

            <p v-if="!form && refusal(result)" class="p-caption msg" role="alert"><Icon name="alert" /><span>{{ refusal(result) }}</span></p>
        </div>

        <div v-if="etag !== null" class="tools">
            <template v-if="isOpen">
                <button
                    type="button"
                    class="btn md btn-ghost icon-only hit handle"
                    :aria-label="`Reorder ${task.task_id}: drag, or press the up and down arrow keys`"
                    data-tip="Drag to reorder"
                    :disabled="busy"
                    @pointerdown="dragReady = true"
                    @pointerup="dragReady = false"
                    @keydown.up.prevent="reorderBy(-1)"
                    @keydown.down.prevent="reorderBy(1)"
                >
                    <Icon name="grip" />
                </button>
                <button
                    type="button"
                    class="btn md btn-ghost icon-only hit"
                    :popovertarget="menuId"
                    aria-haspopup="menu"
                    :aria-label="`Actions for ${task.task_id}`"
                    data-tip="Actions"
                    :style="`anchor-name: --${menuId}`"
                    :disabled="busy"
                >
                    <Icon name="more" />
                </button>
                <div :id="menuId" ref="menu" popover class="menu listbox product" role="menu" :style="`position-anchor: --${menuId}`" @toggle="onMenuToggle" @keydown="onMenuKey">
                    <button type="button" role="menuitem" class="option hit" @click="choose(() => open('edit'))"><span class="p-body">Edit</span></button>
                    <button v-for="section in moveTargets" :key="section" type="button" role="menuitem" class="option hit" @click="choose(() => move(section))">
                        <span class="p-body">Move to {{ section }}{{ section === 'Waiting on' ? '…' : '' }}</span>
                    </button>
                    <button type="button" role="menuitem" class="option hit" @click="choose(() => send('POST', '/cancel', {}, etag))"><span class="p-body">Cancel task</span></button>
                </div>
            </template>
            <button v-else-if="task.from_section" type="button" class="btn md btn-secondary hit" :disabled="busy" @click="undo">
                <span class="lbl">Undo</span>
            </button>
        </div>
    </li>
</template>

<style scoped>
.task {
    --arrive: calc(min(var(--dur-large) * var(--dm), var(--cap)));
    --settle: calc(min(var(--dur-reveal-l) * var(--dm), var(--cap)));

    position: relative;
    transition:
        opacity var(--arrive) var(--ease-out),
        transform var(--arrive) var(--ease-out),
        background-color var(--settle) var(--ease-in-out);
    display: grid;
    grid-template-columns: 5ch minmax(0, 1fr);
    gap: var(--space-3);
    align-items: start;
    padding: var(--space-4) var(--space-3);
}

/* Editable: the checkbox, the ID, the task, then its tools. */
.is-editable {
    grid-template-columns: var(--control-h-md) 5ch minmax(0, 1fr) auto;
}

.lead {
    display: flex;
    justify-content: center;
}

.tools {
    display: flex;
    align-items: center;
    gap: var(--space-1);
    margin-block: calc(-1 * var(--space-2));
}

.handle {
    cursor: grab;
    touch-action: none;
}

/* Arriving from another section: offset toward where it came from (none under reduced motion, which
   keeps only the fade), faded out and tinted. Removing the class transitions it into place. */
.is-arriving-down,
.is-arriving-up {
    opacity: 0;
    background-color: var(--accent-bg);
    transition: none;
}

.is-arriving-down {
    transform: translateY(calc(-1 * var(--dr)));
}

.is-arriving-up {
    transform: translateY(var(--dr));
}

/* The selected task: the accent marks it, with a 2px bar that reaches 3:1 (SPEC 3.5 rule 2). */
.is-highlighted {
    background: var(--accent-bg);
}

.is-highlighted::before,
.is-drop-target::after {
    content: '';
    position: absolute;
    background: var(--accent-strong);
}

.is-highlighted::before {
    inset-block: 0;
    inset-inline-start: 0;
    inline-size: 2px;
}

/* A task being dragged over: the bar shows where it will land. */
.is-drop-target::after {
    inset-inline: 0;
    inset-block-start: 0;
    block-size: 2px;
}

.id {
    color: var(--fg-muted);
    text-box: trim-both cap alphabetic;
}

/* Muted text on the accent tint would sit close to 4.5:1, so the highlighted row uses the default. */
.is-highlighted :is(.id, .is-checked) {
    color: var(--fg-default);
}

.title {
    display: flex;
    align-items: center;
    gap: var(--space-2);
    overflow-wrap: anywhere;
}

.is-checked {
    color: var(--fg-muted);
}

.mark {
    color: var(--success-fg);
}

.facts {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: var(--space-2) var(--space-3);
}

.meta {
    overflow-wrap: anywhere;
}

.proof,
.note {
    color: var(--fg-default);
    overflow-wrap: anywhere;
}

.inline-form {
    display: flex;
    flex-wrap: wrap;
    align-items: flex-end;
    gap: var(--space-3);
}

.inline-form .field {
    flex: 1 1 30ch;
}

.date {
    max-width: 100%;
    inline-size: max-content;
}

/* The task menu sits in the top layer, tied to its button by anchor positioning (recipes: Popover and menu). */
.menu {
    position: fixed;
    position-area: block-end span-inline-start;
    position-try-fallbacks: flip-block, flip-inline;
    inset: auto;
    margin: var(--space-1) 0 0;
    background: var(--bg-surface);
    color: var(--fg-default);
    box-shadow: var(--shadow-overlay);
}

.menu:not(:popover-open) {
    display: none;
}

.menu .option {
    border: 0;
    background: none;
    inline-size: 100%;
    font: inherit;
    color: inherit;
    text-align: start;
}

@media (hover: hover) {
    .menu .option:hover {
        background: var(--bg-control-hover);
    }
}

/* On a phone the tools wrap to a row of their own, under the task. */
@media (max-width: 479px) {
    .is-editable {
        grid-template-columns: var(--control-h-md) 5ch minmax(0, 1fr);
    }

    .tools {
        grid-column: 3;
        margin-block: 0;
    }
}
</style>
