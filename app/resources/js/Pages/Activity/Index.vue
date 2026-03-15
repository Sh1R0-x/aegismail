<template>
  <CrmLayout title="Activité" current-page="activity">
    <div class="space-y-4">
      <!-- Filters -->
      <div class="flex items-center gap-3">
        <select v-model="filter" class="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm text-gray-700">
          <option value="all">Tous les événements</option>
          <option value="sent">Envois</option>
          <option value="opened">Ouvertures</option>
          <option value="replied">Réponses</option>
          <option value="auto_replied">Réponses automatiques</option>
          <option value="bounced">Bounces</option>
        </select>
        <input
          v-model="search"
          type="text"
          placeholder="Rechercher par e-mail, contact, organisation…"
          class="flex-1 rounded-md border border-gray-300 px-3 py-1.5 text-sm placeholder:text-gray-400"
        />
      </div>

      <!-- Timeline -->
      <div class="rounded-lg border border-gray-200 bg-white p-4">
        <div v-if="events.length === 0" class="py-8 text-center text-sm text-gray-400">
          Aucune activité enregistrée.
        </div>
        <div v-else>
          <TimelineEntry
            v-for="event in events"
            :key="event.id"
            :title="event.title"
            :description="event.description"
            :status="event.status"
            :direction="event.direction"
            :is-auto-reply="event.isAutoReply"
            :is-bounce="event.isBounce"
            :date="event.date"
          />
        </div>
      </div>
    </div>
  </CrmLayout>
</template>

<script setup>
import { ref } from 'vue';
import CrmLayout from '@/Layouts/CrmLayout.vue';
import TimelineEntry from '@/Components/Timeline/TimelineEntry.vue';

defineProps({
  events: { type: Array, default: () => [] },
});

const filter = ref('all');
const search = ref('');
</script>
