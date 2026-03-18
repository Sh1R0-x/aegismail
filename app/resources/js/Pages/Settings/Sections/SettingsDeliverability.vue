<template>
  <div class="space-y-6">

    <!-- DNS Authentication with retest -->
    <SectionCard title="Authentification domaine" subtitle="Vérifications DNS requises pour la délivrabilité">
      <div class="space-y-4">
        <!-- Domain info + retest -->
        <div class="flex items-center justify-between gap-3">
          <div>
            <p class="text-xs font-bold text-slate-700">
              Domaine contrôlé :
              <span class="ml-1 font-mono font-bold text-slate-900">
                {{ localDeliverability.domain || '—' }}
              </span>
            </p>
            <p class="mt-0.5 text-[11px] font-medium text-slate-400">
              Configuré depuis les paramètres mail (sender_email ou domain_override).
            </p>
          </div>
          <button
            :disabled="retesting"
            class="shrink-0 rounded-xl border border-slate-200 bg-white px-4 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50 shadow-sm transition-all disabled:opacity-50"
            @click="retestAll"
          >
            {{ retesting ? 'Test en cours…' : 'Retester / Actualiser' }}
          </button>
        </div>

        <p v-if="retestError" class="text-xs font-medium text-red-600">{{ retestError }}</p>

        <!-- SPF / DKIM / DMARC -->
        <div class="space-y-3">
          <DeliverabilityCheckRow
            v-for="mech in mechanisms"
            :key="mech.key"
            :label="mech.label"
            :check="localDeliverability.checks?.[mech.key]"
          />
        </div>
      </div>
    </SectionCard>

    <!-- Tracking -->
    <SectionCard title="Tracking" subtitle="Suivi des ouvertures et clics">
      <div class="space-y-3">
        <ToggleRow label="Tracking des ouvertures" :enabled="settings.trackOpens !== false" />
        <ToggleRow label="Tracking des clics" :enabled="settings.trackClicks !== false" />
      </div>
    </SectionCard>

    <!-- Alert thresholds -->
    <SectionCard title="Seuils d'alerte" subtitle="Règles d'arrêt automatique en cas d'anomalie">
      <div class="grid grid-cols-2 gap-4">
        <FieldGroup label="Seuil bounce warning (%)" :value="settings.bounceWarningThreshold || '5'" />
        <FieldGroup label="Seuil bounce critique (%)" :value="settings.bounceCriticalThreshold || '10'" />
        <FieldGroup label="Max hard bounces consécutifs" :value="settings.maxConsecutiveHardBounces || '3'" />
      </div>
    </SectionCard>

    <!-- HTML alerts -->
    <SectionCard title="Alertes HTML" subtitle="Seuils pour les vérifications pré-envoi">
      <div class="grid grid-cols-2 gap-4">
        <FieldGroup label="Max liens dans un mail" :value="settings.maxLinks || '10'" />
        <FieldGroup label="Max images distantes" :value="settings.maxImages || '5'" />
        <FieldGroup label="Taille max HTML (Ko)" :value="settings.maxHtmlSizeKb || '100'" />
        <FieldGroup label="Taille max pièces jointes (Mo)" :value="settings.maxAttachmentSizeMb || '10'" />
      </div>
    </SectionCard>
  </div>
</template>

<script setup>
import { ref } from 'vue';
import axios from 'axios';
import SectionCard from './SectionCard.vue';
import FieldGroup from './FieldGroup.vue';
import ToggleRow from './ToggleRow.vue';
import DeliverabilityCheckRow from './DeliverabilityCheckRow.vue';

const props = defineProps({
  settings: { type: Object, default: () => ({}) },
});

const mechanisms = [
  { key: 'spf', label: 'SPF' },
  { key: 'dkim', label: 'DKIM' },
  { key: 'dmarc', label: 'DMARC' },
];

// Local copy of deliverability, updated after retest
const localDeliverability = ref({ ...props.settings });

const retesting = ref(false);
const retestError = ref(null);

async function retestAll() {
  retesting.value = true;
  retestError.value = null;
  try {
    const endpoint = props.settings.refreshEndpoint || '/api/settings/deliverability/checks/refresh';
    const { data } = await axios.post(endpoint, { mechanisms: ['spf', 'dkim', 'dmarc'] });
    localDeliverability.value = data.deliverability;
  } catch (e) {
    retestError.value =
      e.response?.data?.message ?? 'Erreur lors de l’actualisation des vérifications DNS.';
  } finally {
    retesting.value = false;
  }
}
</script>
