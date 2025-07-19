<template>
  <div class="fm-wrapper" @click="closeContextMenu">
    <h1 class="font-normal text-xl md:text-xl mb-3 flex items-center">{{ titolo }}</h1>

    <!-- Actions Header -->
    <div class="flex gap-2 mb-6">
      <div class="relative h-9 w-full md:w-1/3 md:shrink-0">
        <SearchInput v-model="searchQuery" />
      </div>
      <div class="inline-flex items-center gap-2 ml-auto">
        <ActionButton @click="showCreateFolderModal = true" dusk="create-folder-button">
          <span class="md:inline-block">Crea Cartella</span>
          <span class="inline-block md:hidden">Crea</span>
        </ActionButton>
        <ActionButton @click="triggerFileInput" dusk="upload-file-button">
          <span class="md:inline-block">Carica File</span>
          <span class="inline-block md:hidden">Carica</span>
        </ActionButton>
      </div>
    </div>

    <!-- File Manager Container -->
    <div
      class="fm-container"
      @dragover.prevent="isDragging = true"
      @dragleave.prevent="isDragging = false"
      @drop.prevent="handleDrop"
    >
      <!-- Header with Breadcrumbs and View Switcher -->
      <header class="fm-header">
        <Breadcrumbs :crumbs="breadcrumbs" @navigate="navigateTo" />
        <div class="fm-actions">
          <PerPageSelector v-model="perPage" :options="perPageOptions" />
          <ViewSwitcher v-model="viewMode" />
        </div>
      </header>

      <!-- Dropzone -->
      <Dropzone v-if="isDragging" />

      <!-- Main Content -->
      <div v-if="loading" class="p-4">
        <LoadingSkeleton :view-mode="viewMode" />
      </div>
      <div v-else-if="paginatedItems.length > 0">
        <component
          :is="currentViewComponent"
          :items="paginatedItems"
          :editing-item="editingItem"
          v-model:new-item-name="newItemName"
          @item-double-click="handleDoubleClick"
          @item-context-menu="showContextMenu"
          @rename-item="renameItem"
          @cancel-editing="cancelEditing"
          @download-item="downloadItem"
          @start-editing="startEditing"
          @delete-item="openDeleteModal"
          @openAssignTypeModal="openAssignTypeModal"
        />
      </div>
      <div
        v-else
        class="flex flex-col items-center justify-center p-8 bg-gray-50 dark:bg-gray-800 border border-dashed border-gray-200 dark:border-gray-700 rounded-lg space-y-4"
      >
        <h3 class="text-2xl font-semibold text-gray-700 dark:text-gray-200">
          Cartella vuota
        </h3>
        <p class="text-sm text-gray-500 dark:text-gray-400">
          Trascina qui i file o
          <button
            @click="triggerFileInput"
            class="text-sky-500 hover:underline font-medium focus:outline-none"
          >
            seleziona manualmente
          </button>
        </p>
      </div>

      <!-- Pagination -->
      <Pagination
        v-if="pagination && pagination.total > 0"
        :pagination="pagination"
        @change-page="changePage"
      />
    </div>

    <!-- Hidden File Input -->
    <input
      type="file"
      @change="handleFileUpload"
      ref="fileInput"
      class="hidden"
      multiple
    />

    <!-- Context Menu -->
    <ContextMenu
      v-if="contextMenu.show"
      :menu="contextMenu"
      @rename="openRenameModal"
      @download="downloadItem"
      @delete="openDeleteModal"
      @assignType="openAssignTypeModal"
    />

    <!-- Modals -->
    <CreateFolderModal
      :show="showCreateFolderModal"
      v-model="newFolderName"
      @close="showCreateFolderModal = false"
      @create="createFolder"
    />
    <RenameModal
      :show="showRenameModal"
      v-model="newItemName"
      @close="showRenameModal = false"
      @rename="renameItem"
    />
    <DeleteModal
      :show="showDeleteModal"
      :item-name="contextMenu.item?.name"
      @close="showDeleteModal = false"
      @delete="deleteItem"
    />
    <AssignTypeModal
      :show="showAssignTypeModal"
      :type-options="typeOptions"
      v-model="selectedType"
      @close="showAssignTypeModal = false"
      @assign="assignType"
    />
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import { useDebounceFn } from '@vueuse/core'

import SearchInput from './FileManager/SearchInput.vue'
import ActionButton from './FileManager/ActionButton.vue'
import Breadcrumbs from './FileManager/Breadcrumbs.vue'
import PerPageSelector from './FileManager/PerPageSelector.vue'
import ViewSwitcher from './FileManager/ViewSwitcher.vue'
import Dropzone from './FileManager/Dropzone.vue'
import LoadingSkeleton from './FileManager/LoadingSkeleton.vue'
import GridView from './FileManager/GridView.vue'
import ListView from './FileManager/ListView.vue'
import Pagination from './FileManager/Pagination.vue'
import ContextMenu from './FileManager/ContextMenu.vue'
import CreateFolderModal from './FileManager/Modals/CreateFolderModal.vue'
import RenameModal from './FileManager/Modals/RenameModal.vue'
import DeleteModal from './FileManager/Modals/DeleteModal.vue'
import AssignTypeModal from './FileManager/Modals/AssignTypeModal.vue'

