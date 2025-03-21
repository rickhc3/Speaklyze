<script setup>
import { ref, computed, defineProps, defineEmits } from 'vue';
import { Tabs, TabsList, TabsTrigger, TabsContent } from '@/components/ui/tabs';
import { Button } from '@/components/ui/button';

const props = defineProps({
    videos: Array,
    deletedVideos: Array
});

const emit = defineEmits(['select', 'delete', 'restore']);

const activeTab = ref('processing'); // Começa com "Processando"
const showTrash = ref(false); // Exibe ou não a lixeira

// Computed para filtrar vídeos por status
const processingVideos = computed(() => props.videos.filter(v => v.status === 'processing'));
const completedVideos = computed(() => props.videos.filter(v => v.status === 'completed'));
const failedVideos = computed(() => props.videos.filter(v => ['failed_video_processing', 'failed_audio_processing', 'failed_transcription'].includes(v.status)));
const sentVideos = computed(() => props.videos.filter(v => !v.status || v.status === 'sent')); // Status "enviado"
</script>

<template>
    <div class="w-[25%] bg-gray-800 p-4 border-r border-gray-700 overflow-y-auto">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-xl font-bold"> {{ showTrash ? 'Lixeira 🗑️' : 'Vídeos 📺' }} </h2>
            <Button variant="outline" size="sm" @click="showTrash = !showTrash">
                {{ showTrash ? 'Voltar' : 'Lixeira' }}
            </Button>
        </div>

        <!-- Tabs apenas se a Lixeira NÃO estiver ativada -->
        <Tabs v-if="!showTrash" v-model="activeTab" class="w-full">
            <TabsList class="grid grid-cols-3 gap-2">
                <TabsTrigger value="processing">⏳ Processando</TabsTrigger>
                <TabsTrigger value="completed">✅ Concluído</TabsTrigger>
                <TabsTrigger value="failed">❌ Falha</TabsTrigger>
            </TabsList>

            <!-- Processando -->
            <TabsContent value="processing">
                <div v-if="processingVideos.length" class="mt-2">
                    <div v-for="video in processingVideos" :key="video.id"
                         class="p-2 mb-2 border border-gray-700 rounded cursor-pointer hover:bg-gray-700 flex justify-between items-center">
                        <span @click="emit('select', video)">
                            {{ video.title }}
                        </span>
                        <Button variant="ghost" size="sm" @click="emit('delete', video.id)">🗑️</Button>
                    </div>
                </div>
                <p v-else class="text-gray-400 mt-2 text-center">Nenhum vídeo em processamento</p>
            </TabsContent>

            <!-- Concluído -->
            <TabsContent value="completed">
                <div v-if="completedVideos.length" class="mt-2">
                    <div v-for="video in completedVideos" :key="video.id"
                         class="p-2 mb-2 border border-gray-700 rounded cursor-pointer hover:bg-gray-700 flex justify-between items-center">
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
                         class="p-2 mb-2 border border-gray-700 rounded cursor-pointer hover:bg-gray-700 flex justify-between items-center">
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
                </div>
            </div>
            <p v-else class="text-gray-400 text-center">Nenhum vídeo excluído</p>
        </div>
    </div>
</template>
