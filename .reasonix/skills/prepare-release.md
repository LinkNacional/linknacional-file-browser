---
name: prepare-release
description: Prepara release do linknacional-file-browser: atualiza README.txt, CHANGELOG.md, cabeçalho PHP, constante VERSION, fallback, package.json e os .yml workflows. Changelog baseado nos highlights do usuário (git log é só dica).
---

# prepare-release

Atualiza **todos** os arquivos que contêm o número de versão para uma nova release do plugin.

## Parâmetros (via `arguments`)

O usuário pode passar os valores diretamente: `"version=1.1.0 tested_up=7.1 php=8.2 highlights=Correção do breadcrumb das coleções"`. Se algum valor faltar, pergunte.

- **version** — nova versão (Stable tag)
- **tested_up** — versão do WP testada (Tested up to)
- **php** — versão mínima do PHP (Requires PHP)
- **highlights** — resumo da versão (itens de mudança). É a **fonte primária** do changelog. Se vazio, **pergunte obrigatoriamente** ao usuário o que mudou — NÃO invente a partir do git log.

## Fluxo de execução

### 1. Coletar valores
Se não recebidos via arguments, pergunte ao usuário um por um. Detecte a versão atual via grep no `.php` raiz:
```
grep -E "Version:|Requires PHP:" linknacional-file-browser.php
```

### 2. Levantar o contexto das mudanças (changelog)

⚠️ **Regra anti-redundância.** O changelog NUNCA deve listar itens que já pertencem a versões anteriores. Antes de escrever qualquer entrada:

1. **Pergunte ao usuário** o que mudou nesta versão (se `highlights` não veio nos arguments). A resposta dele é a fonte da verdade.
2. O `git log` é **apenas uma dica** para o usuário lembrar — não gera os bullets sozinho. O range de commits costuma estar dessincronizado (tags antigas/ausentes) e pode trazer commits de releases já publicadas.
3. **Leia o topo do changelog atual** (`README.txt` e `CHANGELOG.md`) e **descarte** qualquer item já descrito nas entradas anteriores.
4. Escreva os bullets **somente** com o que o usuário confirmou como novo.

```bash
# dica opcional — jamais usar como fonte única
LAST_TAG=$(git describe --tags --abbrev=0 2>/dev/null)
if [ -z "$LAST_TAG" ]; then
    git log -n 10 --oneline
else
    git log ${LAST_TAG}..HEAD --oneline
fi
```

### 3. Atualizar TODOS os arquivos com versão

A versão aparece em **9 locais** espalhados por **7 arquivos**. Atualize todos:

#### 3a. `README.txt`
- `Stable tag:` → nova versão
- `Tested up to:` e `Requires PHP:` se alterados
- Adicionar entrada no `== Changelog ==` (topo da seção), **sempre em inglês**, usando a **data de hoje** (obtida via `date +%Y-%m-%d`). Deixar uma **linha em branco** entre a nova entrada e a anterior:
  ```
  = VERSION - YYYY-MM-DD =
  * Item (escrito a partir do `highlights` do usuário, NÃO do git log)

  = VERSAO_ANTERIOR - YYYY-MM-DD =
  ```
  > ⚠️ Aqui o cabeçalho usa `=` (não `#`) por ser formato WordPress.org `readme.txt`.
- Se `highlights` foi fornecido, avalie adicionar na `== Description ==` (NUNCA apague conteúdo existente)

#### 3b. `CHANGELOG.md`
- Adicionar entrada no topo do arquivo, **em português**, usando a **data de hoje** (obtida via `date +%d/%m/%y`):
  ```
  # VERSION - DD/MM/AA
  * Item (escrito a partir do `highlights` do usuário, NÃO do git log)
  ```

#### 3c. Arquivo PHP principal (`linknacional-file-browser.php`)
- `* Version: NOVA_VERSION` (cabeçalho do plugin)
- `define( 'LINKNACIONAL_FILEBROWSER_VERSION', 'NOVA_VERSION' );` (constante)

#### 3d. `includes/LinkNacionalFilebrowser.php`
- `$this->version = 'NOVA_VERSION';` (fallback quando a constante não está definida)

#### 3e. `package.json`
- `"version": "NOVA_VERSION"`

#### 3f. `.github/workflows/main.yml`
- `PLUGIN_VERSION: 'NOVA_VERSION'` (versão usada no build do .zip + release)

#### 3g. `.github/workflows/wordpressRelease.yml`
- `DEPLOY_TAG: "NOVA_VERSION"` (tag de deploy para o WordPress.org)

#### 3h. `AGENTS.md` — seção Constantes
- `LINKNACIONAL_FILEBROWSER_VERSION       // 'NOVA_VERSION'` (exemplo de comentário da constante)

> ℹ️ O `README.md` **não** contém campos de versão neste plugin — não precisa mexer.

### 4. Validação final
Rodar grep com a versão **antiga** para confirmar que não restou nenhuma ocorrência fora do esperado:
```
grep -rn "VERSAO_ANTIGA" --include="*.php" --include="*.md" --include="*.txt" --include="*.yml" --include="*.json" . | grep -v node_modules | grep -v vendor
```
O esperado: `README.txt` e `CHANGELOG.md` ainda contêm a versão antiga **apenas** nas entradas antigas do Changelog (isso é correto). Qualquer outro arquivo retornando a versão antiga é **erro** e deve ser corrigido.

Depois, grep com a versão **nova** para confirmar que aparece em todos os **9 locais** (7 arquivos):
```
grep -rn "NOVA_VERSAO" --include="*.php" --include="*.md" --include="*.txt" --include="*.yml" --include="*.json" . | grep -v node_modules | grep -v vendor
```
