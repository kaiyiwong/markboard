<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed } from 'vue';
import { daysFrom, useToday } from '@/dates';
import AppLayout from '@/Layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

interface BriefSection {
    title: string;
    /** Top-level list items, the section's count. */
    items: number;
    /** Rendered on the server, raw HTML escaped, project tags linked. */
    html: string;
}

const props = defineProps<{
    /** Null for today's brief (TODAY.md). */
    date: string | null;
    /** Null when there is no TODAY.md. */
    brief: { title: string | null; intro: string; sections: BriefSection[] } | null;
    dates: string[];
}>();

const today = useToday();
const fullDate = (date: string) =>
    new Intl.DateTimeFormat('en', { weekday: 'long', month: 'short', day: 'numeric', timeZone: 'UTC' }).format(new Date(`${date}T00:00:00Z`));
const shortDay = (date: string) =>
    daysFrom(today.value, date) === -1
        ? 'Yesterday'
        : new Intl.DateTimeFormat('en', { weekday: 'short', month: 'short', day: 'numeric', timeZone: 'UTC' }).format(new Date(`${date}T00:00:00Z`));

// The page's key element is the brief's day. A title with a date in it ("Today, 2026-10-08") is shown
// as that day in words; any other title as written.
const heading = computed(() => {
    const title = props.brief?.title ?? null;
    const date = title?.match(/\d{4}-\d{2}-\d{2}/)?.[0] ?? props.date;
    const days = date ? daysFrom(today.value, date) : null;
    return {
        label: props.date ? 'Brief' : "Today's brief",
        key: date ? fullDate(date) : (title ?? 'Brief'),
        note: days === null || days === 0 ? null : days === -1 ? 'Yesterday' : days < 0 ? `${-days} days ago` : null,
    };
});

// A short intro (a line about how the brief was made) reads as a caption; a list gets its own surface.
const introIsList = computed(() => props.brief?.intro.includes('<li') ?? false);
// The hub also files today's brief under today's date: "Today" already stands for it.
const pastDates = computed(() => props.dates.filter((past) => past !== today.value));

const filled = computed(() => props.brief?.sections.filter((section) => section.html !== '') ?? []);
const empty = computed(() => props.brief?.sections.filter((section) => section.html === '').map((section) => section.title) ?? []);

// The brief's links are plain <a> tags in server-rendered HTML; follow the app's own links as Inertia
// visits so the page doesn't fully reload.
function follow(event: MouseEvent) {
    const link = (event.target as HTMLElement).closest('a');
    const href = link?.getAttribute('href');
    if (href?.startsWith('/') && !event.metaKey && !event.ctrlKey && !event.shiftKey) {
        event.preventDefault();
        router.visit(href);
    }
}
</script>

<template>
    <Head :title="date ? `Brief, ${date}` : 'Brief'" />
    <div class="layout">
        <div class="stack gap-6 main-col">
            <div class="stack gap-3">
                <p class="p-label">{{ heading.label }}</p>
                <h1 class="p-key">{{ brief ? heading.key : 'No brief yet' }}</h1>
                <p v-if="brief && heading.note" class="p-caption">{{ heading.note }}</p>
            </div>

            <div v-if="!brief" class="panel stack gap-3">
                <p class="p-body">Today's brief appears here once the hub has a TODAY.md. Past briefs are listed with it.</p>
            </div>

            <template v-else>
                <!-- Escaped on the server (raw HTML as text, no unsafe links). -->
                <div v-if="brief.intro" class="content" :class="introIsList ? 'panel p-body' : 'p-caption intro'" @click="follow" v-html="brief.intro" />
                <div class="sections">
                    <section v-for="section in filled" :key="section.title" class="panel section">
                        <h2 class="p-title section-head">
                            <span>{{ section.title }}</span>
                            <span v-if="section.items" class="chip"><span class="p-label num">{{ section.items }}</span></span>
                        </h2>
                        <div class="content p-body" @click="follow" v-html="section.html" />
                    </section>
                </div>
                <p v-if="empty.length" class="p-caption">Nothing in {{ empty.join(', ') }}.</p>
            </template>
        </div>

        <nav class="panel past-nav" aria-labelledby="past-briefs">
            <h2 id="past-briefs" class="p-label">Past briefs</h2>
            <ul v-if="pastDates.length || date" class="past-list">
                <li>
                    <Link href="/brief" class="p-body past" :aria-current="date === null ? 'page' : undefined">Today</Link>
                </li>
                <li v-for="past in pastDates" :key="past">
                    <Link :href="`/brief/${past}`" class="p-body past" :aria-current="past === date ? 'page' : undefined" :title="past">{{ shortDay(past) }}</Link>
                </li>
            </ul>
            <p v-else class="p-caption">No past briefs in the hub's briefs folder.</p>
        </nav>
    </div>
