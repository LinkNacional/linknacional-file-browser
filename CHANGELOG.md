# 1.1.0 - 24/09/26
* Redesign completo das interfaces (admin e frontend): coleções na sidebar, barra de ferramentas, painel de detalhes (drawer), toasts e visual renovado.
* Favoritos: marque arquivos e pastas como favoritos e navegue por eles em uma coleção dedicada (admin e frontend).
* Recentes: coleção com os arquivos adicionados mais recentemente (admin e frontend).
* Lixeira: exclusão reversível com restaurar, excluir permanentemente e esvaziar lixeira (admin).
* Copiar: duplicar arquivos e árvores de pastas inteiras.
* Mover: mover arquivos e pastas via arrastar e soltar ou pelo modal "Mover para…", com proteção contra ciclos.
* Ações em massa: selecionar vários itens e excluir de uma vez.
* Upload: arrastar e soltar com fila de múltiplos arquivos e progresso; suporte a WEBP.
* Escopo do shortcode: novos atributos `root` e `exclude` para iniciar em uma pasta e ocultar pastas (sem diferenciar maiúsculas/minúsculas).
* Atualização do banco de dados automática ao atualizar (colunas de favoritos/lixeira) sem reativar o plugin.
* Correção do breadcrumb das coleções — exibe apenas a coleção selecionada, com o ícone correspondente.
* Visualizador fullscreen: abre PDF, Office e imagens em um popup com zoom nítido (PDF.js), navegação por páginas e pan.
* Preview de Office: arquivos Word/Excel/PowerPoint convertidos em PDF no servidor para visualização (sem virar download).
* Link temporário de compartilhamento por arquivo (válido por 1 hora), utilizável fora da sessão e por IAs.
* Restrição de download por arquivo: o arquivo continua visível, mas o download é bloqueado.
* Copiar para IA: exporta o markdown com links de compartilhamento dos arquivos.
* Imagens restritas renderizadas em canvas, com bloqueio de clique-direito/arrastar.

# 1.0.2 - 03/08/2026
* Bug: Carregamento de adaptação do LiteSpeed.
