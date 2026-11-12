# TraceForms

Plataforma de formulários, pesquisas e inscrições em **Laravel 12**, **Bulma 1** e **Alpine.js**.

O diferencial é a **coleta de dados**: cada resposta chega com a campanha, o anúncio, a página de origem, o dispositivo, a localização e o caminho que a pessoa percorreu. Os relatórios mostram o que converte e em qual pergunta as pessoas desistem.

O login é feito pelo **Trace ID**, o mesmo das outras plataformas Tracezap. Quem entra, em qual empresa e com qual nível é definido lá.

---

## Sumário

1. [Requisitos](#requisitos)
2. [Instalação no Ubuntu](#instalação-no-ubuntu)
3. [Primeiro acesso](#primeiro-acesso)
4. [Atualizar para uma versão nova](#atualizar-para-uma-versão-nova)
5. [Funcionalidades](#funcionalidades)
6. [Níveis de acesso](#níveis-de-acesso)
7. [Login pelo Trace ID](#login-pelo-trace-id), incluindo a [migração dos usuários que já existiam](#migração-dos-usuários-que-já-existiam)
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

TRACEID_CLIENT_ID=id-da-plataforma-no-trace-id
TRACEID_CLIENT_SECRET=segredo-da-plataforma
```

Os dados do Trace ID vêm do cadastro do TraceForms como plataforma no Trace ID. Veja [Login pelo Trace ID](#login-pelo-trace-id).

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

1. Cadastre o TraceForms como plataforma no Trace ID e preencha `TRACEID_CLIENT_ID` e `TRACEID_CLIENT_SECRET` no `.env` (veja [Configurar](#configurar)).
2. No Trace ID, dê à sua pessoa acesso ao TraceForms com o nível **admin** na sua empresa.
3. Abra o TraceForms e clique em **Entrar com Trace ID**.

No primeiro login, a empresa é criada no TraceForms com o nome que ela tem no Trace ID, e você entra como Administrador. As outras pessoas aparecem depois do primeiro login de cada uma.

O `--seed` cria dados de demonstração ("Acme Marketing", com cerca de 9 mil visitas e 3 mil respostas, e "Cliente Beta"), com pessoas de e-mail `@traceforms.test`. Para usar esses dados localmente, dê acesso no Trace ID a uma pessoa com um desses e-mails, ou ligue a empresa de demonstração à sua conta com `traceforms:trace-link`.

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

Foto (cortada em quadrado), empresas em que a pessoa tem acesso, com troca rápida entre elas, e dispositivos conectados, com a opção de sair de todos os outros. Nome, e-mail e senha são os do Trace ID e são alterados lá.

---

## Níveis de acesso

Uma pessoa pode ter acesso a várias **empresas**, com um nível diferente em cada uma. O nível vem do Trace ID e é traduzido assim:

| Nível no Trace ID | Papel no TraceForms |
|---|---|
| admin | Administrador |
| gestor | Editor |
| usuario | Analista |
| cliente | Leitor |

A tradução pode ser mudada no `.env` com `TRACEID_LEVEL_ADMIN`, `TRACEID_LEVEL_GESTOR`, `TRACEID_LEVEL_USUARIO` e `TRACEID_LEVEL_CLIENTE` (valores: `admin`, `editor`, `analyst`, `viewer`).

| Permissão | Leitor | Analista | Editor | Administrador |
|---|:-:|:-:|:-:|:-:|
| Ver painéis e relatórios | ✓ | ✓ | ✓ | ✓ |
| Ver respostas individuais e exportar CSV | | ✓ | ✓ | ✓ |
| Criar, editar e publicar formulários; gerenciar respostas e links | | | ✓ | ✓ |
| Excluir formulários, configurações da empresa, auditoria, restringir membros a formulários | | | | ✓ |

- Leitor, Analista e Editor podem ser **restritos a formulários específicos** na tela *Equipe*, o que é útil para agências darem acesso a clientes.
- Quem entra, quem sai e o nível de cada pessoa é definido no Trace ID. A tela *Equipe* do TraceForms mostra quem tem acesso e permite só a restrição por formulário.
- As alterações importantes ficam no histórico (auditoria).

---

## Login pelo Trace ID

O TraceForms não tem cadastro, senha nem painel de administração próprios. Tudo isso fica no Trace ID:

- **Entrar:** a tela de login tem só o botão *Entrar com Trace ID*. O login segue o OpenID Connect (código de autorização com PKCE), e a assinatura do token é conferida com as chaves públicas do Trace ID.
- **Pessoa:** cada pessoa fica ligada ao identificador permanente dela no Trace ID. Nome e e-mail são atualizados a cada login.
- **Empresa:** cada empresa fica ligada ao identificador da conta no Trace ID. O nome e os recursos do plano vêm de lá.
- **Nível:** vem do Trace ID e é conferido de novo a cada 5 minutos (`TRACEID_RECHECK_SECONDS`). Se o nível mudar lá, muda aqui sem novo login. Se o acesso for suspenso ou removido, a pessoa sai do TraceForms.
- **Trocar de empresa:** pelo seletor no topo do menu. A troca passa rapidamente pelo Trace ID, que confirma o acesso àquela empresa.
- **Sair:** encerra a sessão no TraceForms e no Trace ID.
- **Licença:** o comando agendado `traceforms:trace-sync` confere as licenças a cada hora. Empresa com licença inativa ou vencida fica suspensa: ninguém entra e os formulários param de aceitar respostas, mas nada é apagado. Quando a licença volta, a empresa é reativada sozinha.
- **Trace ID fora do ar:** quem já está dentro continua usando normalmente, e a conferência é tentada de novo a cada minuto. Só novos logins dependem do Trace ID.

### Configurar

**1. Cadastre o TraceForms como plataforma no Trace ID** com:

| Campo | Valor |
|---|---|
| URL de retorno | `https://seu-dominio/auth/trace/callback` |
| URL depois de sair | `https://seu-dominio/` |
| Escopos do login | `openid profile email offline_access` |
| Escopos da API | `members:read members:write` |

Para testar na sua máquina, cadastre também `http://127.0.0.1:8000/auth/trace/callback` e `http://127.0.0.1:8000/`.

**2. Preencha o `.env`:**

```env
TRACEID_CLIENT_ID=id-da-plataforma
TRACEID_CLIENT_SECRET=segredo-da-plataforma
# Opcionais
TRACEID_ISSUER=https://id.tracezap.com.br
TRACEID_RECHECK_SECONDS=300
TRACEID_PROFILE_URL=https://id.tracezap.com.br
```

**3. Rode** `php artisan config:cache`.

### Migração dos usuários que já existiam

Nada é apagado: formulários, respostas, relatórios e restrições por formulário continuam onde estão.

**1. Veja a situação atual.** O comando lista as empresas do TraceForms e as contas com licença do TraceForms no Trace ID:

```bash
php artisan traceforms:trace-status
```

**2. Ligue cada empresa à conta correspondente do Trace ID:**

```bash
php artisan traceforms:trace-link 1 aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa
```

O primeiro número é o ID da empresa no TraceForms; o segundo, o UUID da conta no Trace ID (os dois aparecem no passo 1). Para desfazer, use `--unlink`.

Se pular este passo, a ligação é feita no primeiro login quando a pessoa é **admin** no Trace ID e é dona ou administradora de **uma única** empresa ainda não ligada. Em qualquer outro caso, uma empresa nova e vazia é criada. Por isso, ligue as empresas antes de avisar a equipe.

**3. Crie no Trace ID os acessos de quem já usava o TraceForms.** Rode primeiro a simulação, que não grava nada:

```bash
php artisan traceforms:trace-push 1 --dry-run
php artisan traceforms:trace-push 1
```

Cada membro recebe o nível equivalente ao papel atual (o antigo Proprietário vira admin). Quem já tem conta no Trace ID mantém a senha. Para quem não tinha, o Trace ID gera uma senha, que aparece **uma única vez** no terminal para você enviar à pessoa. Rodar de novo não duplica nada; quem já tem acesso aparece como "Já tem acesso". Se o plano tiver limite de pessoas por nível, quem passar do limite aparece como "Falhou" e precisa ser ajustado no Trace ID.

**4. Avise a equipe.** No primeiro login, cada pessoa é reconhecida pelo e-mail e fica ligada ao Trace ID. Sessões abertas antes da migração são encerradas e pedem um novo login.

**5. Confira** com `traceforms:trace-status`: a coluna "Já entraram pelo Trace ID" mostra quem já fez o primeiro login.

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

1. **`.env`:** defina `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://seu-dominio`, o e-mail (`MAIL_*`) e o Trace ID (`TRACEID_CLIENT_ID` e `TRACEID_CLIENT_SECRET`).
2. **Permissões:**
   ```bash
   sudo chown -R www-data:www-data storage bootstrap/cache
   php artisan optimize
   ```
3. **Agendador** (retenção de dados, limpeza de arquivos de quem desistiu e conferência das licenças no Trace ID). No `crontab -e` do usuário do servidor:
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
  Http/Middleware/SetCurrentAccount     Empresa ativa da sessão e papel da pessoa
  Http/Middleware/EnsureTraceSession    Renova o token e confere o acesso no Trace ID
  Http/Controllers/
    Public/PublicFormController         Página pública, coleta (/collect), envio, arquivos, links curtos
    FormController                      Formulários, construtor (JSON), configurações, publicação
    ReportController, CampaignController Relatórios
    SubmissionController                Respostas, parciais, arquivos, exportação CSV
    Auth/AuthController                 Login, retorno e saída pelo Trace ID
    ProfileController                   Perfil, foto, empresas, dispositivos
    MemberController                    Equipe (consulta e restrição por formulário)
  Console/Commands/                     trace-status, trace-link, trace-push, trace-sync
  Services/
    TraceId/                            TraceIdClient (OIDC, chaves, API), TraceIdSync (pessoa, empresa, nível)
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
  layouts/app | guest                   Painel e tela de login
```

**Tabelas principais:** `users` (com `trace_id`), `accounts` (com `trace_account_id` e os recursos do plano), `account_user` (papel e restrição por formulário), `forms`, `form_fields`, `tracking_links`, `form_sessions` (uma por visita, com todo o rastreamento), `submissions`, `uploads`, `audit_logs`.

**Comandos:**

| Comando | O que faz |
|---|---|
| `php artisan traceforms:trace-status` | Mostra empresas ligadas e não ligadas ao Trace ID e as contas licenciadas lá |
| `php artisan traceforms:trace-link ID UUID` | Liga uma empresa a uma conta do Trace ID (`--unlink` desfaz) |
| `php artisan traceforms:trace-push ID` | Cria no Trace ID os acessos dos membros atuais (`--dry-run` só simula) |
| `php artisan traceforms:trace-sync` | Atualiza licença, nome e recursos das empresas (roda a cada hora pelo agendador) |
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
| "O login pelo Trace ID ainda não foi configurado" | Preencha `TRACEID_CLIENT_ID` e `TRACEID_CLIENT_SECRET` no `.env` e rode `php artisan config:cache`. |
| O Trace ID mostra página de erro ao clicar em entrar | A URL de retorno cadastrada no Trace ID precisa ser exatamente `APP_URL` + `/auth/trace/callback`, inclusive `https` e sem barra no fim do `APP_URL`. |
| "O TraceForms não foi reconhecido pelo Trace ID" | ID ou segredo da plataforma errados no `.env`. |
| A pessoa entrou numa empresa nova e vazia | A empresa antiga não estava ligada. Ligue com `traceforms:trace-link` e peça para a pessoa sair e entrar de novo. A empresa vazia pode ficar sem uso. |
| "Este e-mail já está ligado a outra identidade do Trace ID" | Existem duas pessoas no Trace ID com o mesmo e-mail em momentos diferentes. Corrija no Trace ID ou limpe o `trace_id` do usuário antigo no banco. |
| `traceforms:trace-status` não lista as contas | A plataforma precisa dos escopos `members:read` e `members:write` para a API no Trace ID. |
| Qualquer outro erro | Veja a mensagem em `storage/logs/laravel.log`. Recusas de login aparecem como "Trace ID: login recusado". |

---

Fontes Bricolage Grotesque e Instrument Sans sob a licença SIL Open Font License. Ícones Font Awesome Free.
