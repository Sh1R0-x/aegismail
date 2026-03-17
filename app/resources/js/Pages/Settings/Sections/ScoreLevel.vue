<template>
  <div class="flex items-center justify-between rounded-xl px-4 py-2.5">
    <div class="flex items-center gap-2">
      <span :class="['inline-flex items-center rounded-md border px-3 py-1 text-[10px] font-black uppercase tracking-wider', color]">
        {{ label }}
      </span>
    </div>
    <span class="text-xs font-bold text-slate-500">{{ range }} pts</span>
  </div>
</template>

<script setup>
defineProps({
  label: { type: String, required: true },
  range: { type: String, required: true },
  color: { type: String, required: true },
});
</script>
