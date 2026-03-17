<template>
  <CrmLayout title="Nouvelle campagne" subtitle="Préparez une campagne sans passer visiblement par le module Brouillons" current-page="campaigns">
    <template #header-actions>
      <Link href="/campaigns" class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-bold text-slate-600 hover:bg-slate-50 shadow-sm transition-all">
        ← Retour aux campagnes
      </Link>
    </template>

    <div class="space-y-4">
      <div v-if="banner" class="rounded-xl border px-4 py-3 text-sm font-medium" :class="banner.type === 'error' ? 'border-red-200 bg-red-50 text-red-800' : 'border-emerald-200 bg-emerald-50 text-emerald-800'">
        {{ banner.message }}
      </div>

      <MailComposer
        mode="multiple"
        :templates="templates"
        @close="router.visit('/campaigns')"
        @saved="materializeCampaign"
        @scheduled="materializeScheduledCampaign"
      />
    </div>
  </CrmLayout>
</template>

<script setup>
import { ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import axios from 'axios';
import CrmLayout from '@/Layouts/CrmLayout.vue';
import MailComposer from '@/Components/Composer/MailComposer.vue';

defineProps({
  templates: { type: Array, default: () => [] },
});

const banner = ref(null);
const redirecting = ref(false);

async function materializeCampaign(draft) {
  if (!draft?.id || redirecting.value) return;

  redirecting.value = true;
  banner.value = null;

  try {
    const { data } = await axios.post(`/api/drafts/${draft.id}/campaign`, {
      name: draft.subject || 'Nouvelle campagne',
    });
    router.visit(`/campaigns/${data.campaign.id}`);
  } catch (error) {
    redirecting.value = false;
    banner.value = { type: 'error', message: error.response?.data?.message ?? 'Impossible de créer la campagne.' };
  }
}

async function materializeScheduledCampaign(draft) {
  if (!draft?.id) {
    router.visit('/campaigns');
    return;
  }

  try {
    const { data } = await axios.get('/api/campaigns');
    const linked = data.campaigns.find((campaign) => campaign.draftId === draft.id);
    if (linked) {
      router.visit(`/campaigns/${linked.id}`);
      return;
    }
  } catch {
    // fallback below
  }

  router.visit('/campaigns');
}
</script>
