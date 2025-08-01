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
                    <ViewSwitcher v-model="viewMode" />
                    <div class="relative" ref="filterDropdown">
                        <button
                            @click="showFilterDropdown = !showFilterDropdown"
                            type="button"
                            class="border text-left appearance-none cursor-pointer rounded text-sm font-bold focus:outline-none focus:ring ring-primary-200 dark:ring-gray-600 relative disabled:cursor-not-allowed inline-flex items-center justify-center bg-transparent border-transparent h-9 px-1.5 text-gray-600 dark:text-gray-400 hover:[&:not(:disabled)]:bg-gray-700/5 dark:hover:[&:not(:disabled)]:bg-gray-950"
                        >
                            <span class="flex items-center gap-1">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true" class="w-6 h-6"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3c2.755 0 5.455.232 8.083.678.533.09.917.556.917 1.096v1.044a2.25 2.25 0 01-.659 1.591l-5.432 5.432a2.25 2.25 0 00-.659 1.591v2.927a2.25 2.25 0 01-1.244 2.013L9.75 21v-6.568a2.25 2.25 0 00-.659-1.591L3.659 7.409A2.25 2.25 0 013 5.818V4.774c0-.54.384-1.006.917-1.096A48.32 48.32 0 0112 3z"></path></svg>
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" class="w-5 h-5"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd"></path></svg>
                            </span>
                        </button>
                        <div v-if="showFilterDropdown" class="absolute z-10 right-0 mt-2 w-64 select-none overflow-hidden bg-white dark:bg-gray-900 shadow-lg rounded-lg border border-gray-200 dark:border-gray-700">
                            <div class="scroll-wrap overflow-x-hidden overflow-y-auto bg-white dark:bg-gray-900" style="max-height: 350px;">
                                <div class="divide-y divide-gray-200 dark:divide-gray-800 divide-solid">
                                    <div class="pt-2 pb-3">
                                        <h3 class="px-3 text-xs uppercase font-bold tracking-wide">Tipi Personalizzati</h3>
                                        <div class="mt-1 px-3">
                                            <select v-model="selectedTag" class="w-full block form-control form-control-bordered form-input h-8 text-xs">
                                                <option value="">Tutti</option>
                                                <option v-for="option in typeOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="pt-2 pb-3">
                                        <h3 class="px-3 text-xs uppercase font-bold tracking-wide">Tipi di File (Mimetype)</h3>
                                        <div class="mt-1 px-3">
                                            <select v-model="selectedMimetype" class="w-full block form-control form-control-bordered form-input h-8 text-xs">
                                                <option value="">Tutti</option>
                                                <option value="image/jpeg">JPEG</option>
                                                <option value="image/png">PNG</option>
                                                <option value="application/pdf">PDF</option>
                                                <option value="application/zip">ZIP</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="pt-2 pb-3">
                                        <h3 class="px-3 text-xs uppercase font-bold tracking-wide">Per Pagina</h3>
                                        <div class="mt-1 px-3">
                                            <select v-model="perPage" class="w-full block form-control form-control-bordered form-input h-8 text-xs">
                                                <option v-for="option in perPageOptions" :key="option" :value="option">{{ option }}</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
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
  <!-- Heroicon • Outline • Folder Open -->
  

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
            <Pagination v-if="pagination && pagination.total > 0" :pagination="pagination" @change-page="changePage" />
        </div>

        <!-- Hidden File Input -->
        <input type="file" @change="handleFileUpload" ref="fileInput" class="hidden" multiple>

        <!-- Context Menu -->
        <ContextMenu v-if="contextMenu.show" :menu="contextMenu" @rename="openRenameModal" @download="downloadItem" @delete="openDeleteModal" @assignType="openAssignTypeModal"  />

        <!-- Modals -->
        <CreateFolderModal :show="showCreateFolderModal" v-model="newFolderName" @close="showCreateFolderModal = false" @create="createFolder" />
        <RenameModal :show="showRenameModal" v-model="newItemName" @close="showRenameModal = false" @rename="renameItem" />
        <DeleteModal :show="showDeleteModal" :item-name="contextMenu.item?.name" @close="showDeleteModal = false" @delete="deleteItem" />
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
import { ref, computed, watch, onMounted, nextTick } from 'vue';
import { useDebounceFn } from '@vueuse/core';

