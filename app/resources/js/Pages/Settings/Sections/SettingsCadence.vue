<template>
  <div class="space-y-6">
    <SectionCard title="Plafonds d'envoi" subtitle="Limites de volume pour protéger la réputation du domaine">
      <div class="grid grid-cols-2 gap-4">
        <FieldGroup label="Plafond journalier" v-model="form.daily_limit_default" type="number" suffix="mails/jour" />
        <FieldGroup label="Plafond horaire" v-model="form.hourly_limit_default" type="number" suffix="mails/heure" />
      </div>
    </SectionCard>

    <SectionCard title="Délais entre messages" subtitle="Cadence d'envoi pour un envoi progressif naturel">
      <div class="grid grid-cols-2 gap-4">
        <FieldGroup label="Délai minimum" v-model="form.min_delay_seconds" type="number" suffix="secondes" />
        <FieldGroup label="Jitter minimum" v-model="form.jitter_min_seconds" type="number" suffix="secondes" />
        <FieldGroup label="Jitter maximum" v-model="form.jitter_max_seconds" type="number" suffix="secondes" />
      </div>
      <p class="mt-2 text-xs text-slate-400">
        Le jitter ajoute un délai aléatoire entre les messages pour simuler un envoi naturel.
      </p>
    </SectionCard>

    <SectionCard title="Mode ralenti" subtitle="Réduction automatique de la cadence">
      <ToggleRow label="Mode ralenti activé" v-model="form.slow_mode_enabled" />
    </SectionCard>

    <SectionCard title="Arrêt automatique" subtitle="Seuils de sécurité pour stopper les envois">
      <div class="grid grid-cols-2 gap-4">
        <FieldGroup label="Seuil de rebond permanent" v-model="form.stop_on_hard_bounce_threshold" type="number" suffix="%" />
        <FieldGroup label="Max erreurs consécutives" v-model="form.stop_on_consecutive_failures" type="number" />
      </div>
    </SectionCard>

    <div class="flex items-center gap-3">
      <button
        :disabled="saving"
        class="rounded-xl bg-slate-900 px-6 py-2.5 text-sm font-bold text-white shadow-sm hover:bg-slate-800 transition disabled:opacity-50"
        @click="save"
      >
        {{ saving ? 'Enregistrement…' : 'Enregistrer la cadence' }}
      </button>
      <span v-if="feedback" :class="feedback.ok ? 'text-emerald-600' : 'text-red-600'" class="text-sm font-medium">
        {{ feedback.text }}
      </span>
    </div>
  </div>
</template>

<script setup>
import { reactive, ref } from 'vue';
import axios from 'axios';
import SectionCard from './SectionCard.vue';
import FieldGroup from './FieldGroup.vue';
import ToggleRow from './ToggleRow.vue';

const props = defineProps({
  settings: { type: Object, default: () => ({}) },
  generalSettings: { type: Object, default: () => ({}) },
});

const form = reactive({
  daily_limit_default: props.generalSettings.daily_limit_default ?? props.settings.dailyLimit ?? 100,
  hourly_limit_default: props.generalSettings.hourly_limit_default ?? props.settings.hourlyLimit ?? 12,
  min_delay_seconds: props.generalSettings.min_delay_seconds ?? props.settings.minDelay ?? 60,
  jitter_min_seconds: props.generalSettings.jitter_min_seconds ?? 5,
  jitter_max_seconds: props.generalSettings.jitter_max_seconds ?? props.settings.maxJitter ?? 20,
  slow_mode_enabled: props.generalSettings.slow_mode_enabled ?? props.settings.slowModeEnabled ?? false,
  stop_on_consecutive_failures: props.generalSettings.stop_on_consecutive_failures ?? props.settings.maxConsecutiveErrors ?? 5,
  stop_on_hard_bounce_threshold: props.generalSettings.stop_on_hard_bounce_threshold ?? props.settings.bounceStopThreshold ?? 3,
});

const saving = ref(false);
const feedback = ref(null);

async function save() {
  saving.value = true;
  feedback.value = null;
  try {
    const payload = { ...props.generalSettings, ...form };
    await axios.put('/api/settings/general', payload);
    feedback.value = { ok: true, text: 'Cadence enregistrée.' };
  } catch (e) {
    const msg = e.response?.data?.message ?? 'Erreur lors de l\'enregistrement.';
    feedback.value = { ok: false, text: msg };
  } finally {
    saving.value = false;
  }
}
</script>
