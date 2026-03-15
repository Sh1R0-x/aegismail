<template>
  <div class="rounded-lg border border-gray-200 bg-white">
    <div class="border-b border-gray-100 px-4 py-3">
      <h3 class="text-sm font-semibold text-gray-900">{{ title }}</h3>
      <p v-if="subtitle" class="mt-0.5 text-xs text-gray-500">{{ subtitle }}</p>
    </div>
    <div class="px-4 py-4">
      <slot />
    </div>
    <div v-if="$slots.footer" class="border-t border-gray-100 px-4 py-3">
      <slot name="footer" />
    </div>
  </div>
</template>

<script setup>
defineProps({
  title: { type: String, required: true },
  subtitle: { type: String, default: '' },
});
</script>
