<template>
  <div class="flex items-start gap-3 px-4 py-2.5">
    <!-- Icon -->
    <div class="shrink-0 pt-0.5">
      <span
        :class="[
          'flex h-5 w-5 items-center justify-center rounded-full text-xs',
          check.level === 'error' ? 'bg-red-100 text-red-600' :
          check.level === 'warning' ? 'bg-amber-100 text-amber-600' :
          'bg-green-100 text-green-600',
        ]"
      >
        <template v-if="check.level === 'pass'">✓</template>
        <template v-else-if="check.level === 'warning'">!</template>
        <template v-else>✕</template>
      </span>
    </div>
    <!-- Details -->
    <div class="min-w-0 flex-1">
      <p class="text-sm text-gray-900">{{ check.label }}</p>
      <p v-if="check.detail" class="mt-0.5 text-xs text-gray-500">{{ check.detail }}</p>
    </div>
  </div>
</template>

<script setup>
defineProps({
  check: { type: Object, required: true },
});
</script>
