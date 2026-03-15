<template>
  <span
    :class="[
      'inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium',
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
  cold:       { label: 'Froid',      class: 'bg-blue-50 text-blue-700',      dotClass: 'bg-blue-400' },
  warm:       { label: 'Tiède',      class: 'bg-yellow-50 text-yellow-800',  dotClass: 'bg-yellow-400' },
  interested: { label: 'Intéressé',  class: 'bg-orange-50 text-orange-700',  dotClass: 'bg-orange-400' },
  engaged:    { label: 'Engagé',     class: 'bg-green-50 text-green-700',    dotClass: 'bg-green-500' },
  excluded:   { label: 'À exclure',  class: 'bg-red-100 text-red-700',       dotClass: 'bg-red-500' },
};

const config = computed(() => levelMap[props.level] || levelMap.cold);
</script>
