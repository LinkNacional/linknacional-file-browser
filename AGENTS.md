# AGENTS.md — Link Nacional File Browser

> **Modo Caveman + RTK ativos.** Respostas telegráficas. Zero enrolação. Código > prosa.

---

## 🔤 Modo Caveman (Token Killer)

- 1 frase por resposta. Sem saudações, sem "claro!", sem "aqui está".
- Nada de "Let me explain..." ou "I'll walk you through...". Código ou silêncio.
- SEARCH/REPLACE direto. Sem narrar o diff.
- Se responder com prosa, limite: 3 linhas.

## 🦀 RTK — Rust Token Killer

- Logs de terminal: só a linha do erro. Nunca o stack trace inteiro.
- `run_command`: usar `2>&1 | tail -5` para erros, `grep -c` para contagens.
- Nunca fazer `cat` de arquivo grande. Sempre `head`/`tail`/`grep`.
- `search_content` com `summary_only` antes de expandir com `context`.
- Zero comentários explicando o óbvio no código.

---

## 🏗️ Arquitetura — PSR-4 + Loader Pattern

### Namespace base
```
LinkNacional\Filebrowser\
  ├── Includes\  → LinkNacionalFilebrowser, LinkNacionalFilebrowserLoader, LinkNacionalFilebrowserActivator, LinkNacionalFilebrowserDeactivator
  ├── Admin\     → LinkNacionalFilebrowserAdmin (menu page + AJAX handlers)
  └── Public\    → LinkNacionalFilebrowserPublic (shortcode + frontend AJAX)
```

