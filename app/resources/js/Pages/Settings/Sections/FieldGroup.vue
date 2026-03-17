<template>
  <div>
    <label class="block text-sm font-bold text-slate-700 mb-1.5">
      {{ label }}
    </label>
    <div class="flex items-center gap-3">
      <input
        :type="type"
        :value="value"
        :placeholder="placeholder"
        class="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-2.5 text-sm font-medium placeholder:text-slate-400"
        readonly
      />
      <span v-if="suffix" class="shrink-0 text-xs font-medium text-slate-400">{{ suffix }}</span>
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