import { isImage, onImageError } from '../utils/filetypes'

// Props iniettate da Nova via withMeta()
const props = defineProps({
  resourceName: String,
  resourceId: String,
  resource: Object,
  typeOptions: {
    type: Array,
    default: () => []
  }
})

// mantengo una ref locale per reattività
const typeOptions = ref(props.typeOptions)

// Se Nova aggiorna le meta in runtime, aggiorno la ref
watch(() => props.typeOptions, opts => {
  typeOptions.value = opts
})

const titolo = computed(() => {
  const fld = props.resource.fields?.find(f => f.component === 'resource-file-manager')
  return fld?.label || 'Gestione File'
})

// Stati
const loading = ref(true)
const allItems = ref([])
const breadcrumbs = ref([])
const currentPath = ref('')
const pagination = ref(null)
const perPage = ref(15)
const perPageOptions = [5, 15, 30, 50, 100]
const isDragging = ref(false)
const searchQuery = ref('')
const viewMode = ref('grid')

// Modals
const showCreateFolderModal = ref(false)
const newFolderName = ref('')
const showRenameModal = ref(false)
const newItemName = ref('')
const showDeleteModal = ref(false)
const showAssignTypeModal = ref(false)
const selectedType = ref('')

// Context menu & editing
const contextMenu = ref({ show: false, x: 0, y: 0, item: null })
const editingItem = ref(null)

// ref per input file
const fileInput = ref(null)

const filteredItems = computed(() => {
  if (!searchQuery.value) return allItems.value
  return allItems.value.filter(i =>
    i.name.toLowerCase().includes(searchQuery.value.toLowerCase())
  )
})
const paginatedItems = computed(() => filteredItems.value)
const currentViewComponent = computed(() => viewMode.value === 'grid' ? GridView : ListView)

// API calls
function getApiParams(extra = {}) {
  return {
    resourceName: props.resourceName,
    resourceId: props.resourceId,
    path: currentPath.value,
    ...extra
  }
}
async function fetchFiles() {
  loading.value = true
  try {
    const resp = await Nova.request().get('/api/rfm/list', { params: getApiParams({ page: pagination.value?.currentPage || 1, perPage: perPage.value }) })
    allItems.value = resp.data.files
    breadcrumbs.value = resp.data.breadcrumbs
    pagination.value = resp.data.pagination
  } catch {
    Nova.error('Impossibile caricare i file.')
  } finally {
    loading.value = false
  }
}
const debouncedFetch = useDebounceFn(fetchFiles, 300)

// Azioni
function navigateTo(path) {
  currentPath.value = path
  pagination.value.currentPage = 1
  fetchFiles()
}
function changePage(page) {
  if (page < 1 || page > pagination.value.lastPage) return
  pagination.value.currentPage = page
  fetchFiles()
}
async function handleDoubleClick(item) {
  if (item.type === 'folder') return navigateTo(item.path)
  try {
    const { data } = await Nova.request().get('/api/rfm/generate-temp-link', { params: getApiParams({ path: item.path }) })
    window.open(data.url, '_blank')
  } catch {
    Nova.error('Impossibile generare il link per il file.')
  }
}
function generateUniqueName(name, existing) {
  let base = name, ext = ''
  const idx = name.lastIndexOf('.')
  if (idx !== -1) {
    base = name.slice(0, idx)
    ext = name.slice(idx)
  }
  let candidate = name, i = 1
  while (existing.includes(candidate)) {
    candidate = `${base} (${i++})${ext}`
  }
  return candidate
}
// createFolder, renameItem, deleteItem, downloadItem simili a prima…
async function assignType(type) {
  const item = contextMenu.value.item
  if (!item) return
  try {
    await Nova.request().post('/api/rfm/assign-type', {
      path: item.path,
      type,
      ...getApiParams()
    })
    Nova.success('Tipo assegnato!')
    fetchFiles()
  } catch {
    Nova.error('Errore in assegnazione tipo.')
  }
}

