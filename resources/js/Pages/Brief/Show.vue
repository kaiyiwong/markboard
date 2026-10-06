<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

const props = defineProps<{
    /** Null for today's brief (TODAY.md). */
    date: string | null;
    /** Rendered on the server, with raw HTML escaped; null when there is no TODAY.md. */
    html: string | null;
    dates: string[];
}>();

// A brief normally opens with its own heading, which is the page's title; one that doesn't gets a
// hidden one, so the page still has a heading.
const hasTitle = computed(() => props.html?.trimStart().startsWith('<h1>') ?? false);

// The brief's task links are plain <a> tags in server-rendered HTML; follow the app's own links
// as Inertia visits so the page doesn't fully reload.
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
    <div class="stack gap-8">
        <h1 v-if="html && !hasTitle" class="visually-hidden">{{ date ? `Brief, ${date}` : 'Today\'s brief' }}</h1>
        <!-- Escaped on the server (raw HTML as text, no unsafe links). Its first heading is the page's title. -->
        <article v-if="html" class="brief stack stack-para" @click="follow" v-html="html" />
        <div v-else class="stack gap-3">
            <h1 class="p-headline">No brief yet</h1>
            <p class="p-body">Today's brief appears here once the hub has a TODAY.md. Past briefs are listed below.</p>
        </div>

        <nav class="stack gap-4" aria-labelledby="past-briefs">
            <h2 id="past-briefs" class="p-title">Past briefs</h2>
            <ul v-if="dates.length || date" class="ledger">
                <li v-if="date" class="past">
                    <Link href="/brief" class="p-body link-quiet">Today</Link>
                </li>
                <li v-for="past in dates" :key="past" class="past">
                    <Link :href="`/brief/${past}`" class="p-body link-quiet num" :aria-current="past === date ? 'page' : undefined">{{ past }}</Link>
                </li>
            </ul>
            <p v-else class="p-caption">No past briefs in the hub's briefs folder.</p>
        </nav>
    </div>
</template>

<style scoped>
/* The brief is a document to read, so it uses the editorial roles (SPEC 4.3) at a reading measure. */
.brief {
    max-inline-size: 65ch;
    overflow-wrap: anywhere;
}

.brief :deep(> *),
.brief :deep(li) {
    margin: 0;
    text-box: trim-both cap alphabetic;
}

.brief :deep(h1) {
    font-size: var(--ed-headline);
    line-height: 1.15;
    letter-spacing: var(--ls-headline);
    font-weight: var(--weight-strong);
    text-wrap: balance;
}

.brief :deep(h2) {
    font-size: var(--ed-title);
    line-height: 1.3;
    letter-spacing: var(--ls-title);
    font-weight: var(--weight-strong);
    text-wrap: balance;
}

.brief :deep(:is(p, li)) {
    font-size: var(--ed-body);
    line-height: var(--ed-body-lh);
    text-wrap: pretty;
}

.brief :deep(:is(ul, ol)) {
    display: flex;
    flex-direction: column;
    gap: var(--u);
    padding-inline-start: var(--space-5);
}

.brief :deep(:is(h1, h2, h3):not(:first-child)) {
    margin-block-start: var(--u);
}

.brief :deep(:is(pre, table)) {
    display: block;
    max-inline-size: 100%;
    overflow-x: auto;
}

.past {
    padding-block: var(--space-3);
}

.past [aria-current='page'] {
    color: var(--fg-default);
}
</style>