### Regras PSR-4
- **1 classe por arquivo.** Sem exceções.
- Nome do arquivo = nome da classe (ex: `LinkNacionalFilebrowserAdmin.php`).
- `use` statements no topo. Nunca FQCN inline exceto em strings de hook.
- Namespace bate com path físico conforme `composer.json`:
  - `LinkNacional\Filebrowser\Includes\` → `includes/`
  - `LinkNacional\Filebrowser\Admin\` → `admin/`
  - `LinkNacional\Filebrowser\Public\` → `public/`

### Loader Pattern
```php
$this->loader->add_action( 'hook_name', $component_instance, 'method_name' );
$this->loader->add_filter( 'hook_name', $component_instance, 'method_name' );
$this->loader->run(); // dispara todos na seq
```
- `LinkNacionalFilebrowserLoader` → `includes/LinkNacionalFilebrowserLoader.php`
- Hooks definidos em `define_admin_hooks()` + `define_public_hooks()` na classe principal.
- **Exceções ao Loader** (hooks registrados direto):
  - `register_activation_hook()` / `register_deactivation_hook()` — no arquivo raiz `linknacional-file-browser.php`.
- **`wp_ajax_nopriv_*`** → sempre parear com `wp_ajax_*` correspondente para operações públicas.

---

## 📐 Constantes e Prefixos

### Constantes (`linknacional-file-browser.php`)
```php
LINKNACIONAL_FILEBROWSER_VERSION       // '1.1.0'
LINKNACIONAL_FILEBROWSER_PLUGIN_NAME                // 'linknacional-file-browser'
LINKNACIONAL_FILEBROWSER_PLUGIN_URL    // plugin_dir_url(__FILE__)
LINKNACIONAL_FILEBROWSER_PLUGIN_PATH   // plugin_dir_path(__FILE__)
```
- Sempre usar `LINKNACIONAL_FILEBROWSER_PLUGIN_URL` e `LINKNACIONAL_FILEBROWSER_PLUGIN_PATH`. Nunca `plugin_dir_path( __FILE__ )` direto.
- `LINKNACIONAL_FILEBROWSER_PLUGIN_URL` + `LINKNACIONAL_FILEBROWSER_VERSION` como cache-buster em `wp_enqueue_style`/`wp_enqueue_script`.

### Prefixos
- Options/DB: `linknacional_filebrowser_*` (ex: `linknacional_filebrowser_db_version`)
- Hooks/AJAX actions: `linknacional_*` (ex: `wp_ajax_linknacional_create_folder`, `wp_ajax_linknacional_frontend_*`)
- JS handles: `linknacional-filebrowser` (admin e public — mesmo handle, hooks diferentes)
- JS globals: `linknacional_ajax` (admin), `linknacional_public_ajax` (public)
- Nonces: `linknacional_filebrowser_nonce`, `linknacional_filebrowser_admin_nonce`, `linknacional_filebrowser_public_nonce`
- Shortcode: `[linkn_filebrowser]`

### Text Domain
`linknacional-file-browser` — usar em `__()`, `esc_html__()`, `wp_send_json_error()` etc.
- **Sempre em inglês com text domain.** Comentários internos no AGENTS podem ser em pt_BR, mas strings de código (PHP/JS/CSS) devem ser sempre em inglês + text domain.

---

## 🗄️ Banco de Dados

### Tabelas (criadas na ativação via `dbDelta`)
- `{prefix}linknacional_filebrowser_folders` — id, name, parent_id, path, created_at, updated_at
- `{prefix}linknacional_filebrowser_files` — id, name, original_name, folder_id, file_type, file_size, file_path, file_url, description, created_at, updated_at

### Regras
- **Sempre usar `$wpdb->prepare()`** para queries com variáveis. Nunca concatenar SQL.
- `global $wpdb` no topo dos métodos que acessam DB.
- `$wpdb->prefix` para nome das tabelas (já usadas na ativação).
- Arquivos físicos em `wp-content/uploads/linknacional-filebrowser/` (criado via `wp_mkdir_p` na ativação).

---

## 🖥️ Front-End — JS/CSS

### Arquivos
| Arquivo | Contexto | Handle |
|---|---|---|
| `assets/js/compiled/fontawesome.compiled.js` | Admin + Public (compartilhado) | `linknacional-filebrowser-fontawesome` |
| `admin/js/linknacional-filebrowser-admin.js` | Admin | `linknacional-filebrowser` |
| `admin/css/linknacional-filebrowser-admin.css` | Admin | `linknacional-filebrowser` |
| `public/js/linknacional-filebrowser-public.js` | Public | `linknacional-filebrowser` |
| `public/css/linknacional-filebrowser-public.css` | Public | `linknacional-filebrowser` |

### Regras
- JS vanilla (IIFE `(function($) { ... })(jQuery);`). Sem React/TSX.
- **Nunca `<script>` ou `<style>` inline no PHP.** Usar `wp_enqueue_script`/`wp_enqueue_style`.
- Dependência: `jquery` (única).
- Localize com `wp_localize_script()` — admin usa `linknacional_ajax`, public usa `linknacional_public_ajax`.
- **Qualquer string visível no JS deve vir via `wp_localize_script()`** com text domain. Nunca hardcoded no JS (nem em inglês). Fallback: `${linknacional_ajax.key || 'Default English'}`.
- **Dentro de template literals (backticks), usar `${...}`, nunca `' + ... + '`.**
- **Font Awesome 6** → instalado via npm (`@fortawesome/fontawesome-free`), compilado com webpack (`style-loader` + `css-loader`) em `assets/js/compiled/fontawesome.compiled.js`. CSS injetado no runtime — zero CDN.
- `console.log` proibido em production. Só `console.error` para erros reais.
- Admin enfileira **somente** na página do plugin (`toplevel_page_linknacional-filebrowser`), via `$hook_suffix`.
- Public enfileira **somente** quando `[linkn_filebrowser]` é renderizado — `wp_enqueue_style`/`wp_enqueue_script` dentro do shortcode, não via `wp_enqueue_scripts`.

### Build
```bash
npm install
npm run build    # webpack --mode production → assets/js/compiled/fontawesome.compiled.js
npm run dev      # webpack --mode development --watch
```
- Entry: `assets/js/fontawesome-entry.js` importa `@fortawesome/fontawesome-free/css/all.min.css`.
- Pasta `assets/js/compiled/` está em `.gitignore` (build output).
- Webpack 5, sem React/Babel — apenas `style-loader` + `css-loader`.

---

## 🔒 Segurança — Regras Absolutas

### Superglobais
```php
// ❌ PROIBIDO
$name = $_POST['folder_name'];

