<script setup>
import { ref, onMounted } from 'vue';
import axios from 'axios';
import VideoSidebar from '@/components/VideoSidebar.vue';
import VideoChat from '@/components/VideoChat.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

const videos = ref([]);
const deletedVideos = ref([]);
const newVideoUrl = ref('');
const selectedVideo = ref(null);

async function fetchVideos() {
    const { data } = await axios.get('/api/videos');
    videos.value = data.filter(v => !v.deleted_at);
    deletedVideos.value = data.filter(v => v.deleted_at);
}

async function addVideo() {
    await axios.post('/api/videos', { youtube_url: newVideoUrl.value });
    newVideoUrl.value = '';
    fetchVideos();
}

async function deleteVideo(videoId) {
    await axios.delete(`/api/videos/${videoId}`);
    fetchVideos();
}

async function restoreVideo(videoId) {
    await axios.post(`/api/videos/${videoId}/restore`);
    fetchVideos();
}

onMounted(fetchVideos);
</script>

<template>
    <div class="flex h-screen bg-gray-900 text-white">
        <!-- Sidebar -->
        <VideoSidebar :videos="videos" :deletedVideos="deletedVideos" 
            @select="selectedVideo = $event"
            @delete="deleteVideo"
            @restore="restoreVideo" />

        <!-- Conteúdo Principal -->
        <div class="w-3/4 p-6">
            <div v-if="!selectedVideo">
                <h1 class="text-2xl font-bold mb-4">Processar Vídeo do YouTube</h1>
                <div class="flex gap-2 mb-4">
                    <Input v-model="newVideoUrl" placeholder="Insira a URL do YouTube" class="flex-1" />
                    <Button @click="addVideo">Adicionar</Button>
                </div>
            </div>

            <VideoChat v-if="selectedVideo" :video="selectedVideo" @close="selectedVideo = null" />
        </div>
    </div>
</template>
