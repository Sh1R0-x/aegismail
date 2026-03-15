<template>
  <div class="bg-white">
    <!-- Overall status banner -->
    <div
      :class="[
        'flex items-center justify-between px-4 py-3',
        result.ok ? 'bg-green-50' : 'bg-red-50',
      ]"
    >
      <div class="flex items-center gap-2">
        <!-- icon: ok -->
        <svg
          v-if="result.ok"
          class="h-4 w-4 text-green-600 shrink-0"
          fill="none" stroke="currentColor" viewBox="0 0 24 24"
        >
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
        </svg>
        <!-- icon: blocked -->
        <svg
          v-else
          class="h-4 w-4 text-red-600 shrink-0"
          fill="none" stroke="currentColor" viewBox="0 0 24 24"
        >
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" />
        </svg>
        <span :class="['text-sm font-medium', result.ok ? 'text-green-800' : 'text-red-800']">
          {{ result.ok ? 'Prêt à planifier' : 'Envoi bloqué' }}
        </span>
        <span v-if="result.errors.length > 0" class="text-xs text-red-600">
          {{ result.errors.length }} erreur(s) bloquante(s)
        </span>
        <span v-else-if="result.warnings.length > 0" class="text-xs text-amber-600">
          {{ result.warnings.length }} avertissement(s)
        </span>
      </div>
      <span class="text-xs text-gray-500">
        {{ result.recipientSummary.deliverable }}/{{ result.recipientSummary.total }} destinataire(s) exploitable(s)
      </span>
    </div>

    <!-- Errors (blocking) -->
    <div v-if="result.errors.length > 0" class="border-t border-red-100 bg-red-50/60 px-4 py-3">
      <p class="mb-1 text-xs font-semibold uppercase tracking-wide text-red-700">Erreurs bloquantes</p>
      <ul class="space-y-1">
        <li
          v-for="err in result.errors"
          :key="err.code"
          class="flex items-start gap-1.5 text-sm text-red-700"
        >
          <span class="mt-0.5 shrink-0 font-bold">✕</span>
          {{ err.message }}
        </li>
      </ul>
    </div>

    <!-- Warnings -->
    <div v-if="result.warnings.length > 0" class="border-t border-amber-100 bg-amber-50/60 px-4 py-3">
      <p class="mb-1 text-xs font-semibold uppercase tracking-wide text-amber-700">Avertissements</p>
      <ul class="space-y-1">
        <li
          v-for="warn in result.warnings"
          :key="warn.code"
          class="flex items-start gap-1.5 text-sm text-amber-700"
        >
          <span class="mt-0.5 shrink-0 font-bold">!</span>
          {{ warn.message }}
        </li>
      </ul>
    </div>

    <!-- Stats row -->
    <div class="grid grid-cols-2 gap-6 border-t border-gray-100 px-4 py-3">
      <!-- Recipient summary -->
      <div>
        <p class="mb-1.5 text-xs font-semibold uppercase tracking-wide text-gray-500">Destinataires</p>
        <dl class="space-y-0.5 text-xs">
          <div class="flex justify-between">
            <dt class="text-gray-600">Total</dt>
            <dd class="font-medium text-gray-900">{{ result.recipientSummary.total }}</dd>
          </div>
          <div class="flex justify-between">
            <dt class="text-green-700">Exploitables</dt>
            <dd class="font-medium text-green-800">{{ result.recipientSummary.deliverable }}</dd>
          </div>
          <div v-if="result.recipientSummary.excluded > 0" class="flex justify-between">
            <dt class="text-red-600">Exclus (hard bounce)</dt>
            <dd class="font-medium text-red-700">{{ result.recipientSummary.excluded }}</dd>
          </div>
          <div v-if="result.recipientSummary.optOut > 0" class="flex justify-between">
            <dt class="text-amber-600">Désinscrits</dt>
            <dd class="font-medium text-amber-700">{{ result.recipientSummary.optOut }}</dd>
          </div>
          <div v-if="result.recipientSummary.invalid > 0" class="flex justify-between">
            <dt class="text-red-600">Adresses invalides</dt>
            <dd class="font-medium text-red-700">{{ result.recipientSummary.invalid }}</dd>
          </div>
        </dl>
      </div>

      <!-- Technical quality -->
      <div>
        <p class="mb-1.5 text-xs font-semibold uppercase tracking-wide text-gray-500">Qualité technique</p>
        <dl class="space-y-0.5 text-xs">
          <div class="flex justify-between">
            <dt class="text-gray-600">Poids estimé</dt>
            <dd class="font-medium text-gray-900">{{ formatBytes(result.estimatedWeightBytes) }}</dd>
          </div>
          <div class="flex justify-between">
            <dt :class="result.hasTextVersion ? 'text-green-700' : 'text-amber-600'">
              Version texte
            </dt>
            <dd :class="['font-medium', result.hasTextVersion ? 'text-green-800' : 'text-amber-700']">
              {{ result.hasTextVersion ? 'Présente' : 'Absente' }}
            </dd>
          </div>
          <div class="flex justify-between">
            <dt class="text-gray-600">Liens</dt>
            <dd class="font-medium text-gray-900">{{ result.deliverability.linkCount }}</dd>
          </div>
          <div class="flex justify-between">
            <dt :class="result.deliverability.remoteImageCount > 0 ? 'text-amber-600' : 'text-gray-600'">
              Images distantes
            </dt>
            <dd :class="['font-medium', result.deliverability.remoteImageCount > 0 ? 'text-amber-700' : 'text-gray-900']">
              {{ result.deliverability.remoteImageCount }}
            </dd>
          </div>
          <div v-if="result.deliverability.attachmentCount > 0" class="flex justify-between">
            <dt class="text-gray-600">Pièces jointes</dt>
            <dd class="font-medium text-gray-900">{{ result.deliverability.attachmentCount }}</dd>
          </div>
          <div class="flex justify-between">
            <dt :class="result.mailboxValid ? 'text-green-700' : 'text-red-600'">
              Boîte OVH MX
            </dt>
            <dd :class="['font-medium', result.mailboxValid ? 'text-green-800' : 'text-red-700']">
              {{ result.mailboxValid ? 'Prête' : 'Non prête' }}
            </dd>
          </div>
        </dl>
      </div>
    </div>
  </div>
</template>

<script setup>
defineProps({
  result: { type: Object, required: true },
});

function formatBytes(bytes) {
  if (!bytes) return '0 o';
  if (bytes < 1024) return bytes + ' o';
  if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' Ko';
  return (bytes / 1024 / 1024).toFixed(2) + ' Mo';
}
</script>