// Import child components (assuming they are created in the same directory)
// Note: These components would need to be created as separate .vue files.
// This example proceeds as if they exist.
import SearchInput from './FileManager/SearchInput.vue';
import ActionButton from './FileManager/ActionButton.vue';
import Breadcrumbs from './FileManager/Breadcrumbs.vue';
import PerPageSelector from './FileManager/PerPageSelector.vue';
import ViewSwitcher from './FileManager/ViewSwitcher.vue';
import Dropzone from './FileManager/Dropzone.vue';
import LoadingSkeleton from './FileManager/LoadingSkeleton.vue';
import GridView from './FileManager/GridView.vue';
import ListView from './FileManager/ListView.vue';
import Pagination from './FileManager/Pagination.vue';
import ContextMenu from './FileManager/ContextMenu.vue';
import CreateFolderModal from './FileManager/Modals/CreateFolderModal.vue';
import RenameModal from './FileManager/Modals/RenameModal.vue';
import DeleteModal from './FileManager/Modals/DeleteModal.vue';
import AssignTypeModal from './FileManager/Modals/AssignTypeModal.vue';

import { formatSize, formatDate } from '../utils/formatters';
import { isImage, isPdf, isWord, isExcel, isArchive, onImageError } from '../utils/filetypes';

// Props
const props = defineProps(['resourceName', 'resourceId', 'panel', 'resource']);
const typeOptions = computed(() => props.panel.fields[0].typeOptions || []);

const titolo = computed(() => props.panel.name || 'Gestione File');
// State
const loading = ref(true);
const allItems = ref([]);
const breadcrumbs = ref([]);
const currentPath = ref('');
const pagination = ref(null);
const perPage = ref(15);
const perPageOptions = [5, 15, 30, 50, 100];
const isDragging = ref(false);
const searchQuery = ref('');
const selectedTag = ref('');
const selectedMimetype = ref('');
const showFilterDropdown = ref(false);
const viewMode = ref('grid'); // 'grid' or 'list'

// Modals State
const showCreateFolderModal = ref(false);
const newFolderName = ref('');
const showRenameModal = ref(false);
const newItemName = ref('');
const showDeleteModal = ref(false);
const showAssignTypeModal = ref(false);
const selectedType = ref('offerta'); // Default type
// Context Menu State
const contextMenu = ref({ show: false, x: 0, y: 0, item: null });

// Editing State
const editingItem = ref(null);

// Template Refs
const fileInput = ref(null);

// --- Computed Properties ---

const paginatedItems = computed(() => {
    // Il filtraggio e la paginazione sono ora gestiti dal server
    return allItems.value;
});

const currentViewComponent = computed(() => (viewMode.value === 'grid' ? GridView : ListView));
 
// --- API Communication ---

const getApiParams = (extraParams = {}) => ({
    resourceName: props.resourceName,
    resourceId: props.resourceId,
    path: currentPath.value,
    search: searchQuery.value,
    filter_tag: selectedTag.value,
    filter_mimetype: selectedMimetype.value,
    ...extraParams,
});

const fetchFiles = async () => {
    loading.value = true;
    try {
        const response = await Nova.request().get('/api/rfm/list', {
            params: getApiParams({ page: pagination.value?.currentPage || 1, perPage: perPage.value }),
        });
        allItems.value = response.data.files; // Non è più necessario ordinare qui
        breadcrumbs.value = response.data.breadcrumbs;
        pagination.value = response.data.pagination;
    } catch (error) {
        console.error('[FileManager] Error fetching files:', error);
        Nova.error('Impossibile caricare i file.');
    } finally {
        loading.value = false;
    }
};

const debouncedFetchFiles = useDebounceFn(fetchFiles, 300);

// --- Core Actions ---

