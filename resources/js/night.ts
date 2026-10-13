// Small facts about a night task that the Night page and its review card both show.
import type { NightVerdict } from '@/types';

/** How many things the verifier asks a human to check by eye: its rows to confirm and its "Look at" points. */
export function thingsToCheck(verdict: NightVerdict | null): number {
    if (!verdict) {
        return 0;
    }
    return verdict.look_at.length + verdict.confirm.reduce((sum, block) => sum + block.groups.reduce((rows, group) => rows + group.rows.length, 0), 0);
}

export const plural = (count: number, word: string): string => `${count} ${word}${count === 1 ? '' : 's'}`;

/** A work order's title without its "project task: " prefix. */
export const bareTitle = (title: string | null, task: string): string => title?.replace(/^\S+ \S+: /, '') ?? task;
