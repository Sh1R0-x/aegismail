<template>
  <CrmLayout :title="campaign.name || 'Campagne'" subtitle="Détails, audience et pilotage de la campagne" current-page="campaigns">
    <template #header-actions>
      <div class="flex items-center gap-3">
        <Link href="/campaigns" class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-bold text-slate-600 hover:bg-slate-50 shadow-sm transition-all">
          ← Retour aux campagnes
        </Link>
        <button v-if="campaign.draft?.id" class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-bold text-slate-600 hover:bg-slate-50 shadow-sm transition-all" @click="editing = !editing">
          {{ editing ? 'Voir le résumé' : 'Modifier' }}
        </button>
        <button class="rounded-xl border border-red-200 bg-red-50 px-4 py-2.5 text-xs font-bold text-red-700 hover:bg-red-100 shadow-sm transition-all" @click="removeCampaign">
          Supprimer
        </button>
      </div>
    </template>

    <div v-if="banner" class="mb-4 rounded-xl border px-4 py-3 text-sm font-medium" :class="banner.type === 'error' ? 'border-red-200 bg-red-50 text-red-800' : 'border-emerald-200 bg-emerald-50 text-emerald-800'">
      {{ banner.message }}
    </div>

    <MailComposer
      v-if="editing && campaign.draft?.id"
      :draft="campaign.draft"
      :templates="templates"
      :mode="campaign.type === 'multiple' ? 'multiple' : 'single'"
      @close="editing = false"
      @saved="onDraftSaved"
      @scheduled="onDraftScheduled"
    />

    <div v-else class="space-y-6">
      <section class="grid grid-cols-1 gap-4 md:grid-cols-4">
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
          <p class="text-[10px] font-black uppercase tracking-[0.1em] text-slate-400">Statut</p>
          <p class="mt-2 text-sm font-bold text-slate-900">{{ campaign.status }}</p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
          <p class="text-[10px] font-black uppercase tracking-[0.1em] text-slate-400">Destinataires</p>
          <p class="mt-2 text-sm font-bold text-slate-900">{{ campaign.recipientCount }}</p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
          <p class="text-[10px] font-black uppercase tracking-[0.1em] text-slate-400">Réponses</p>
          <p class="mt-2 text-sm font-bold text-slate-900">{{ campaign.replyCount }}</p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
          <p class="text-[10px] font-black uppercase tracking-[0.1em] text-slate-400">Programmation</p>
          <p class="mt-2 text-sm font-bold text-slate-900">{{ campaign.scheduledAt || 'Non planifiée' }}</p>
        </div>
      </section>

      <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="flex items-center justify-between gap-3">
          <div>
            <p class="text-[10px] font-black uppercase tracking-[0.1em] text-slate-400">Campagne</p>
            <h2 class="mt-1 text-sm font-bold text-slate-900">{{ campaign.name }}</h2>
            <p class="mt-1 text-xs text-slate-500">Couche technique interne: draft #{{ campaign.draftId ?? '—' }}</p>
          </div>
          <span class="text-xs font-medium text-slate-400">{{ campaign.updatedAt || '—' }}</span>
        </div>
      </section>

      <section class="rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 bg-slate-50 px-6 py-4">
          <h3 class="text-sm font-bold text-slate-900">Destinataires et statuts</h3>
        </div>
        <div v-if="campaign.recipients.length === 0" class="px-6 py-12 text-sm text-slate-400">
          Aucun destinataire matérialisé pour le moment. Éditez la campagne puis planifiez-la pour construire l’audience finale.
        </div>
        <table v-else class="w-full text-sm">
          <thead>
            <tr class="border-b border-slate-200 bg-slate-50 text-left text-[10px] font-black uppercase tracking-[0.1em] text-slate-500">
              <th class="px-6 py-4">Destinataire</th>
              <th class="px-6 py-4">Contact</th>
              <th class="px-6 py-4">Organisation</th>
              <th class="px-6 py-4">Statut</th>
              <th class="px-6 py-4">Programmé</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <tr v-for="recipient in campaign.recipients" :key="recipient.id" class="hover:bg-slate-50 transition-colors">
              <td class="px-6 py-4 font-bold text-slate-900">{{ recipient.email }}</td>
              <td class="px-6 py-4 text-slate-600">{{ recipient.contactName || '—' }}</td>
              <td class="px-6 py-4 text-slate-600">{{ recipient.organization || '—' }}</td>
              <td class="px-6 py-4 text-slate-600">{{ recipient.status }}</td>
              <td class="px-6 py-4 text-slate-500">{{ recipient.scheduledFor || '—' }}</td>
            </tr>
          </tbody>
        </table>
      </section>
    </div>
  </CrmLayout>
</template>

<script setup>
import { ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import axios from 'axios';
import CrmLayout from '@/Layouts/CrmLayout.vue';
import MailComposer from '@/Components/Composer/MailComposer.vue';

const props = defineProps({
  campaign: { type: Object, required: true },
  templates: { type: Array, default: () => [] },
});

const editing = ref(props.campaign.status === 'draft' && Boolean(props.campaign.draft?.id));
const banner = ref(null);

function onDraftSaved() {
  editing.value = false;
  router.reload({ preserveState: false });
}

function onDraftScheduled() {
  editing.value = false;
  router.reload({ preserveState: false });
}

async function removeCampaign() {
  if (!window.confirm('Supprimer cette campagne et son brouillon technique associé ?')) return;

  try {
    await axios.delete(`/api/campaigns/${props.campaign.id}`);
    router.visit('/campaigns');
  } catch (error) {
    banner.value = { type: 'error', message: error.response?.data?.message ?? 'Impossible de supprimer la campagne.' };
  }
}
</script>
