<script setup lang="ts">
import { ref, watch, computed, onMounted, nextTick } from 'vue';
import axios from 'axios';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogTrigger,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogDescription,
    DialogFooter,
    DialogClose
} from '@/components/ui/dialog';
import { toast } from 'vue-sonner';
import { Toaster } from '@/components/ui/sonner';
import { marked } from 'marked';

const props = defineProps({
    video: Object // Vídeo selecionado
});

const searchTerm = ref('');

const filteredTranscription = computed(() => {
    const term = searchTerm.value.toLowerCase();

    // Verifica se props.video e transcription existem
    if (!props.video || !props.video.transcription) return [];

    return parseTranscription(props.video.transcription).filter((line) =>
        line.text.toLowerCase().includes(term)
    );
});

const emit = defineEmits(['close', 'refresh']);
const messages = ref([]);
const newMessage = ref('');
const sessionId = ref(null);
const chatContainer = ref(null);
const isSending = ref(false);
const messageInput = ref(null);
const isProcessed = computed(() => props.video?.status === 'completed');
const isTyping = ref(false);

// Carregar mensagens da conversa
async function fetchMessages() {
    if (!props.video || !isProcessed.value) return;

    try {
        const { data } = await axios.get(`/api/chat/${props.video.id}`);
        messages.value = data.messages;
        sessionId.value = data.session_id;
        await nextTick();
        scrollToBottom();
    } catch (error) {
        console.error('Erro ao buscar mensagens:', error);
    }
}

// Rola o chat para a última mensagem
function scrollToBottom() {
    if (chatContainer.value) {
        chatContainer.value.scrollTop = chatContainer.value.scrollHeight;
    }
}

// Observa mudança de vídeo e carrega mensagens
watch(() => props.video, (newVideo) => {
    if (newVideo) {
        fetchMessages();
    }
});

onMounted(() => {
    if (props.video) {
        fetchMessages();
    }
});

// Envia mensagem ao pressionar Enter
function handleKeyDown(event: KeyboardEvent) {
    if (event.key === 'Enter' && !event.shiftKey) {
        event.preventDefault();
        sendMessage();
    }
}

// Envia mensagem para o backend
async function sendMessage() {
    if (!props.video || !newMessage.value.trim() || isSending.value || isTyping.value) return;

    isSending.value = true;
    const userMessage = newMessage.value.trim();
    newMessage.value = '';

    messages.value.push({ role: 'user', message: userMessage });
    messages.value.push({ role: 'assistant', message: '' }); // Começa vazio

    await nextTick();
    scrollToBottom();

    try {
        const { data } = await axios.post(`/api/chat/${props.video.id}`, {
            message: userMessage,
            session_id: sessionId.value
        });

        if (!sessionId.value) {
            sessionId.value = data.session_id;
        }

        // Mostra a resposta como se estivesse sendo digitada
        const fullText = data.reply;
        let currentText = '';
        let index = 0;

        isTyping.value = true;

        const typingInterval = setInterval(async () => {
            if (index < fullText.length) {
                currentText += fullText[index];
                messages.value[messages.value.length - 1].message = currentText;
                index++;

                await nextTick(); // aguarda o DOM ser atualizado
                scrollToBottom(); // então rola até o fim
            } else {
                clearInterval(typingInterval);
                isTyping.value = false; // ✅ Libera o envio após terminar
            }
        }, 5);

    } catch (error) {
        messages.value.pop(); // remove resposta vazia
        messages.value.push({ role: 'assistant', message: '❌ Erro ao obter resposta. Tente novamente.' });
    } finally {
        isSending.value = false;
    }

    isSending.value = false;

    await nextTick();
    scrollToBottom();

    setTimeout(() => {
        messageInput.value?.focus();
    }, 100);
}


