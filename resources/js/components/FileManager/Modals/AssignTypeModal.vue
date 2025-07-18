<template>
    <div
        v-if="show"
        class="modal fixed inset-0 z-[60] px-3 md:px-0 py-3 md:py-6 overflow-x-hidden overflow-y-auto"
        style="background-color: rgba(0, 0, 0, 0.5);"
        role="dialog"
        @click.self="$emit('close')"
    >
        <div class="relative mx-auto z-20 max-w-2xl text-left">
            <form @submit.prevent="handleAssign" autocomplete="off" class="bg-white dark:bg-gray-800 rounded-lg shadow-lg overflow-hidden">
                <div class="space-y-6">
                    <h3 class="uppercase tracking-wide font-bold text-xs border-b border-gray-100 dark:border-gray-700 py-4 px-8">Assegna Tipo</h3>
                    <div class="px-8">
                        <label for="type" class="block text-sm font-medium text-gray-700 dark:text-gray-200">Tipo</label>
                        <select
                            id="type"
                            v-model="selectedType"
                            class="mt-1 block w-full form-control form-input form-input-bordered"
                            required
                        >
                            <!-- Opzioni dinamiche -->
                            <option v-for="(label, key) in typeOptions" :key="key" :value="key">{{ label }}</option>
                        </select>
                    </div>
                </div>
                <div class="bg-gray-100 dark:bg-gray-700 px-6 py-3 flex" style="margin-top:30px">
                    <div class="flex items-center ml-auto">
                        <button type="button" @click="$emit('close')" class="ml-auto mr-3 appearance-none bg-transparent font-bold text-gray-400 hover:text-gray-300 active:text-gray-500 dark:text-gray-500 dark:hover:text-gray-400 dark:active:text-gray-600 dark:hover:bg-gray-800 cursor-pointer rounded text-sm font-bold focus:outline-none focus:ring ring-primary-200 dark:ring-gray-600 inline-flex items-center justify-center h-9 px-3">
                            Annulla
                        </button>
                        <button type="submit" class="border text-left appearance-none cursor-pointer rounded text-sm font-bold focus:outline-none focus:ring ring-primary-200 dark:ring-gray-600 relative disabled:cursor-not-allowed inline-flex items-center justify-center shadow h-9 px-3 bg-primary-500 border-primary-500 hover:[&:not(:disabled)]:bg-primary-400 hover:[&:not(:disabled)]:border-primary-400 text-white dark:text-gray-900">
                            Assegna Tipo
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</template>

<script setup>
import { ref } from 'vue';

// Prop per ricevere le opzioni dinamiche dei tipi
const props = defineProps({
    show: Boolean,
    typeOptions: Object // Riceve un oggetto con le opzioni dei tipi
});

const emit = defineEmits(['close', 'assign']);

const selectedType = ref(Object.keys(props.typeOptions)[0] || ''); // Imposta un tipo predefinito (primo nella lista)

const handleAssign = () => {
    emit('assign', selectedType.value);
    emit('close');
};
</script>

<style scoped>
/* Modal styles */
.modal {
    z-index: 50;
}
</style>
