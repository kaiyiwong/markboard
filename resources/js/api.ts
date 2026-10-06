// Edits go to the JSON API (/api/v1), never through Inertia: each request names the version of the
// file the user saw (If-Match), and after any answer the page reloads its props, so the screen
// only ever shows what the file holds (DESIGN.md, Never).
import { router } from '@inertiajs/vue3';
import { onBeforeUnmount, ref, watch, type Ref } from 'vue';
import type { Conflict } from '@/types';

export type EditResult =
    | { ok: true }
    | { ok: false; status: 412; message: string; conflict: Conflict }
    | { ok: false; status: 422; message: string; errors: Record<string, string[]> }
    | { ok: false; status: number; message: string };

/** How many edits are in progress (a form open, a drag under way): the page doesn't poll while any is. */
export const editsInProgress = ref(0);

/** A flag for one edit in progress; it counts towards editsInProgress while true. */
export function useEditing(): Ref<boolean> {
    const editing = ref(false);
    watch(editing, (now) => (editsInProgress.value += now ? 1 : -1));
    onBeforeUnmount(() => {
        if (editing.value) {
            editsInProgress.value--;
        }
    });
    return editing;
}

/**
 * Sends one edit with If-Match, then reloads the page's props whatever the answer: a 412's
 * conflict then shows in the page's conflict panel, read from the server like everything else.
 */
export async function edit(method: 'POST' | 'PATCH' | 'PUT', url: string, etag: string | null, body: object = {}): Promise<EditResult> {
    let result: EditResult;
    try {
        const response = await fetch(url, {
            method,
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                ...(etag === null ? {} : { 'If-Match': `"${etag}"` }),
            },
            body: JSON.stringify(body),
        });
        const json = (await response.json().catch(() => ({}))) as { message?: string; errors?: Record<string, string[]>; conflict?: Conflict };
        const message = json.message ?? `Markboard answered ${response.status}.`;
        if (response.ok) {
            result = { ok: true };
        } else if (response.status === 412 && json.conflict) {
            result = { ok: false, status: 412, message, conflict: json.conflict };
        } else if (response.status === 422) {
            result = { ok: false, status: 422, message, errors: json.errors ?? {} };
        } else {
            result = { ok: false, status: response.status, message: response.status === 503 ? `${message} Nothing was written.` : message };
        }
    } catch {
        result = { ok: false, status: 0, message: "Markboard didn't answer, so nothing was written. Is the app running?" };
    }
    router.reload();
    return result;
}

/** The first message for a field (or the edit as a whole), from a 422. */
export function fieldError(result: EditResult | null, field: string): string | null {
    return result && !result.ok && result.status === 422 && 'errors' in result ? (result.errors[field]?.[0] ?? null) : null;
}

/** The message to show beside the control, for any refusal but a 412 (the conflict panel shows that). */
export function refusal(result: EditResult | null): string | null {
    if (!result || result.ok || result.status === 412) {
        return null;
    }
    return 'errors' in result ? (result.errors.edit?.[0] ?? null) : result.message;
}
