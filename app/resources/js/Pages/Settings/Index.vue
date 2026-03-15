<template>
  <CrmLayout title="Réglages" current-page="settings">
    <div class="flex gap-6">
      <!-- Section nav -->
      <nav class="w-48 shrink-0">
        <ul class="space-y-0.5">
          <li v-for="section in sections" :key="section.id">
            <button
              :class="[
                'w-full rounded-md px-3 py-2 text-left text-sm font-medium transition-colors',
                activeSection === section.id
                  ? 'bg-gray-100 text-gray-900'
                  : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900',
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
        />

        <!-- SCORING -->
        <SettingsScoring
          v-if="activeSection === 'scoring'"
          :settings="settings.scoring"
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
