# wp-plugin-middag-boilerplate

**A proposta de arquitetura da MIDDAG para um plugin WordPress.** É uma
referência instalável e testável — não um framework prescritivo. Mostra, ponta a
ponta, como compor as libs OSS da MIDDAG (`framework` + `wordpress` + `ui` +
`@middag-io/react`) num plugin real. Você é livre para seguir tudo, pegar só uma
camada, ou ignorar o React.

Domínio de exemplo: **Widget** — um agregado mínimo persistido em tabela própria,
exposto por REST, listado numa página Inertia/React e configurado por uma página
de settings nativa do WordPress.

---

## Instalação (clone fresco, sem credencial MIDDAG)

As libs OSS resolvem do **Packagist público** — nenhum `auth.json`, nenhum Satis.

```bash
composer install          # framework + wordpress (+ ui transitivo) do Packagist
cd ui && npm install       # React 19 + Inertia v3 + @middag-io/react
npm run build:wp           # gera ../assets/dist/app.js + style.css (IIFE)
```

Ative o plugin no wp-admin: a tabela `wp_mdgbp_widget` é criada na ativação, o
menu "MIDDAG Boilerplate" aparece com as páginas **Widgets** (React) e
**Settings** (nativa).

Qualidade:

```bash
composer check   # php-cs-fixer + rector + phpstan (level 6, sem baseline)
composer test    # phpunit — Unit + Integration
cd ui && npm run typecheck && npm run lint && npm run test
```

---

## O único botão: o slug + o namespace

Renomear o plugin = trocar **duas** identidades. Tudo o resto deriva delas.

| Botão | Valor aqui | Deriva |
|---|---|---|
| **slug** (`extra.installer-name` no `composer.json`) | `middag-boilerplate` | pasta instalada `wp-content/plugins/middag-boilerplate/`, text domain, `id` do mount React (`middag-boilerplate-app`), namespace REST (`middag-boilerplate/v1`), nome dos assets |
| **namespace PSR-4** | `Middag\WpBoilerplate\` → `src/` | todas as classes |

> O namespace é **PascalCase escolhido**, NÃO derivado do slug kebab. `middag-boilerplate`
> (slug) e `Middag\WpBoilerplate` (namespace) são independentes — troque os dois.

O nome do **repositório** (`wp-plugin-middag-boilerplate`) ≠ a **pasta instalada**
(`middag-boilerplate`): o prefixo `wp-plugin-` desambigua o repo na org, o
`installer-name` governa o path de instalação.

---

## A regra: `src/` (PHP) vs `ui/` (visual)

- **`src/`** — TODO o PHP, subdividido **por CAMADA** (nunca `includes/admin/public`):
  - `Core/` — composition root. `AppBootstrap` **compõe** o `WpBootstrap` da lib
    (não reimplementa a plataforma), `AppServiceProvider` auto-descobre serviços,
    `Plugin::boot` monta o container 1x e fia os hooks.
  - `Domain/` — DDD puro, **zero WordPress**. Entidade + enum + DTO + service +
    descritor de schema (`#[Table]`/`#[Column]`).
  - `WordPress/` — o ÚNICO namespace que toca a API do WP/`$wpdb`: repository
    (sobre o `AbstractRepository` do framework), mapper, hooks, CPT, settings.
  - `Api/V1/` — controllers REST sobre `AbstractWpRestController`.
  - `UI/` — page controllers Inertia (constroem o `PageContract` em PHP).
- **`ui/`** — React + Inertia + `@middag-io/react` + Vite → IIFE em `assets/dist/`.

**A UI é opcional.** Um plugin SEM interface rica não tem `ui/` nem `src/UI/` —
some as duas pastas e o plugin continua válido (Domain + WordPress + Api).

---

## Duas UIs, um menu (não é 100% React)

O boilerplate mostra os DOIS mundos de admin do WordPress sob o mesmo menu:

