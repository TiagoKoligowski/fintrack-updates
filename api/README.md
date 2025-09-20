Fintrack Update Site (exemplo)

Este diretório contém um site simples (HTML/CSS/JS/PHP) para publicar a versão da plataforma e expor um manifesto JSON de atualização.

Arquivos:
- index.php: página simples que mostra a última versão e link para download.
- manifest.json: manifesto em JSON (dados brutos) com os campos:
  - latest: versão mais recente disponível (semver)
  - download: URL do instalador/patch (.exe)
  - notes: observações (opcional)
- manifest.php: endpoint que serve o manifest.json com headers corretos
  (Content-Type application/json, CORS Allow-Origin: * e no-cache). Use
  este endpoint no frontend para evitar problemas de CORS.

Como usar em produção:
1) Faça upload destes arquivos para seu servidor (ex.: /var/www/fintrack-update ou hosting compartilhado).
2) Atualize o arquivo manifest.json a cada release (versão e URL do instalador).
3) No frontend, defina a variável `VITE_UPDATE_URL` apontando para a URL pública de `manifest.php` (ex.: https://seu-dominio/fintrack-update/manifest.php).

Exemplo de manifest.json:
{
  "latest": "0.1.1",
  "download": "https://seu-dominio/fintrack/Fintrack_Update_0.1.1.exe",
  "notes": "Correções e melhorias diversas"
}

Geração do instalador/patch (.exe):
- Recomenda-se criar um instalador de patch contendo apenas os arquivos alterados e NUNCA incluir pastas de dados do usuário. Exemplos de ferramentas:
  - NSIS (Nullsoft Scriptable Install System)
  - Inno Setup

Diretórios a preservar (não incluir no patch):
- backend/data
- backend/uploads
- prisma/dev.db (se existir)
- qualquer pasta de configuração do usuário

Sugestão de fluxo de release:
1) Gere a nova versão do backend (atualize version em backend/package.json se necessário).
2) Construa frontend e backend (inclua public/ atualizado ao lado do executável, se aplicável).
3) Monte uma pasta PATCH contendo apenas arquivos modificados desde a versão anterior (use `git diff --name-only V_ANTERIOR..V_ATUAL` para listar; copie estes arquivos mantendo a estrutura de diretórios).
4) Compile o instalador de patch a partir da pasta PATCH.
5) Publique o .exe e atualize o manifest.json com a nova versão e URL.

Endpoint JSON com CORS
----------------------

`manifest.php` lê o `manifest.json` e responde com:
- `Content-Type: application/json; charset=utf-8`
- `Access-Control-Allow-Origin: *` (CORS liberado)
- Cabeçalhos de no-cache

Use no frontend a URL pública do `manifest.php` (ex.: `https://seu-dominio/fintrack-update/manifest.php`).
Assim, as requisições do navegador não terão bloqueio de CORS e você pode fazer o fetch normalmente.

Estrutura esperada do JSON de resposta:
{
  "latest": "0.1.1",
  "download": "https://seu-dominio/fintrack/Fintrack_Update_0.1.1.exe",
  "notes": "Correções e melhorias diversas"
}

Página de upload (admin.php)
----------------------------

O arquivo `admin.php` oferece um painel simples para subir um novo instalador e atualizar o `manifest.json` automaticamente.

Passo a passo para habilitar e usar:
- 1) Faça deploy do conteúdo da pasta `update-site/` no seu servidor (ex.: Apache ou Nginx + PHP). Recomenda-se HTTPS.
- 2) No servidor, copie `config.sample.php` para `config.php` e edite as credenciais:
     - `ADMIN_USER` e `ADMIN_PASS` (obrigatório)
     - `ALLOWED_EXTS` (padrão: exe, zip, msi)
     - `MAX_UPLOAD_MB` (tamanho máximo do upload)
     - `BASE_PUBLIC_URL` (opcional; se vazio, é deduzido do host atual)
- 3) Permissões: garanta que o processo do servidor web tenha permissão de escrita em `update-site/` (pelo menos na subpasta `releases/`).
     - Em Linux, algo como: `chown -R www-data:www-data update-site` e `chmod -R 775 update-site/releases`
- 4) Acesse `https://SEU-DOMINIO/caminho/update-site/admin.php` e faça login (Basic Auth).
- 5) Preencha a versão (ex.: 0.1.2), as notas (opcional) e selecione o arquivo do instalador (.exe/.zip/.msi). Clique em "Enviar atualização".
- 6) O arquivo será gravado em `update-site/releases/` e o `manifest.json` será atualizado com `latest`, `download` e `notes`.
- 7) Verifique o manifesto em `manifest.php` e a página `index.php` para confirmar.

Integração com o frontend
-------------------------
- Configure a variável `VITE_UPDATE_URL` no frontend apontando para a URL pública do `manifest.php` (ex.: `https://seu-dominio/fintrack-update/manifest.php`).
- Configure também `VITE_APP_VERSION` com a versão atual da sua plataforma.
- O frontend pode verificar a cada 5 minutos o manifesto e avisar quando houver atualização (exemplos de hook e componentes foram incluídos na resposta do assistente).

Boas práticas de segurança
--------------------------
- Use HTTPS e senha forte no `ADMIN_PASS`.
- Opcional: restrinja o acesso ao `admin.php` por IP no servidor.
- Opcional: mova o `update-site/` para um vHost próprio e mantenha logs de acesso.
