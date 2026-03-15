<template>
  <CrmLayout title="Mails" current-page="mails">
    <div class="space-y-6">

      <!-- Mode selector + Actions -->
      <div class="flex items-center justify-between">
        <div class="flex items-center gap-1 rounded-lg border border-gray-200 bg-white p-0.5">
          <button
            :class="[
              'rounded-md px-4 py-1.5 text-sm font-medium transition-colors',
              view === 'list' ? 'bg-gray-900 text-white' : 'text-gray-600 hover:text-gray-900',
            ]"
            @click="view = 'list'"
          >
            Envois
          </button>
          <button
            :class="[
              'rounded-md px-4 py-1.5 text-sm font-medium transition-colors',
              view === 'compose' ? 'bg-gray-900 text-white' : 'text-gray-600 hover:text-gray-900',
            ]"
            @click="openComposer('simple')"
          >
            Mail simple
          </button>
          <button
            :class="[
              'rounded-md px-4 py-1.5 text-sm font-medium transition-colors',
              view === 'compose-multi' ? 'bg-gray-900 text-white' : 'text-gray-600 hover:text-gray-900',
            ]"
            @click="openComposer('multiple')"
          >
            Mail multiple
          </button>
        </div>
        <div class="flex items-center gap-2 text-xs text-gray-500">
          <span class="inline-flex items-center gap-1">
            <span class="h-1.5 w-1.5 rounded-full bg-green-500" />
            {{ stats.sentToday }}/{{ stats.dailyLimit }} aujourd'hui
          </span>
        </div>
      </div>

      <!-- LIST VIEW -->
      <div v-if="view === 'list'" class="space-y-4">
        <!-- Filters -->
        <div class="flex items-center gap-3">
          <select v-model="statusFilter" class="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm text-gray-700">
            <option value="all">Tous les statuts</option>
            <option value="sent">Envoyés</option>
            <option value="opened">Ouverts</option>
            <option value="replied">Répondus</option>
            <option value="auto_replied">Réponses auto</option>
            <option value="hard_bounced">Hard bounces</option>
            <option value="scheduled">Planifiés</option>
          </select>
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Rechercher par destinataire, sujet…"
            class="flex-1 rounded-md border border-gray-300 px-3 py-1.5 text-sm placeholder:text-gray-400"
          />
        </div>

        <!-- Messages table -->
        <div class="rounded-lg border border-gray-200 bg-white">
          <table class="w-full text-sm">
            <thead>
              <tr class="border-b border-gray-100 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                <th class="px-4 py-2.5">Destinataire</th>
                <th class="px-4 py-2.5">Sujet</th>
                <th class="px-4 py-2.5">Statut</th>
                <th class="px-4 py-2.5">Type</th>
                <th class="px-4 py-2.5">Date</th>
                <th class="px-4 py-2.5 text-right">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
              <tr v-if="messages.length === 0">
                <td colspan="6" class="px-4 py-12 text-center text-gray-400">
                  Aucun message. Lancez un envoi simple ou multiple.
                </td>
              </tr>
              <tr
                v-for="msg in messages"
                :key="msg.id"
                :class="[
                  'hover:bg-gray-50',
                  msg.status === 'hard_bounced' ? 'bg-red-50/50' : '',
                  msg.status === 'auto_replied' ? 'bg-amber-50/30' : '',
                ]"
              >
                <td class="px-4 py-2.5">
                  <p class="font-medium text-gray-900 truncate max-w-[200px]">{{ msg.recipientEmail }}</p>
                  <p v-if="msg.recipientName" class="text-xs text-gray-400">{{ msg.recipientName }}</p>
                </td>
                <td class="px-4 py-2.5 text-gray-700 truncate max-w-xs">{{ msg.subject }}</td>
                <td class="px-4 py-2.5">
                  <StatusBadge :status="msg.status" />
                </td>
                <td class="px-4 py-2.5">
                  <span class="inline-flex items-center rounded bg-gray-100 px-1.5 py-0.5 text-xs text-gray-600">
                    {{ msg.type === 'campaign' ? 'Multiple' : 'Simple' }}
                  </span>
                </td>
                <td class="px-4 py-2.5 text-gray-400 text-xs">{{ msg.sentAt }}</td>
                <td class="px-4 py-2.5 text-right">
                  <button class="text-xs font-medium text-blue-600 hover:text-blue-800">Voir</button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- COMPOSE VIEW (shared for simple & multiple) -->
      <div v-if="view === 'compose' || view === 'compose-multi'" class="space-y-4">
        <div class="rounded-lg border border-gray-200 bg-white">
          <div class="border-b border-gray-100 px-4 py-3">
            <h2 class="text-sm font-semibold text-gray-900">
              {{ composeMode === 'multiple' ? 'Nouvel envoi multiple' : 'Nouveau mail' }}
            </h2>
          </div>

          <div class="space-y-4 p-4">
            <!-- Recipients -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">
                {{ composeMode === 'multiple' ? 'Destinataires' : 'Destinataire' }}
              </label>
              <input
                type="text"
                :placeholder="composeMode === 'multiple'
                  ? 'Ajouter des destinataires ou coller une liste…'
                  : 'adresse@exemple.fr'"
                class="w-full rounded-md border border-gray-300 px-3 py-1.5 text-sm placeholder:text-gray-400"
              />
              <p v-if="composeMode === 'multiple'" class="mt-1 text-xs text-gray-400">
                Les destinataires recevront chacun un mail individuel.
              </p>
            </div>

            <!-- Subject -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Sujet</label>
              <input
                type="text"
                placeholder="Objet du mail"
                class="w-full rounded-md border border-gray-300 px-3 py-1.5 text-sm placeholder:text-gray-400"
              />
            </div>

            <!-- Template selector -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Modèle (optionnel)</label>
              <select class="w-full rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm text-gray-700">
                <option value="">Aucun modèle</option>
              </select>
            </div>

            <!-- Content -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Contenu HTML</label>
              <textarea
                rows="8"
                placeholder="Contenu du message…"
                class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm font-mono placeholder:text-gray-400"
              />
            </div>

            <!-- Text version -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Version texte</label>
              <textarea
                rows="4"
                placeholder="Version texte brut du message (obligatoire pour la délivrabilité)"
                class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm placeholder:text-gray-400"
              />
            </div>

            <!-- Attachments -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Pièces jointes</label>
              <div class="flex items-center gap-2">
                <button class="rounded-md border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50">
                  Ajouter un fichier
                </button>
                <span class="text-xs text-gray-400">Aucun fichier sélectionné</span>
              </div>
            </div>
          </div>

          <!-- Actions bar -->
          <div class="flex items-center justify-between border-t border-gray-100 px-4 py-3">
            <div class="flex items-center gap-2">
              <button
                class="rounded-md bg-gray-900 px-4 py-1.5 text-sm font-medium text-white hover:bg-gray-800"
                @click="showPreflight = true"
              >
                Vérifier et envoyer
              </button>
              <button class="rounded-md border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50">
                Sauvegarder brouillon
              </button>
              <button class="rounded-md border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50">
                Planifier
              </button>
            </div>
            <button
              class="text-sm text-gray-500 hover:text-gray-700"
              @click="view = 'list'"
            >
              Annuler
            </button>
          </div>
        </div>

        <!-- Preflight panel -->
        <PreflightPanel v-if="showPreflight" :checks="preflightChecks" />
      </div>
    </div>
  </CrmLayout>
