// Dates the files hold as YYYY-MM-DD, shown relative to "today" (the server's date in
// MARKBOARD_TIMEZONE, shared with every page), so no browser timezone ever shifts a day.
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import type { SharedProps } from '@/types';

export type Tone = 'danger' | 'warning' | null;

/** Whole days from today to date: negative in the past. Both are YYYY-MM-DD. */
export function daysFrom(today: string, date: string): number {
    return Math.round((Date.parse(`${date}T00:00:00Z`) - Date.parse(`${today}T00:00:00Z`)) / 86_400_000);
}

const plural = (n: number, word: string) => `${n} ${word}${n === 1 ? '' : 's'}`;

/** "Oct 1", with the year when it isn't this year's. */
export function shortDate(today: string, date: string): string {
    const sameYear = today.slice(0, 4) === date.slice(0, 4);
    return new Intl.DateTimeFormat('en', { month: 'short', day: 'numeric', year: sameYear ? undefined : 'numeric', timeZone: 'UTC' }).format(
        new Date(`${date}T00:00:00Z`),
    );
}

/** A due date as the user reads it, toned: overdue is danger, today or tomorrow is warning. */
export function dueLabel(today: string, date: string): { text: string; tone: Tone } {
    const days = daysFrom(today, date);
    if (days < 0) {
        return { text: `Overdue by ${plural(-days, 'day')}`, tone: 'danger' };
    }
    if (days === 0) {
        return { text: 'Due today', tone: 'warning' };
    }
    if (days === 1) {
        return { text: 'Due tomorrow', tone: 'warning' };
    }
    return { text: days <= 13 ? `Due in ${plural(days, 'day')}` : `Due ${shortDate(today, date)}`, tone: null };
}

/** A date with no deadline meaning (a pipeline row's next action): today, in 3 days, 2 days ago. */
export function relativeDate(today: string, date: string): { text: string; tone: Tone } {
    const days = daysFrom(today, date);
    if (days === 0) {
        return { text: 'Today', tone: 'warning' };
    }
    if (days < 0) {
        return { text: `${plural(-days, 'day')} ago`, tone: 'danger' };
    }
    return { text: days === 1 ? 'Tomorrow' : days <= 13 ? `In ${plural(days, 'day')}` : shortDate(today, date), tone: null };
}

/** Today's date as the server sees it. */
export function useToday() {
    const page = usePage<SharedProps>();
    return computed(() => page.props.today);
}
