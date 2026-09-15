<script setup lang="ts">
import type { HTMLAttributes } from 'vue'
import { cn } from '@/lib/utils'
import { reactiveOmit } from '@vueuse/core'
import { SelectItemText, type SelectItemTextProps, useForwardProps } from 'reka-ui'

const props = defineProps<SelectItemTextProps & { class?: HTMLAttributes['class'] }>()

const delegatedProps = reactiveOmit(props, 'class')

const forwardedProps = useForwardProps(delegatedProps)
</script>

<template>
  <SelectItemText
    data-slot="select-item-text"
    v-bind="forwardedProps"
    :class="cn('flex-1 truncate', props.class)"
  >
    <slot />
  </SelectItemText>
</template>
