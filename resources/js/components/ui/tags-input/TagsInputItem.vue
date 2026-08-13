<script setup lang="ts">
import type { TagsInputItemProps } from "reka-ui"
import type { HTMLAttributes } from "vue"
import { reactiveOmit } from "@vueuse/core"
import { TagsInputItem, useForwardProps } from "reka-ui"
import { cn } from "@/lib/utils"

const props = defineProps<TagsInputItemProps & { class?: HTMLAttributes["class"] }>()

const delegatedProps = reactiveOmit(props, "class")

const forwarded = useForwardProps(delegatedProps)
</script>

<template>
  <TagsInputItem
    data-slot="tags-input-item"
    v-bind="forwarded"
    :class="
      cn('bg-secondary text-secondary-foreground data-[state=active]:ring-ring/50 inline-flex max-w-full items-center gap-1 rounded-md px-2 py-0.5 text-xs data-[state=active]:ring-[2px]',
         props.class)"
  >
    <slot />
  </TagsInputItem>
</template>
