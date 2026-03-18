<template>
  <CrmLayout title="Réglages" subtitle="Configuration système et paramètres opérationnels" current-page="settings">
    <div class="flex gap-8">
      <!-- Section nav -->
      <nav class="w-52 shrink-0">
        <ul class="space-y-1">
          <li v-for="section in sections" :key="section.id">
            <button
              :class="[
                'w-full rounded-xl px-4 py-3 text-left text-sm font-bold transition-colors',
                activeSection === section.id
                  ? 'bg-slate-900 text-white shadow-sm'
                  : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900',
              ]"
              @click="activeSection = section.id"
            >
              {{ section.label }}
            </button>
          </li>
        </ul>
      </nav>

      <!-- Content -->
      <div class="flex-1 min-w-0">
        <!-- MAIL -->
        <SettingsMail
          v-if="activeSection === 'mail'"
          :settings="settings.mail"
        />

        <!-- DÉLIVRABILITÉ -->
        <SettingsDeliverability
          v-if="activeSection === 'deliverability'"
          :settings="settings.deliverability"
        />

        <!-- CADENCE -->
        <SettingsCadence
          v-if="activeSection === 'cadence'"
          :settings="settings.cadence"
          :general-settings="settings.general"
        />

        <!-- SCORING -->
        <SettingsScoring
          v-if="activeSection === 'scoring'"
          :settings="settings.scoring"
          :general-settings="settings.general"
        />

        <!-- SIGNATURE -->
        <SettingsSignature
          v-if="activeSection === 'signature'"
          :settings="settings.signature"
        />
      </div>
    </div>
  </CrmLayout>
</template>

<script setup>
import { ref } from 'vue';
import CrmLayout from '@/Layouts/CrmLayout.vue';
import SettingsMail from './Sections/SettingsMail.vue';
import SettingsDeliverability from './Sections/SettingsDeliverability.vue';
import SettingsCadence from './Sections/SettingsCadence.vue';
import SettingsScoring from './Sections/SettingsScoring.vue';
import SettingsSignature from './Sections/SettingsSignature.vue';

defineProps({
  settings: {
    type: Object,
    default: () => ({
      mail: {},
      deliverability: {},
      cadence: {},
      scoring: {},
      signature: {},
    }),
  },
});

const sections = [
  { id: 'mail', label: 'Paramètres mail' },
  { id: 'deliverability', label: 'Délivrabilité' },
  { id: 'cadence', label: 'Cadence d\'envoi' },
  { id: 'scoring', label: 'Scoring' },
  { id: 'signature', label: 'Signature' },
];

const activeSection = ref('mail');
</script>
