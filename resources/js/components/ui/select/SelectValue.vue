<script setup lang="ts">
import type { HTMLAttributes } from 'vue'
import { cn } from '@/lib/utils'
import { reactiveOmit } from '@vueuse/core'
import { SelectValue, type SelectValueProps, useForwardProps } from 'reka-ui'

const props = defineProps<SelectValueProps & { class?: HTMLAttributes['class'] }>()

const delegatedProps = reactiveOmit(props, 'class')

const forwardedProps = useForwardProps(delegatedProps)
</script>

<template>
  <SelectValue
    data-slot="select-value"
    v-bind="forwardedProps"
    :class="cn('data-[placeholder]:text-muted-foreground flex w-full truncate text-sm', props.class)"
  >
    <slot />
  </SelectValue>
</template>
