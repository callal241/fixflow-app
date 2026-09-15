<script setup lang="ts">
import { cn } from '@/lib/utils'
import {
  SelectArrow,
  SelectContent,
  type SelectContentEmits,
  type SelectContentProps,
  SelectPortal,
  SelectScrollDownButton,
  SelectScrollUpButton,
  SelectViewport,
  useForwardPropsEmits,
} from 'reka-ui'
import { computed, type HTMLAttributes } from 'vue'

const props = withDefaults(
  defineProps<SelectContentProps & { class?: HTMLAttributes['class'] }>(),
  {
    position: 'popper',
    sideOffset: 4,
  },
)
const emits = defineEmits<SelectContentEmits>()

const delegatedProps = computed(() => {
  const { class: _, ...delegated } = props

  return delegated
})

const forwarded = useForwardPropsEmits(delegatedProps, emits)
</script>

<template>
  <SelectPortal>
    <SelectContent
      data-slot="select-content"
      v-bind="forwarded"
      :class="cn('bg-popover text-popover-foreground data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 data-[state=closed]:zoom-out-95 data-[state=open]:zoom-in-95 data-[side=bottom]:slide-in-from-top-2 data-[side=left]:slide-in-from-right-2 data-[side=right]:slide-in-from-left-2 data-[side=top]:slide-in-from-bottom-2 z-50 max-h-(--reka-select-content-available-height) min-w-[8rem] origin-(--reka-select-content-transform-origin) overflow-x-hidden overflow-y-auto rounded-md border p-1 shadow-md', props.class)"
    >
      <SelectScrollUpButton />
      <SelectViewport
        :class="cn('data-[side=bottom]:pt-1 data-[side=left]:pl-1 data-[side=right]:pr-1 data-[side=top]:pb-1 grid max-h-(--reka-select-content-available-height) w-auto gap-1 overflow-x-hidden overflow-y-auto')"
      >
        <slot />
      </SelectViewport>
      <SelectScrollDownButton />
      <SelectArrow :class="cn('bg-popover fill-popover text-popover-foreground z-50 size-2.5 rotate-45 rounded-tl-sm shadow-md')" />
    </SelectContent>
  </SelectPortal>
</template>
