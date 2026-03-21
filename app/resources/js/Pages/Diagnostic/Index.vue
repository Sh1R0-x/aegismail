<template>
  <CrmLayout title="Diagnostic" subtitle="Supervision système, événements et détection de blocages" current-page="diagnostic">
    <!-- Health panel -->
    <div class="mb-8 grid gap-4 md:grid-cols-4">
      <div class="rounded-xl border border-slate-200 bg-white p-4">
        <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Pilote passerelle</p>
        <p class="mt-1 text-lg font-bold" :class="health.gateway_driver === 'stub' ? 'text-amber-600' : 'text-slate-900'">
          {{ health.gateway_driver ?? '…' }}
        </p>
      </div>
      <div class="rounded-xl border border-slate-200 bg-white p-4">
        <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">File d'envoi</p>
        <p class="mt-1 text-lg font-bold text-slate-900">
          {{ health.queue?.queued ?? 0 }} en attente · {{ health.queue?.sending ?? 0 }} en cours
        </p>
      </div>
      <div class="rounded-xl border border-slate-200 bg-white p-4">
        <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Bloqués</p>
        <p class="mt-1 text-lg font-bold" :class="(health.queue?.stuck ?? 0) > 0 ? 'text-red-600' : 'text-emerald-600'">
          {{ health.queue?.stuck ?? 0 }}
        </p>
      </div>
      <div class="rounded-xl border border-slate-200 bg-white p-4">
        <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Erreurs (24h)</p>
        <p class="mt-1 text-lg font-bold" :class="(health.errors_last_24h ?? 0) > 0 ? 'text-amber-600' : 'text-slate-900'">
          {{ health.errors_last_24h ?? 0 }}
        </p>
      </div>
    </div>

    <!-- Provider health -->
    <div v-if="health.providers && health.providers.length" class="mb-8">
      <h2 class="mb-3 text-sm font-bold text-slate-700">Providers SMTP</h2>
      <div class="grid gap-3 md:grid-cols-2">
        <div
          v-for="prov in health.providers"
          :key="prov.provider"
          class="flex items-center justify-between rounded-xl border border-slate-200 bg-white px-4 py-3"
        >
          <div>
            <p class="text-sm font-bold text-slate-800">{{ prov.label }}</p>
            <p class="text-xs text-slate-500">{{ prov.health_message ?? 'Aucun message' }}</p>
          </div>
          <span
            :class="[
              'rounded-full px-2.5 py-1 text-[11px] font-bold uppercase tracking-wide',
              healthBadge(prov.health_status),
            ]"
          >
            {{ { healthy: 'Sain', warning: 'Dégradé', critical: 'Critique', unknown: 'Inconnu' }[prov.health_status] || prov.health_status }}
          </span>
        </div>
      </div>
    </div>

    <!-- Stuck recipients alert -->
    <div
      v-if="(health.queue?.stuck ?? 0) > 0"
      class="mb-6 rounded-xl border border-red-200 bg-red-50 px-5 py-3"
    >
      <div class="flex items-center justify-between">
        <div>
          <p class="text-sm font-bold text-red-800">{{ health.queue.stuck }} destinataire(s) bloqué(s)</p>
          <p class="text-xs text-red-600">En statut « queued » ou « sending » depuis plus de 30 min.</p>
        </div>
        <button
          class="rounded-lg border border-red-300 bg-white px-3 py-1.5 text-xs font-bold text-red-700 hover:bg-red-50"
          @click="showStuck = !showStuck"
        >
          {{ showStuck ? 'Masquer' : 'Voir détails' }}
        </button>
      </div>
      <div v-if="showStuck && stuckList.length" class="mt-3 max-h-60 overflow-y-auto">
        <table class="w-full text-xs">
          <thead>
            <tr class="border-b border-red-200 text-left text-red-700">
              <th class="py-1 pr-3">Email</th>
              <th class="py-1 pr-3">Statut</th>
              <th class="py-1 pr-3">Campagne</th>
              <th class="py-1">Programmé</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="r in stuckList" :key="r.id" class="border-b border-red-100 text-red-800">
              <td class="py-1 pr-3 font-medium">{{ r.email }}</td>
              <td class="py-1 pr-3">{{ r.status }}</td>
              <td class="py-1 pr-3">{{ r.campaign?.name ?? '—' }}</td>
              <td class="py-1">{{ formatDateFR(r.scheduled_for ?? r.created_at) }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Events -->
    <div class="rounded-xl border border-slate-200 bg-white">
      <div class="flex items-center justify-between border-b border-slate-100 px-5 py-3">
        <h2 class="text-sm font-bold text-slate-800">Journal des événements</h2>
        <div class="flex items-center gap-2">
          <select
            v-model="eventTypeFilter"
            class="rounded-lg border border-slate-200 bg-slate-50 px-2 py-1.5 text-xs font-medium"
            @change="loadEvents"
          >
            <option value="">Tous les types</option>
            <option v-for="(count, type) in eventTypes" :key="type" :value="type">
              {{ type }} ({{ count }})
            </option>
          </select>
          <input
            v-model="eventSearch"
            type="text"
            placeholder="Rechercher…"
            class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-1.5 text-xs placeholder:text-slate-400"
            @keyup.enter="loadEvents"
          />
          <button
            class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-bold text-slate-600 hover:bg-slate-50"
            @click="loadEvents"
          >
            Filtrer
          </button>
        </div>
      </div>

      <div v-if="loadingEvents" class="px-5 py-8 text-center text-xs text-slate-400">Chargement…</div>

      <div v-else-if="events.length === 0" class="px-5 py-8 text-center text-xs text-slate-400">
        Aucun événement trouvé.
      </div>

      <div v-else class="divide-y divide-slate-100">
        <div
          v-for="event in events"
          :key="event.id"
          class="px-5 py-3 hover:bg-slate-50 cursor-pointer"
          @click="toggleEvent(event.id)"
        >
          <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
              <span
                :class="[
                  'inline-block h-2 w-2 rounded-full',
                  event.event_type.includes('failed') ? 'bg-red-500'
                    : event.event_type.includes('succeeded') ? 'bg-emerald-500'
                    : 'bg-slate-400',
                ]"
              ></span>
              <span class="text-xs font-bold text-slate-800">{{ event.event_type }}</span>
              <span v-if="event.campaign_id" class="text-[10px] text-slate-400">campagne #{{ event.campaign_id }}</span>
            </div>
            <span class="text-[10px] text-slate-400">{{ formatDateFR(event.occurred_at) }}</span>
          </div>
          <div v-if="expandedEvent === event.id" class="mt-2 rounded-lg bg-slate-50 p-3 text-[11px] font-mono text-slate-600 overflow-x-auto">
            <pre class="whitespace-pre-wrap">{{ JSON.stringify(event.event_payload, null, 2) }}</pre>
          </div>
        </div>
      </div>

      <!-- Pagination -->
      <div v-if="eventPagination.last_page > 1" class="flex items-center justify-between border-t border-slate-100 px-5 py-3">
        <span class="text-[10px] text-slate-400">
          {{ eventPagination.from }}–{{ eventPagination.to }} sur {{ eventPagination.total }}
        </span>
        <div class="flex gap-1">
          <button
            :disabled="eventPagination.current_page <= 1"
            class="rounded-lg border border-slate-200 px-2.5 py-1 text-xs font-bold text-slate-600 disabled:opacity-30"
            @click="loadEvents(eventPagination.current_page - 1)"
          >
            ←
          </button>
          <button
            :disabled="eventPagination.current_page >= eventPagination.last_page"
            class="rounded-lg border border-slate-200 px-2.5 py-1 text-xs font-bold text-slate-600 disabled:opacity-30"
            @click="loadEvents(eventPagination.current_page + 1)"
          >
            →
          </button>
        </div>
      </div>
    </div>
  </CrmLayout>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import axios from 'axios';
