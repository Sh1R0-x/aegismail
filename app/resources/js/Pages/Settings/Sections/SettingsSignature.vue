<template>
  <div class="space-y-6">
    <SectionCard title="Signature globale" subtitle="Appliquée automatiquement à tous les e-mails sortants">
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Contenu de la signature (HTML)</label>
        <textarea
          :value="settings.html || ''"
          rows="6"
          class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm font-mono placeholder:text-gray-400"
          placeholder="<p>Cordialement,<br>AEGIS Network</p>"
          readonly
        />
      </div>
      <div class="mt-4">
        <label class="block text-sm font-medium text-gray-700 mb-1">Aperçu</label>
        <div
          class="rounded-md border border-gray-200 bg-gray-50 px-4 py-3 text-sm"
          v-html="settings.html || '<em class=\'text-gray-400\'>Aucune signature définie</em>'"
        />
      </div>
    </SectionCard>
  </div>
</template>

<script setup>
import SectionCard from './SectionCard.vue';

defineProps({
  settings: { type: Object, default: () => ({}) },
});
</script>
