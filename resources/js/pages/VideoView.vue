<script setup>
import { ref, onMounted, watch } from 'vue';
import axios from 'axios';
import VideoSidebar from '@/components/VideoSidebar.vue';
import VideoChat from '@/components/VideoChat.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import '@/echo';

const videos = ref([]);
const deletedVideos = ref([]);
const newVideoUrl = ref('');
const selectedVideo = ref(null);
const toastMessage = ref(null);
const showToast = ref(false);

function showCustomToast(message) {
    toastMessage.value = message;
    showToast.value = true;
    setTimeout(() => showToast.value = false, 3000); // Esconde após 3s
}

async function fetchVideos() {
    const { data } = await axios.get('/api/videos');
    videos.value = data.filter(v => !v.deleted_at);
    deletedVideos.value = data.filter(v => v.deleted_at);
}

async function addVideo() {
    await axios.post('/api/videos', { youtube_url: newVideoUrl.value });
    showCustomToast("📤 Vídeo enviado! Processando...");
    newVideoUrl.value = '';
    fetchVideos();
}

async function deleteVideo(videoId) {
    await axios.delete(`/api/videos/${videoId}`);
    showCustomToast("🗑️ Vídeo removido.");
    fetchVideos();
}

async function restoreVideo(videoId) {
    await axios.post(`/api/videos/${videoId}/restore`);
    showCustomToast("✅ Vídeo restaurado!");
    fetchVideos();
}

// Escuta eventos quando um vídeo for selecionado
watch(selectedVideo, (newVideo) => {
    if (newVideo) {
        console.log(`Escutando eventos do vídeo ${newVideo.id}`);

        window.Echo.private(`videos.${newVideo.id}`)
            .listen('.video.processed', (data) => {
                console.log(`Vídeo ${data.id} foi processado`);

                // Atualiza os dados do vídeo
                selectedVideo.value.title = data.title;
                selectedVideo.value.summary = data.summary;

                // Atualiza a sidebar automaticamente
                fetchVideos();

                // Mostra notificação
                showCustomToast(`🎉 O vídeo "${data.title}" foi processado!`);
            });
    }
});

onMounted(fetchVideos);
</script>

<template>
    <div class="flex h-screen bg-gray-900 text-white">
        <!-- Sidebar fixa -->
        <VideoSidebar
            :videos="videos"
            :deletedVideos="deletedVideos"
            @select="selectedVideo = $event"
            @delete="deleteVideo"
            @restore="restoreVideo"
        />

        <!-- Conteúdo Principal -->
        <div class="w-[75%] p-6 flex flex-col h-screen overflow-hidden">
            <div v-if="!selectedVideo">
                <h1 class="text-2xl font-bold mb-4">Processar Vídeo do YouTube</h1>
                <div class="flex gap-2 mb-4">
                    <Input v-model="newVideoUrl" placeholder="Insira a URL do YouTube" class="flex-1" />
                    <Button @click="addVideo">Adicionar</Button>
                </div>
            </div>

            <VideoChat v-if="selectedVideo" :video="selectedVideo" @close="selectedVideo = null" />
        </div>

        <!-- Toast -->
        <div v-if="showToast" class="fixed bottom-4 right-4 bg-gray-800 text-white p-3 rounded-lg shadow-lg">
            {{ toastMessage }}
        </div>
    </div>
</template>
