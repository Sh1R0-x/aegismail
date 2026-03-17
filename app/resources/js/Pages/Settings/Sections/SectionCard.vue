<template>
  <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
    <div class="border-b border-slate-200 px-6 py-4">
      <h3 class="text-sm font-bold text-slate-900">{{ title }}</h3>
      <p v-if="subtitle" class="mt-0.5 text-xs font-medium text-slate-500">{{ subtitle }}</p>
    </div>
    <div class="px-6 py-5">
      <slot />
    </div>
    <div v-if="$slots.footer" class="border-t border-slate-200 px-6 py-4">
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
