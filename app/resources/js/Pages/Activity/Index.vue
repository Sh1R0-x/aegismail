<template>
  <CrmLayout title="Activité" subtitle="Historique des événements et interactions" current-page="activity">
    <div class="space-y-6">
      <!-- Filters -->
      <div class="flex items-center gap-3">
        <select v-model="filter" class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-2.5 text-sm font-medium text-slate-700 focus:ring-2 focus:ring-blue-500/30 outline-none">
          <option value="all">Tous les événements</option>
          <option value="sent">Envois</option>
          <option value="opened">Ouvertures</option>
          <option value="replied">Réponses</option>
          <option value="auto_replied">Réponses automatiques</option>
          <option value="bounced">Rebonds</option>
        </select>
        <input
          v-model="search"
          type="text"
          placeholder="Rechercher par e-mail, contact, organisation…"
          class="flex-1 rounded-xl border border-slate-200 bg-slate-50 px-4 py-2.5 text-sm font-medium placeholder:text-slate-500 focus:ring-2 focus:ring-blue-500/30 outline-none"
        />
      </div>

      <!-- Timeline -->
      <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <div v-if="filteredEvents.length === 0" class="py-12 text-center text-sm font-medium text-slate-500">
          <template v-if="events.length === 0">Aucune activité enregistrée.</template>
          <template v-else>Aucun résultat pour ces filtres.</template>
        </div>
        <div v-else>
          <TimelineEntry
            v-for="event in filteredEvents"
            :key="event.id"
            :title="event.title"
            :description="event.description"
            :status="event.status"
            :direction="event.direction"
            :is-auto-reply="event.isAutoReply"
            :is-bounce="event.isBounce"
            :date="event.date"
            :thread-id="event.threadId"
          />
        </div>
      </div>
    </div>
  </CrmLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import CrmLayout from '@/Layouts/CrmLayout.vue';
import TimelineEntry from '@/Components/Timeline/TimelineEntry.vue';

const props = defineProps({
  events: { type: Array, default: () => [] },
});

const filter = ref('all');
const search = ref('');

const filteredEvents = computed(() => {
  let list = props.events;

  if (filter.value !== 'all') {
    list = list.filter(e => {
      if (filter.value === 'bounced') return e.isBounce === true;
      if (filter.value === 'auto_replied') return e.isAutoReply === true;
      return e.status === filter.value;
    });
  }

  if (search.value.trim()) {
    const q = search.value.toLowerCase();
    list = list.filter(e =>
      e.title?.toLowerCase().includes(q) ||
      e.description?.toLowerCase().includes(q),
    );
  }

  return list;
});
</script>
