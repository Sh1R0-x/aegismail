<template>
  <CrmLayout
    title="Import / Export"
    subtitle="Importez et exportez vos organisations et contacts"
    current-page="import-export"
  >
    <div class="space-y-6 max-w-4xl mx-auto">

      <!-- ── Export section ──────────────────────────────────── -->
      <div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
        <div class="border-b border-slate-100 bg-slate-50 px-6 py-4">
          <h2 class="text-sm font-bold text-slate-900">Exporter</h2>
          <p class="mt-0.5 text-xs font-medium text-slate-400">
            Téléchargez le template vierge ou exportez vos données au même format CSV pour les modifier puis les réimporter.
          </p>
        </div>
        <div class="px-6 py-5 flex flex-wrap items-center gap-3">
          <a
            href="/api/import-export/template"
            download
            class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-bold text-slate-700 hover:bg-slate-50 shadow-sm transition-all"
          >
            ↓ Exporter le template
          </a>
          <a
            href="/api/import-export/export"
            download
            class="btn-primary-gradient text-white px-5 py-2.5 rounded-xl text-xs font-bold shadow-lg shadow-blue-500/20 hover:opacity-90 transition-all"
          >
            ↓ Exporter les données
          </a>
          <p class="text-xs font-medium text-slate-400 ml-2">
            Le fichier exporté est au même format que l'import — modifiez-le puis réimportez-le ici.
          </p>
        </div>
      </div>

      <!-- ── Import section ──────────────────────────────────── -->

      <!-- Step: Upload -->
      <template v-if="step === 'upload'">
        <!-- Règle obligatoire -->
        <div class="rounded-xl border border-amber-200 bg-amber-50 px-5 py-4 text-xs font-medium text-amber-800 space-y-1">
          <p class="font-bold">Règle obligatoire</p>
          <p>
            Chaque ligne du fichier représente une société + un contact.
            Les colonnes
            <code class="rounded bg-amber-100 px-1 font-mono">societe</code> et
            <code class="rounded bg-amber-100 px-1 font-mono">email</code>
            sont obligatoires. Les lignes sans organisation seront rejetées.
          </p>
        </div>

        <!-- Upload zone -->
        <div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
          <div class="border-b border-slate-100 bg-slate-50 px-6 py-4">
            <h2 class="text-sm font-bold text-slate-900">Importer un fichier CSV</h2>
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

      <!-- Step: Preview / Diff -->
      <template v-else-if="step === 'preview'">
        <!-- File info -->
        <div class="rounded-xl border border-slate-200 bg-white px-5 py-3 shadow-sm flex items-center justify-between">
          <div class="flex items-center gap-3">
            <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-blue-100 text-blue-600 text-sm font-bold">CSV</span>
            <div>
              <p class="text-sm font-bold text-slate-800">{{ preview.sourceName }}</p>
              <p class="text-xs text-slate-400">{{ preview.rows.length }} lignes détectées</p>
            </div>
          </div>
          <button
            class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-bold text-slate-600 hover:bg-slate-50 shadow-sm transition-all"
            @click="resetToUpload"
          >
            ← Changer de fichier
          </button>
        </div>

        <!-- Mapping (detected columns) -->
        <div v-if="preview.detectedColumns && preview.detectedColumns.length > 0" class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
          <div class="border-b border-slate-100 bg-slate-50 px-6 py-3 flex items-center justify-between">
            <h3 class="text-xs font-bold text-slate-700">Colonnes détectées</h3>
            <span class="text-[10px] font-medium text-slate-400">{{ preview.detectedColumns.filter(c => c.retained).length }} colonnes retenues</span>
          </div>
          <div class="px-6 py-3 flex flex-wrap gap-2">
            <span
              v-for="col in preview.detectedColumns"
              :key="col.index"
              :class="[
                'inline-flex items-center rounded-md border px-2 py-0.5 text-[10px] font-bold',
                col.retained
                  ? 'border-emerald-200 bg-emerald-50 text-emerald-700'
                  : 'border-slate-200 bg-slate-50 text-slate-400',
              ]"
              :title="col.retained ? `→ ${col.field}` : 'Non retenue'"
            >
              {{ col.sourceHeader }}
              <span v-if="col.retained && col.field" class="ml-1 text-emerald-500">→ {{ col.label || col.field }}</span>
            </span>
          </div>
        </div>

        <!-- Global errors / warnings -->
        <div v-if="preview.errors && preview.errors.length > 0" class="rounded-xl border border-red-200 bg-red-50 px-5 py-4 space-y-2">
          <p class="text-xs font-bold text-red-800">Erreurs bloquantes</p>
          <ul class="space-y-1">
            <li v-for="err in preview.errors" :key="err.code" class="text-xs text-red-700">
              <span class="font-bold">{{ err.message }}</span>
              <span v-if="err.count > 1" class="ml-1 text-red-500">({{ err.count }} lignes)</span>
            </li>
          </ul>
        </div>

        <div v-if="preview.warnings && preview.warnings.length > 0" class="rounded-xl border border-amber-200 bg-amber-50 px-5 py-4 space-y-2">
          <p class="text-xs font-bold text-amber-800">Avertissements</p>
          <ul class="space-y-1">
            <li v-for="warn in preview.warnings" :key="warn.code" class="text-xs text-amber-700">
              <span class="font-bold">{{ warn.message }}</span>
              <span v-if="warn.count > 1" class="ml-1 text-amber-500">({{ warn.count }} lignes)</span>
            </li>
          </ul>
        </div>

        <!-- Counters summary -->
        <div class="grid grid-cols-2 gap-3 md:grid-cols-5">
          <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <p class="text-[10px] font-black uppercase tracking-[0.1em] text-slate-400">À créer</p>
            <p class="mt-2 text-2xl font-black text-emerald-600">{{ preview.counters?.create ?? 0 }}</p>
          </div>
          <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <p class="text-[10px] font-black uppercase tracking-[0.1em] text-slate-400">À mettre à jour</p>
            <p class="mt-2 text-2xl font-black text-blue-600">{{ preview.counters?.update ?? 0 }}</p>
          </div>
          <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <p class="text-[10px] font-black uppercase tracking-[0.1em] text-slate-400">Inchangé</p>
            <p class="mt-2 text-2xl font-black text-slate-500">{{ preview.counters?.unchanged ?? 0 }}</p>
          </div>
          <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <p class="text-[10px] font-black uppercase tracking-[0.1em] text-slate-400">Ignoré</p>
            <p class="mt-2 text-2xl font-black text-amber-600">{{ preview.counters?.skip ?? 0 }}</p>
          </div>
          <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <p class="text-[10px] font-black uppercase tracking-[0.1em] text-slate-400">Erreur</p>
            <p class="mt-2 text-2xl font-black text-red-600">{{ preview.counters?.error ?? 0 }}</p>
          </div>
        </div>

        <!-- Organization / Contact breakdown -->
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
          <!-- Organization summary -->
          <div v-if="preview.organizationSummary" class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
            <div class="border-b border-slate-100 bg-slate-50 px-5 py-3">
              <h3 class="text-xs font-bold text-slate-700">Organisations</h3>
            </div>
            <div class="px-5 py-3 space-y-1.5 text-xs">
              <div class="flex justify-between">
                <span class="text-slate-500">Existantes réutilisées</span>
                <span class="font-bold text-slate-700">{{ preview.organizationSummary.matchedExistingCount ?? preview.organizationSummary.keptExistingCount ?? 0 }}</span>
              </div>
              <div class="flex justify-between">
                <span class="text-slate-500">À créer</span>
                <span class="font-bold text-emerald-600">{{ preview.organizationSummary.createdCount ?? 0 }}</span>
              </div>
              <div v-if="preview.organizationSummary.updatedCount" class="flex justify-between">
                <span class="text-slate-500">À mettre à jour</span>
                <span class="font-bold text-blue-600">{{ preview.organizationSummary.updatedCount }}</span>
              </div>
              <div v-if="preview.organizationSummary.missingCount" class="flex justify-between">
                <span class="text-slate-500">Manquantes</span>
                <span class="font-bold text-red-600">{{ preview.organizationSummary.missingCount }}</span>
              </div>
            </div>
          </div>

          <!-- Contact summary -->
          <div v-if="preview.contactSummary" class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
            <div class="border-b border-slate-100 bg-slate-50 px-5 py-3">
              <h3 class="text-xs font-bold text-slate-700">Contacts</h3>
            </div>
            <div class="px-5 py-3 space-y-1.5 text-xs">
              <div class="flex justify-between">
                <span class="text-slate-500">À créer</span>
                <span class="font-bold text-emerald-600">{{ preview.contactSummary.createdCount ?? 0 }}</span>
              </div>
              <div class="flex justify-between">
                <span class="text-slate-500">À mettre à jour</span>
                <span class="font-bold text-blue-600">{{ preview.contactSummary.updatedCount ?? 0 }}</span>
              </div>
              <div class="flex justify-between">
                <span class="text-slate-500">Inchangés</span>
                <span class="font-bold text-slate-500">{{ preview.contactSummary.unchangedCount ?? 0 }}</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Diff heading -->
        <div class="flex items-center justify-between">
          <h2 class="text-sm font-bold text-slate-900">Aperçu avant import — Différences détectées</h2>
          <div class="flex items-center gap-2">
            <button
              v-for="f in diffFilters"
              :key="f.key"
              :class="[
                'rounded-lg px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide border transition-all',
                activeDiffFilter === f.key
                  ? f.activeClass
                  : 'border-slate-200 bg-white text-slate-400 hover:text-slate-600',
              ]"
              @click="activeDiffFilter = activeDiffFilter === f.key ? 'all' : f.key"
            >
              {{ f.label }} ({{ f.count }})
            </button>
          </div>
        </div>

        <!-- Diff table -->
        <div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
          <div class="overflow-x-auto max-h-[480px] overflow-y-auto">
            <table class="w-full text-xs">
              <thead class="sticky top-0 z-10">
                <tr class="border-b border-slate-200 bg-slate-50 text-left text-[10px] font-black uppercase tracking-[0.1em] text-slate-500">
                  <th class="px-3 py-3 w-10">#</th>
                  <th class="px-3 py-3 w-20">Action</th>
                  <th class="px-3 py-3">Organisation</th>
                  <th class="px-3 py-3">Contact</th>
                  <th class="px-3 py-3">E-mail</th>
                  <th class="px-3 py-3">LinkedIn</th>
                  <th class="px-3 py-3">Tél. fixe</th>
                  <th class="px-3 py-3">Tél. portable</th>
                  <th class="px-3 py-3 w-44">Détails</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100">
                <tr v-if="filteredRows.length === 0">
                  <td colspan="9" class="px-6 py-8 text-center text-xs text-slate-400">
                    Aucune ligne ne correspond au filtre sélectionné.
                  </td>
                </tr>
                <tr
                  v-for="row in filteredRows"
                  :key="row.lineNumber"
                  :class="[
                    'hover:bg-slate-50/50 transition-colors',
                    row.action === 'error' ? 'bg-red-50/50' : '',
                    row.action === 'skip' ? 'bg-amber-50/30' : '',
                    row.action === 'create' ? 'bg-emerald-50/30' : '',
                    row.action === 'update' ? 'bg-blue-50/30' : '',
                    row.action === 'unchanged' ? '' : '',
                  ]"
                >
                  <td class="px-3 py-2.5 font-mono text-slate-400">{{ row.lineNumber }}</td>
                  <td class="px-3 py-2.5">
                    <span
                      :class="[
                        'inline-flex rounded-md border px-2 py-0.5 text-[10px] font-black uppercase tracking-wide whitespace-nowrap',
                        actionBadgeClass(row.action),
                      ]"
                    >
                      {{ actionLabel(row.action) }}
                    </span>
                  </td>
                  <td class="px-3 py-2.5">
                    <div class="flex flex-col gap-0.5">
                      <span class="font-medium text-slate-700 truncate max-w-[140px]">{{ row.organizationName || '—' }}</span>
                      <span
                        v-if="row.organization"
                        :class="[
                          'text-[10px] font-bold',
                          orgActionColor(row.organization.action),
                        ]"
                      >
                        {{ row.plannedActions?.organization?.label || orgActionLabel(row.organization.action) }}
                      </span>
                      <template v-if="row.organization?.changes?.length > 0">
                        <span
                          v-for="change in row.organization.changes"
                          :key="change.field || change"
                          class="text-[10px] text-blue-500"
                        >
                          {{ typeof change === 'string' ? change : `${change.field}: ${change.from ?? '∅'} → ${change.to ?? '∅'}` }}
                        </span>
                      </template>
                    </div>
                  </td>
                  <td class="px-3 py-2.5">
                    <div class="flex flex-col gap-0.5">
                      <span class="font-medium text-slate-700 truncate max-w-[140px]">{{ row.name || '—' }}</span>
                      <span
                        v-if="row.contact"
                        :class="[
                          'text-[10px] font-bold',
                          contactActionColor(row.contact.action),
                        ]"
                      >
                        {{ row.plannedActions?.contact?.label || contactActionLabel(row.contact.action) }}
                      </span>
                      <template v-if="row.contact?.changes?.length > 0">
                        <span
                          v-for="change in row.contact.changes"
                          :key="change.field || change"
                          class="text-[10px] text-blue-500"
                        >
                          {{ typeof change === 'string' ? change : `${change.field}: ${change.from ?? '∅'} → ${change.to ?? '∅'}` }}
                        </span>
                      </template>
                    </div>
                  </td>
                  <td class="px-3 py-2.5 font-medium text-slate-600 truncate max-w-[160px]">
                    {{ row.primaryEmail || '—' }}
                  </td>
                  <td class="px-3 py-2.5 truncate max-w-[120px]">
                    <a
                      v-if="row.linkedinUrl"
                      :href="row.linkedinUrl"
                      target="_blank"
                      rel="noopener noreferrer"
                      class="text-blue-600 hover:text-blue-800 hover:underline"
                      title="Ouvrir le profil LinkedIn"
                    >
                      Voir
                    </a>
                    <span v-else class="text-slate-300">—</span>
                  </td>
                  <td class="px-3 py-2.5 text-slate-600 truncate max-w-[100px]">
                    {{ row.phoneLandline || '—' }}
                  </td>
                  <td class="px-3 py-2.5 text-slate-600 truncate max-w-[100px]">
                    {{ row.phoneMobile || '—' }}
                  </td>
                  <td class="px-3 py-2.5 text-slate-400 max-w-[200px]">
                    <template v-if="row.errors && row.errors.length > 0">
                      <span v-for="e in row.errors" :key="e.code || e" class="block text-[10px] text-red-600">{{ e.message || e }}</span>
                    </template>
                    <template v-else-if="row.warnings && row.warnings.length > 0">
                      <span v-for="w in row.warnings" :key="w.code || w" class="block text-[10px] text-amber-600">{{ w.message || w }}</span>
                    </template>
                    <template v-else-if="row.reason">
                      <span class="text-[10px]">{{ row.reason }}</span>
                    </template>
                    <template v-else>
                      <span class="text-[10px] text-slate-300">—</span>
                    </template>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Confirm action -->
        <div class="flex items-center justify-between gap-3">
          <button
            class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-bold text-slate-600 hover:bg-slate-50 shadow-sm transition-all"
            @click="resetToUpload"
          >
            ← Changer de fichier
          </button>
          <div class="flex items-center gap-3">
            <p v-if="hasBlockingErrors" class="text-xs font-medium text-red-600">
              Import impossible — erreurs bloquantes détectées.
            </p>
            <p v-else-if="writeCount === 0" class="text-xs font-medium text-slate-500">
              Aucune ligne à importer.
            </p>
            <p v-if="confirmError" class="text-xs font-medium text-red-600">{{ confirmError }}</p>
            <button
              :disabled="writeCount === 0 || hasBlockingErrors || confirming"
              class="btn-primary-gradient text-white px-5 py-2.5 rounded-xl text-xs font-bold shadow-lg shadow-blue-500/20 hover:opacity-90 transition-all disabled:opacity-40"
              @click="confirmImport"
            >
              {{ confirming ? 'Import en cours…' : `Lancer l'import (${writeCount} lignes)` }}
            </button>
          </div>
        </div>
      </template>

      <!-- Step: Result -->
      <template v-else-if="step === 'result'">
        <!-- Result banner -->
        <div
          :class="[
            'rounded-xl border px-5 py-4',
            importResult.summary?.importedRows > 0 || importResult.summary?.createdRows > 0
              ? 'border-emerald-200 bg-emerald-50'
              : 'border-amber-200 bg-amber-50',
          ]"
        >
          <p
            :class="[
              'text-sm font-bold',
              importResult.summary?.importedRows > 0 || importResult.summary?.createdRows > 0 ? 'text-emerald-800' : 'text-amber-800',
            ]"
          >
            {{ importResult.message || 'Import terminé.' }}
          </p>
        </div>

        <!-- Result counters -->
        <div class="grid grid-cols-2 gap-3 md:grid-cols-5">
          <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <p class="text-[10px] font-black uppercase tracking-[0.1em] text-slate-400">Créés</p>
            <p class="mt-2 text-2xl font-black text-emerald-600">{{ importResult.summary?.createdRows ?? importResult.summary?.importedRows ?? 0 }}</p>
          </div>
          <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <p class="text-[10px] font-black uppercase tracking-[0.1em] text-slate-400">Mis à jour</p>
            <p class="mt-2 text-2xl font-black text-blue-600">{{ importResult.summary?.updatedRows ?? 0 }}</p>
          </div>
          <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <p class="text-[10px] font-black uppercase tracking-[0.1em] text-slate-400">Inchangés</p>
            <p class="mt-2 text-2xl font-black text-slate-500">{{ importResult.summary?.unchangedRows ?? 0 }}</p>
          </div>
          <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <p class="text-[10px] font-black uppercase tracking-[0.1em] text-slate-400">Ignorés</p>
            <p class="mt-2 text-2xl font-black text-amber-600">{{ importResult.summary?.skippedRows ?? importResult.summary?.errorRows ?? 0 }}</p>
          </div>
          <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <p class="text-[10px] font-black uppercase tracking-[0.1em] text-slate-400">Erreurs</p>
            <p class="mt-2 text-2xl font-black text-red-600">{{ importResult.summary?.errorRows ?? importResult.summary?.invalidRows ?? 0 }}</p>
          </div>
        </div>

        <!-- Non-imported rows table -->
        <div
          v-if="nonImportedRows.length > 0"
          class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden"
        >
          <div class="border-b border-slate-200 bg-slate-50 px-6 py-4">
            <h2 class="text-sm font-bold text-slate-900">Lignes non importées ({{ nonImportedRows.length }})</h2>
          </div>
          <div class="overflow-x-auto max-h-64 overflow-y-auto">
            <table class="w-full text-xs">
              <thead class="sticky top-0">
                <tr class="border-b border-slate-200 bg-slate-50 text-left text-[10px] font-black uppercase tracking-[0.1em] text-slate-500">
                  <th class="px-4 py-3">#</th>
                  <th class="px-4 py-3">Résultat</th>
                  <th class="px-4 py-3">E-mail</th>
                  <th class="px-4 py-3">Raison</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100">
                <tr
                  v-for="row in nonImportedRows"
                  :key="row.lineNumber"
                  class="hover:bg-slate-50"
                >
                  <td class="px-4 py-2.5 font-mono text-slate-400">{{ row.lineNumber }}</td>
                  <td class="px-4 py-2.5">
                    <span
                      :class="[
                        'inline-flex rounded-md border px-2 py-0.5 text-[10px] font-black uppercase tracking-wide',
                        row.resultAction === 'skip' ? 'border-amber-200 bg-amber-50 text-amber-700' : 'border-red-200 bg-red-50 text-red-700',
                      ]"
                    >
                      {{ resultActionLabel(row.resultAction || row.resultStatus) }}
                    </span>
                  </td>
                  <td class="px-4 py-2.5 text-slate-600">{{ row.primaryEmail || '—' }}</td>
                  <td class="px-4 py-2.5 text-slate-400">{{ row.resultMessage || row.reason || '—' }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Result actions -->
        <div class="rounded-xl border border-slate-100 bg-slate-50 px-5 py-4 text-xs font-medium text-slate-500">
          <p>
            Pour corriger des erreurs, exportez les données, modifiez le fichier CSV, puis réimportez-le.
          </p>
        </div>

        <div class="flex items-center gap-3">
          <Link
            href="/contacts"
            class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-bold text-slate-600 hover:bg-slate-50 shadow-sm transition-all"
          >
            Voir les contacts →
          </Link>
          <Link
            href="/organizations"
            class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-bold text-slate-600 hover:bg-slate-50 shadow-sm transition-all"
          >
            Voir les organisations →
          </Link>
          <button
            class="btn-primary-gradient text-white px-5 py-2.5 rounded-xl text-xs font-bold shadow-lg shadow-blue-500/20 hover:opacity-90 transition-all"
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
import { ref, computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import axios from 'axios';
import CrmLayout from '@/Layouts/CrmLayout.vue';

// ── State ──────────────────────────────────────────────────
const step = ref('upload'); // 'upload' | 'preview' | 'result'
const isDragging = ref(false);
const uploading = ref(false);
const uploadError = ref(null);
const confirming = ref(false);
const confirmError = ref(null);

const preview = ref(null);
const importResult = ref(null);

const filePicker = ref(null);
const activeDiffFilter = ref('all');

// ── Computed ───────────────────────────────────────────────
const writeCount = computed(() => {
  if (!preview.value) return 0;
  return preview.value.summary?.writeRows
    ?? (preview.value.counters?.create ?? 0) + (preview.value.counters?.update ?? 0);
});

const hasBlockingErrors = computed(() => {
  return (preview.value?.errors?.length ?? 0) > 0;
});

const diffFilters = computed(() => {
  if (!preview.value?.counters) return [];
  const c = preview.value.counters;
  return [
    { key: 'all', label: 'Tout', count: preview.value.rows?.length ?? 0, activeClass: 'border-slate-400 bg-slate-100 text-slate-700' },
    { key: 'create', label: 'À créer', count: c.create ?? 0, activeClass: 'border-emerald-300 bg-emerald-50 text-emerald-700' },
    { key: 'update', label: 'À mettre à jour', count: c.update ?? 0, activeClass: 'border-blue-300 bg-blue-50 text-blue-700' },
    { key: 'unchanged', label: 'Inchangé', count: c.unchanged ?? 0, activeClass: 'border-slate-300 bg-slate-100 text-slate-600' },
    { key: 'skip', label: 'Ignoré', count: c.skip ?? 0, activeClass: 'border-amber-300 bg-amber-50 text-amber-700' },
    { key: 'error', label: 'Erreur', count: c.error ?? 0, activeClass: 'border-red-300 bg-red-50 text-red-700' },
  ];
});

const filteredRows = computed(() => {
  if (!preview.value?.rows) return [];
  if (activeDiffFilter.value === 'all') return preview.value.rows;
  return preview.value.rows.filter(r => r.action === activeDiffFilter.value);
});

const nonImportedRows = computed(() => {
  if (!importResult.value?.rows) return [];
  return importResult.value.rows.filter(r => r.resultStatus !== 'imported' && r.resultAction !== 'create' && r.resultAction !== 'update' && r.resultAction !== 'unchanged');
});

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
    const { data } = await axios.post('/api/import-export/preview', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    });
    preview.value = data.preview ?? data;
    step.value = 'preview';
  } catch (e) {
    const errors = e.response?.data?.errors?.file;
    uploadError.value =
      Array.isArray(errors) ? errors[0] : (e.response?.data?.message ?? 'Erreur lors de l\'analyse du fichier.');
  } finally {
    uploading.value = false;
    if (filePicker.value) filePicker.value.value = '';
  }
}

