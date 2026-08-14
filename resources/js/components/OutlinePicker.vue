<script setup lang="ts">
import { ChevronsUpDown, ExternalLink, Search } from '@lucide/vue';
import { computed, ref } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { useT } from '@/composables/useT';
import type { OutlineOption } from '@/types';

type Props = {
    outlines: OutlineOption[];
    multiple?: boolean;
    modelValue?: string | null;
    selectedIds?: string[];
    preparedIds?: string[];
    placeholder?: string;
    dataTest?: string;
};

const props = withDefaults(defineProps<Props>(), {
    multiple: false,
    modelValue: null,
    selectedIds: () => [],
    preparedIds: () => [],
    placeholder: '',
    dataTest: 'outline-picker',
});

const emit = defineEmits<{
    'update:modelValue': [value: string | null];
    toggle: [outlineId: string];
}>();

const { t } = useT();

const open = ref(false);
const search = ref('');

const normalize = (value: string): string =>
    value
        .toLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '');

const filteredOutlines = computed<OutlineOption[]>(() => {
    const term = normalize(search.value.trim());

    if (term === '') {
        return props.outlines;
    }

    return props.outlines.filter(
        (outline) =>
            String(outline.number).includes(term) ||
            normalize(outline.title).includes(term),
    );
});

const selectedOutline = computed<OutlineOption | null>(
    () =>
        props.outlines.find((outline) => outline.id === props.modelValue) ??
        null,
);

const isPrepared = (outline: OutlineOption): boolean =>
    props.preparedIds.includes(outline.id);

const isSelected = (outline: OutlineOption): boolean =>
    props.multiple
        ? props.selectedIds.includes(outline.id)
        : props.modelValue === outline.id;

const outlineLabel = (outline: OutlineOption): string =>
    `nº ${outline.number} · ${outline.title}`;

const pick = (outline: OutlineOption) => {
    if (props.multiple) {
        emit('toggle', outline.id);

        return;
    }

    emit('update:modelValue', outline.id);
    open.value = false;
};
</script>

<template>
    <div v-if="props.multiple" class="grid gap-2" :data-test="props.dataTest">
        <div class="relative">
            <Search
                class="absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground"
            />
            <Input
                v-model="search"
                class="pl-8"
                :placeholder="t('app.public_talks.outlines.search_placeholder')"
                :data-test="`${props.dataTest}-search`"
            />
        </div>
        <div class="max-h-48 space-y-1 overflow-y-auto rounded-lg border p-2">
            <p
                v-if="filteredOutlines.length === 0"
                class="p-1.5 text-sm text-muted-foreground"
            >
                {{ t('app.public_talks.outlines.empty') }}
            </p>
            <label
                v-for="outline in filteredOutlines"
                :key="outline.id"
                class="flex cursor-pointer items-center gap-2 rounded p-1.5 text-sm hover:bg-accent"
                :data-test="`${props.dataTest}-item`"
            >
                <Checkbox
                    :model-value="isSelected(outline)"
                    @update:model-value="() => pick(outline)"
                />
                <span class="text-muted-foreground">
                    {{ outline.number }}.
                </span>
                <span class="min-w-0 flex-1 truncate">{{ outline.title }}</span>
                <a
                    v-if="outline.reference_url"
                    :href="outline.reference_url"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="shrink-0 text-muted-foreground hover:text-foreground"
                    :title="t('app.public_talks.outlines.open_link')"
                    @click.stop
                >
                    <ExternalLink class="size-3.5" />
                </a>
            </label>
        </div>
    </div>

    <Popover v-else v-model:open="open">
        <PopoverTrigger as-child>
            <Button
                variant="outline"
                role="combobox"
                :aria-expanded="open"
                class="w-full justify-between font-normal"
                :data-test="props.dataTest"
            >
                <span class="truncate" :class="{ 'text-muted-foreground': !selectedOutline }">
                    {{
                        selectedOutline
                            ? outlineLabel(selectedOutline)
                            : props.placeholder
                    }}
                </span>
                <ChevronsUpDown class="size-4 shrink-0 opacity-50" />
            </Button>
        </PopoverTrigger>
        <PopoverContent
            class="w-(--reka-popover-trigger-width) p-2"
            align="start"
        >
            <div class="relative mb-2">
                <Search
                    class="absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground"
                />
                <Input
                    v-model="search"
                    class="h-8 pl-8"
                    :placeholder="
                        t('app.public_talks.outlines.search_placeholder')
                    "
                    :data-test="`${props.dataTest}-search`"
                />
            </div>
            <div class="max-h-56 space-y-0.5 overflow-y-auto">
                <p
                    v-if="filteredOutlines.length === 0"
                    class="p-1.5 text-sm text-muted-foreground"
                >
                    {{ t('app.public_talks.outlines.empty') }}
                </p>
                <div
                    v-for="outline in filteredOutlines"
                    :key="outline.id"
                    class="flex cursor-pointer items-center gap-2 rounded p-1.5 text-sm hover:bg-accent"
                    :class="{ 'bg-accent': isSelected(outline) }"
                    :data-test="`${props.dataTest}-item`"
                    @click="pick(outline)"
                >
                    <span class="text-muted-foreground">
                        {{ outline.number }}.
                    </span>
                    <span class="min-w-0 flex-1 truncate">
                        {{ outline.title }}
                    </span>
                    <Badge v-if="isPrepared(outline)" variant="secondary">
                        {{ t('app.public_talks.schedule.outline_prepared') }}
                    </Badge>
                    <a
                        v-if="outline.reference_url"
                        :href="outline.reference_url"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="shrink-0 text-muted-foreground hover:text-foreground"
                        :title="t('app.public_talks.outlines.open_link')"
                        @click.stop
                    >
                        <ExternalLink class="size-3.5" />
                    </a>
                </div>
            </div>
        </PopoverContent>
    </Popover>
</template>
