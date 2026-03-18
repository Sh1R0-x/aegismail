<template>
  <CrmLayout
    title="Nouvelle campagne"
    subtitle="Préparez votre campagne — sauvegarde automatique activée"
    current-page="campaigns"
  >
    <template #header-actions>
      <Link
        href="/campaigns"
        class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-bold text-slate-600 hover:bg-slate-50 shadow-sm transition-all"
      >
        ← Retour aux campagnes
      </Link>
    </template>

    <CampaignEditor
      :templates="templates"
      :initial-audiences="audiences"
      @autosaved="onAutosaved"
      @scheduled="onScheduled"
    />
  </CrmLayout>
</template>

<script setup>
import { router } from '@inertiajs/vue3';
import { Link } from '@inertiajs/vue3';
import CrmLayout from '@/Layouts/CrmLayout.vue';
import CampaignEditor from '@/Components/Campaigns/CampaignEditor.vue';

defineProps({
  templates: { type: Array, default: () => [] },
  audiences: { type: Object, default: null },
  autosave: { type: Object, default: null },
});

function onAutosaved(data) {
  const campaignId = data?.campaign?.id;
  if (campaignId) {
    // After first autosave, silently replace URL with campaign detail URL
    // so refresh keeps user on the right page
    window.history.replaceState({}, '', `/campaigns/${campaignId}`);
  }
}

function onScheduled() {
  router.visit('/campaigns');
}
</script>