// ✅ OBRIGATÓRIO
$name = isset( $_POST['folder_name'] ) ? sanitize_text_field( wp_unslash( $_POST['folder_name'] ) ) : '';
```
- `$_POST`, `$_GET`, `$_REQUEST` → sempre `wp_unslash()` + `sanitize_*` adequado.
- Uploads: `$_FILES` → validar com `wp_check_filetype()`, nome único com `wp_unique_filename()`.

### Escaping de output
```php
echo esc_html( $name );
echo esc_attr( $value );
echo esc_url( $url );
```
- HTML: `esc_html()`. Atributos: `esc_attr()`. URLs: `esc_url()`. Classes CSS: `esc_attr()` ou `sanitize_html_class()`.
- Dados JSON em `wp_send_json_success()`/`wp_send_json_error()` → GiveWP faz escaping automático, mas dados crus devem ser sanitizados antes.

### Nonces e Capabilities
- Admin page: `current_user_can( 'manage_options' )` + `wp_verify_nonce()` em todos AJAX handlers.
- Admin nonces: `linknacional_filebrowser_nonce`, `linknacional_filebrowser_admin_nonce`.
- Public nonces: `linknacional_filebrowser_public_nonce` (obtido via AJAX `linknacional_get_public_nonce`).
- ⚠️ Nonces obtidos via AJAX em vez de localizados no enqueue — verificar antes de mudar.

### Arquivos
- `wp_unique_filename()` + `wp_check_filetype()` em uploads.
- Caminhos de arquivo: sempre relativos a `wp_upload_dir()` — nunca path absoluto direto.
- `basename()` + `sanitize_file_name()` em nomes de arquivo vindos do usuário.

---

## 🔌 Dependências

### Hard
- **PHP** ≥ 8.2
- **WordPress** — sem versão mínima explícita, mas usa `dbDelta`, `wp_mkdir_p`, `wp_unique_filename`.
- **jQuery** — dependência de script no front-end.

### Externas
- **api.linknacional.com** — Update checker (já removido, plugin será distribuído via WordPress.org).

### Build
- `composer.json`: autoload PSR-4, sem dependências de produção além do PHP ≥ 8.2.
- CI: GitHub Actions — zip release no merge para `main`.

---

## 🚫 Anti-padrões (NUNCA fazer)

1. `echo` de variável não escapada (HTML/atributo/URL).
2. `$_GET` / `$_POST` / `$_FILES` sem `wp_unslash()` + sanitize adequado.
3. `add_action` / `add_filter` fora do Loader (exceto activation/deactivation).
4. Duas classes no mesmo arquivo.
5. `plugin_dir_path( __FILE__ )` — usar constantes `LINKNACIONAL_FILEBROWSER_PLUGIN_PATH`/`LINKNACIONAL_FILEBROWSER_PLUGIN_URL`.
6. `<script>` ou `<style>` inline no PHP — sempre `wp_enqueue_*`.
7. SQL concatenado — sempre `$wpdb->prepare()`.
8. Strings em português hardcoded — tudo em `__()` com text domain.
9. Comentar código morto. Remover.
10. `console.log` em production code.
11. `global $wpdb` sem sanitização da query.
12. Path de arquivo direto sem `wp_upload_dir()` / `basename()`.

---

## 📋 Fluxo de Admin

### Admin → Link File Browser
- Menu principal (`manage_options` cap, `dashicons-portfolio`).
- Admin page renderizada inline em `LinkNacionalFilebrowserAdmin::admin_page()`.
- JS carrega árvore de pastas + grid de arquivos via AJAX.
- CRUD completo: criar/renomear/deletar pastas e arquivos, upload de arquivos.

### Shortcode `[linkn_filebrowser]`
- Atributos: `folder_id`, `show_search`, `show_breadcrumb`, `show_folder_tree`, `layout` (grid/list).
- Frontend: navegação somente leitura (sem upload/delete).

---

## 📋 PR Checklist Mental

- [ ] WPINC guard? (`if ( ! defined('WPINC') ) exit;`)
- [ ] Superglobais sanitizadas (`wp_unslash` + `sanitize_*`)?
- [ ] Output escapado (`esc_html`/`esc_attr`/`esc_url`)?
- [ ] SQL com `$wpdb->prepare()`?
- [ ] Hooks registrados via Loader (ou exceção justificada)?
- [ ] PSR-4: 1 classe por arquivo?
- [ ] Constantes do plugin usadas (não path direto)?
- [ ] Text domain `linknacional-file-browser` em todas strings traduzíveis?
- [ ] `console.log` removido? Só `console.error` permanece.
- [ ] Nada de `<script>`/`<style>` inline no PHP?
- [ ] Uploads validados com `wp_check_filetype()` + `wp_unique_filename()`?
- [ ] Nonce verificado em handlers AJAX?
- [ ] `current_user_can( 'manage_options' )` nas rotas admin?
