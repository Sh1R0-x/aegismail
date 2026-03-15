<template>
  <div>
    <label class="block text-sm font-medium text-gray-700 mb-1">
      {{ label }}
    </label>
    <div class="flex items-center gap-2">
      <input
        :type="type"
        :value="value"
        :placeholder="placeholder"
        class="w-full rounded-md border border-gray-300 px-3 py-1.5 text-sm placeholder:text-gray-400"
        readonly
      />
      <span v-if="suffix" class="shrink-0 text-xs text-gray-400">{{ suffix }}</span>
    </div>
  </div>
</template>

<script setup>
defineProps({
  label: { type: String, required: true },
  value: { type: [String, Number], default: '' },
  placeholder: { type: String, default: '' },
  type: { type: String, default: 'text' },
  suffix: { type: String, default: '' },
});
</script>
