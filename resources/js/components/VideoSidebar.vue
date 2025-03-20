<script setup>
import { ref, defineProps, defineEmits } from 'vue';
import { Button } from '@/components/ui/button';

const props = defineProps({
    videos: Array,
    deletedVideos: Array
});

const emit = defineEmits(['select', 'delete', 'restore']);

const showTrash = ref(false);
</script>

<template>
    <div class="w-1/4 bg-gray-800 p-4 border-r border-gray-700 overflow-y-auto">
        <div class="flex justify-between items-center">
            <h2 class="text-xl font-bold">Histórico</h2>
            <Button variant="outline" size="sm" @click="showTrash = !showTrash">
                {{ showTrash ? 'Voltar' : 'Lixeira' }}
            </Button>
        </div>

        <div v-if="!showTrash">
            <div v-for="video in videos" :key="video.id"
                class="p-2 mb-2 border border-gray-700 rounded cursor-pointer hover:bg-gray-700 flex justify-between items-center">
                <span @click="emit('select', video)">{{ video.title }}</span>
                <Button variant="ghost" size="sm" @click="emit('delete', video.id)">🗑️</Button>
            </div>
        </div>

        <div v-else>
            <h3 class="text-lg font-bold mb-2">Vídeos Excluídos</h3>
            <div v-for="video in deletedVideos" :key="video.id"
                class="p-2 mb-2 border border-gray-700 rounded cursor-pointer hover:bg-gray-700 flex justify-between items-center">
                <span>{{ video.title }}</span>
                <Button variant="ghost" size="sm" @click="emit('restore', video.id)">♻️</Button>
            </div>
        </div>
    </div>
</template>
