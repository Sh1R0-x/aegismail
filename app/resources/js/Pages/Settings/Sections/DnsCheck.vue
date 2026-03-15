<template>
  <div class="flex items-center justify-between rounded-md border border-gray-100 px-3 py-2">
    <div class="flex items-center gap-2">
      <span
        :class="[
          'flex h-5 w-5 items-center justify-center rounded-full text-xs font-bold',
          status === 'valid' ? 'bg-green-100 text-green-600' : 'bg-red-100 text-red-600',
        ]"
      >
        {{ status === 'valid' ? '✓' : '✕' }}
      </span>
      <span class="text-sm font-medium text-gray-900">{{ label }}</span>
    </div>
    <span
      :class="[
        'text-xs font-medium',
        status === 'valid' ? 'text-green-600' : 'text-red-600',
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
