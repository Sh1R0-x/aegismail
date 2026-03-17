<template>
  <span
    :class="[
      'inline-flex items-center gap-1.5 rounded-md border px-3 py-1 text-[10px] font-black uppercase tracking-wider',
      config.class,
    ]"
  >
    <span :class="['h-1.5 w-1.5 rounded-full', config.dotClass]" />
    {{ config.label }}
    <span v-if="score !== undefined" class="text-[10px] opacity-70">({{ score }})</span>
  </span>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
  level: { type: String, required: true },
  score: { type: Number, default: undefined },
});

const levelMap = {
  cold:       { label: 'Froid',      class: 'border-blue-200 bg-blue-50 text-blue-700',         dotClass: 'bg-blue-400' },
  warm:       { label: 'Tiède',      class: 'border-yellow-200 bg-yellow-50 text-yellow-800',   dotClass: 'bg-yellow-400' },
  interested: { label: 'Intéressé',  class: 'border-orange-200 bg-orange-50 text-orange-700',   dotClass: 'bg-orange-400' },
  engaged:    { label: 'Engagé',     class: 'border-emerald-200 bg-emerald-50 text-emerald-700', dotClass: 'bg-emerald-500' },
  excluded:   { label: 'À exclure',  class: 'border-red-200 bg-red-100 text-red-700',           dotClass: 'bg-red-500' },
};

const config = computed(() => levelMap[props.level] || levelMap.cold);
</script>
