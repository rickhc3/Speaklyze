<script setup lang="ts">
import { ref, onMounted, watch } from 'vue';
import axios from 'axios';
import { Head } from '@inertiajs/vue3';
import '@/echo';
import VideoSidebar from '@/components/VideoSidebar.vue';
import VideoChat from '@/components/VideoChat.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Toaster } from '@/components/ui/sonner';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { toast } from 'vue-sonner';

const videos = ref([]);
const deletedVideos = ref([]);
const newVideoUrl = ref('');
const selectedVideo = ref(null);
const showToast = ref(false);
const isLoadingVideo = ref(false);

const props = defineProps({
    userId: Number
});
const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Videos',
        href: '/videos'
    }
];

function selectVideo(video) {
    isLoadingVideo.value = true;
    // Garante que o DOM atualize antes de mostrar o novo conteúdo
    setTimeout(() => {
        selectedVideo.value = video;
        isLoadingVideo.value = false;
    }, 200); // ajuste esse tempo conforme o necessário
}

function showCustomToast(title: string, message: string, label: string, onClick: () => void) {
    toast(title, {
        description: message,
        action: {
            label: label,
            onClick: onClick
        }
    });
}

async function fetchVideos() {
    const { data } = await axios.get('/api/videos');
    videos.value = data.filter(v => !v.deleted_at);
    deletedVideos.value = data.filter(v => v.deleted_at);
}

async function addVideo() {
    await axios.post('/api/videos', { youtube_url: newVideoUrl.value });
    showCustomToast('📤 Vídeo enviado! 🚀', 'Seu vídeo foi enviado para processamento.', 'Ok', () => {
        showToast.value = false;
    });
    newVideoUrl.value = '';
    fetchVideos();
}

async function deleteVideo(videoId) {
    await axios.delete(`/api/videos/${videoId}`);
    showCustomToast('🗑️ Vídeo removido.', 'O vídeo foi removido com sucesso.', 'Desfazer', () => {
        restoreVideo(videoId);
    });
    fetchVideos();
}

async function restoreVideo(videoId) {
    await axios.post(`/api/videos/${videoId}/restore`);
    showCustomToast('✅ Vídeo restaurado!', 'O vídeo foi restaurado com sucesso.', 'Ok', () => {
        showToast.value = false;
    });
    fetchVideos();
}

onMounted(() => {
    fetchVideos();
    window.Echo.private(`users.${props.userId}`)
        .listen('.video.processed', (data) => {
            console.log('Vídeo processado:', data);
            showCustomToast(`✅ O vídeo "${data.title}" foi processado!`, 'Clique para ver o resultado.', 'Ver', () => {
                //selectedVideo.value = data;
                selectVideo(data);
            });
            fetchVideos();
        })
        .listen('.video.failed', (data) => {
            showCustomToast(`❌ Falha ao processar "${data.title}": ${data.reason}`);
            fetchVideos();
        });
});</script>

<template>
    <Head title="Videos" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <Toaster position="top-center" :expand="true" />

        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <div class="grid auto-rows-min gap-4 md:grid-cols-4">
                <!-- Sidebar (1/4) -->
                <div
                    class="relative col-span-1 overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border h-full">
                    <VideoSidebar
                        :videos="videos"
                        :deletedVideos="deletedVideos"
                        :selectedVideo="selectedVideo"
                        @select="selectVideo"
                        @delete="deleteVideo"
                        @restore="restoreVideo"
                        @refresh="fetchVideos"
                    />
                </div>

                <!-- Conteúdo Principal (3/4) -->
                <div
                    class="relative col-span-3 flex flex-col rounded-xl border border-sidebar-border/70 dark:border-sidebar-border h-full">


                    <div v-if="!selectedVideo" class="p-6">
                        <h1 class="text-2xl font-bold mb-4">Processar Vídeo do YouTube</h1>
                        <div class="flex gap-2 mb-4">
                            <Input v-model="newVideoUrl" placeholder="Insira a URL do YouTube" class="flex-1" />
                            <Button @click="addVideo">Adicionar</Button>
                        </div>
                    </div>

                    <div v-if="isLoadingVideo" class="flex items-center justify-center h-full">
                        <span class="animate-pulse text-gray-500">Carregando vídeo...</span>
                    </div>

                    <VideoChat v-else :video="selectedVideo" @close="selectedVideo = null"
                               @refresh="fetchVideos" />
                </div>
            </div>
        </div>
    </AppLayout>
</template>