// ── Confirm import ─────────────────────────────────────────
async function confirmImport() {
  if (!preview.value?.previewToken) return;
  confirming.value = true;
  confirmError.value = null;

  try {
    const { data } = await axios.post('/api/import-export/confirm', {
      previewToken: preview.value.previewToken,
    });
    importResult.value = data;
    step.value = 'result';
  } catch (e) {
    const status = e.response?.status;
    const errors = e.response?.data?.errors?.previewToken;
    if (status === 422 && errors) {
      confirmError.value = Array.isArray(errors) ? errors[0] : errors;
    } else if (status === 410 || status === 404) {
      confirmError.value = 'Ce jeton de prévisualisation est invalide ou déjà utilisé. Veuillez relancer un import.';
    } else {
      confirmError.value = e.response?.data?.message ?? 'Erreur lors de l\'import.';
    }
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
  activeDiffFilter.value = 'all';
}

// ── Label/style helpers ────────────────────────────────────
function actionLabel(action) {
  const map = { create: 'À créer', update: 'À mettre à jour', unchanged: 'Inchangé', skip: 'Ignoré', error: 'Erreur' };
  return map[action] ?? action;
}

function actionBadgeClass(action) {
  const map = {
    create: 'border-emerald-200 bg-emerald-50 text-emerald-700',
    update: 'border-blue-200 bg-blue-50 text-blue-700',
    unchanged: 'border-slate-200 bg-slate-50 text-slate-500',
    skip: 'border-amber-200 bg-amber-50 text-amber-700',
    error: 'border-red-200 bg-red-50 text-red-700',
  };
  return map[action] ?? 'border-slate-200 bg-slate-50 text-slate-500';
}

function orgActionLabel(action) {
  const map = {
    create: 'Nouvelle',
    update: 'Mise à jour',
    reuse: 'Existante',
    keep_existing: 'Existante',
    preserve_existing: 'Existante',
    missing: 'Manquante',
    ambiguous: 'Ambiguë',
    unchanged: 'Inchangée',
  };
  return map[action] ?? action;
}

function orgActionColor(action) {
  const map = {
    create: 'text-emerald-600',
    update: 'text-blue-600',
    reuse: 'text-slate-500',
    keep_existing: 'text-slate-500',
    preserve_existing: 'text-slate-500',
    missing: 'text-red-600',
    ambiguous: 'text-amber-600',
    unchanged: 'text-slate-400',
  };
  return map[action] ?? 'text-slate-500';
}

function contactActionLabel(action) {
  const map = {
    create: 'Nouveau',
    update: 'Mise à jour',
    unchanged: 'Inchangé',
  };
  return map[action] ?? action;
}

function contactActionColor(action) {
  const map = {
    create: 'text-emerald-600',
    update: 'text-blue-600',
    unchanged: 'text-slate-400',
  };
  return map[action] ?? 'text-slate-500';
}

function resultActionLabel(action) {
  const map = {
    create: 'Créé',
    update: 'Mis à jour',
    unchanged: 'Inchangé',
    skip: 'Ignoré',
    error: 'Erreur',
    imported: 'Importé',
    skipped: 'Ignoré',
  };
  return map[action] ?? action;
}
</script>