</template>

<style scoped>
/* The brief first, past briefs beside it from 1024, under it on smaller screens. */
.layout {
    display: grid;
    gap: var(--space-6);
}

@media (min-width: 1024px) {
    .layout {
        grid-template-columns: minmax(0, 1fr) minmax(12rem, 16rem);
        align-items: start;
    }
}

/* Each section is a surface. The first, usually the day's focus, spans the width; the rest flow in
   newspaper columns, so a short section never leaves a hole beside a long one. */
.sections {
    columns: 24rem;
    column-gap: var(--space-4);
}

.sections > :first-child {
    column-span: all;
}

.section {
    break-inside: avoid;
    margin-block-end: var(--space-4);
    display: grid;
    gap: var(--space-3);
    padding: var(--space-5) var(--space-5) var(--space-2);
}

.intro {
    margin-block-start: calc(-1 * var(--space-3));
}

.section-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: var(--space-3);
}

/* The brief's Markdown, as product rows: each list item a row with a hairline, its project and task as chips. */
.content {
    overflow-wrap: anywhere;
}

.content :deep(> * + *) {
    margin-block-start: var(--space-3);
}

.content :deep(:is(p, ul, ol, h3)) {
    margin-block: 0;
}

.content :deep(:is(ul, ol)) {
    padding: 0;
    list-style: none;
    counter-reset: item;
}

.content :deep(li) {
    display: flex;
    flex-wrap: wrap;
    align-items: baseline;
    gap: var(--space-1) var(--space-2);
    padding-block: var(--space-3);
    border-top: 1px solid var(--border-subtle);
}

.content :deep(li:first-child) {
    border-top: 0;
}

/* A numbered list ("Top today") keeps its order as a number in a circle. */
.content :deep(ol > li) {
    counter-increment: item;
}

.content :deep(ol > li)::before {
    content: counter(item);
    flex: none;
    display: inline-grid;
    place-items: center;
    inline-size: var(--control-h-sm);
    block-size: var(--control-h-sm);
    border-radius: var(--radius-bar);
    background: var(--accent-bg);
    color: var(--accent-fg-strong);
    font-weight: var(--weight-medium);
    font-variant-numeric: tabular-nums;
}

/* A project tag links to the project, by name; a task ID after it links to the task, as a pill in the
   accent tint (its text uses the strong role: accent-fg on a tint is under 4.5:1). */
.content :deep(a[href^='/projects/']) {
    text-decoration: none;
}

.content :deep(a[href^='/projects/']:not([href*='/tasks/'])) {
    color: var(--fg-default);
    font-weight: var(--weight-medium);
}

.content :deep(a[href*='/tasks/']) {
    padding-inline: var(--space-1);
    border-radius: var(--control-radius);
    background: var(--accent-bg);
    color: var(--accent-fg-strong);
    font-family: var(--font-mono);
}

@media (hover: hover) {
    .content :deep(a[href^='/projects/']:hover) {
        text-decoration: underline;
    }
}

.content :deep(:is(pre, table)) {
    display: block;
    max-inline-size: 100%;
    overflow-x: auto;
}

.past-nav {
    display: grid;
    gap: var(--space-3);
    padding: var(--space-4);
}

.past-list {
    display: grid;
    gap: var(--space-1);
    margin: 0;
    padding: 0;
    list-style: none;
}

.past {
    display: block;
    padding: var(--space-2) var(--space-3);
    border-radius: var(--control-radius);
    color: var(--fg-default);
    text-decoration: none;
}

/* The brief on screen: the accent marks it, with a 2px bar that reaches 3:1 (SPEC 3.5 rule 2). */
.past[aria-current='page'] {
    position: relative;
    background: var(--accent-bg);
    color: var(--accent-fg-strong);
    font-weight: var(--weight-medium);
}

.past[aria-current='page']::before {
    content: '';
    position: absolute;
    inset-block: var(--space-2);
    inset-inline-start: 0;
    inline-size: 2px;
    background: var(--accent-strong);
}

@media (hover: hover) {
    .past:not([aria-current='page']):hover {
        background: var(--bg-control-hover);
    }
}
</style>
