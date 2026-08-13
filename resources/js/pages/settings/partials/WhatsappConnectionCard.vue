<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import {
    CheckCircle2,
    ChevronDown,
    LoaderCircle,
    ShieldAlert,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { useT } from '@/composables/useT';
import { useWhatsapp } from '@/composables/useWhatsapp';
import { agree, mode } from '@/routes/whatsapp';
import { save as cloudSave } from '@/routes/whatsapp/cloud';

const { t } = useT();
const page = usePage();
const {
    apiEnabled,
    isConnected,
    usesSharedNumber,
    canManage,
    termsAccepted,
    metaConfigured,
    verifiedName,
    qualityRating,
    messagingLimit,
} = useWhatsapp();

// Badge for the official-number state: own active × shared × not configured.
const statusLabel = computed(() => {
    if (isConnected.value) {
        return t('app.whatsapp.cloud.active');
    }

    if (usesSharedNumber.value) {
        return t('app.whatsapp.cloud.shared');
    }

    if (metaConfigured.value) {
        return t('app.whatsapp.cloud.configured');
    }

    return t('app.whatsapp.cloud.not_configured');
});
const statusVariant = computed(() => {
    if (isConnected.value) {
        return 'default' as const;
    }

    return usesSharedNumber.value
        ? ('outline' as const)
        : ('secondary' as const);
});

const savingMode = ref(false);

// Opção B (time traz o próprio número da Meta) está desligada por ora: todas as
// equipes usam o número compartilhado do Coordena. Para reativar o formulário de
// número próprio, basta voltar isto para `true`.
const allowOwnNumber = false;

// WhatsApp oficial (Meta Cloud API): colar credenciais e ativar.
const cloudForm = ref({
    phone_number_id: '',
    waba_id: '',
    app_id: '',
    cloud_access_token: '',
    verified_name: '',
});
const savingCloud = ref(false);

function onSaveCloud() {
    savingCloud.value = true;

    router.post(
        cloudSave().url,
        { ...cloudForm.value },
        {
            preserveScroll: true,
            onSuccess: () => {
                cloudForm.value = {
                    phone_number_id: '',
                    waba_id: '',
                    app_id: '',
                    cloud_access_token: '',
                    verified_name: '',
                };
            },
            onFinish: () => {
                savingCloud.value = false;
            },
        },
    );
}

// Flip the team between automatic (API) and manual WhatsApp mode. Only managers
// reach this control; the flag just hides/blocks the API, it does not disconnect.
function onToggleMode(value: boolean) {
    savingMode.value = true;

    router.patch(
        mode().url,
        { api_enabled: value },
        {
            preserveScroll: true,
            only: ['whatsapp', 'flash'],
            onFinish: () => {
                savingMode.value = false;
            },
        },
    );
}

// Terms of use gate.
const agreeChecked = ref(false);
const agreeing = ref(false);
const termsOpen = ref(false);

const hasAgreed = computed(() => termsAccepted.value);

// The legal items live in the shared translation tree as an array of strings.
const termsItems = computed<string[]>(() => {
    const app = page.props.translations?.app as
        | Record<string, unknown>
        | undefined;
    const items = (app?.whatsapp as Record<string, unknown> | undefined)
        ?.terms as Record<string, unknown> | undefined;
    const list = items?.items;

    return Array.isArray(list) ? (list as string[]) : [];
});

function onAgree() {
    if (!agreeChecked.value) {
        return;
    }

    agreeing.value = true;

    router.post(
        agree().url,
        {},
        {
            preserveScroll: true,
            only: ['whatsapp', 'flash'],
            onFinish: () => {
                agreeing.value = false;
            },
        },
    );
}
</script>

<template>
    <section class="space-y-6">
        <Heading
            variant="small"
            title="app.whatsapp.card.title"
            description="app.whatsapp.card.description"
        />

        <!-- Modo de envio: API (automático) × manual. -->
        <div class="flex flex-col gap-3 rounded-lg border p-4">
            <label class="flex items-start justify-between gap-4">
                <span class="space-y-1">
                    <span class="block text-sm font-medium">
                        {{ t('app.whatsapp.mode.toggle') }}
                    </span>
                    <span class="block text-sm text-muted-foreground">
                        {{ t('app.whatsapp.mode.toggle_help') }}
                    </span>
                </span>
                <Switch
                    :model-value="apiEnabled"
                    :disabled="!canManage || savingMode"
                    @update:model-value="onToggleMode($event === true)"
                />
            </label>
        </div>

        <!-- Modo manual: nenhuma UI de conexão. -->
        <div
            v-if="!apiEnabled"
            class="rounded-lg border border-dashed p-4 text-sm text-muted-foreground"
        >
            <p class="font-medium text-foreground">
                {{ t('app.whatsapp.mode.manual_title') }}
            </p>
            <p class="mt-1">{{ t('app.whatsapp.mode.manual_description') }}</p>
        </div>

        <!-- Modo API: WhatsApp oficial (Meta Cloud API). -->
        <template v-else>
            <p v-if="!canManage" class="text-sm text-muted-foreground">
                {{ t('app.whatsapp.card.manage_forbidden') }}
            </p>

            <!-- Passo 1: termo de uso e responsabilidade. -->
            <div
                v-else-if="!hasAgreed"
                class="flex flex-col gap-4 rounded-lg border p-4"
            >
                <div class="space-y-1">
                    <p class="text-sm font-medium">
                        {{ t('app.whatsapp.terms.title') }}
                    </p>
                    <p class="text-sm text-muted-foreground">
                        {{ t('app.whatsapp.terms.intro') }}
                    </p>
                </div>

                <div
                    class="flex gap-3 rounded-md border border-destructive/40 bg-destructive/5 p-3 text-sm"
                >
                    <ShieldAlert
                        class="mt-0.5 h-5 w-5 shrink-0 text-destructive"
                    />
                    <div class="space-y-1">
                        <p class="font-semibold text-destructive">
                            {{ t('app.whatsapp.terms.new_number_title') }}
                        </p>
                        <p class="text-muted-foreground">
                            {{ t('app.whatsapp.terms.new_number') }}
                        </p>
                    </div>
                </div>

                <Collapsible v-model:open="termsOpen">
                    <CollapsibleTrigger as-child>
                        <Button
                            type="button"
                            variant="outline"
                            class="w-full justify-between"
                        >
                            {{ t('app.whatsapp.terms.read') }}
                            <ChevronDown
                                class="h-4 w-4 transition-transform"
                                :class="{ 'rotate-180': termsOpen }"
                            />
                        </Button>
                    </CollapsibleTrigger>
                    <CollapsibleContent>
                        <ul
                            class="mt-3 max-h-56 space-y-2 overflow-y-auto rounded-md border p-3 text-sm text-muted-foreground"
                        >
                            <li
                                v-for="(item, i) in termsItems"
                                :key="i"
                                class="flex gap-2"
                            >
                                <span class="text-foreground">•</span>
                                <span>{{ item }}</span>
                            </li>
                        </ul>
                    </CollapsibleContent>
                </Collapsible>

                <label class="flex items-start gap-3 text-sm">
                    <Checkbox
                        id="wa-terms"
                        :model-value="agreeChecked"
                        class="mt-0.5"
                        @update:model-value="agreeChecked = $event === true"
                    />
                    <span>{{ t('app.whatsapp.terms.checkbox') }}</span>
                </label>

                <p class="text-xs text-muted-foreground">
                    {{ t('app.whatsapp.terms.record_notice') }}
                </p>

                <Button
                    type="button"
                    :disabled="!agreeChecked || agreeing"
                    @click="onAgree"
                >
                    <LoaderCircle
                        v-if="agreeing"
                        class="mr-1 h-4 w-4 animate-spin"
                    />
                    {{ t('app.whatsapp.terms.agree') }}
                </Button>
            </div>

            <!-- Passo 2: credenciais + estado do número oficial. -->
            <div v-else class="flex flex-col gap-4 rounded-lg border p-4">
                <div class="flex items-start justify-between gap-4">
                    <div class="space-y-1">
                        <p class="text-sm font-medium">
                            {{ t('app.whatsapp.cloud.title') }}
                        </p>
                        <p class="text-sm text-muted-foreground">
                            {{ t('app.whatsapp.cloud.description') }}
                        </p>
                    </div>
                    <Badge :variant="statusVariant">
                        <CheckCircle2
                            v-if="isConnected || usesSharedNumber"
                            class="mr-1 h-3 w-3"
                        />
                        {{ statusLabel }}
                    </Badge>
                </div>

                <!-- Configurado: estado do número oficial. -->
                <div v-if="metaConfigured" class="space-y-1 text-sm">
                    <p v-if="verifiedName">
                        <span class="text-muted-foreground">
                            {{ t('app.whatsapp.cloud.verified_name') }}:
                        </span>
                        {{ verifiedName }}
                    </p>
                    <p v-if="messagingLimit">
                        <span class="text-muted-foreground">
                            {{ t('app.whatsapp.cloud.tier') }}:
                        </span>
                        {{ messagingLimit }}
                    </p>
                    <p v-if="qualityRating">
                        <span class="text-muted-foreground">
                            {{ t('app.whatsapp.cloud.quality') }}:
                        </span>
                        {{ qualityRating }}
                    </p>
                </div>

                <!-- Sem número próprio: número compartilhado do Coordena. -->
                <div v-else class="space-y-3">
                    <!-- Enviando pelo número compartilhado do Coordena. -->
                    <div
                        v-if="usesSharedNumber"
                        class="flex gap-3 rounded-md border bg-muted/40 p-3 text-sm"
                    >
                        <CheckCircle2 class="mt-0.5 h-5 w-5 shrink-0" />
                        <div class="space-y-1">
                            <p class="font-medium">
                                {{ t('app.whatsapp.cloud.shared_title') }}
                            </p>
                            <p class="text-muted-foreground">
                                {{ t('app.whatsapp.cloud.shared_description') }}
                            </p>
                        </div>
                    </div>

                    <!-- Número compartilhado ainda não configurado pelo admin. -->
                    <div
                        v-else
                        class="rounded-md border border-dashed p-3 text-sm text-muted-foreground"
                    >
                        {{ t('app.whatsapp.cloud.not_configured_help') }}
                    </div>

                    <!-- Número próprio (Opção B): escondido por ora. Para reativar,
                         volte `allowOwnNumber` para true no <script>. -->
                    <template v-if="allowOwnNumber">
                        <p class="text-sm font-medium text-foreground">
                            {{ t('app.whatsapp.cloud.own_number') }}
                        </p>
                        <div class="grid gap-3 sm:grid-cols-2">
                            <div class="grid gap-1.5">
                                <Label for="wa-cloud-phone-id">
                                    {{
                                        t('app.whatsapp.cloud.phone_number_id')
                                    }}
                                </Label>
                                <Input
                                    id="wa-cloud-phone-id"
                                    v-model="cloudForm.phone_number_id"
                                />
                            </div>
                            <div class="grid gap-1.5">
                                <Label for="wa-cloud-waba">
                                    {{ t('app.whatsapp.cloud.waba_id') }}
                                </Label>
                                <Input
                                    id="wa-cloud-waba"
                                    v-model="cloudForm.waba_id"
                                />
                            </div>
                            <div class="grid gap-1.5">
                                <Label for="wa-cloud-app">
                                    {{ t('app.whatsapp.cloud.app_id') }}
                                </Label>
                                <Input
                                    id="wa-cloud-app"
                                    v-model="cloudForm.app_id"
                                />
                            </div>
                            <div class="grid gap-1.5">
                                <Label for="wa-cloud-name">
                                    {{ t('app.whatsapp.cloud.verified_name') }}
                                </Label>
                                <Input
                                    id="wa-cloud-name"
                                    v-model="cloudForm.verified_name"
                                />
                            </div>
                            <div class="grid gap-1.5 sm:col-span-2">
                                <Label for="wa-cloud-token">
                                    {{ t('app.whatsapp.cloud.token') }}
                                </Label>
                                <Input
                                    id="wa-cloud-token"
                                    v-model="cloudForm.cloud_access_token"
                                    type="password"
                                    autocomplete="off"
                                />
                            </div>
                        </div>
                        <Button
                            type="button"
                            :disabled="
                                savingCloud ||
                                !cloudForm.phone_number_id ||
                                !cloudForm.cloud_access_token
                            "
                            @click="onSaveCloud"
                        >
                            <LoaderCircle
                                v-if="savingCloud"
                                class="mr-1 h-4 w-4 animate-spin"
                            />
                            {{ t('app.whatsapp.cloud.save') }}
                        </Button>
                    </template>
                </div>
            </div>
        </template>
    </section>
</template>