- **Widgets** — SPA Inertia/React. O PHP (`WidgetPageController` + `PageBuilder`
  da lib `ui`) constrói o `PageContract` inteiro; o React `<ContractPage>`
  renderiza. Fluxo único (sem `contractKey`/`contractData` separado). O React
  registra SÓ o shell + os blocos que usa (`registerBlock`), nunca
  `registerDefaults()` — proibido no build IIFE.
- **Settings** — página nativa do WordPress (Settings API), server-rendered, ZERO
  React, via `Middag\WordPress\Settings` (Tab → Section → Field).

---

## Onde um produto proprietário pluga `core` + `licensing`

O boilerplate é **OSS estrito (TIER A)**: só depende de `framework` + `wordpress`
+ `ui`. **Nunca** requer `middag-io/core` ou `middag-io/licensing` (proprietários)
— senão deixaria de ser instalável por um dev externo, e o gate de CI (boundary
OSS) falha o build.

Um produto MIDDAG real adiciona esses tiers em dois pontos, **sem tocar no resto
da arquitetura**:

- **Auth/SSO** — o `WidgetController` injeta um `RequestAuthenticatorInterface`.
  O boilerplate liga o `WpSessionAuthenticator` (cookie/sessão). Um produto liga
  o SEU composite (token → sessão) do `core`/`licensing` no `AppBootstrap`, e
  todos os controllers passam a autenticar por ele — nenhuma outra mudança.
- **Composition root** — `AppBootstrap::configure()` é onde um produto registra
  os serviços proprietários e faz `bootModules([...])` dos módulos do `core`.

Ou seja: a fronteira OSS↔proprietário é uma **seam de DI**, não um fork.

---

## Isolamento de vendor (concern do produto, não demonstrado aqui)

Este boilerplate **não roda Strauss**. Prefixar só o Symfony DI seria incoerente:
o `middag-io/framework` expõe tipos do Symfony DI na API pública
(`BootstrapInterface::configure(ContainerBuilder)`) e não é prefixado junto, então
uma app que prefixasse só o DI quebraria a implementação da interface.

Isolar o vendor de verdade (para conviver com outros plugins que empacotam um
Symfony diferente) significa escopar o **grafo inteiro** — `framework` + Symfony +
PSR — com **PHP-Scoper** no build do produto. Isso é concern do build do produto,
deliberadamente fora do escopo desta referência para mantê-la coerente e simples.

> **Colisão de `Middag\Framework` entre múltiplos plugins:** mesmo caso — resolvida
> por PHP-Scoper no produto, documentada aqui, não demonstrada.

---

## Demonstra vs. Liberdade

**O boilerplate DEMONSTRA:** composição via `WpBootstrap` + `ContainerFactory`;
identidade via `WpComponentContext`; uma classe por camada; auto-discovery por
sufixo + auto-alias de interface única; tabela gerada do `#[Table]` via
`SchemaBuilder`; REST com `permission_callback` reais + envelope `RestResponse`;
cron com `CronInterval` tipado; `InertiaAdapter` com mount id derivado; registro
SELETIVO de blocos; página de settings nativa; CSRF nas mutações.

**Você é LIVRE para:** escolher `SCAN_DIRS`/namespace; quais blocos/shells
registrar; injetar seu próprio `RequestAuthenticatorInterface`; usar o bus async
do framework; adicionar CPTs/rotas/páginas; ou ignorar Inertia/React e usar só uma
camada. As libs são um menu, não um mandato.

---

## Release

`release-please` mantém o `CHANGELOG.md` e a constante `MIDDAG_BOILERPLATE_VERSION`
(anotada com `x-release-please-version`) a partir de Conventional Commits. O
cabeçalho `Version:` do arquivo principal (lido pelo WordPress) deve ser bumped
junto no PR de release — o WP parseia o header literalmente, então ele não carrega
a anotação do release-please.

---

## Licença

GPL-3.0-or-later.
