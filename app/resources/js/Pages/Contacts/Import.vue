<template>
  <CrmLayout
    title="Importer des contacts"
    subtitle="Chargez un fichier CSV ou XLSX pour importer vos contacts"
    current-page="contacts"
  >
    <template #header-actions>
      <Link
        href="/contacts"
        class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-bold text-slate-600 hover:bg-slate-50 shadow-sm transition-all"
      >
        ← Retour aux contacts
      </Link>
    </template>

    <div class="space-y-6 max-w-3xl mx-auto">

      <!-- ── Step 0: Upload ─────────────────────────────────── -->
      <template v-if="step === 'upload'">
        <!-- Règle importante -->
        <div class="rounded-xl border border-amber-200 bg-amber-50 px-5 py-4 text-xs font-medium text-amber-800 space-y-1">
          <p class="font-bold">Règle obligatoire</p>
          <p>
            Chaque contact doit être rattaché à une organisation. Les colonnes
            <code class="rounded bg-amber-100 px-1 font-mono">organization_name</code> et
            <code class="rounded bg-amber-100 px-1 font-mono">primary_email</code>
            sont obligatoires. Les lignes sans organisation seront rejetées.
          </p>
        </div>

        <!-- Template download -->
        <div class="rounded-2xl border border-slate-200 bg-white px-6 py-5 shadow-sm flex items-center justify-between gap-4">
          <div>
            <p class="text-sm font-bold text-slate-900">Template CSV</p>
            <p class="mt-0.5 text-xs font-medium text-slate-400">
              Téléchargez le fichier modèle, remplissez-le puis importez-le ici.
            </p>
          </div>
          <a
            href="/api/contacts/imports/template"
            download
            class="shrink-0 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-bold text-slate-700 hover:bg-slate-50 shadow-sm transition-all"
          >
            ↓ Télécharger le template
          </a>
        </div>

        <!-- Upload zone -->
        <div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
          <div class="border-b border-slate-100 bg-slate-50 px-6 py-4">
            <h2 class="text-sm font-bold text-slate-900">Charger un fichier</h2>
          </div>
          <div class="px-6 py-8">
            <div
              :class="[
                'flex flex-col items-center justify-center rounded-xl border-2 border-dashed px-8 py-12 text-center transition-colors cursor-pointer',
                isDragging ? 'border-blue-400 bg-blue-50' : 'border-slate-300 bg-slate-50 hover:border-slate-400',
              ]"
              @dragover.prevent="isDragging = true"
              @dragleave="isDragging = false"
              @drop.prevent="onFileDrop"
              @click="filePicker?.click()"
            >
              <div class="mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-slate-200 text-slate-500 text-xl">
                ↑
              </div>
              <p class="text-sm font-bold text-slate-700">Glissez un fichier ici ou cliquez</p>
              <p class="mt-1 text-xs font-medium text-slate-400">CSV ou XLSX acceptés</p>
              <input
                ref="filePicker"
                type="file"
                accept=".csv,.xlsx"
                class="hidden"
                @change="onFileSelect"
              />
            </div>

            <p v-if="uploadError" class="mt-4 text-xs font-medium text-red-600">
              {{ uploadError }}
            </p>

            <div v-if="uploading" class="mt-4 flex items-center justify-center gap-2 text-xs font-medium text-slate-500">
              <span class="h-2 w-2 rounded-full bg-blue-400 animate-pulse" />
              Analyse du fichier en cours…
            </div>
          </div>
        </div>
      </template>

      <!-- ── Step 1: Preview ────────────────────────────────── -->
      <template v-else-if="step === 'preview'">
        <!-- Summary -->
        <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
          <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <p class="text-[10px] font-black uppercase tracking-[0.1em] text-slate-400">Lignes valides</p>
            <p class="mt-2 text-2xl font-black text-emerald-600">{{ preview.summary.validRows }}</p>
          </div>
          <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <p class="text-[10px] font-black uppercase tracking-[0.1em] text-slate-400">Doublons</p>
            <p class="mt-2 text-2xl font-black text-amber-600">
              {{ (preview.summary.duplicateExistingRows || 0) + (preview.summary.duplicateFileRows || 0) }}
            </p>
          </div>
          <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <p class="text-[10px] font-black uppercase tracking-[0.1em] text-slate-400">Lignes rejetées</p>
            <p class="mt-2 text-2xl font-black text-red-600">{{ preview.summary.invalidRows }}</p>
          </div>
          <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <p class="text-[10px] font-black uppercase tracking-[0.1em] text-slate-400">Orgs à créer</p>
            <p class="mt-2 text-2xl font-black text-blue-600">{{ preview.summary.organizationCreates }}</p>
          </div>
        </div>

        <!-- Row preview table -->
        <div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
          <div class="border-b border-slate-200 bg-slate-50 px-6 py-4 flex items-center justify-between">
            <h2 class="text-sm font-bold text-slate-900">Aperçu des lignes ({{ preview.rows.length }})</h2>
            <span class="text-xs font-medium text-slate-400">{{ preview.sourceName }}</span>
          </div>
          <div class="overflow-x-auto max-h-80 overflow-y-auto">
            <table class="w-full text-xs">
              <thead class="sticky top-0">
                <tr class="border-b border-slate-200 bg-slate-50 text-left text-[10px] font-black uppercase tracking-[0.1em] text-slate-500">
                  <th class="px-4 py-3">#</th>
                  <th class="px-4 py-3">Statut</th>
                  <th class="px-4 py-3">Email principal</th>
                  <th class="px-4 py-3">Nom</th>
                  <th class="px-4 py-3">Organisation</th>
                  <th class="px-4 py-3">Raison</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100">
                <tr
                  v-for="row in preview.rows"
                  :key="row.lineNumber"
                  :class="[
                    'hover:bg-slate-50 transition-colors',
                    row.status === 'invalid' ? 'bg-red-50' : '',
                    row.status === 'duplicate_existing' || row.status === 'duplicate_in_file' ? 'bg-amber-50' : '',
                  ]"
                >
                  <td class="px-4 py-2.5 font-mono text-slate-400">{{ row.lineNumber }}</td>
                  <td class="px-4 py-2.5">
                    <span
                      :class="[
                        'inline-flex rounded-md border px-2 py-0.5 text-[10px] font-black uppercase tracking-wide',
                        row.status === 'valid'
                          ? 'border-emerald-200 bg-emerald-50 text-emerald-700'
                          : row.status === 'invalid'
                            ? 'border-red-200 bg-red-50 text-red-700'
                            : 'border-amber-200 bg-amber-50 text-amber-700',
                      ]"
                    >
                      {{ rowStatusLabel(row.status) }}
                    </span>
                  </td>
                  <td class="px-4 py-2.5 font-medium text-slate-700 truncate max-w-[180px]">
                    {{ row.primaryEmail || '—' }}
                  </td>
                  <td class="px-4 py-2.5 text-slate-600 truncate max-w-[140px]">
                    {{ row.name || '—' }}
                  </td>
                  <td class="px-4 py-2.5 text-slate-600 truncate max-w-[140px]">
                    {{ row.organization?.name || row.organizationName || '—' }}
                    <span
                      v-if="row.organization?.action === 'create'"
                      class="ml-1 text-[10px] font-bold text-blue-600"
                    >
                      (nouvelle)
                    </span>
                  </td>
                  <td class="px-4 py-2.5 text-slate-400 max-w-[200px]">
                    {{ row.reason || '—' }}
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Actions -->
        <div class="flex items-center justify-between gap-3">
          <button
            class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-bold text-slate-600 hover:bg-slate-50 shadow-sm transition-all"
            @click="resetToUpload"
          >
            ← Changer de fichier
          </button>
          <div class="flex items-center gap-3">
            <p v-if="preview.summary.validRows === 0" class="text-xs font-medium text-red-600">
              Aucune ligne valide à importer.
            </p>
            <button
              :disabled="preview.summary.validRows === 0 || confirming"
              class="btn-primary-gradient text-white px-5 py-2.5 rounded-xl text-xs font-bold shadow-lg shadow-blue-500/20 hover:opacity-90 transition-all disabled:opacity-40"
              @click="confirmImport"
            >
              {{ confirming ? 'Import en cours…' : `Confirmer l'import (${preview.summary.validRows} contacts)` }}
            </button>
          </div>
        </div>

        <p v-if="confirmError" class="text-xs font-medium text-red-600">{{ confirmError }}</p>
      </template>

      <!-- ── Step 2: Result ─────────────────────────────────── -->
      <template v-else-if="step === 'result'">
        <!-- Result banner -->
        <div
          :class="[
            'rounded-xl border px-5 py-4',
            importResult.summary.importedRows > 0
              ? 'border-emerald-200 bg-emerald-50'
              : 'border-amber-200 bg-amber-50',
          ]"
        >
          <p
            :class="[
              'text-sm font-bold',
              importResult.summary.importedRows > 0 ? 'text-emerald-800' : 'text-amber-800',
            ]"
          >
            {{ importResult.message }}
          </p>
        </div>

        <!-- Summary stats -->
        <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
          <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <p class="text-[10px] font-black uppercase tracking-[0.1em] text-slate-400">Importés</p>
            <p class="mt-2 text-2xl font-black text-emerald-600">
              {{ importResult.summary.importedRows }}
            </p>
          </div>
          <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <p class="text-[10px] font-black uppercase tracking-[0.1em] text-slate-400">Doublons ignorés</p>
            <p class="mt-2 text-2xl font-black text-amber-600">
              {{ importResult.summary.duplicateExistingRows }}
            </p>
          </div>
          <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <p class="text-[10px] font-black uppercase tracking-[0.1em] text-slate-400">Ignorés</p>
            <p class="mt-2 text-2xl font-black text-slate-500">{{ importResult.summary.skippedRows }}</p>
          </div>
          <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <p class="text-[10px] font-black uppercase tracking-[0.1em] text-slate-400">Rejetés</p>
            <p class="mt-2 text-2xl font-black text-red-600">{{ importResult.summary.invalidRows }}</p>
          </div>
        </div>

        <!-- Row result table (errors only unless no errors) -->
        <div
          v-if="importResult.rows.filter((r) => r.resultStatus !== 'imported').length > 0"
          class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden"
        >
          <div class="border-b border-slate-200 bg-slate-50 px-6 py-4">
            <h2 class="text-sm font-bold text-slate-900">Lignes non importées</h2>
          </div>
          <div class="overflow-x-auto max-h-64 overflow-y-auto">
            <table class="w-full text-xs">
              <thead class="sticky top-0">
                <tr class="border-b border-slate-200 bg-slate-50 text-left text-[10px] font-black uppercase tracking-[0.1em] text-slate-500">
                  <th class="px-4 py-3">#</th>
                  <th class="px-4 py-3">Résultat</th>
                  <th class="px-4 py-3">Email</th>
                  <th class="px-4 py-3">Raison</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100">
                <tr
                  v-for="row in importResult.rows.filter((r) => r.resultStatus !== 'imported')"
                  :key="row.lineNumber"
                  class="hover:bg-slate-50"
                >
                  <td class="px-4 py-2.5 font-mono text-slate-400">{{ row.lineNumber }}</td>
                  <td class="px-4 py-2.5">
                    <span
                      :class="[
                        'inline-flex rounded-md border px-2 py-0.5 text-[10px] font-black uppercase tracking-wide',
                        row.resultStatus === 'duplicate_existing'
                          ? 'border-amber-200 bg-amber-50 text-amber-700'
                          : 'border-red-200 bg-red-50 text-red-700',
                      ]"
                    >
                      {{ resultStatusLabel(row.resultStatus) }}
                    </span>
                  </td>
                  <td class="px-4 py-2.5 text-slate-600">{{ row.primaryEmail || '—' }}</td>
                  <td class="px-4 py-2.5 text-slate-400">{{ row.resultMessage || row.reason || '—' }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Actions -->
        <div class="flex items-center gap-3">
          <Link
            href="/contacts"
            class="btn-primary-gradient text-white px-5 py-2.5 rounded-xl text-xs font-bold shadow-lg shadow-blue-500/20 hover:opacity-90 transition-all"
          >
            Voir les contacts →
          </Link>
          <button
            class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-bold text-slate-600 hover:bg-slate-50 shadow-sm transition-all"
            @click="resetToUpload"
          >
            Nouvel import
          </button>
        </div>
      </template>

    </div>
  </CrmLayout>
</template>

<script setup>
import { ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import axios from 'axios';
import CrmLayout from '@/Layouts/CrmLayout.vue';

// ── State ──────────────────────────────────────────────────
// 'upload' | 'preview' | 'result'
const step = ref('upload');
const isDragging = ref(false);
const uploading = ref(false);
const uploadError = ref(null);
const confirming = ref(false);
const confirmError = ref(null);

const preview = ref(null);   // POST /api/contacts/imports/preview response
const importResult = ref(null); // POST /api/contacts/imports response

const filePicker = ref(null);

// ── File handling ───────────────────────────────────────────
function onFileSelect(event) {
  const file = event.target.files?.[0];
  if (file) uploadFile(file);
}

function onFileDrop(event) {
  isDragging.value = false;
  const file = event.dataTransfer?.files?.[0];
  if (file) uploadFile(file);
}

async function uploadFile(file) {
  uploadError.value = null;
  uploading.value = true;

  const ext = file.name.split('.').pop()?.toLowerCase();
  if (!['csv', 'xlsx'].includes(ext)) {
    uploadError.value = 'Format non supporté. Utilisez un fichier CSV ou XLSX.';
    uploading.value = false;
    return;
  }

  try {
    const formData = new FormData();
    formData.append('file', file);
    const { data } = await axios.post('/api/contacts/imports/preview', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    });
    preview.value = data.preview;
    step.value = 'preview';
  } catch (e) {
    const errors = e.response?.data?.errors?.file;
    uploadError.value =
      Array.isArray(errors) ? errors[0] : (e.response?.data?.message ?? 'Erreur lors de l\'analyse du fichier.');
  } finally {
    uploading.value = false;
    // Reset file picker so same file can be re-selected
    if (filePicker.value) filePicker.value.value = '';
  }
}

// ── Confirm import ─────────────────────────────────────────
async function confirmImport() {
  if (!preview.value?.previewToken) return;
  confirming.value = true;
  confirmError.value = null;

  try {
    const { data } = await axios.post('/api/contacts/imports', {
      previewToken: preview.value.previewToken,
    });
    importResult.value = data;
    step.value = 'result';
  } catch (e) {
    const errors = e.response?.data?.errors?.previewToken;
    confirmError.value =
      Array.isArray(errors) ? errors[0] : (e.response?.data?.message ?? 'Erreur lors de l\'import.');
  } finally {
    confirming.value = false;
  }
}

// ── Reset ──────────────────────────────────────────────────
function resetToUpload() {
  step.value = 'upload';
  preview.value = null;
  importResult.value = null;
  uploadError.value = null;
  confirmError.value = null;
}

// ── Label helpers ──────────────────────────────────────────
function rowStatusLabel(status) {
  const map = {
    valid: 'Valide',
    invalid: 'Rejeté',
    duplicate_existing: 'Doublon',
    duplicate_in_file: 'Doublon fichier',
  };
  return map[status] ?? status;
}

function resultStatusLabel(status) {
  const map = {
    imported: 'Importé',
    skipped: 'Ignoré',
    duplicate_existing: 'Doublon',
  };
  return map[status] ?? status;
}
</script>
