<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { Progress } from '@/components/ui/progress';
import axios from 'axios';
import '@/echo';
import { Tabs, TabsList, TabsTrigger, TabsContent } from '@/components/ui/tabs';
import { Button } from '@/components/ui/button';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
    AlertDialogTrigger
} from '@/components/ui/alert-dialog';

const props = defineProps({
    videos: Array,
    deletedVideos: Array,
    selectedVideo: Object

});

const emit = defineEmits(['select', 'delete', 'restore']);

const activeTab = ref('processing'); // Começa com "Processando"
const showTrash = ref(false); // Exibe ou não a lixeira

// Computed para filtrar vídeos por status
const processingVideos = computed(() => props.videos.filter(v => v.status === 'processing'));
const completedVideos = computed(() => props.videos.filter(v => v.status === 'completed'));
const failedVideos = computed(() => props.videos.filter(v => ['failed_video_processing', 'failed_audio_processing', 'failed_transcription'].includes(v.status)));
const progressMap = ref({});


onMounted(() => {
    props.videos.forEach(video => {
        if (video.status === 'processing') {
            window.Echo.private(`video.progress.${video.id}`)
                .listen('.progress', (e) => {
                    progressMap.value[video.id] = e.percent
                });

        }
    });
});

onUnmounted(() => {
    props.videos.forEach(video => {
        if (video.status === 'processing') {
            window.Echo.leave(`video.progress.${video.id}`);
        }
    });
});

async function forceDelete(videoId) {
    await axios.delete(`/api/videos/${videoId}/force-delete`);
    emit('refresh');
}

async function emptyTrash() {
    await axios.delete(`/api/videos/trash/empty`);
    emit('refresh');
}
</script>