async function retryVideo(videoId: number) {
    try {
        await axios.post(`/api/videos/${videoId}/retry`);
        showCustomToast('✅ O vídeo será reprocessado!', 'Aguarde alguns instantes.', 'Ok', () => {
            emit('close');
        });
        emit('refresh');
    } catch (error) {
        showCustomToast('❌ Não foi possível reprocessar o vídeo.', 'Tente novamente mais tarde.', 'Ok', () => {
            emit('close');
        });
        console.error(error);
    }
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

const transcriptPlayer = ref<HTMLIFrameElement | null>(null);

function jumpToTime(seconds: number) {
    if (transcriptPlayer.value?.contentWindow) {
        transcriptPlayer.value.contentWindow.postMessage(
            JSON.stringify({
                event: 'command',
                func: 'seekTo',
                args: [seconds, true]
            }),
            '*'
        );
    }
}

function parseTranscription(transcription: string) {
    const regex = /\[(\d{2}):(\d{2})\.\d{3} --> \d{2}:\d{2}\.\d{3}]\s+(.+)/g;
    const lines: { time: number; text: string }[] = [];
    let match;
    while ((match = regex.exec(transcription)) !== null) {
        const minutes = parseInt(match[1]);
        const seconds = parseInt(match[2]);
        const totalSeconds = minutes * 60 + seconds;
        lines.push({ time: totalSeconds, text: match[3] });
    }
    return lines;
}

// Renderiza mensagens com formatação básica (negrito, listas, etc.)
function formatMessage(text: string): string {
    return marked.parse(text);
}
</script>

<template>
    <Toaster position="top-center" :expand="true" />
    <Card v-if="video" class="h-full flex flex-col">
        <!-- Título e botão de fechar na mesma linha -->
        <CardHeader class="relative flex justify-between items-center">
            <CardTitle>{{ video.title }}</CardTitle>
            <Button class="absolute right-3" variant="outline" @click="$emit('close')">❌</Button>
        </CardHeader>

        <CardContent class="flex flex-col flex-grow overflow-hidden">
            <!-- Resumo e vídeo lado a lado -->
            <div class="grid grid-cols-4 gap-4 items-start" v-if="isProcessed">
                <!-- Vídeo (1/4) -->
                <div class="col-span-1">
                    <div class="relative w-full" style="aspect-ratio: 16 / 9;">
                        <iframe
                            ref="transcriptPlayer"
                            :src="`https://www.youtube.com/embed/${video.video_id}?enablejsapi=1`"
                            class="absolute top-0 left-0 w-full h-full rounded-lg"
                            frameborder="0"
                            allowfullscreen
                        ></iframe>
                    </div>
                    <Dialog>
                        <DialogTrigger as-child>
                            <Button variant="secondary" class="mt-2 w-full">📝 Ver Transcrição</Button>
                        </DialogTrigger>
                        <DialogContent class="sm:max-w-[1425px] max-h-[90dvh] p-0 overflow-hidden">
                            <DialogHeader class="p-6 pb-0">
                                <DialogTitle>Transcrição Completa</DialogTitle>
                                <DialogDescription>
                                    Abaixo está a transcrição do vídeo. Clique nas minutagens para pular.
                                </DialogDescription>
                            </DialogHeader>

                            <!-- Conteúdo lado a lado -->
                            <div class="flex gap-4 p-6 overflow-auto max-h-[65vh]">
                                <!-- Vídeo à esquerda -->
                                <div class="w-1/2">
                                    <div class="relative w-full" style="aspect-ratio: 16 / 9;">
                                        <iframe
                                            ref="transcriptPlayer"
                                            :src="`https://www.youtube.com/embed/${video.video_id}?enablejsapi=1`"
                                            class="absolute top-0 left-0 w-full h-full rounded-lg"
                                            frameborder="0"
                                            allowfullscreen
                                        ></iframe>
                                    </div>
                                </div>


                                <!-- Transcrição à direita -->
                                <div class="w-1/2 overflow-y-auto pr-2">
                                    <input
                                        v-model="searchTerm"
                                        type="text"
                                        placeholder="Buscar na transcrição..."
                                        class="mb-4 w-full p-2 border rounded bg-background text-foreground"
                                    />
                                    <div v-if="video.transcription">
                                        <div
                                            v-for="(line, i) in filteredTranscription"
                                            :key="i"
                                            class="mb-2"
                                        >
                                            <button
                                                class="text-blue-500 hover:underline"
                                                @click="jumpToTime(line.time)"
                                            >
                                                {{ new Date(line.time * 1000).toISOString().substr(14, 5) }}
                                            </button>
                                            <span class="ml-2">{{ line.text }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <DialogFooter class="p-4 pt-0">
                                <DialogClose as-child>
                                    <Button variant="outline">Fechar</Button>
                                </DialogClose>
                            </DialogFooter>
                        </DialogContent>
                    </Dialog>
                </div>

                <!-- Resumo (3/4) -->
                <div class="col-span-3">
                    <h3 class="text-lg font-semibold">Resumo</h3>
                    <p class="mb-4">{{ video.summary }}</p>
                </div>
            </div>

            <template v-else>
                <div v-if="video.status === 'processing'">
                    <p class="text-center mt-4">⏳ Processando vídeo...</p>
                </div>

                <div v-else>
                    <p class="text-center mt-4" v-if="video.status === 'failed_video_processing'">❌📹 Erro ao processar
                        vídeo</p>
                    <p class="text-center mt-4" v-else-if="video.status === 'failed_audio_processing'">❌🎵 Erro ao
                        processar áudio</p>
                    <p class="text-center mt-4" v-else-if="video.status === 'failed_transcription'">❌📝 Erro ao
                        transcrever</p>
                    <p class="text-center mt-4" v-else>❌⚠️ Erro desconhecido</p>

                    <!-- Botão visível apenas para status de erro -->
                    <div class="text-center mt-2" v-if="video.status.startsWith('failed')">
                        <Button @click="retryVideo(video.id)">
                            🔁 Tentar novamente
                        </Button>
                    </div>
                </div>
            </template>

            <!-- Exibe chat APENAS se o vídeo estiver processado -->
            <template v-if="isProcessed">
                <h3 class="text-lg font-semibold mt-4">Perguntar algo</h3>
                <div class="flex flex-col gap-2">
                    <Textarea
                        ref="messageInput"
                        v-model="newMessage"
                        placeholder="Digite sua pergunta (Shift + Enter para nova linha)"
                        rows="3"
                        @keydown="handleKeyDown"
                        :disabled="isSending"
                        class="resize-none"
                    />
                    <Button @click="sendMessage" :disabled="isSending || isTyping">
                        {{ isSending || isTyping ? 'Aguardando resposta...' : 'Enviar' }}
                    </Button>
                </div>

                <h3 class="text-lg font-semibold mt-4">Conversas</h3>
                <div ref="chatContainer"
                     class="mt-2 flex flex-col gap-2 max-h-100 overflow-y-auto p-4 border border-gray-700 rounded-lg scroll-smooth">
                    <div v-for="msg in messages" :key="msg.message"
                         class="p-2 rounded-lg max-w-3/4"
                         :class="msg.role === 'user' ? 'bg-blue-500 text-white self-end' : 'bg-gray-700 text-white self-start'"
                         v-html="formatMessage(msg.message)">
                    </div>
                </div>
            </template>

        </CardContent>
    </Card>
</template>

<style scoped>
.DialogContent {
    min-width: 300px;
    background: white;
    padding: 30px;
    border-radius: 4px;
}
</style>
