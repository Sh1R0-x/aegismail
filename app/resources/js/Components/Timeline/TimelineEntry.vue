<template>
  <div class="relative flex gap-4 pb-7 last:pb-0">
    <!-- Connective line -->
    <div class="absolute left-3.5 top-7 -bottom-0 w-px bg-slate-200 last:hidden" />

    <!-- Dot -->
    <div class="relative flex h-7 w-7 shrink-0 items-center justify-center">
      <span
        :class="[
          'h-3 w-3 rounded-full ring-4 ring-white',
          dotColor,
        ]"
      />
    </div>

    <!-- Content -->
    <div class="flex-1 min-w-0">
      <div class="flex items-center gap-2">
        <Link
          v-if="threadId"
          :href="`/threads/${threadId}`"
          class="text-sm font-bold text-blue-700 hover:text-blue-900 hover:underline truncate"
        >
          {{ title }}
        </Link>
        <p v-else class="text-sm font-bold text-slate-900 truncate">{{ title }}</p>
        <StatusBadge v-if="status" :status="status" />
        <span
          v-if="isAutoReply"
          class="inline-flex items-center rounded-md border border-amber-200 bg-amber-50 px-2.5 py-0.5 text-[10px] font-black uppercase tracking-wider text-amber-700"
        >
          Auto
        </span>
        <span
          v-if="isBounce"
          class="inline-flex items-center rounded-md border border-red-200 bg-red-50 px-2.5 py-0.5 text-[10px] font-black uppercase tracking-wider text-red-700"
        >
          Bounce
        </span>
      </div>
      <p v-if="description" class="mt-0.5 text-sm text-slate-500 truncate">{{ description }}</p>
      <p class="mt-1 text-xs font-medium text-slate-400">{{ formattedDate }}</p>
    </div>

    <!-- Direction indicator -->
    <div v-if="direction" class="shrink-0 pt-0.5">
      <span
        :class="[
          'inline-flex items-center rounded-md border px-3 py-1 text-[10px] font-black uppercase tracking-wider',
          direction === 'outbound'
            ? 'border-blue-200 bg-blue-50 text-blue-600'
            : 'border-slate-200 bg-slate-50 text-slate-600',
        ]"
      >
        {{ direction === 'outbound' ? '→ Envoyé' : '← Reçu' }}
      </span>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import StatusBadge from '@/Components/Badges/StatusBadge.vue';

const props = defineProps({
  title: { type: String, required: true },
  description: { type: String, default: '' },
  status: { type: String, default: '' },
  direction: { type: String, default: '' },
  isAutoReply: { type: Boolean, default: false },
  isBounce: { type: Boolean, default: false },
  date: { type: String, default: '' },
  threadId: { type: [Number, String], default: null },
});

const dotColor = computed(() => {
  if (props.isBounce) return 'bg-red-500';
  if (props.isAutoReply) return 'bg-amber-400';
  if (props.direction === 'outbound') return 'bg-blue-400';
  return 'bg-slate-400';
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
