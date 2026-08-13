import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

/**
 * Single source of truth for the current team's WhatsApp connection state.
 * Consumed by the settings connection card today and, later, to gate
 * WhatsApp-dependent menu options (e.g. hide API-only actions when offline).
 */
export function useWhatsapp() {
    const page = usePage();

    const whatsapp = computed(() => page.props.whatsapp ?? null);
    // Whether the team uses the automatic WhatsApp API. Default true when the
    // prop is missing (no team) preserves the current behaviour. When false the
    // team is in "manual mode": hide every API-dependent button/option.
    const apiEnabled = computed(() => whatsapp.value?.apiEnabled ?? true);
    // Convenience alias read at call sites gating API-only UI.
    const showWhatsappApi = apiEnabled;
    const isConnected = computed(() => whatsapp.value?.connected ?? false);
    // The team has no own number but sends through the shared Coordena number
    // (the resolver's default fallback). It can send, just not on its own line.
    const usesSharedNumber = computed(
        () => whatsapp.value?.usesSharedNumber ?? false,
    );
    // Whether the team can send at all — its own connection or the shared number.
    const canSend = computed(() => isConnected.value || usesSharedNumber.value);
    const canManage = computed(() => whatsapp.value?.canManage ?? false);
    const termsAccepted = computed(
        () => whatsapp.value?.termsAccepted ?? false,
    );
    // Meta official-number state (Meta Cloud API is the only provider now).
    const metaConfigured = computed(
        () => whatsapp.value?.metaConfigured ?? false,
    );
    const verifiedName = computed(() => whatsapp.value?.verifiedName ?? null);
    const qualityRating = computed(() => whatsapp.value?.qualityRating ?? null);
    const messagingLimit = computed(
        () => whatsapp.value?.messagingLimit ?? null,
    );

    return {
        whatsapp,
        apiEnabled,
        showWhatsappApi,
        isConnected,
        usesSharedNumber,
        canSend,
        canManage,
        termsAccepted,
        metaConfigured,
        verifiedName,
        qualityRating,
        messagingLimit,
    };
}
