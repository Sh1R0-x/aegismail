<template>
  <div>
    <label class="block text-sm font-bold text-slate-700 mb-1.5">
      {{ label }}
    </label>
    <div class="flex items-center gap-3">
      <input
        :type="type"
        :value="modelValue !== undefined ? modelValue : value"
        :placeholder="placeholder"
        :readonly="modelValue === undefined"
        :class="[
          'w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-medium placeholder:text-slate-400',
          modelValue !== undefined ? 'bg-white focus:ring-2 focus:ring-blue-500/30 outline-none' : 'bg-slate-50',
        ]"
        @input="$emit('update:modelValue', type === 'number' ? Number($event.target.value) : $event.target.value)"
      />
      <span v-if="suffix" class="shrink-0 text-xs font-medium text-slate-400">{{ suffix }}</span>
    </div>
  </div>
</template>

<script setup>
defineProps({
  label: { type: String, required: true },
  value: { type: [String, Number], default: '' },
  modelValue: { type: [String, Number], default: undefined },
  placeholder: { type: String, default: '' },
  type: { type: String, default: 'text' },
  suffix: { type: String, default: '' },
});

defineEmits(['update:modelValue']);
</script>
