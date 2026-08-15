/**
 * Types for the public talks module (arranjo de oradores).
 */

export type ScheduleWeek = {
    id: string;
    date: string;
    week_start: string;
    type: 'home' | 'incoming' | 'outgoing';
    status:
        | 'open'
        | 'scheduled'
        | 'notified'
        | 'confirmed'
        | 'needs_reschedule';
    speaker: { id: string; name: string; phone: string | null } | null;
    outline: { id: string; number: number; title: string } | null;
    counterpart: string | null;
    editable: boolean;
    notifiable: boolean;
    notification: ScheduleNotification | null;
};

export type ScheduleNotification = {
    kind: 'assignment' | 'reminder';
    status: 'pending' | 'sent' | 'failed' | 'confirmed' | 'reschedule_requested';
    sent_at: string | null;
};

export type ScheduleSpeaker = {
    id: string;
    name: string;
    role: SpeakerRole;
    phone: string | null;
    outline_ids: string[];
    available: boolean;
};

export type OutlineOption = {
    id: string;
    number: number;
    title: string;
    theme?: string | null;
    reference_url?: string | null;
};

export type SpeakerRole = 'elder' | 'ministerial_servant' | 'other';

export type CoordinatorRole = 'responsible' | 'helper';

export type CoordinatorItem = {
    id: string;
    name: string;
    phone: string | null;
    role: CoordinatorRole;
    is_active: boolean;
};

export type CongregationSummary = {
    id: string;
    name: string;
    city: string | null;
    circuit: string | null;
    meeting_weekday: number | null;
    meeting_time: string | null;
    speakers_count: number;
    is_home: boolean;
};

export type CongregationDetail = {
    id: string;
    name: string;
    city: string | null;
    circuit: string | null;
    address: string | null;
    contact_name: string | null;
    contact_phone: string | null;
    contact_email: string | null;
    secretary_name: string | null;
    secretary_phone: string | null;
    secretary_email: string | null;
    meeting_weekday: number | null;
    meeting_time: string | null;
    exchange_opt: 'opted_in' | 'opted_out' | 'unknown';
    is_home: boolean;
};

export type CongregationIntroSummary = {
    status: 'pending' | 'sent' | 'failed' | 'accepted' | 'declined' | 'expired';
    sent_at: string | null;
    responded_at: string | null;
};

export type SpeakerDetail = {
    id: string;
    name: string;
    role: SpeakerRole;
    phone: string | null;
    is_active: boolean;
    notes: string | null;
    outline_ids: string[];
};

export type HomeCongregation = {
    id: string;
    name: string;
    meeting_weekday: number | null;
    meeting_time: string | null;
};