// Drag & drop, upload
function triggerFileInput() { fileInput.value.click() }
function handleFileUpload(e) { uploadFiles(e.target.files) }
function handleDrop(e) { isDragging.value = false; uploadFiles(e.dataTransfer.files) }
async function uploadFiles(files) {
  if (!files.length) return
  loading.value = true
  const existing = allItems.value.filter(i => i.type === 'file').map(i => i.name)
  const promises = Array.from(files).map(file => {
    const finalName = generateUniqueName(file.name, existing)
    const f = finalName !== file.name ? new File([file], finalName, { type: file.type }) : file
    const fd = new FormData()
    fd.append('file', f)
    Object.entries(getApiParams()).forEach(([k,v]) => fd.append(k,v))
    return Nova.request().post('/api/rfm/upload', fd)
  })
  try {
    await Promise.all(promises)
    Nova.success('File caricati con successo!')
    fetchFiles()
  } catch {
    Nova.error('Errore durante il caricamento.')
  } finally {
    loading.value = false
  }
}

// Context menu & modals
function showContextMenu(e, item) {
  contextMenu.value = { show: true, x: e.clientX, y: e.clientY, item }
}
function closeContextMenu() {
  contextMenu.value.show = false
}
function openAssignTypeModal(item) {
  contextMenu.value.item = item
  selectedType.value = item.tags || typeOptions.value[0]?.value || ''
  showAssignTypeModal.value = true
}
function openRenameModal(item) { /* … */ }
function openDeleteModal(item) { /* … */ }

onMounted(fetchFiles)
</script>

<style scoped>
/* All the previous styles from the original component should be kept here */
/* They are well-structured and use CSS variables, so no major change is needed. */
/* For brevity, they are not repeated in this response. */
/* ... (paste original <style scoped> content here) ... */
    :root {
    --primary-500: #3b82f6;
    --primary-400: #60a5fa;
    --primary-600: #2563eb;
    }

    .fm-wrapper {
      --fm-font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, "Noto Sans", sans-serif, "Apple Color Emoji", "Segoe UI Emoji", "Segoe UI Symbol", "Noto Color Emoji";
      --fm-border-radius: 0.75rem; /* .xl */
      --fm-border-radius-sm: 0.5rem; /* .lg */
      
      /* Colori Primari (es. Blu di Tailwind) */
      --fm-primary-color: #3b82f6;
      --fm-primary-color-hover: #2563eb;
      
      /* Colori Testo e Sfondo (Light Mode) */
      --fm-text-color: rgb(100,116,139);
      --fm-text-color-dim: #6b7285;
      --fm-bg-color: #ffffff;
      --fm-bg-alt-color: #f9fafb;
      --fm-border-color: #e5e7eb;
      --fm-border-color-focus: #93c5fd;
      
      /* Colore Pericolo */
      --fm-danger-color: #ef4444;
      --fm-danger-color-hover: #dc2626;

      /* Ombre */
      --fm-shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
      --fm-shadow: 0 1px 3px 0 rgb(0 0 0 / 0.1), 0 1px 2px -1px rgb(0 0 0 / 0.1);
      --fm-shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1);
      --fm-shadow-xl: 0 20px 25px -5px rgb(0 0 0 / 0.1), 0 8px 10px -6px rgb(0 0 0 / 0.1);
  }

  /*
    Se la classe .dark è applicata al body o html, questo selettore
    applicherà correttamente le variabili dark al wrapper del componente.
  */
  .dark .fm-wrapper {
      /* Colori Primari (Dark Mode) */
      --fm-primary-color: #60a5fa;
      --fm-primary-color-hover: #3b82f6;

      /* Colori Testo e Sfondo (Dark Mode) */
      --fm-text-color: rgb(148,163,184);
      --fm-text-color-dim: #9ca3af;
      --fm-bg-color: rgb(30, 42, 59);
      --fm-bg-alt-color: #1f2937;
      --fm-border-color: #374151;
      --fm-border-color-focus: #3b82f6;
  }

  /* ---------- WRAPPER ---------- */
  .fm-wrapper {
    font-family: var(--fm-font-family);
    border-radius: var(--fm-border-radius);

  }

  /* ---------- CONTAINER ---------- */
  .fm-container {
    background-color: var(--fm-bg-color);
    padding: 0;
    border-radius: var(--fm-border-radius);
    box-shadow: var(--fm-shadow-lg);
    overflow: hidden; /* Ensures children with rounded corners look right */
  }
  .dark .fm-container {
      background: var(--fm-bg-color);
      backdrop-filter: saturate(180%) blur(20px);
  }

  /* ---------- HEADER ---------- */
  .fm-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 1rem;
    border-bottom: 1px solid var(--fm-border-color);
    padding: 0.75rem 1.25rem;
  }
  
  .fm-actions { display: flex; gap: 0.75rem; align-items: center; flex-wrap: wrap; }

  /* ---------- TRANSITIONS ---------- */
  .fade-scale-enter-active, .fade-scale-leave-active { transition: all .2s ease-in-out; }
  .fade-scale-enter-from, .fade-scale-leave-to { opacity: 0; transform: scale(0.97); }

  /* ---------- INPUT HIDDEN ---------- */
  .hidden { display: none; }
</style>

