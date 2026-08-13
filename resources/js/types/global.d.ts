import type { TranslationTree } from '@/composables/useT';
import type { Auth } from '@/types/auth';
import type { Cargo, Team } from '@/types/teams';
import type { WhatsappState } from '@/types/whatsapp';

// Extend ImportMeta interface for Vite...
declare module 'vite/client' {
    interface ImportMetaEnv {
        readonly VITE_APP_NAME: string;
        [key: string]: string | boolean | undefined;
    }

    interface ImportMeta {
        readonly env: ImportMetaEnv;
        readonly glob: <T>(pattern: string) => Record<string, () => Promise<T>>;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: Auth;
            sidebarOpen: boolean;
            currentTeam: Team | null;
            teams: Team[];
            permissions: Record<string, boolean>;
            cargos: Cargo[];
            translations: Record<string, TranslationTree>;
            whatsapp: WhatsappState | null;
            locale: string;
            [key: string]: unknown;
        };
    }
}

declare module 'vue' {
    interface ComponentCustomProperties {
        $inertia: typeof Router;
        $page: Page;
        $headManager: ReturnType<typeof createHeadManager>;
    }
}
