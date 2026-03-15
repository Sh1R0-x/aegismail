<template>
  <div class="relative flex gap-3 pb-6 last:pb-0">
    <!-- Connective line -->
    <div class="absolute left-3.5 top-7 -bottom-0 w-px bg-gray-200 last:hidden" />

    <!-- Dot -->
    <div class="relative flex h-7 w-7 shrink-0 items-center justify-center">
      <span
        :class="[
          'h-2.5 w-2.5 rounded-full ring-4 ring-white',
          dotColor,
        ]"
      />
    </div>

    <!-- Content -->
    <div class="flex-1 min-w-0">
      <div class="flex items-center gap-2">
        <p class="text-sm font-medium text-gray-900 truncate">{{ title }}</p>
        <StatusBadge v-if="status" :status="status" />
        <span
          v-if="isAutoReply"
          class="inline-flex items-center rounded bg-amber-50 px-1.5 py-0.5 text-xs font-medium text-amber-700 ring-1 ring-amber-200"
        >
          Auto
        </span>
        <span
          v-if="isBounce"
          class="inline-flex items-center rounded bg-red-50 px-1.5 py-0.5 text-xs font-bold text-red-700 ring-1 ring-red-300"
        >
          Bounce
        </span>
      </div>
      <p v-if="description" class="mt-0.5 text-sm text-gray-500 truncate">{{ description }}</p>
      <p class="mt-1 text-xs text-gray-400">{{ formattedDate }}</p>
    </div>

    <!-- Direction indicator -->
    <div v-if="direction" class="shrink-0 pt-0.5">
      <span
        :class="[
          'inline-flex items-center rounded px-1.5 py-0.5 text-xs font-medium',
          direction === 'outbound'
            ? 'bg-blue-50 text-blue-600'
            : 'bg-gray-100 text-gray-600',
        ]"
      >
        {{ direction === 'outbound' ? '→ Envoyé' : '← Reçu' }}
      </span>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import StatusBadge from '@/Components/Badges/StatusBadge.vue';

const props = defineProps({
  title: { type: String, required: true },
  description: { type: String, default: '' },
  status: { type: String, default: '' },
  direction: { type: String, default: '' },
  isAutoReply: { type: Boolean, default: false },
  isBounce: { type: Boolean, default: false },
  date: { type: String, default: '' },
});

const dotColor = computed(() => {
  if (props.isBounce) return 'bg-red-500';
  if (props.isAutoReply) return 'bg-amber-400';
  if (props.direction === 'outbound') return 'bg-blue-400';
  return 'bg-gray-400';
});

const formattedDate = computed(() => {
  if (!props.date) return '';
  return new Date(props.date).toLocaleString('fr-FR', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
});
</script>
