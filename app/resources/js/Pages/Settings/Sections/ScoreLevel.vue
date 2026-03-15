<template>
  <div class="flex items-center justify-between rounded-md px-3 py-1.5">
    <div class="flex items-center gap-2">
      <span :class="['inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium', color]">
        {{ label }}
      </span>
    </div>
    <span class="text-xs text-gray-500">{{ range }} pts</span>
  </div>
</template>

<script setup>
defineProps({
  label: { type: String, required: true },
  range: { type: String, required: true },
  color: { type: String, required: true },
});
</script>
