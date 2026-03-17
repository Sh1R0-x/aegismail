<template>
  <div class="flex items-center justify-between rounded-xl border border-slate-200 px-4 py-3">
    <div class="flex items-center gap-3">
      <span
        :class="[
          'flex h-6 w-6 items-center justify-center rounded-lg text-xs font-black',
          status === 'valid' ? 'bg-emerald-100 text-emerald-600' : 'bg-red-100 text-red-600',
        ]"
      >
        {{ status === 'valid' ? '✓' : '✕' }}
      </span>
      <span class="text-sm font-bold text-slate-900">{{ label }}</span>
    </div>
    <span
      :class="[
        'text-xs font-bold',
        status === 'valid' ? 'text-emerald-600' : 'text-red-600',
      ]"
    >
      {{ status === 'valid' ? 'Configuré' : 'Non détecté' }}
    </span>
  </div>
</template>

<script setup>
defineProps({
  label: { type: String, required: true },
  status: { type: String, default: 'missing' },
});
</script>
