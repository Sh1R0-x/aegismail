<template>
  <div class="rounded-xl border border-slate-200 overflow-hidden">
    <!-- Main row -->
    <div class="flex items-center gap-4 px-4 py-3">
      <!-- Status icon -->
      <span
        :class="[
          'flex h-7 w-7 shrink-0 items-center justify-center rounded-lg text-xs font-black',
          statusConfig.iconBg,
        ]"
      >
        {{ statusConfig.icon }}
      </span>

      <!-- Label + diagnostic -->
      <div class="min-w-0 flex-1">
        <div class="flex items-center gap-2">
          <span class="text-sm font-bold text-slate-900">{{ label }}</span>
          <span
            :class="[
              'rounded-md border px-2 py-0.5 text-[10px] font-black uppercase tracking-wider',
              statusConfig.badgeClass,
            ]"
          >
            {{ statusConfig.label }}
          </span>
        </div>
        <p class="mt-0.5 text-xs font-medium text-slate-500">{{ check?.diagnostic_message || '\u2014' }}</p>
      </div>

      <!-- Date + detected value -->
      <div class="shrink-0 text-right space-y-0.5">
        <p v-if="check?.checked_at" class="text-[11px] font-medium text-slate-400">
          {{ formattedDate }}
        </p>
        <p v-if="check?.detected_value" class="text-[11px] font-mono font-medium text-slate-500 max-w-[180px] truncate">
          {{ check.detected_value }}
        </p>
      </div>

      <!-- Toggle logs -->
      <button
        v-if="haslogs"
        class="shrink-0 rounded-lg border border-slate-200 px-2.5 py-1 text-[10px] font-bold text-slate-500 hover:bg-slate-50 transition-colors"
        @click="logsExpanded = !logsExpanded"
      >
        {{ logsExpanded ? 'Masquer' : 'Logs' }}
      </button>
    </div>

    <!-- Logs panel -->
    <div v-if="logsExpanded && haslogs" class="border-t border-slate-100 bg-slate-50 px-4 py-3 space-y-1.5">
      <p class="text-[10px] font-black uppercase tracking-[0.1em] text-slate-400 mb-2">
        Logs de v\u00e9rification
      </p>
      <div
        v-for="(entry, index) in check.logs"
        :key="index"
        class="flex items-start gap-2"
      >
        <span
          :class="[
            'mt-0.5 h-2 w-2 shrink-0 rounded-full',
            entry.level === 'error'
              ? 'bg-red-500'
              : entry.level === 'warning'
                ? 'bg-amber-500'
                : 'bg-slate-400',
          ]"
        />
        <div class="min-w-0 flex-1">
          <p class="text-xs font-medium text-slate-700">{{ entry.message }}</p>
          <p v-if="entry.ts" class="text-[11px] font-medium text-slate-400">
            {{ new Date(entry.ts).toLocaleString('fr-FR') }}
          </p>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue';

const props = defineProps({
  label: { type: String, required: true },
  check: { type: Object, default: () => ({}) },
});

const logsExpanded = ref(false);

const haslogs = computed(() => Array.isArray(props.check?.logs) && props.check.logs.length > 0);

const statusConfig = computed(() => {
  const status = props.check?.status ?? 'not_detected';
  const map = {
    pass: {
      label: 'Pass',
      icon: '\u2713',
      iconBg: 'bg-emerald-100 text-emerald-700',
      badgeClass: 'border-emerald-200 bg-emerald-50 text-emerald-700',
    },
    warning: {
      label: 'Avertissement',
      icon: '\u26a0',
      iconBg: 'bg-amber-100 text-amber-700',
      badgeClass: 'border-amber-200 bg-amber-50 text-amber-700',
    },
    fail: {
      label: '\u00c9chec',
      icon: '\u2715',
      iconBg: 'bg-red-100 text-red-700',
      badgeClass: 'border-red-200 bg-red-50 text-red-700',
    },
    not_detected: {
      label: 'Non d\u00e9tect\u00e9',
      icon: '?',
      iconBg: 'bg-slate-100 text-slate-500',
      badgeClass: 'border-slate-200 bg-slate-50 text-slate-500',
    },
  };
  return map[status] ?? map['not_detected'];
});

const formattedDate = computed(() => {
  if (!props.check?.checked_at) return null;
  try {
    return new Date(props.check.checked_at).toLocaleString('fr-FR', {
      day: '2-digit',
      month: '2-digit',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    });
  } catch {
    return props.check.checked_at;
  }
});
</script>
