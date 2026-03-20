<template>
  <div class="space-y-6">
    <SectionCard title="Points de scoring" subtitle="Pondération des signaux d'engagement (sans IA)">
      <div class="grid grid-cols-2 gap-4">
        <FieldGroup label="Ouverture" v-model="form.open_points" type="number" suffix="pts" />
        <FieldGroup label="Clic" v-model="form.click_points" type="number" suffix="pts" />
        <FieldGroup label="Réponse" v-model="form.reply_points" type="number" suffix="pts" />
        <FieldGroup label="Réponse automatique" v-model="form.auto_reply_points" type="number" suffix="pts" />
        <FieldGroup label="Rebond temporaire" v-model="form.soft_bounce_points" type="number" suffix="pts" />
        <FieldGroup label="Rebond permanent" v-model="form.hard_bounce_points" type="number" suffix="pts" />
        <FieldGroup label="Désinscription" v-model="form.unsubscribe_points" type="number" suffix="pts" />
      </div>
    </SectionCard>

    <SectionCard title="Niveaux de chaleur" subtitle="Lecture métier du score d'engagement">
      <div class="space-y-2">
        <ScoreLevel label="Froid" range="0 – 2" color="bg-blue-100 text-blue-700" />
        <ScoreLevel label="Tiède" range="3 – 5" color="bg-yellow-100 text-yellow-800" />
        <ScoreLevel label="Intéressé" range="6 – 10" color="bg-orange-100 text-orange-700" />
        <ScoreLevel label="Engagé" range="11+" color="bg-green-100 text-green-700" />
        <ScoreLevel label="À exclure" range="< 0" color="bg-red-100 text-red-700" />
      </div>
    </SectionCard>

    <SectionCard title="Décroissance" subtitle="Pénalité d'inactivité">
      <div class="grid grid-cols-2 gap-4">
        <FieldGroup label="Décroissance après inactivité" v-model="form.inactivity_decay_days" type="number" suffix="jours" />
      </div>
    </SectionCard>

    <div class="flex items-center gap-3">
      <button
        :disabled="saving"
        class="rounded-xl bg-slate-900 px-6 py-2.5 text-sm font-bold text-white shadow-sm hover:bg-slate-800 transition disabled:opacity-50"
        @click="save"
      >
        {{ saving ? 'Enregistrement…' : 'Enregistrer le scoring' }}
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
import { router } from '@inertiajs/vue3';
import SectionCard from './SectionCard.vue';
import FieldGroup from './FieldGroup.vue';
import ScoreLevel from './ScoreLevel.vue';

const props = defineProps({
  settings: { type: Object, default: () => ({}) },
  generalSettings: { type: Object, default: () => ({}) },
});

const form = reactive({
  open_points: props.generalSettings.open_points ?? props.settings.openPoints ?? 1,
  click_points: props.generalSettings.click_points ?? props.settings.clickPoints ?? 2,
  reply_points: props.generalSettings.reply_points ?? props.settings.replyPoints ?? 8,
  auto_reply_points: props.generalSettings.auto_reply_points ?? 0,
  soft_bounce_points: props.generalSettings.soft_bounce_points ?? -5,
  hard_bounce_points: props.generalSettings.hard_bounce_points ?? props.settings.bouncePoints ?? -15,
  unsubscribe_points: props.generalSettings.unsubscribe_points ?? props.settings.unsubscribePoints ?? -20,
  inactivity_decay_days: props.generalSettings.inactivity_decay_days ?? props.settings.inactivityDecayDays ?? 30,
});

const saving = ref(false);
const feedback = ref(null);

async function save() {
  saving.value = true;
  feedback.value = null;
  try {
    const payload = { ...props.generalSettings, ...form };
    await axios.put('/api/settings/general', payload);
    feedback.value = { ok: true, text: 'Scoring enregistré.' };
    router.reload({ preserveState: true });
  } catch (e) {
    const msg = e.response?.data?.message ?? 'Erreur lors de l\'enregistrement.';
    feedback.value = { ok: false, text: msg };
  } finally {
    saving.value = false;
  }
}
</script>
