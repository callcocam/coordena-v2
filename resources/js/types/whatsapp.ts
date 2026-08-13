/**
 * Current team's WhatsApp connection state, shared on every Inertia response
 * (see HandleInertiaRequests::whatsappState). Null when there is no current team.
 */
export interface WhatsappState {
    apiEnabled: boolean;
    connected: boolean;
    canManage: boolean;
    canManageTemplates: boolean;
    termsAccepted: boolean;
    metaConfigured: boolean;
    usesSharedNumber: boolean;
    verifiedName: string | null;
    qualityRating: string | null;
    messagingLimit: string | null;
}
