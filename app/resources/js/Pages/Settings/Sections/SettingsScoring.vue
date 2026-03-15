<template>
  <div class="space-y-6">
    <SectionCard title="Points de scoring" subtitle="Pondération des signaux d'engagement (sans IA)">
      <div class="grid grid-cols-2 gap-4">
        <FieldGroup label="Ouverture" :value="settings.openPoints || '1'" suffix="pts" />
        <FieldGroup label="Clic" :value="settings.clickPoints || '2'" suffix="pts" />
        <FieldGroup label="Réponse" :value="settings.replyPoints || '5'" suffix="pts" />
        <FieldGroup label="Bounce" :value="settings.bouncePoints || '-10'" suffix="pts" />
        <FieldGroup label="Désinscription" :value="settings.unsubscribePoints || '-20'" suffix="pts" />
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
        <FieldGroup label="Décroissance après inactivité" :value="settings.inactivityDecayDays || '30'" suffix="jours" />
        <FieldGroup label="Points retirés" :value="settings.inactivityDecayPoints || '-1'" suffix="pts" />
      </div>
    </SectionCard>
  </div>
</template>

<script setup>
import SectionCard from './SectionCard.vue';
import FieldGroup from './FieldGroup.vue';
import ScoreLevel from './ScoreLevel.vue';

defineProps({
  settings: { type: Object, default: () => ({}) },
});
</script>
