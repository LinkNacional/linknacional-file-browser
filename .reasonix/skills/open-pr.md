---
name: open-pr
description: Abre PR da branch dev para main no padrão Link Nacional (título VERSION - repo - resumo; corpo com readme/metadados e resumo copiado do changelog)
---

# open-pr

Abre um Pull Request de `dev` → `main` via `gh pr create`, no padrão Link Nacional.

## Parâmetros (via `arguments`)

O usuário pode passar: `version=1.1.0 tested_up=7.1 php=8.2 summary=Correção do breadcrumb das coleções`. Qualquer valor ausente é extraído do código.

- **version** — versão da release (Stable tag / cabeçalho PHP)
- **tested_up** — WP testado até
- **php** — PHP mínimo requerido
- **summary** — resumo CURTO das mudanças (usado no TÍTULO). Se ausente, derive da entrada mais recente do changelog (NÃO do git log).

## Fluxo de execução

### 1. Extrair metadados (se não vierem nos arguments)

```bash
# Header PHP (fonte da verdade)
grep -m1 -E "^\s*\*\s*Version:" linknacional-file-browser.php
grep -m1 -E "^\s*\*\s*Requires PHP:" linknacional-file-browser.php

# README.txt (fallback para Tested up to)
grep -m1 -i "^Tested up to:" README.txt
grep -m1 -i "^Requires PHP:" README.txt
grep -m1 -i "^Stable tag:" README.txt

# Nome do repositório
REPO_NAME=$(basename "$PWD")
```

### 2. Ler o changelog da versão atual (fonte do resumo e dos bullets)

⚠️ **Regra anti-redundância.** NÃO use `git log` para gerar o resumo — o range de commits está dessincronizado (tags antigas/ausentes) e traz itens de versões já publicadas. Em vez disso, leia a entrada mais recente do changelog:

```bash
# Preferir CHANGELOG.md (português). Fallback: README.txt, seção == Changelog ==.
head -n 30 CHANGELOG.md
# ou
grep -A 20 "^== Changelog ==" README.txt
```

A entrada mais recente tem o formato `# VERSION - DD/MM/AA` seguido de bullets `* ...`.

- **TÍTULO**: resuma esses bullets em uma frase curta (≤ ~12 palavras).
- **CORPO**: copie os bullets tal como estão no changelog (sem hash, sem reescrever).

### 3. Montar TÍTULO

Formato exato (obrigatório):

```
VERSION - REPO_NAME (RESUMO_CURTO)
```

Exemplo:
```
1.1.0 - linknacional-file-browser (Correção do breadcrumb das coleções)
```

### 4. Montar CORPO

Use como gabarito o modelo abaixo. Extraia os campos fixos do `README.txt` (Contributors, Tags, License, License URI, Description, etc.). Substitua APENAS os placeholders `{...}`:

```markdown
# {PLUGIN_NAME}

* Contribuidores: {CONTRIBUTORS}
* Link: {PLUGIN_URI}
* Tags: {TAGS}
* Testado até: {TESTED_UP}
* Versão estável: {VERSION}
* Licença: GPLv2 ou posterior
* URI da Licença: {LICENSE_URI}
* Traduções: Português(Brasil) / Inglês

{COLE A DESCRIÇÃO — seção == Description == do README.txt. NUNCA invente nem resuma demais; preserve o texto real.}

## Instalação

1. Baixe o plugin.
2. No painel administrativo do WordPress, vá para **Plugins > Adicionar Novo**.
3. Clique em "Enviar Plugin" e selecione o arquivo ZIP do plugin que você baixou.
4. Clique em "Instalar Agora" e, em seguida, em "Ativar Plugin".

## Resumo da Versão {VERSION}

{BULLETS copiados da entrada mais recente do CHANGELOG.md (ou do README.txt, seção == Changelog ==). NÃO invente a partir do git log.}
```

### 5. Abrir o PR

Sempre `dev` → `main`:

```bash
gh pr create \
  --base main \
  --head dev \
  --title "VERSION - REPO_NAME (RESUMO_CURTO)" \
  --body "$(cat <<'EOF'
...corpo...
EOF
)"
```

### 6. Confirmar

Mostre a URL retornada pelo `gh` e o comando usado. Se o PR já existir para `dev` → `main`, `gh` vai avisar — não force `--force` sem pedir.

## Regras

- **Nunca** edite arquivos do repo para abrir o PR (é só `gh pr create`).
- Título SEMPRE `dev → main` no formato `VERSION - REPO_NAME (resumo)`.
- Corpo SEMPRE com Testado até, Versão estável, Resumo da Versão e a própria versão.
- Se `version`, `tested_up` ou `php` estiverem divergindo entre header PHP e README.txt, use o **header PHP** e avise.
- Nunca inclua hashes de commit no corpo.
- Resumo e bullets do corpo SEMPRE vindos do changelog da versão atual — nunca do `git log`.