const navigateTo = (path) => {
    currentPath.value = path;
    pagination.value.currentPage = 1;
    fetchFiles();
};

const changePage = (page) => {
    if (page < 1 || page > pagination.value.lastPage) return;
    pagination.value.currentPage = page;
    fetchFiles();
};

const handleDoubleClick = async (item) => {
    if (item.type === 'folder') {
        navigateTo(item.path);
    } else {
        try {
            const res = await Nova.request().get(`/api/rfm/generate-temp-link`, {
                params: getApiParams({ path: item.path }),
            });
            window.open(res.data.url, '_blank');
        } catch (error) {
            Nova.error('Impossibile generare il link per il file.');
        }
    }
};

// --- File & Folder Operations ---

const generateUniqueName = (name, existingNames) => {
    let finalName = name;
    let i = 1;
    const dotIndex = name.lastIndexOf('.');
    const baseName = dotIndex !== -1 ? name.substring(0, dotIndex) : name;
    const ext = dotIndex !== -1 ? name.substring(dotIndex) : '';

    while (existingNames.includes(finalName)) {
        finalName = `${baseName} (${i})${ext}`;
        i++;
    }
    return finalName;
};

const createFolder = async () => {
    if (!newFolderName.value) return Nova.error('Il nome della cartella non può essere vuoto.');
    
    const existingFolderNames = allItems.value.filter(i => i.type === 'folder').map(i => i.name);
    const finalName = generateUniqueName(newFolderName.value, existingFolderNames);

    loading.value = true;
    showCreateFolderModal.value = false;
    try {
        await Nova.request().post('/api/rfm/create-folder', getApiParams({ folderName: finalName }));
        Nova.success('Cartella creata con successo!');
        newFolderName.value = '';
        await fetchFiles();
    } catch (error) {
        Nova.error('Errore durante la creazione della cartella.');
    } finally {
        loading.value = false;
    }
};

const renameItem = async (itemToRename = null) => {
    // Usa l'item passato o quello in editing/context menu
    const item = itemToRename || editingItem.value || contextMenu.value.item;
    if (!newItemName.value) return Nova.error('Il nuovo nome non può essere vuoto.');
    if (!item) return;

    // Ottieni i nomi esistenti dello stesso tipo (escludendo quello che stai rinominando)
    const existingNames = allItems.value
        .filter(i => i.type === item.type && i.path !== item.path)
        .map(i => i.name);

    // Usa la funzione generateUniqueName per evitare duplicati
    const finalName = generateUniqueName(newItemName.value, existingNames);

    loading.value = true;
    showRenameModal.value = false;
    editingItem.value = null;

    try {
        await Nova.request().post('/api/rfm/rename', {
            path: item.path,
            newName: finalName,
            resourceName: props.resourceName,
            resourceId: props.resourceId,
        });
        Nova.success('Elemento rinominato con successo!');
        await fetchFiles();
    } catch (error) {
        Nova.error('Errore durante la rinomina.');
    } finally {
        loading.value = false;
    }
};

const deleteItem = async (itemToDelete = null) => {
    // Usa l'item passato o quello dal context menu
    const item = itemToDelete || contextMenu.value.item;
    if (!item) return;

    loading.value = true;
    showDeleteModal.value = false;
    try {
        await Nova.request().post('/api/rfm/delete', {
            path: item.path,
            type: item.type,
            resourceName: props.resourceName,
            resourceId: props.resourceId,
        });
        Nova.success('Elemento eliminato con successo!');
        await fetchFiles();
    } catch (error) {
        Nova.error('Errore durante l\'eliminazione.');
    } finally {
        loading.value = false;
    }
};

const downloadItem = (itemToDownload) => {
    const item = itemToDownload || contextMenu.value.item;
    if (!item || item.type !== 'file') return;
    
    const params = new URLSearchParams(getApiParams({ path: item.path }));
    const url = `/api/rfm/download?${params.toString()}`;
    
    const link = document.createElement('a');
    link.href = url;
    link.setAttribute('download', item.name);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    
    closeContextMenu();
};