import CrmLayout from '@/Layouts/CrmLayout.vue';
import { formatDateFR } from '@/Utils/formatDate.js';

const health = ref({});
const events = ref([]);
const eventTypes = ref({});
const loadingEvents = ref(false);
const eventTypeFilter = ref('');
const eventSearch = ref('');
const expandedEvent = ref(null);
const showStuck = ref(false);
const stuckList = ref([]);
const eventPagination = ref({ current_page: 1, last_page: 1, from: 0, to: 0, total: 0 });

function healthBadge(status) {
  if (status === 'healthy') return 'bg-emerald-100 text-emerald-700';
  if (status === 'warning') return 'bg-amber-100 text-amber-700';
  return 'bg-slate-100 text-slate-500';
}

function toggleEvent(id) {
  expandedEvent.value = expandedEvent.value === id ? null : id;
}

async function loadHealth() {
  try {
    const { data } = await axios.get('/api/diagnostic/health');
    health.value = data;
    if ((data.queue?.stuck ?? 0) > 0) {
      await loadStuck();
    }
  } catch {
    // silent
  }
}

async function loadEventTypes() {
  try {
    const { data } = await axios.get('/api/diagnostic/event-types');
    eventTypes.value = data;
  } catch {
    // silent
  }
}

async function loadEvents(page = 1) {
  loadingEvents.value = true;
  try {
    const params = { page, per_page: 30 };
    if (eventTypeFilter.value) params.event_type = eventTypeFilter.value;
    if (eventSearch.value.trim()) params.search = eventSearch.value.trim();
    const { data } = await axios.get('/api/diagnostic/events', { params });
    events.value = data.data ?? [];
    eventPagination.value = {
      current_page: data.current_page,
      last_page: data.last_page,
      from: data.from ?? 0,
      to: data.to ?? 0,
      total: data.total ?? 0,
    };
  } catch {
    events.value = [];
  } finally {
    loadingEvents.value = false;
  }
}

async function loadStuck() {
  try {
    const { data } = await axios.get('/api/diagnostic/stuck-recipients');
    stuckList.value = data.data ?? [];
  } catch {
    stuckList.value = [];
  }
}

onMounted(() => {
  loadHealth();
  loadEventTypes();
  loadEvents();
});
</script>
