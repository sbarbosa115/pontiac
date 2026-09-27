import type { Schema } from './types';

export type PortalSession = Schema<'PortalSessionOutput'>;
export type PortalPlan = Schema<'PortalPlanOutput'>;

/** Plans the client can book a session of now: active, with sessions left. */
export function bookablePlans(plans: readonly PortalPlan[]): PortalPlan[] {
    return plans.filter((plan) => plan.status === 'active' && plan.sessionsTaken < plan.sessionsIncluded);
}

/** Sessions still ahead (soonest first), then the rest (latest first). */
export function splitSessions(sessions: readonly PortalSession[], now: Date = new Date()): { upcoming: PortalSession[]; past: PortalSession[] } {
    const upcoming = sessions.filter((s) => s.status === 'scheduled' && new Date(s.endsAt) > now).sort((a, b) => a.startsAt.localeCompare(b.startsAt));
    const past = sessions.filter((s) => !upcoming.includes(s)).sort((a, b) => b.startsAt.localeCompare(a.startsAt));
    return { upcoming, past };
}

/** The files the API accepts (it checks the content; this only narrows the file picker). */
export const FILE_ACCEPT = '.pdf,.jpg,.jpeg,.png,.webp,.xlsx,.csv,.docx';
