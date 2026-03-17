<template>
  <div class="space-y-6">
    <SectionCard title="Plafonds d'envoi" subtitle="Limites de volume pour protéger la réputation du domaine">
      <div class="grid grid-cols-2 gap-4">
        <FieldGroup label="Plafond journalier" :value="settings.dailyLimit || '100'" suffix="mails/jour" />
        <FieldGroup label="Plafond horaire" :value="settings.hourlyLimit || '15'" suffix="mails/heure" />
      </div>
    </SectionCard>

    <SectionCard title="Délais entre messages" subtitle="Cadence d'envoi pour un envoi progressif naturel">
      <div class="grid grid-cols-2 gap-4">
        <FieldGroup label="Délai minimum" :value="settings.minDelay || '30'" suffix="secondes" />
        <FieldGroup label="Jitter maximum" :value="settings.maxJitter || '15'" suffix="secondes" />
      </div>
      <p class="mt-2 text-xs text-slate-400">
        Le jitter ajoute un délai aléatoire entre les messages pour simuler un envoi naturel.
      </p>
    </SectionCard>

    <SectionCard title="Mode ralenti" subtitle="Réduction automatique de la cadence">
      <div class="space-y-3">
        <ToggleRow label="Mode ralenti activé" :enabled="settings.slowModeEnabled === true" />
        <div v-if="settings.slowModeEnabled" class="grid grid-cols-2 gap-4 pt-2">
          <FieldGroup label="Facteur de ralentissement" :value="settings.slowModeFactor || '2'" suffix="×" />
        </div>
      </div>
    </SectionCard>

    <SectionCard title="Arrêt automatique" subtitle="Seuils de sécurité pour stopper les envois">
      <div class="grid grid-cols-2 gap-4">
        <FieldGroup label="Seuil hard bounce (%)" :value="settings.bounceStopThreshold || '10'" suffix="%" />
        <FieldGroup label="Max erreurs consécutives" :value="settings.maxConsecutiveErrors || '5'" />
      </div>
    </SectionCard>
  </div>
</template>

<script setup>
import SectionCard from './SectionCard.vue';
import FieldGroup from './FieldGroup.vue';
import ToggleRow from './ToggleRow.vue';

defineProps({
  settings: { type: Object, default: () => ({}) },
});
</script>
