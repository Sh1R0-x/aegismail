<template>
  <span
    :class="[
      'inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium',
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
  draft:              { label: 'Brouillon',       class: 'bg-gray-100 text-gray-700',        dot: true, dotClass: 'bg-gray-400' },
  scheduled:          { label: 'Planifié',         class: 'bg-blue-50 text-blue-700',         dot: true, dotClass: 'bg-blue-400' },
  queued:             { label: 'En file',          class: 'bg-blue-50 text-blue-600',         dot: true, dotClass: 'bg-blue-300' },
  sending:            { label: 'Envoi en cours',   class: 'bg-indigo-50 text-indigo-700',     dot: true, dotClass: 'bg-indigo-400' },
  sent:               { label: 'Envoyé',           class: 'bg-green-50 text-green-700',       dot: true, dotClass: 'bg-green-400' },
  delivered_if_known: { label: 'Délivré',          class: 'bg-green-50 text-green-700',       dot: true, dotClass: 'bg-green-500' },
  opened:             { label: 'Ouvert',           class: 'bg-emerald-50 text-emerald-700',   dot: true, dotClass: 'bg-emerald-500' },
  clicked:            { label: 'Cliqué',           class: 'bg-teal-50 text-teal-700',         dot: true, dotClass: 'bg-teal-500' },
  replied:            { label: 'Répondu',          class: 'bg-cyan-50 text-cyan-800',         dot: true, dotClass: 'bg-cyan-500' },
  auto_replied:       { label: 'Réponse auto',     class: 'bg-amber-50 text-amber-800 ring-1 ring-amber-200', dot: true, dotClass: 'bg-amber-500' },
  soft_bounced:       { label: 'Soft bounce',      class: 'bg-orange-50 text-orange-700',     dot: true, dotClass: 'bg-orange-400' },
  hard_bounced:       { label: 'Hard bounce',      class: 'bg-red-100 text-red-800 ring-1 ring-red-300 font-semibold', dot: true, dotClass: 'bg-red-600' },
  unsubscribed:       { label: 'Désinscrit',       class: 'bg-rose-50 text-rose-700',         dot: true, dotClass: 'bg-rose-500' },
  failed:             { label: 'Erreur',           class: 'bg-red-50 text-red-700',           dot: true, dotClass: 'bg-red-500' },
  cancelled:          { label: 'Annulé',           class: 'bg-gray-100 text-gray-500',        dot: true, dotClass: 'bg-gray-400' },
};

const config = computed(() => statusMap[props.status] || {
  label: props.status,
  class: 'bg-gray-100 text-gray-600',
  dot: false,
  dotClass: '',
});
</script>