</template>

<script setup>
import { ref } from 'vue';
import CrmLayout from '@/Layouts/CrmLayout.vue';
import StatusBadge from '@/Components/Badges/StatusBadge.vue';
import PreflightPanel from '@/Components/Preflight/PreflightPanel.vue';

defineProps({
  messages: { type: Array, default: () => [] },
  stats: {
    type: Object,
    default: () => ({ sentToday: 0, dailyLimit: 100 }),
  },
});

const view = ref('list');
const composeMode = ref('simple');
const statusFilter = ref('all');
const searchQuery = ref('');
const showPreflight = ref(false);

const preflightChecks = ref([
  { id: 'smtp', label: 'Connexion SMTP', level: 'pass', detail: 'Connexion active' },
  { id: 'recipients', label: 'Destinataires valides', level: 'pass', detail: 'Aucun exclu' },
  { id: 'text_version', label: 'Version texte présente', level: 'warning', detail: 'Recommandé pour la délivrabilité' },
  { id: 'weight', label: 'Poids du message', level: 'pass', detail: 'Estimation : 12 Ko' },
  { id: 'links', label: 'Nombre de liens', level: 'pass', detail: '2 liens détectés' },
  { id: 'images', label: 'Images distantes', level: 'pass', detail: 'Aucune image distante' },
  { id: 'auth', label: 'Authentification domaine', level: 'pass', detail: 'SPF + DKIM + DMARC valides' },
]);

function openComposer(mode) {
  composeMode.value = mode;
  view.value = mode === 'multiple' ? 'compose-multi' : 'compose';
  showPreflight.value = false;
}
</script>
