<script setup lang="ts">
import { Link, router, usePage, usePoll } from '@inertiajs/vue3';
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { editsInProgress } from '@/api';
import type { SharedProps } from '@/types';

const page = usePage<SharedProps>();

const nav = [
    { href: '/', label: 'Projects', match: (url: string) => url === '/' || url.startsWith('/?') || url.startsWith('/projects') },
    { href: '/pipeline', label: 'Pipeline', match: (url: string) => url.startsWith('/pipeline') },
    { href: '/brief', label: 'Brief', match: (url: string) => url.startsWith('/brief') },
];

// The files change under the page (agents and scripts edit them), so the page reloads its props
// every 15 seconds and whenever the window regains focus. Each request re-syncs changed files.
// Polling pauses while an edit form is open or a drag is under way.
const poll = usePoll(15_000);
watch(editsInProgress, (count) => (count > 0 ? poll.stop() : poll.start()));
const reload = () => {
    if (editsInProgress.value === 0) {
        router.reload();
    }
};
onMounted(() => window.addEventListener('focus', reload));
onBeforeUnmount(() => window.removeEventListener('focus', reload));

// Light, dark, or the OS setting. The choice is a class on <html>, applied before the styles load
// by resources/views/app.blade.php so the page never flashes the wrong theme.
type Theme = 'system' | 'light' | 'dark';
const themes: { value: Theme; label: string }[] = [
    { value: 'system', label: 'Auto' },
    { value: 'light', label: 'Light' },
    { value: 'dark', label: 'Dark' },
];
const theme = ref<Theme>('system');
onMounted(() => {
    const root = document.documentElement.classList;
    theme.value = root.contains('dark') ? 'dark' : root.contains('light') ? 'light' : 'system';
});
function setTheme(value: Theme) {
    theme.value = value;
    document.documentElement.classList.remove('light', 'dark');
    if (value !== 'system') {
        document.documentElement.classList.add(value);
    }
    try {
        if (value === 'system') {
            localStorage.removeItem('theme');
        } else {
            localStorage.setItem('theme', value);
        }
    } catch {
        // Storage can be blocked; the choice then lasts until the page reloads.
    }
}
</script>

<template>
    <div class="product app">
        <header class="page bar">
            <nav class="nav" aria-label="Main">
                <Link href="/" class="brand p-title">Markboard</Link>
                <ul class="tabs-list">
                    <li v-for="item in nav" :key="item.href">
                        <Link :href="item.href" class="tab" :aria-current="item.match(page.url) ? 'page' : undefined">
                            <span class="lbl">{{ item.label }}</span>
                        </Link>
                    </li>
                </ul>
            </nav>
            <div class="seg segmented md" role="group" aria-label="Theme">
                <button
                    v-for="option in themes"
                    :key="option.value"
                    type="button"
                    class="btn btn-ghost hit"
                    :aria-pressed="theme === option.value"
                    @click="setTheme(option.value)"
                >
                    <span class="lbl">{{ option.label }}</span>
                </button>
            </div>
        </header>
        <main id="main" tabindex="-1" class="page main">
            <section v-if="!page.props.hub.found" class="panel stack gap-4" aria-labelledby="hub-missing">
                <h1 id="hub-missing" class="p-headline">Hub not found</h1>
                <p class="p-body">
                    Markboard looked for <code>projects.md</code> in <code class="path">{{ page.props.hub.path }}</code> and didn't find it.
                    Set <code>MARKBOARD_HUB_PATH</code> to your hub folder, then reload.
                </p>
            </section>
            <slot v-else />
        </main>
    </div>
</template>

<style scoped>
.app {
    min-height: 100vh;
}

.bar {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: var(--space-3) var(--space-5);
    padding-block: var(--space-3);
    border-bottom: 1px solid var(--border-subtle);
}

.nav {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: var(--space-2) var(--space-6);
}

.brand {
    color: var(--fg-default);
    text-decoration: none;
}

.tabs-list {
    display: flex;
    gap: var(--space-5);
    margin: 0;
    padding: 0;
    list-style: none;
}

.tab {
    text-decoration: none;
}

/* The current page gets the tab's marker: accent text and a 2px bar under it. */
.tab[aria-current='page'] {
    color: var(--accent-fg);
}

.tab[aria-current='page']::before {
    content: '';
    position: absolute;
    inset-inline: 0;
    bottom: 0;
    height: 2px;
    background: var(--accent-strong);
}

@media (hover: hover) {
    .tab:not([aria-current='page']):hover {
        color: var(--fg-default);
    }
}

.main {
    padding-block: var(--space-7) var(--space-9);
}

.path {
    overflow-wrap: anywhere;
}
</style>
