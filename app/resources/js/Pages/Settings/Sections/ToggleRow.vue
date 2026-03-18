<template>
  <div class="flex items-center justify-between">
    <span class="text-sm font-bold text-slate-700">{{ label }}</span>
    <button
      :class="[
        'relative inline-flex h-6 w-10 shrink-0 rounded-full border-2 border-transparent transition-colors',
        currentValue ? 'bg-emerald-500' : 'bg-slate-200',
        modelValue !== undefined ? 'cursor-pointer' : 'cursor-default',
      ]"
      role="switch"
      :aria-checked="currentValue"
      @click="toggle"
    >
      <span
        :class="[
          'pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition-transform',
          currentValue ? 'translate-x-4' : 'translate-x-0',
        ]"
      />
    </button>
  </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
  label: { type: String, required: true },
  enabled: { type: Boolean, default: false },
  modelValue: { type: Boolean, default: undefined },
});

const emit = defineEmits(['update:modelValue']);

const currentValue = computed(() =>
  props.modelValue !== undefined ? props.modelValue : props.enabled,
);

function toggle() {
  if (props.modelValue !== undefined) {
    emit('update:modelValue', !props.modelValue);
  }
}
</script>
