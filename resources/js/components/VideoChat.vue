<script setup>
import { ref, watch } from 'vue';
import axios from 'axios';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

const props = defineProps({
    video: Object, // Vídeo selecionado
});

const emit = defineEmits(['close']);

const messages = ref([]);
const newMessage = ref('');
const sessionId = ref(null); // ID da sessão para manter o contexto

// Função para carregar as mensagens da conversa
async function fetchMessages() {
    if (!props.video) return;

    try {
        const { data } = await axios.get(`/api/chat/${props.video.id}`);
        messages.value = data.messages; // Mensagens armazenadas no banco
        sessionId.value = data.session_id; // Recupera a session ID
    } catch (error) {
        console.error("Erro ao buscar mensagens:", error);
    }
}

// Carregar resumo quando o vídeo for selecionado e buscar histórico do chat
watch(() => props.video, (newVideo) => {
    if (newVideo) {
        fetchMessages();
    }
});

async function sendMessage() {
    if (!props.video) return;

    const { data } = await axios.post(`/api/chat/${props.video.id}`, {
        message: newMessage.value,
        session_id: sessionId.value
    });

    if (!sessionId.value) {
        sessionId.value = data.session_id; // Salva o ID da sessão se for a primeira mensagem
    }

    messages.value.push({ role: 'user', message: newMessage.value });
    messages.value.push({ role: 'assistant', message: data.reply });
    newMessage.value = '';
}
</script>

<template>
    <Card v-if="video">
        <CardHeader class="flex justify-between items-center">
            <CardTitle>{{ video.title }}</CardTitle>
            <Button variant="outline" @click="$emit('close')">❌</Button>
        </CardHeader>
        <CardContent>
            <!-- Embed do vídeo -->
            <div class="mb-4">
                <iframe :src="`https://www.youtube.com/embed/${video.video_id}`"
                    class="w-full h-64 rounded-lg" frameborder="0" allowfullscreen></iframe>
            </div>

            <h3 class="text-lg font-semibold">Resumo</h3>
            <p class="mb-4">{{ video.summary }}</p>

            <h3 class="text-lg font-semibold">Perguntar algo</h3>
            <div class="flex gap-2">
                <Input v-model="newMessage" placeholder="Digite sua pergunta" class="flex-1" />
                <Button @click="sendMessage">Enviar</Button>
            </div>

            <h3 class="text-lg font-semibold mt-4">Conversas</h3>
            <ul class="mt-2">
                <li v-for="msg in messages" :key="msg.message" class="mb-2">
                    <strong>{{ msg.role }}:</strong> {{ msg.message }}
                </li>
            </ul>
        </CardContent>
    </Card>
</template>
