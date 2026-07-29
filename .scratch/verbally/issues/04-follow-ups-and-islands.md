# Entregar follow-ups e atualizações independentes

Status: resolved

Blocked by: 03 — Entregar correções Gemini com streaming e contrato validado.

## What to build

Uma Correction concluída usa Gemini para substituir os resultados controlados de follow-up por uma reescrita natural ou um exemplo relevante no próprio cartão, enquanto as regiões da página atualizam isoladamente e a timeline respeita a posição de leitura.

## Acceptance criteria

- [x] “Rewrite naturally” acrescenta uma única alternativa com o mesmo sentido, e “More examples” acrescenta um único exemplo curto; nenhum dos dois cria nova Correction.
- [x] Durante follow-up ou retry, envio, ações dos cartões e limpeza da sessão ficam indisponíveis; testes cobrem essa exclusão mútua.
- [x] Cabeçalho/contador, editor e shell da conversa atualizam como islands; cada Correction attempt é um MFC Livewire com island próprio e `wire:key` estável, sem transformar componentes Blade de apresentação em islands.
- [x] A timeline acompanha o cartão ativo apenas quando a pessoa já está próxima do final e preserva a leitura de histórico anterior.
- [x] Há testes para os follow-ups e verificação no navegador dos estados pendente, concluído, erro, vazio e tela estreita.

## Execution checkpoint

Não inicie o ticket seguinte sem confirmação explícita do usuário após a entrega deste ticket.

## Comments

- Entregue em `8256823`, `42387dc`, `9cf04a4`, `bb12a47` e `ec76f17`. `./vendor/bin/pest` passou com 44 testes / 119 asserções; Pint, `npm run build` e `git diff --check` limpos.
- Revisão independente: APROVADO COM RESSALVAS, sem defeito bloqueante. As ressalvas de design (destravamento da sessão e sincronia dos controles durante follow-up) foram corrigidas em `ec76f17`; a de teste fraco também.
- A arquitetura de attempt independente foi entregue em `50b3296` e o retry específico de follow-up em `450df66`. A revisão final foi APROVADA após `9ad397f` e `e1d481b`: `./vendor/bin/pest tests/Browser/CorrectionFlowTest.php` passou com 8 testes / 42 asserções; `PAO_DISABLE=1 ./vendor/bin/pest --compact` com 35 / 65; Pint, build e `git diff --check` passaram.

### Boundary por Correction attempt

`@island` continua incompatível diretamente com `@foreach`, como confirma a documentação Livewire. O critério é atendido sem alterá-lo: cada attempt é um MFC Livewire filho, renderizado no loop com `wire:key="attempt-{id}"`, e seu template contém o island nomeado `attempt`. Componentes Livewire filhos são independentes e podem ser renderizados em loops; o próprio filho mantém estado, streaming, retry e follow-up, enquanto o pai mantém sessão, contador, limite, limpeza e lock global.

O primeiro request do pai cria o MFC e trava a sessão; só depois de o alvo `wire:ref` existir o filho dispara o request de streaming. Eventos dinâmicos pai-filho concedem follow-up/retry e notificam o término. Não há `wire:island=""`, nem outro desvio de API interna.

### Dois defeitos do ticket 03 encontrados pela validação com modelo real

Os fakes do ticket 03 sempre devolviam `type` válido, então a suíte passava enquanto o app estava quebrado em produção. Corrigidos em `bb12a47`, com os dois payloads reais fixados como teste de regressão:

1. `CorrectionPrompt::detailsInstructions()` nunca nomeava os `type` permitidos. O Gemini inventava `same`/`replacement`, `validDetails()` rejeitava, e **nenhum diff ou explicação jamais apareceria**.
2. O prompt também não dizia que uma Off-topic response não carrega diff nem explicações. O modelo preenchia ambos, e **toda** Off-topic response virava erro.

### Pendências conhecidas (não deste ticket)

- Uma pergunta didática em inglês ("What is the difference between affect and effect?") é classificada como frase corrigível, não como Off-topic response. `CONTEXT.md` diz que deveria ser recusa. É o prompt de classificação do stage 1, critério do ticket 03.
- `count($attempts)` alimenta contador e limite de 20 contando tentativas com erro e off-topic, contra `CONTEXT.md`. Decisão do usuário: manter fora do escopo deste ticket.
- O contador de caracteres do editor só atualiza no round-trip (`wire:model` sem `.live`), então fica em `0 / 2000` enquanto a pessoa digita. Pré-existente do ticket 02.
- O tagline do cabeçalho renderiza `&amp;` literal (dupla escapação vinda do HTML de referência). Pré-existente do ticket 01.

### Verificação no navegador

O fluxo foi verificado de forma reproduzível com Pest Browser/Playwright e fixtures de agentes ativas somente no ambiente `testing`: estado vazio; pending e bloqueio global; correção/detalhes concluídos; erro e retry de correção; erro e retry exclusivo de follow-up; follow-up no mesmo cartão; tela estreita; e preservação/seguimento da leitura na timeline. A suíte roda em Chromium pelo plugin oficial; não há alegação de validação manual em Safari nem de chamadas Gemini reais.
