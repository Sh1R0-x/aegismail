<template>
  <CrmLayout
    :title="editorOpen ? (editingTemplate ? 'Modifier le modèle' : 'Nouveau modèle') : 'Modèles'"
    :subtitle="editorOpen ? 'Créez ou modifiez un modèle d\'e-mail' : 'Modèles d\'e-mail réutilisables'"
    current-page="templates"
  >
    <template #header-actions>
      <template v-if="editorOpen">
        <button
          class="rounded-xl border border-slate-200 bg-white px-5 py-2.5 text-xs font-bold text-slate-600 hover:bg-slate-50 shadow-sm transition-all"
          @click="closeEditor"
        >
          ← Retour aux modèles
        </button>
      </template>
      <template v-else>
        <button
          class="btn-primary-gradient text-white px-5 py-2.5 rounded-xl text-xs font-bold shadow-lg shadow-blue-500/20 hover:opacity-90 transition-all"
          @click="openEditor(null)"
        >
          Nouveau modèle
        </button>
      </template>
    </template>

    <TemplateEditor
      v-if="editorOpen"
      :template="editingTemplate"
      @close="closeEditor"
      @saved="onSaved"
    />

    <div v-else class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
      <div class="flex items-center justify-between border-b border-slate-200 bg-slate-50 px-6 py-4">
        <h2 class="text-sm font-bold text-slate-900">Modèles d'e-mail</h2>
        <span class="text-xs font-bold text-slate-400">{{ templates.length }} modèle(s)</span>
      </div>

      <div v-if="templates.length === 0" class="px-6 py-16 text-center">
        <p class="text-sm font-medium text-slate-400">Aucun modèle créé.</p>
        <p class="mt-1 text-xs text-slate-400">Créez un modèle pour l'utiliser dans vos envois simples ou multiples.</p>
      </div>

      <table v-else class="w-full text-sm">
        <thead>
          <tr class="border-b border-slate-200 bg-slate-50 text-left text-[10px] font-black uppercase tracking-[0.1em] text-slate-500">
            <th class="px-6 py-4">Nom</th>
            <th class="px-6 py-4">Sujet</th>
            <th class="px-6 py-4">Statut</th>
            <th class="px-6 py-4">Utilisé</th>
            <th class="px-6 py-4">Modifié</th>
            <th class="px-6 py-4 text-right">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <tr
            v-for="tpl in templates"
            :key="tpl.id"
            :class="['hover:bg-slate-50 transition-colors', !tpl.active ? 'opacity-60' : '']"
          >
            <td class="px-6 py-4 font-bold text-slate-900">{{ tpl.name }}</td>
            <td class="px-6 py-4 text-slate-600 truncate max-w-xs">{{ tpl.subject || '—' }}</td>
            <td class="px-6 py-4">
              <span
                :class="[
                  'inline-flex items-center rounded-md border px-3 py-1 text-[10px] font-black uppercase tracking-wider',
                  tpl.active ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-slate-200 bg-slate-50 text-slate-500',
                ]"
              >
                {{ tpl.active ? 'Actif' : 'Archivé' }}
              </span>
            </td>
            <td class="px-6 py-4 font-bold text-slate-600">{{ tpl.usageCount }} fois</td>
            <td class="px-6 py-4 text-xs font-medium text-slate-400">{{ tpl.updatedAt }}</td>
            <td class="px-6 py-4 text-right space-x-3">
              <button
                class="text-xs font-bold text-blue-600 hover:text-blue-800 disabled:opacity-40"
                :disabled="actionLoading === tpl.id"
                @click="openEditor(tpl)"
              >
                Éditer
              </button>
              <span class="text-slate-200">·</span>
              <button
                class="text-xs font-bold text-slate-500 hover:text-slate-700 disabled:opacity-40"
                :disabled="actionLoading === tpl.id"
                @click="duplicateTemplate(tpl.id)"
              >
                Dupliquer
              </button>
              <span class="text-slate-200">·</span>
              <button
                :class="[
                  'text-xs font-bold disabled:opacity-40',
                  tpl.active
                    ? 'text-amber-600 hover:text-amber-800'
                    : 'text-emerald-600 hover:text-emerald-800',
                ]"
                :disabled="actionLoading === tpl.id"
                @click="toggleArchive(tpl)"
              >
                {{ tpl.active ? 'Archiver' : 'Activer' }}
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </CrmLayout>
</template>

<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import CrmLayout from '@/Layouts/CrmLayout.vue';
import TemplateEditor from '@/Components/Templates/TemplateEditor.vue';

defineProps({
  templates: { type: Array, default: () => [] },
});

const editorOpen     = ref(false);
const editingTemplate = ref(null);
const actionLoading  = ref(null);

function openEditor(template) {
  editingTemplate.value = template;
  editorOpen.value = true;
}

function closeEditor() {
  editorOpen.value = false;
  editingTemplate.value = null;
}

function onSaved() {
  closeEditor();
  router.reload({ preserveState: false });
}

async function duplicateTemplate(id) {
  actionLoading.value = id;
  try {
    await axios.post(`/api/templates/${id}/duplicate`);
    router.reload({ preserveState: false });
  } finally {
    actionLoading.value = null;
  }
}

async function toggleArchive(tpl) {
  actionLoading.value = tpl.id;
  try {
    const endpoint = tpl.active ? 'archive' : 'activate';
    await axios.post(`/api/templates/${tpl.id}/${endpoint}`);
    router.reload({ preserveState: false });
  } finally {
    actionLoading.value = null;
  }
}
</script>
