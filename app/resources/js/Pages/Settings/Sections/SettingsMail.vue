<template>
  <div class="space-y-6">
    <SectionCard title="Connexion SMTP" subtitle="Configuration d'envoi OVH MX Plan">
      <div class="grid grid-cols-2 gap-4">
        <FieldGroup label="Hôte SMTP" :value="settings.smtpHost" placeholder="ssl0.ovh.net" />
        <FieldGroup label="Port SMTP" :value="settings.smtpPort" placeholder="465" />
        <FieldGroup label="Identifiant" :value="settings.smtpUsername" placeholder="contact@domaine.fr" />
        <FieldGroup label="Mot de passe" value="••••••••" type="password" />
        <FieldGroup label="Chiffrement" :value="settings.smtpEncryption || 'SSL'" />
      </div>
      <template #footer>
        <button class="rounded-md border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50">
          Tester la connexion SMTP
        </button>
      </template>
    </SectionCard>

    <SectionCard title="Connexion IMAP" subtitle="Synchronisation et lecture des réponses">
      <div class="grid grid-cols-2 gap-4">
        <FieldGroup label="Hôte IMAP" :value="settings.imapHost" placeholder="ssl0.ovh.net" />
        <FieldGroup label="Port IMAP" :value="settings.imapPort" placeholder="993" />
        <FieldGroup label="Identifiant" :value="settings.imapUsername" placeholder="contact@domaine.fr" />
        <FieldGroup label="Mot de passe" value="••••••••" type="password" />
      </div>
      <template #footer>
        <button class="rounded-md border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50">
          Tester la connexion IMAP
        </button>
      </template>
    </SectionCard>

    <SectionCard title="Expéditeur" subtitle="Identité des messages sortants">
      <div class="grid grid-cols-2 gap-4">
        <FieldGroup label="Adresse d'envoi" :value="settings.senderEmail" placeholder="contact@domaine.fr" />
        <FieldGroup label="Nom d'expéditeur" :value="settings.senderName" placeholder="AEGIS Network" />
      </div>
    </SectionCard>

    <SectionCard title="Fenêtre d'envoi" subtitle="Heures autorisées pour l'envoi">
      <div class="grid grid-cols-2 gap-4">
        <FieldGroup label="Début" :value="settings.sendWindowStart || '08:00'" />
        <FieldGroup label="Fin" :value="settings.sendWindowEnd || '19:00'" />
        <FieldGroup label="Fuseau horaire" :value="settings.timezone || 'Europe/Paris'" />
      </div>
    </SectionCard>
  </div>
</template>

<script setup>
import SectionCard from './SectionCard.vue';
import FieldGroup from './FieldGroup.vue';

defineProps({
  settings: { type: Object, default: () => ({}) },
});
</script>