<template>
    <div class="w-full h-full p-4 border-r border-gray-700 overflow-y-auto">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-xl font-bold"> {{ showTrash ? 'Lixeira 🗑️' : 'Vídeos 📺' }} </h2>
            <div class="flex gap-2 justify-end" v-if="showTrash && deletedVideos.length">
                <!-- Limpar Lixeira -->
                <AlertDialog>
                    <AlertDialogTrigger as-child>
                        <Button variant="destructive" size="sm">🧹 Limpar Lixeira</Button>
                    </AlertDialogTrigger>
                    <AlertDialogContent>
                        <AlertDialogHeader>
                            <AlertDialogTitle>Tem certeza que deseja esvaziar a lixeira?</AlertDialogTitle>
                            <AlertDialogDescription>Essa ação é irreversível. Todos os vídeos excluídos serão
                                removidos
                                permanentemente.
                            </AlertDialogDescription>
                        </AlertDialogHeader>
                        <AlertDialogFooter>
                            <AlertDialogCancel>Cancelar</AlertDialogCancel>
                            <AlertDialogAction @click="emptyTrash">Confirmar</AlertDialogAction>
                        </AlertDialogFooter>
                    </AlertDialogContent>
                </AlertDialog>
            </div>

            <Button variant="outline" size="sm" @click="showTrash = !showTrash">
                {{ showTrash ? 'Voltar' : 'Lixeira' }}
            </Button>
        </div>

        <!-- Tabs apenas se a Lixeira NÃO estiver ativada -->
        <Tabs v-if="!showTrash" v-model="activeTab" class="w-full">
            <TabsList class="grid grid-cols-3 gap-2">
                <TabsTrigger class="text-xs" value="processing">🕒 Fila</TabsTrigger>
                <TabsTrigger class="text-xs" value="completed">✅ Concluído</TabsTrigger>
                <TabsTrigger class="text-xs" value="failed">❌ Falha</TabsTrigger>
            </TabsList>

            <!-- Processando -->
            <TabsContent value="processing">
                <div v-if="processingVideos.length" class="mt-2">
                    <div
                        v-for="video in processingVideos"
                        :key="video.id"
                        class="p-2 mb-2 border border-gray-700 rounded hover:bg-white/5"
                        :class="[
                                    props.selectedVideo?.id === video.id ? 'bg-white/10' : ''
                                  ]"
                    >
                        <!-- Cabeçalho: título e botão excluir -->
                        <div class="flex justify-between items-center cursor-pointer" @click="emit('select', video)">
                            <span class="font-medium">{{ video.title }}</span>
                            <Button variant="ghost" size="sm" @click.stop="emit('delete', video.id)">🗑️</Button>
                        </div>

                        <!-- Barra de progresso -->
                        <div class="w-full mt-2">
                            <Progress :model-value="progressMap[video.id] || 0" class="h-2" />
                            <p class="text-xs text-gray-400 mt-1">{{ progressMap[video.id] || 0 }}%</p>
                        </div>
                    </div>
                </div>
                <p v-else class="text-gray-400 mt-2 text-center">Nenhum vídeo na fila</p>
            </TabsContent>


            <!-- Concluído -->
            <TabsContent value="completed">
                <div v-if="completedVideos.length" class="mt-2">
                    <div v-for="video in completedVideos" :key="video.id"
                         class="p-2 mb-2 border border-gray-700 rounded cursor-pointer hover:bg-white/5 flex justify-between items-center"
                         :class="[
                                      'p-2 mb-2 border border-gray-700 rounded cursor-pointer flex justify-between items-center hover:bg-white/5',
                                      props.selectedVideo?.id === video.id ? 'bg-white/10' : ''
                                  ]">
                        <span @click="emit('select', video)">
                            {{ video.title }}
                        </span>
                        <Button variant="ghost" size="sm" @click="emit('delete', video.id)">🗑️</Button>
                    </div>
                </div>
                <p v-else class="text-gray-400 mt-2 text-center">Nenhum vídeo concluído</p>
            </TabsContent>

            <!-- Falha -->
            <TabsContent value="failed">
                <div v-if="failedVideos.length" class="mt-2">
                    <div v-for="video in failedVideos" :key="video.id"
                         class="p-2 mb-2 border border-gray-700 rounded cursor-pointer hover:bg-white/5 flex justify-between items-center"
                         :class="[
                                      'p-2 mb-2 border border-gray-700 rounded cursor-pointer flex justify-between items-center hover:bg-white/5',
                                      props.selectedVideo?.id === video.id ? 'bg-white/10' : ''
                                    ]">
                        <span @click="emit('select', video)">
                            {{ video.title }}
                        </span>
                        <Button variant="ghost" size="sm" @click="emit('delete', video.id)">🗑️</Button>
                    </div>
                </div>
                <p v-else class="text-gray-400 mt-2 text-center">Nenhum vídeo com falha</p>
            </TabsContent>
        </Tabs>

        <!-- Exibir Lixeira se ativado -->
        <div v-else>
            <div v-if="deletedVideos.length">
                <div v-for="video in deletedVideos" :key="video.id"
                     class="p-2 mb-2 border border-gray-700 rounded cursor-pointer hover:bg-gray-700 flex justify-between items-center">
                    <span>{{ video.title }}</span>
                    <Button variant="ghost" size="sm" @click="emit('restore', video.id)">♻️</Button>
                    <AlertDialog>
                        <AlertDialogTrigger as-child>
                            <Button variant="ghost" size="sm" class="text-red-500">🗑️</Button>
                        </AlertDialogTrigger>
                        <AlertDialogContent>
                            <AlertDialogHeader>
                                <AlertDialogTitle>Excluir definitivamente?</AlertDialogTitle>
                                <AlertDialogDescription>
                                    Esta ação não poderá ser desfeita.
                                </AlertDialogDescription>
                            </AlertDialogHeader>
                            <AlertDialogFooter>
                                <AlertDialogCancel>Cancelar</AlertDialogCancel>
                                <AlertDialogAction @click="forceDelete(video.id)">Excluir</AlertDialogAction>
                            </AlertDialogFooter>
                        </AlertDialogContent>
                    </AlertDialog>
                </div>
            </div>
            <p v-else class="text-gray-400 text-center">Nenhum vídeo excluído</p>
        </div>
    </div>
</template>
