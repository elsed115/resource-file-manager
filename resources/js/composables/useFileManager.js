import { ref, onMounted } from 'vue'
import debounce from 'lodash.debounce'

export function useFileManager(resourceName, resourceId) {
  // sub-path iniziale vuoto
  const root        = ref('')
  const defaultRoot = ref('')

  const dirs    = ref([])
  const files   = ref([])
  const loading = ref(false)
  const search  = ref('')
  const filter = ref('') // Aggiunto per il filtro
  const page    = ref(1)
  const perPage = ref(36)

  const fetchAll = async () => {
    loading.value = true
    const { data } = await window.Nova.request().get(
      `/nova-vendor/resource-file-manager/files/${resourceName}/${resourceId}`,
      { 
        params: { 
          path: root.value,
          search: search.value,
          filter: filter.value,
          page: page.value,
          perPage: perPage.value
        } 
      }
    )
    // aggiorna root e dirs/files
    root.value        = data.root
    defaultRoot.value = data.root
    dirs.value  = data.dirs
    files.value = data.files
    loading.value = false
  }

  const basename = path => path.split('/').pop()

  // La logica di filtraggio e paginazione è ora gestita dal server,
  // quindi le computed properties `filtered`, `totalPages`, `pagedDirs`, `pagedFiles`
  // non sono più necessarie e possono essere rimosse o adattate se si vuole
  // mantenere un filtraggio client-side supplementare.
  // Per questo esempio, le rimuoviamo per affidarci al backend.

  const debouncedFetch = debounce(fetchAll, 300)

  function enterDir(dir) {
    // dir = "users/1/sub" → subPath = "sub"
    const sub = dir.replace(`${defaultRoot.value}/`, '')
    root.value = sub
    page.value = 1
    debouncedFetch()
  }

  function goUp() {
    const parts = root.value.split('/')
    parts.pop()
    root.value = parts.join('/')
    page.value = 1
    debouncedFetch()
  }

  onMounted(fetchAll)

  return {
    root,
    defaultRoot,
    dirs,
    files,
    loading,
    search,
    filter, // Esponi il filtro
    page,
    perPage,
    // Rimuovi le computed non più usate
    basename,
    debouncedFetch,
    enterDir,
    goUp,
    fetchAll, // Esponi fetchAll per poterla chiamare direttamente
  }
}
