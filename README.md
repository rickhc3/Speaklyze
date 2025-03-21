# 🧠 Speaklyze

**Speaklyze** é uma aplicação web que permite fazer perguntas com base no conteúdo de um vídeo do YouTube. Ela processa automaticamente a transcrição do vídeo, gera um resumo e cria uma interface de chat para perguntas contextuais sobre o conteúdo.

## ✨ Funcionalidades

- Upload de link do YouTube
- Processamento automático de transcrição e resumo
- Interface de chat
- Exibição do vídeo com transcrição sincronizada
- Visualizar e buscar trechos da transcrição
- Integração com filas e serviços Python via Docker

## 🚀 Tecnologias Utilizadas

- **Frontend**: Vue 3 + TypeScript + shadcn-vue
- **Backend**: Laravel 12 (PHP)
- **Filas**: Laravel Queues com Redis
- **Transcrição/Resumo**: Python (via serviços externos Dockerizados)
- **Containerização**: Docker + Laravel Sail
- **Banco de Dados**: MySQL
- **Notificações**: vue-sonner + Laravel Echo

## 📦 Instalação

```bash
git clone https://github.com/rickhc3/Speaklyze.git

cd Speaklyze

# Inicie o ambiente Docker com Laravel Sail
./vendor/bin/sail up -d

# Instale as dependências PHP e JavaScript
./vendor/bin/sail composer install
./vendor/bin/sail npm install && ./vendor/bin/sail npm run dev

# Rode as migrations
./vendor/bin/sail artisan migrate
```

## 🧪 Testando

Após rodar o projeto, acesse:

```
http://localhost
```

Cole um link de vídeo do YouTube, aguarde o processamento, e interaja com o vídeo via chat ou transcrição.

## 🧠 Como funciona?

1. O usuário envia um link de vídeo.
2. O backend envia o vídeo para um container Python para transcrição e resumo (via Whisper, GPT ou equivalente).
3. A transcrição e o resumo são salvos no banco.
4. O usuário pode:
   - Visualizar o vídeo com resumo
   - Fazer perguntas sobre o vídeo
   - Clicar em minutagens da transcrição para navegar no player

## 🗂 Estrutura resumida

```
Speaklyze/
├── resources/
│   └── js/                # Frontend Vue
├── app/
│   └── Http/
│       └── Controllers/   # ChatController, VideoController etc.
├── routes/
│   └── web.php            # Endpoints principais
├── database/
│   └── migrations/        # Estrutura de tabelas
├── public/
│   └── ...
└── README.md
```

## 🧪 Futuras Melhorias

- Upload direto de arquivos de vídeo
- Download da transcrição como .txt/.srt
- Compartilhamento de sessões

## 🧑‍💻 Contribuindo

Contribuições são bem-vindas! Sinta-se à vontade para abrir issues, enviar PRs ou sugerir melhorias.
