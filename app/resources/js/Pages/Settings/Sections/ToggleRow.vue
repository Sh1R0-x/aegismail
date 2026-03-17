<template>
  <div class="flex items-center justify-between">
    <span class="text-sm font-bold text-slate-700">{{ label }}</span>
    <button
      :class="[
        'relative inline-flex h-6 w-10 shrink-0 cursor-default rounded-full border-2 border-transparent transition-colors',
        enabled ? 'bg-emerald-500' : 'bg-slate-200',
      ]"
      role="switch"
      :aria-checked="enabled"
    >
      <span
        :class="[
          'pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition-transform',
          enabled ? 'translate-x-4' : 'translate-x-0',
        ]"
      />
    </button>
  </div>
</template>

<script setup>
defineProps({
  label: { type: String, required: true },
  enabled: { type: Boolean, default: false },
});
</script>
