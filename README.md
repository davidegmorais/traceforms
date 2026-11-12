# TraceForms

Plataforma de formulários, pesquisas e inscrições em **Laravel 12**, **Bulma 1** e **Alpine.js**.

O diferencial é a **coleta de dados**: cada resposta chega com a campanha, o anúncio, a página de origem, o dispositivo, a localização e o caminho que a pessoa percorreu. Os relatórios mostram o que converte e em qual pergunta as pessoas desistem.

---

## Sumário

1. [Requisitos](#requisitos)
2. [Instalação no Ubuntu](#instalação-no-ubuntu)
3. [Primeiro acesso](#primeiro-acesso)
4. [Atualizar para uma versão nova](#atualizar-para-uma-versão-nova)
5. [Funcionalidades](#funcionalidades)
6. [Níveis de acesso](#níveis-de-acesso)
7. [Administração da plataforma](#administração-da-plataforma) — inclui [cadastro aberto ou fechado](#cadastro-aberto-ou-fechado) e [como se tornar administrador geral](#como-se-tornar-administrador-geral)
8. [Integrações](#integrações)
9. [Colocar em produção](#colocar-em-produção)
10. [Arquitetura](#arquitetura)
11. [Solução de problemas](#solução-de-problemas)

---

## Requisitos

- PHP **8.2 ou superior** com as extensões `pdo_mysql` (ou `pdo_sqlite`), `mbstring`, `xml`, `curl`, `zip`, `intl` e `gd`
- Composer 2
- MySQL 8 / MariaDB 10.6+, PostgreSQL 14+ ou SQLite (para testes)

Não há etapa de build de front-end. Bulma, Alpine.js, Chart.js, SortableJS, Font Awesome e as fontes (Bricolage Grotesque e Instrument Sans) já estão em `public/vendor` e são servidos pelo próprio servidor, sem CDN.

---

## Instalação no Ubuntu

### 1. Pacotes

```bash
sudo apt update
sudo apt install -y php php-cli php-mysql php-sqlite3 php-mbstring php-xml php-curl php-zip php-intl php-gd unzip composer mysql-server
php -v   # precisa ser 8.2 ou superior
```

### 2. Banco de dados MySQL

```bash
sudo systemctl enable --now mysql
sudo mysql
```

```sql
CREATE DATABASE traceforms CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'traceforms'@'localhost' IDENTIFIED BY 'TroqueEstaSenha123!';
GRANT ALL PRIVILEGES ON traceforms.* TO 'traceforms'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

### 3. Projeto

```bash
mkdir -p ~/Development && cd ~/Development
unzip traceforms.zip && mv traceforms TraceForms && cd TraceForms
composer install
cp .env.example .env
php artisan key:generate
```

### 4. Configurar o `.env`

```env
APP_NAME=TraceForms
APP_URL=http://localhost:8000
APP_LOCALE=pt_BR

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=traceforms
DB_USERNAME=traceforms
DB_PASSWORD=TroqueEstaSenha123!
```

No `.env.example`, as linhas `DB_HOST` … `DB_PASSWORD` vêm comentadas com `#`. Tire o `#` delas.

> Para testar sem MySQL, mantenha `DB_CONNECTION=sqlite` e rode `touch database/database.sqlite`.

### 5. Tabelas, dados de demonstração e servidor

```bash
php artisan migrate --seed
php artisan serve
```

Abra `http://localhost:8000`.

---

## Primeiro acesso

O `--seed` cria uma empresa de demonstração ("Acme Marketing") com cerca de 9 mil visitas e 3 mil respostas em 90 dias, além de uma segunda empresa ("Cliente Beta"). Todas as contas usam a senha **`traceforms123`**:

| E-mail | Papel | Para testar |
|---|---|---|
| `demo@traceforms.test` | Proprietário + administrador da plataforma | Tudo, inclusive `/admin` |
| `admin@traceforms.test` | Administrador | Gestão de equipe; participa de duas empresas |
| `editor@traceforms.test` | Editor | Cria e edita formulários |
| `analista@traceforms.test` | Analista | Vê e exporta respostas, sem editar |
| `leitor@traceforms.test` | Leitor | Só painéis, restrito a um formulário |

Sem o `--seed`, crie sua conta de administrador pelo terminal. O comando pergunta nome, empresa e senha:

```bash
php artisan traceforms:make-admin seu@email.com
```

O cadastro público em `/register` vem **fechado**. Os outros usuários são criados por você em *Administração → Usuários → Novo usuário* ou entram por convite de uma empresa. Veja [Cadastro aberto ou fechado](#cadastro-aberto-ou-fechado).

---

## Atualizar para uma versão nova

O zip tem uma pasta `traceforms/` dentro. Para extrair por cima do projeto com `unzip`, crie um atalho com esse nome apontando para a sua pasta:

```bash
cd ~/Development
ln -s TraceForms traceforms
unzip -o ~/Downloads/traceforms.zip
rm traceforms                  # apaga só o atalho

cd TraceForms
composer install
php artisan optimize:clear
php artisan migrate            # aplica só as tabelas e colunas novas
```

O `.env`, a pasta `vendor/`, o banco e os arquivos enviados não estão no zip, então não são sobrescritos.

Para recomeçar do zero com os dados de demonstração, use `php artisan migrate:fresh --seed` no lugar do `migrate`. Esse comando **apaga todas as tabelas**.

---

## Funcionalidades

### Quatro formatos de formulário

O formato é escolhido ao criar o formulário e pode ser trocado a qualquer momento no **Construtor → aba Design → Formato**, sem refazer as perguntas.

| Formato | Como é |
|---|---|
| **Clássico com painel** | Painel lateral fixo na cor do tema, com título e progresso. As perguntas ficam ao lado, ao longo de uma linha que se preenche conforme as respostas. |
| **Página única** | Cabeçalho e cada pergunta em cartões, numa coluna central. |
| **Uma por vez** | Uma pergunta por tela em tela cheia, letras grandes, campos sublinhados, transição vertical (sobe ao avançar e desce ao voltar), atalhos de teclado (A/B/C, 0–9, S/N, Enter), barra de progresso embaixo e imagem de capa opcional na abertura. |
| **Chat** | As perguntas chegam como mensagens, com "digitando…", e as respostas viram balões à direita, como num app de conversa. É possível tocar numa resposta para alterá-la. |

Todos os formatos funcionam no celular e podem ser incorporados em outros sites.

### 26 tipos de pergunta

- **Texto:** texto curto, texto longo
- **Contato:** e-mail, telefone, site
- **Brasil:** CPF/CNPJ (máscara e validação dos dígitos), endereço por CEP (preenchido automaticamente pelo ViaCEP, com digitação manual se o CEP não for encontrado)
- **Números e datas:** número, valor em reais, controle deslizante, data, hora
- **Escolha:** escolha única, múltipla escolha (com limite e opção "Outro"), lista suspensa, sim/não, ordenar por preferência, matriz (grade de linhas × colunas)
- **Opinião:** estrelas, NPS (0–10), escala de opinião
- **Arquivos:** envio de arquivo (tipos, tamanho e quantidade definidos por pergunta), assinatura desenhada na tela
- **Outros:** consentimento (LGPD), seção de texto, campo oculto (preenchido por parâmetro da URL)

### Construtor

- Arrastar e soltar, duplicar, reordenar e colar opções em massa
- **Lógica condicional:** mostrar uma pergunta só se as condições forem atendidas (é igual, diferente, contém, maior, menor, vazio, preenchido; todas ou qualquer condição). A regra também é validada no servidor.
- Temas prontos, cores, fonte, arredondamento, logotipo e imagem de capa
- Salvamento automático (e `Ctrl+S`)
- 8 modelos prontos: captura de leads, pesquisa NPS, satisfação do cliente, inscrição em evento, candidatura a vaga, pesquisa de clima, fale conosco e em branco

### O que é coletado em cada visita

| Grupo | Dados |
|---|---|
| Campanha | `utm_source`, `utm_medium`, `utm_campaign`, `utm_term`, `utm_content`, `utm_id` |
| Anúncios | IDs de clique: `gclid`, `gbraid`, `wbraid`, `dclid` (Google), `fbclid` (Meta), `ttclid` (TikTok), `msclkid` (Microsoft), `li_fat_id` (LinkedIn), `twclid` (X), `sccid` (Snapchat), `epik` (Pinterest), `rdt_cid` (Reddit), `kwai_clid`, `yclid`, `irclickid` |
| Atribuição | Primeiro toque (90 dias) × último toque; canal classificado no padrão do GA4 (pesquisa paga/orgânica, social pago/orgânico, e-mail, display, afiliados, SMS, QR, referência, direto) |
| Origem | Site de referência, página de entrada, página onde o formulário está incorporado, link rastreado usado |
| Dispositivo | Tipo, marca, modelo, sistema, navegador, tela, densidade de pixels, toque, núcleos, memória, conexão, modo escuro |
| Local e idioma | País, região e cidade (via CDN), IP (opcional e com anonimização), idioma, fuso horário |
| Visitante | ID persistente, novo × recorrente, número da visita, todas as visitas da mesma pessoa |
| Comportamento | Tempo até começar, tempo total e ativo, tempo/erros/edições por pergunta, onde parou, trocas de aba, uso de colar |
| Respostas parciais | O que a pessoa digitou antes de desistir (pode ser desligado por formulário) |
| Antispam | Campo-armadilha, tempo mínimo de envio, detecção de robôs |

### Relatórios

- **Visão geral da conta:** indicadores com comparação ao período anterior, evolução diária, canais, formulários em destaque e respostas recentes
- **Por formulário, em cinco abas** — com filtros por período, canal, origem, mídia, campanha, dispositivo e país (clicar numa linha filtra o relatório inteiro):
  - *Visão geral:* visitas, visitantes únicos, inícios, respostas, taxas de início, conclusão e conversão, tempo médio
  - *Aquisição e UTMs:* canais, origem/mídia, campanhas, criativos, termos, links rastreados, IDs de clique, referências, páginas de entrada, primeiro × último toque
  - *Audiência e dispositivos:* tipo, sistema, navegador, marca, modelo, tela, conexão, novos × recorrentes, países, regiões, cidades, idiomas
  - *Comportamento e funil:* funil por pergunta (alcance, abandono, tempo, erros), mapa de calor por dia e hora, tempo até enviar
  - *Respostas por pergunta:* gráficos de escolhas, médias, NPS, matriz como mapa de calor, ranking por posição média, valores em reais
- **Campanhas e UTMs:** todas as campanhas de todos os formulários, com a matriz campanha × formulário

### Respostas

Caixa de entrada com busca e filtros, favoritas, spam, notas internas, detalhe com origem, dispositivo, engajamento e jornada, arquivos com miniatura e download, respostas parciais e **exportação CSV** (pronta para o Excel) com todas as colunas de rastreamento.

### Menu lateral

O menu pode ser recolhido para mostrar só os ícones (botão *Recolher menu*, no pé da barra). Com ele recolhido, o nome de cada item aparece ao passar o mouse. A escolha fica salva no navegador.

### Perfil

Foto (cortada em quadrado), nome, e-mail, troca de senha e lista de dispositivos conectados, com a opção de sair de todos os outros.

---

## Níveis de acesso

Uma pessoa pode participar de várias **empresas (contas)**, com um papel diferente em cada uma.

| Permissão | Leitor | Analista | Editor | Administrador | Proprietário |
|---|:-:|:-:|:-:|:-:|:-:|
| Ver painéis e relatórios | ✓ | ✓ | ✓ | ✓ | ✓ |
| Ver respostas individuais e exportar CSV | | ✓ | ✓ | ✓ | ✓ |
| Criar, editar e publicar formulários; gerenciar respostas e links | | | ✓ | ✓ | ✓ |
| Excluir formulários, gerenciar equipe e configurações, ver auditoria | | | | ✓ | ✓ |
| Excluir a conta e transferir a propriedade | | | | | ✓ |

- Leitor, Analista e Editor podem ser **restritos a formulários específicos** — útil para agências darem acesso a clientes.
- Ninguém altera alguém do mesmo nível ou acima; só o Proprietário nomeia Administradores.
- Convites por e-mail (válidos por 7 dias) e histórico de alterações (auditoria).

---

## Administração da plataforma

Área separada em **`/admin`**, com visual próprio (barra escura), para quem administra o TraceForms como um todo. Não tem relação com o papel "Administrador" dentro de uma empresa.

- **Acesso:** item **Administração** na barra lateral (seção "Plataforma"), que só aparece para administradores da plataforma, ou direto em `/admin`
- **Visão geral:** empresas, usuários, formulários publicados, respostas e espaço usado por arquivos, com gráfico de 30 dias
- **Empresas:** membros (alterar papel, adicionar, remover), trocar proprietário, suspender com motivo, reativar e excluir com todos os dados
- **Usuários:** criar usuários (veja abaixo), editar nome e e-mail, definir nova senha (encerra as sessões abertas), dar ou tirar acesso de administrador, suspender, excluir e **acessar como** a pessoa para dar suporte (com aviso fixo na tela e registro no histórico)
- **Suspensões:** um usuário suspenso não consegue entrar; uma empresa suspensa bloqueia os membros e para de aceitar respostas, sem apagar dados

### Cadastro aberto ou fechado

Por padrão o cadastro público está **fechado**: `/register` leva para a tela de entrar com um aviso, e a página inicial mostra só o botão *Entrar*. Novas pessoas entram de duas formas:

- **Criadas pela Administração:** em *Administração → Usuários → Novo usuário*, preencha nome e e-mail e escolha entre criar uma empresa nova (a pessoa vira proprietária) ou adicionar a uma empresa existente com um papel. Se a senha ficar em branco, uma é gerada e mostrada **uma única vez**, com botão de copiar, para você enviar à pessoa.
- **Convite de uma empresa:** quem tem um convite válido consegue se cadastrar pelo link do convite, com o e-mail convidado, mesmo com o cadastro fechado.

Para abrir o cadastro a qualquer pessoa, coloque no `.env` e limpe o cache de configuração:

```env
TRACEFORMS_REGISTRATION_OPEN=true
```
```bash
php artisan config:cache
```

### Como se tornar administrador geral

O administrador geral (administrador da plataforma) é quem vê a aba **Administração** e acessa `/admin`. É uma marcação no cadastro do usuário, independente do papel dentro das empresas.

**1. Crie o usuário e dê o acesso pelo terminal (recomendado)**, na pasta do projeto:

```bash
php artisan traceforms:make-admin seu@email.com
```

Se ainda não existir usuário com esse e-mail, o comando pergunta nome, nome da empresa e senha, e cria tudo. Se já existir, só dá o acesso. A resposta é `Fulano agora é administrador da plataforma. Entre e acesse /admin.`

Para remover o acesso:

```bash
php artisan traceforms:make-admin seu@email.com --remove
```

**2. Outras formas de dar o acesso** a um usuário que já existe:

- **Por outro administrador geral:** em *Administração → Usuários*, abra a pessoa (ou crie em *Novo usuário*), marque *Administrador da plataforma (acesso a /admin)* e clique em *Salvar*.

- **Direto no banco** (se não tiver acesso ao terminal da aplicação):
  ```sql
  UPDATE users SET is_super_admin = 1 WHERE email = 'seu@email.com';
  ```

**3. Entre de novo** (ou recarregue a página). A seção **Plataforma → Administração** aparece na barra lateral.

Observações:

- Nos dados de demonstração (`--seed`), `demo@traceforms.test` já é administrador geral.
- Ninguém consegue remover o próprio acesso pelo painel, para não ficar sem nenhum administrador. Use o comando com `--remove`, se precisar.
- Enquanto um administrador estiver usando **Acessar como**, a área `/admin` fica bloqueada até ele clicar em *Voltar para minha conta*.

---

## Integrações

### Incorporar no seu site

```html
<div data-traceforms="ID_DO_FORMULARIO"></div>
<script src="https://SEU_DOMINIO/embed.js" async></script>
```

O script repassa ao formulário as UTMs, os IDs de clique, a página onde ele está e o primeiro toque do visitante no seu site. A altura se ajusta sozinha.

- **Opções:** `data-height="700"`, `data-transparent="true"`, `data-popup="true"` (abre em janela), `data-button="Fale conosco"` e `data-color="#0e7c66"`
- **Ao enviar:** dispara o evento `traceforms:submit` no elemento e `traceforms_submit` no `dataLayer` da página

### Webhook

Configurado em *Formulário → Configurações*. Cada resposta gera um `POST` JSON:

```json
{
  "event": "submission.created",
  "form": { "id": "ab12cd34", "title": "Diagnóstico gratuito" },
  "submission": { "id": "uuid", "submitted_at": "2026-11-11T16:45:00+00:00" },
  "answers": [{ "key": "q1_ab12", "label": "Nome", "type": "short_text", "value": "Ana" }],
  "data": { "q1_ab12": "Ana" },
  "tracking": { "channel": "paid_search", "utm_source": "google", "utm_campaign": "...", "device_type": "smartphone", "city": "Fortaleza", "first_touch": { } }
}
```

Funciona com Zapier, Make, n8n ou o seu CRM.

### Pixels e e-mail

- Conversão no **GA4** (`generate_lead`), **Pixel da Meta** (`Lead`) e **Google Tag Manager** (`traceforms_submit`)
- Aviso por e-mail a cada resposta, com a origem e o dispositivo

---

## Colocar em produção

1. **`.env`:** defina `APP_ENV=production`, `APP_DEBUG=false` e `APP_URL=https://seu-dominio`, além do e-mail (`MAIL_*`). O cadastro público fica fechado a menos que você defina `TRACEFORMS_REGISTRATION_OPEN=true`.
2. **Permissões:**
   ```bash
   sudo chown -R www-data:www-data storage bootstrap/cache
   php artisan optimize
   ```
3. **Agendador** (retenção de dados e limpeza de arquivos de quem desistiu). No `crontab -e` do usuário do servidor:
   ```
   * * * * * cd /caminho/TraceForms && php artisan schedule:run >> /dev/null 2>&1
   ```
4. **Tamanho dos arquivos:** o limite por arquivo vai até `TRACEFORMS_UPLOAD_MAX_MB` (padrão 25). Ajuste também o `php.ini`:
   ```ini
   upload_max_filesize = 25M
   post_max_size = 26M
   ```
5. **Nginx (exemplo):**
   ```nginx
   server {
       server_name seu-dominio.com.br;
       root /caminho/TraceForms/public;
       index index.php;
       client_max_body_size 26M;
       location / { try_files $uri $uri/ /index.php?$query_string; }
       location ~ \.php$ {
           include snippets/fastcgi-php.conf;
           fastcgi_pass unix:/run/php/php8.3-fpm.sock;   # ajuste para a sua versão do PHP
       }
   }
   ```
6. **Geolocalização:** país, região e cidade vêm dos cabeçalhos da CDN (Cloudflare, CloudFront, Vercel, Fastly). Sem CDN, o país é estimado pelo fuso do navegador. Para geolocalizar pelo IP, implemente `GeoResolver::resolveByIp()` com o MaxMind GeoLite2.
7. **Webhooks em volume alto:** hoje são enviados logo após a resposta ao visitante. Para ter novas tentativas automáticas, troque por fila (`DeliverWebhook::dispatch(...)`) e rode `php artisan queue:work` com o Supervisor.

---

## Arquitetura

```
app/
  Enums/Role.php                        Papéis e matriz de permissões (cada habilidade vira um Gate)
  Http/Middleware/SetCurrentAccount     Empresa ativa, papel do usuário, bloqueio de suspensos
  Http/Middleware/EnsureSuperAdmin      Protege /admin
  Http/Controllers/
    Public/PublicFormController         Página pública, coleta (/collect), envio, arquivos, links curtos
    FormController                      Formulários, construtor (JSON), configurações, publicação
    ReportController, CampaignController Relatórios
    SubmissionController                Respostas, parciais, arquivos, exportação CSV
    ProfileController                   Perfil, foto, senha, dispositivos
    Admin/                              Administração da plataforma
  Services/
    Tracking/                           SessionRecorder, DeviceParser, ChannelClassifier,
                                        TrafficParser, GeoResolver, IpPrivacy
    Reports/                            ReportService (agregações), ReportFilters
    SubmissionService, FormLogic        Validação, antispam, lógica condicional, arquivos
  Support/                              Tipos de campo, modelos, CPF/CNPJ, SQL portável
public/
  js/tf-tracker.js                      Coleta no navegador (visita, tempo por pergunta, envio em segundo plano)
  embed.js                              Script de incorporação para outros sites
  css/                                  Painel (app.css), formulário público, site
  vendor/                               Bibliotecas e fontes servidas localmente
resources/views/
  public/form.blade.php                 Os quatro formatos de formulário
  layouts/app | admin | guest           Painel da conta, administração e telas de login
```

**Tabelas principais:** `accounts`, `account_user` (papel e restrição por formulário), `invitations`, `forms`, `form_fields`, `tracking_links`, `form_sessions` (uma por visita, com todo o rastreamento), `submissions`, `uploads`, `audit_logs`.

**Comandos:**

| Comando | O que faz |
|---|---|
| `php artisan traceforms:make-admin email` | Dá (ou remove, com `--remove`) acesso a `/admin` |
| `php artisan traceforms:prune` | Aplica a retenção de visitas de cada empresa e apaga arquivos de quem não enviou (roda todo dia pelo agendador) |

---

## Solução de problemas

| Sintoma | Causa e solução |
|---|---|
| `could not find driver` | Falta a extensão do banco: `sudo apt install php-mysql` (ou `php-sqlite3`). |
| `Access denied for user` | Usuário ou senha do `.env` não conferem com o MySQL. Depois de corrigir, rode `php artisan config:clear`. |
| `Table 'accounts' already exists` em `2026_11_11_000100_create_formpulse_tables` | Sobrou o arquivo de migration do nome antigo: `rm database/migrations/2026_11_11_000100_create_formpulse_tables.php` e rode de novo. |
| Página com erro 419 | A sessão expirou. Recarregue a página. Em produção, confira se o `APP_URL` é o mesmo domínio acessado. |
| Upload falha com arquivo grande | Aumente `upload_max_filesize` e `post_max_size` no PHP e `client_max_body_size` no Nginx. |
| Foto de perfil não é cortada | Instale o GD: `sudo apt install php-gd`. |
| Layout antigo depois de atualizar | Rode `php artisan optimize:clear` e recarregue com `Ctrl+Shift+R`. |
| Qualquer outro erro | Veja a mensagem em `storage/logs/laravel.log`. |

---

Fontes Bricolage Grotesque e Instrument Sans sob a licença SIL Open Font License. Ícones Font Awesome Free.