async function assignType(type) {
  const item = contextMenu.value.item;
  await Nova.request().post('/api/rfm/assign-type', {
    resourceName: props.resourceName,
    resourceId:   props.resourceId,
    path:         item.path,
    type,
  });
  showAssignTypeModal.value = false;
  fetchFiles(); // ricarica la lista
}


// --- Upload Logic ---

const triggerFileInput = () => fileInput.value.click();
const handleFileUpload = (event) => uploadFiles(event.target.files);
const handleDrop = (event) => {
    isDragging.value = false;
    uploadFiles(event.dataTransfer.files);
};

const uploadFiles = (files) => {
    if (!files.length) return;
    loading.value = true;

    const existingFileNames = allItems.value.filter(item => item.type === 'file').map(item => item.name);

    const uploadPromises = Array.from(files).map(file => {
        const finalName = generateUniqueName(file.name, existingFileNames);
        const fileToUpload = finalName !== file.name ? new File([file], finalName, { type: file.type }) : file;

        const formData = new FormData();
        formData.append('file', fileToUpload);
        Object.entries(getApiParams()).forEach(([key, value]) => formData.append(key, value));
        
        return Nova.request().post('/api/rfm/upload', formData);
    });

    Promise.all(uploadPromises)
        .then(() => {
            Nova.success('File caricati con successo!');
            fetchFiles();
        })
        .catch(error => {
            console.error('[FileManager] Upload error:', error);
            Nova.error('Errore durante il caricamento di uno o più file.');
            loading.value = false;
        });
};

// --- UI Interaction ---

const showContextMenu = (event, item) => {
    contextMenu.value = { show: true, x: event.clientX, y: event.clientY, item };
};

const closeContextMenu = () => {
    if (contextMenu.value.show) {
        contextMenu.value.show = false;
    }
};
function openAssignTypeModal(item) {
  contextMenu.value = { show: true, item };
  // imposta default
  selectedType.value = contextMenu.value.item.tags || (typeOptions.value[0]?.value) || null;
  showAssignTypeModal.value = true;
}

const openRenameModal = (itemToRename) => {
    const item = itemToRename || contextMenu.value.item;
    newItemName.value = item.name;
    showRenameModal.value = true;
    closeContextMenu();
};

const openDeleteModal = (itemToDelete) => {
    contextMenu.value.item = itemToDelete || contextMenu.value.item;
    showDeleteModal.value = true;
    closeContextMenu();
};

const startEditing = (item) => {
    newItemName.value = item.name;
    editingItem.value = item;
};

const cancelEditing = () => {
    editingItem.value = null;
    newItemName.value = '';
};

// --- Watchers & Lifecycle ---

watch(perPage, () => {
    pagination.value.currentPage = 1;
    debouncedFetchFiles();
});

watch(searchQuery, () => {
    pagination.value.currentPage = 1;
    debouncedFetchFiles();
});

watch(selectedTag, () => {
    pagination.value.currentPage = 1;
    debouncedFetchFiles();
});

watch(selectedMimetype, () => {
    pagination.value.currentPage = 1;
    debouncedFetchFiles();
});

onMounted(() => {
    fetchFiles();
    // Chiudi il dropdown se si clicca fuori
    document.addEventListener('click', (event) => {
        const filterDropdown = document.querySelector('[ref="filterDropdown"]');
        if (filterDropdown && !filterDropdown.contains(event.target)) {
            showFilterDropdown.value = false;
        }
    });
});

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
  .form-control {
    background-color: #fff;
    border-color: #d1d5db;
    border-width: 1px;
    border-radius: 0.375rem;
    padding-left: 0.75rem;
    padding-right: 2rem;
    -webkit-appearance: none;
    appearance: none;
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3e%3c/svg%3e");
    background-position: right 0.5rem center;
    background-repeat: no-repeat;
    background-size: 1.5em 1.5em;
}
.dark .form-control {
    background-color: #1f2937;
    border-color: #4b5563;
    color: #d1d5db;
}
</style>

