<template>
  <div class="rounded-lg border bg-white">
    <div class="flex items-center justify-between border-b border-gray-100 px-4 py-3">
      <h3 class="text-sm font-semibold text-gray-900">Vérification pré-envoi</h3>
      <span
        :class="[
          'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium',
          overallStatus === 'pass' ? 'bg-green-50 text-green-700' :
          overallStatus === 'warning' ? 'bg-amber-50 text-amber-700' :
          'bg-red-50 text-red-700',
        ]"
      >
        {{ overallStatus === 'pass' ? 'OK' : overallStatus === 'warning' ? 'Avertissements' : 'Bloqué' }}
      </span>
    </div>
    <div class="divide-y divide-gray-50">
      <PreflightRow
        v-for="check in checks"
        :key="check.id"
        :check="check"
      />
    </div>
    <div v-if="checks.length === 0" class="px-4 py-6 text-center text-sm text-gray-400">
      Aucune vérification lancée.
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import PreflightRow from './PreflightRow.vue';

const props = defineProps({
  checks: { type: Array, default: () => [] },
});

const overallStatus = computed(() => {
  if (props.checks.some(c => c.level === 'error')) return 'error';
  if (props.checks.some(c => c.level === 'warning')) return 'warning';
  return 'pass';
});
</script>
