<script setup>
import { ref, watch, computed, onMounted, nextTick } from 'vue';
import axios from 'axios';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

const props = defineProps({
    video: Object // Vídeo selecionado
});

const emit = defineEmits(['close']);

const messages = ref([]);
const newMessage = ref('');
const sessionId = ref(null);
const chatContainer = ref(null);
const isSending = ref(false); // Bloqueia enquanto aguarda resposta
const messageInput = ref(null); // Referência para a Textarea

const isProcessed = computed(() => props.video?.status === 'completed');

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
function handleKeyDown(event) {
    if (event.key === 'Enter' && !event.shiftKey) {
        event.preventDefault();
        sendMessage();
    }
}

// Envia mensagem para o backend
async function sendMessage() {
    if (!props.video || !newMessage.value.trim() || isSending.value) return;

    isSending.value = true;
    const userMessage = newMessage.value.trim();
    newMessage.value = ''; // Limpa imediatamente a textarea

    messages.value.push({ role: 'user', message: userMessage });

    const tempLoadingMessage = { role: 'assistant', message: '⏳ Processando resposta...' };
    messages.value.push(tempLoadingMessage);

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

        // Remove a mensagem de "Processando resposta..."
        messages.value.pop();
        messages.value.push({ role: 'assistant', message: data.reply });

    } catch (error) {
        messages.value.pop();
        messages.value.push({ role: 'assistant', message: '❌ Erro ao obter resposta. Tente novamente.' });
    }

    isSending.value = false;

    await nextTick();
    scrollToBottom();

    // Aguarda um pequeno delay antes de focar
    setTimeout(() => {
        if (messageInput.value) {
            messageInput.value.focus();
        }
    }, 100);
}

// Renderiza mensagens com formatação básica (negrito, listas, etc.)
function formatMessage(text) {
    return text
        .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>') // Negrito
        .replace(/(\d+)\.\s(.*?)(?=\n|$)/g, '<li>$1. $2</li>') // Listas numeradas
        .replace(/\n/g, '<br>'); // Quebras de linha
}
</script>

<template>
    <Card v-if="video" class="h-full flex flex-col">
        <!-- Título e botão de fechar na mesma linha -->
        <CardHeader class="relative flex justify-between items-center">
            <CardTitle>{{ video.title }}</CardTitle>
            <Button class="absolute right-3" variant="outline" @click="$emit('close')">❌</Button>
        </CardHeader>

        <CardContent class="flex flex-col flex-grow overflow-hidden">
            <!-- Resumo e vídeo lado a lado -->
            <div class="grid grid-cols-4 gap-4 items-start">
                <!-- Vídeo (1/4) -->
                <div class="col-span-1">
                    <div class="relative w-full" style="aspect-ratio: 16 / 9;">
                        <iframe
                            :src="`https://www.youtube.com/embed/${video.video_id}`"
                            class="absolute top-0 left-0 w-full h-full rounded-lg"
                            frameborder="0"
                            allowfullscreen
                        ></iframe>
                    </div>
                </div>

                <!-- Resumo (3/4) -->
                <div class="col-span-3">
                    <h3 class="text-lg font-semibold">Resumo</h3>
                    <p class="mb-4">{{ video.summary }}</p>
                </div>
            </div>

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
                    <Button @click="sendMessage" :disabled="isSending">
                        {{ isSending ? 'Aguardando resposta...' : 'Enviar' }}
                    </Button>
                </div>

                <h3 class="text-lg font-semibold mt-4">Conversas</h3>
                <div ref="chatContainer"
                     class="mt-2 flex flex-col gap-2 max-h-100 overflow-y-auto p-4 border border-gray-700 rounded-lg">
                    <div v-for="msg in messages" :key="msg.message"
                         class="p-2 rounded-lg max-w-3/4"
                         :class="msg.role === 'user' ? 'bg-blue-500 text-white self-end' : 'bg-gray-700 text-white self-start'"
                         v-html="formatMessage(msg.message)">
                    </div>
                </div>
            </template>
            <template v-else>
                <p class="text-center mt-4" v-if="video.status === 'processing'">⏳ Processando vídeo...</p>
                <p class="text-center mt-4" v-else-if="video.status === 'failed_video_processing'">❌📹 Erro ao processar
                    vídeo</p>
                <p class="text-center mt-4" v-else-if="video.status === 'failed_audio_processing'">❌🎵 Erro ao processar
                    áudio</p>
                <p class="text-center mt-4" v-else-if="video.status === 'failed_transcription'">❌📝 Erro ao
                    transcrever</p>
                <p class="text-center mt-4" v-else>❌⚠️ Erro desconhecido</p>
            </template>

        </CardContent>
    </Card>
</template>
