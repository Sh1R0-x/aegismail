<template>
  <div class="flex items-center justify-between">
    <div>
      <h1 class="text-lg font-semibold text-gray-900">{{ title }}</h1>
      <p v-if="subtitle" class="mt-0.5 text-sm text-gray-500">{{ subtitle }}</p>
    </div>
    <div class="flex items-center gap-2">
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
