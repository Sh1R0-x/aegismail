<template>
  <div class="flex flex-col gap-6 md:flex-row md:items-end md:justify-between">
    <div class="space-y-1">
      <h2 class="text-3xl font-extrabold tracking-tight text-slate-900">{{ title }}</h2>
      <p v-if="subtitle" class="font-medium text-slate-500">{{ subtitle }}</p>
    </div>
    <div class="flex items-center gap-3">
      <slot />
    </div>
  </div>
</template>

<script setup>
defineProps({
  title: { type: String, required: true },
  subtitle: { type: String, default: '' },
});
</script>
