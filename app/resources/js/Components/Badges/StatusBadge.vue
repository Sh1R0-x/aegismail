<template>
  <span
    :class="[
      'inline-flex items-center gap-1.5 rounded-md border px-3 py-1 text-[10px] font-black uppercase tracking-wider',
      config.class,
    ]"
  >
    <span v-if="config.dot" :class="['h-1.5 w-1.5 rounded-full', config.dotClass]" />
    {{ config.label }}
  </span>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
  status: { type: String, required: true },
});

const statusMap = {
  draft:              { label: 'Brouillon',       class: 'border-slate-200 bg-slate-50 text-slate-600',            dot: true, dotClass: 'bg-slate-400' },
  scheduled:          { label: 'Planifié',         class: 'border-blue-200 bg-blue-50 text-blue-600',              dot: true, dotClass: 'bg-blue-500' },
  queued:             { label: 'En file',          class: 'border-blue-200 bg-blue-50 text-blue-600',              dot: true, dotClass: 'bg-blue-400' },
  sending:            { label: 'Envoi en cours',   class: 'border-indigo-200 bg-indigo-50 text-indigo-600',        dot: true, dotClass: 'bg-indigo-500' },
  sent:               { label: 'Envoyé',           class: 'border-emerald-200 bg-emerald-50 text-emerald-600',     dot: true, dotClass: 'bg-emerald-500' },
  delivered_if_known: { label: 'Délivré',          class: 'border-emerald-200 bg-emerald-50 text-emerald-700',     dot: true, dotClass: 'bg-emerald-500' },
  opened:             { label: 'Ouvert',           class: 'border-emerald-200 bg-emerald-50 text-emerald-700',     dot: true, dotClass: 'bg-emerald-600' },
  clicked:            { label: 'Cliqué',           class: 'border-teal-200 bg-teal-50 text-teal-700',              dot: true, dotClass: 'bg-teal-500' },
  replied:            { label: 'Répondu',          class: 'border-cyan-200 bg-cyan-50 text-cyan-700',              dot: true, dotClass: 'bg-cyan-500' },
  auto_replied:       { label: 'Réponse auto',     class: 'border-amber-300 bg-amber-50 text-amber-800',           dot: true, dotClass: 'bg-amber-500' },
  soft_bounced:       { label: 'Rebond temporaire', class: 'border-orange-200 bg-orange-50 text-orange-700',        dot: true, dotClass: 'bg-orange-500' },
  hard_bounced:       { label: 'Rebond permanent',  class: 'border-red-300 bg-red-100 text-red-800',                dot: true, dotClass: 'bg-red-600' },
  unsubscribed:       { label: 'Désinscrit',       class: 'border-rose-200 bg-rose-50 text-rose-700',              dot: true, dotClass: 'bg-rose-500' },
  failed:             { label: 'Erreur',           class: 'border-red-200 bg-red-50 text-red-700',                 dot: true, dotClass: 'bg-red-500' },
  cancelled:          { label: 'Annulé',           class: 'border-slate-200 bg-slate-50 text-slate-500',           dot: true, dotClass: 'bg-slate-400' },
  completed:          { label: 'Terminé',          class: 'border-emerald-200 bg-emerald-50 text-emerald-700',     dot: true, dotClass: 'bg-emerald-500' },
};

const config = computed(() => statusMap[props.status] || {
  label: props.status,
  class: 'border-slate-200 bg-slate-50 text-slate-600',
  dot: false,
  dotClass: '',
});
</script>
