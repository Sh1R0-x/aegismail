<template>
  <div class="space-y-6">
    <SectionCard title="Authentification domaine" subtitle="Vérifications DNS requises pour la délivrabilité">
      <div class="space-y-3">
        <DnsCheck label="SPF" :status="settings.spfValid ? 'valid' : 'missing'" />
        <DnsCheck label="DKIM" :status="settings.dkimValid ? 'valid' : 'missing'" />
        <DnsCheck label="DMARC" :status="settings.dmarcValid ? 'valid' : 'missing'" />
      </div>
    </SectionCard>

    <SectionCard title="Tracking" subtitle="Suivi des ouvertures et clics">
      <div class="space-y-3">
        <ToggleRow label="Tracking des ouvertures" :enabled="settings.trackOpens !== false" />
        <ToggleRow label="Tracking des clics" :enabled="settings.trackClicks !== false" />
      </div>
    </SectionCard>

    <SectionCard title="Seuils d'alerte" subtitle="Règles d'arrêt automatique en cas d'anomalie">
      <div class="grid grid-cols-2 gap-4">
        <FieldGroup label="Seuil bounce warning (%)" :value="settings.bounceWarningThreshold || '5'" />
        <FieldGroup label="Seuil bounce critique (%)" :value="settings.bounceCriticalThreshold || '10'" />
        <FieldGroup label="Max hard bounces consécutifs" :value="settings.maxConsecutiveHardBounces || '3'" />
      </div>
    </SectionCard>

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
import SectionCard from './SectionCard.vue';
import FieldGroup from './FieldGroup.vue';
import DnsCheck from './DnsCheck.vue';
import ToggleRow from './ToggleRow.vue';

defineProps({
  settings: { type: Object, default: () => ({}) },
});
</script>
