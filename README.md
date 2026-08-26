# Spectra

Plataforma Laravel + Vue com checkout, área de membros, comunidade, testes clínicos e integração com múltiplos gateways de pagamento.

## Requisitos

- PHP 8.2+ com `pdo_mysql`, `mbstring`, `openssl`, `ctype`, `json`, `tokenizer`, `xml`, `bcmath`, `intl`, `zip`
- MySQL 8+ ou MariaDB
- Node 18+ (apenas para gerar os assets)
- Permissão de escrita em `storage/` e `bootstrap/cache/`
- Apache/LiteSpeed com `.htaccess`, ou regras equivalentes no Nginx

## Instalação

```bash
composer install
cp .env.example .env
php artisan key:generate
# edite o .env: APP_URL e as credenciais do banco
php artisan migrate --force
npm install && npm run build
```

Depois acesse `/criar-admin` para criar o primeiro administrador. A tela redireciona para o login se já existir um.

Em hospedagem sem SSH, envie os arquivos já com `vendor/` e `public/build/` gerados localmente — os dois são versionados justamente por isso.

## Cron

Rotinas de fila e agendamento precisam de uma chamada por minuto:

```
* * * * * cd /caminho/do/projeto && php artisan schedule:run >> /dev/null 2>&1
```

Em hospedagem compartilhada que só aceita URL, defina `CRON_SECRET` no `.env` e aponte para:

```
https://SEU_DOMINIO/cron?token=SEU_CRON_SECRET
```

Sem cron o sistema ainda funciona: `RunScheduleFallback` executa o agendador após requisições do painel, no máximo uma vez por minuto. É um paliativo — prefira o cron de verdade.

## Fila

`QUEUE_CONNECTION=database` por padrão. Com um worker ativo (`php artisan queue:work`), envios de e-mail e webhooks saem do caminho da requisição. Sem worker, o sistema detecta a ausência de heartbeat e executa de forma síncrona, para nada ficar preso na fila.

## Storage

Uploads são separados por visibilidade (`App\Support\StorageVisibility`):

- **Público** — imagem de checkout, logo, avatar. Servido em `/storage/{path}`, sem autenticação, porque o checkout atende visitante anônimo.
- **Restrito** — material de aula, áudio, mídia de comunidade e anexo de teste clínico. Servido em `/arquivo/{path}`, que exige sessão e acesso ao produto.

Ao migrar de uma versão anterior, mova o que ficou exposto:

```bash
php artisan storage:mover-restritos --dry-run   # lista
php artisan storage:mover-restritos             # aplica
```

## Manutenção

- `php artisan migrate --force` — aplica migrations pendentes. Também disponível no painel, em Configurações › Manutenção, para instalações sem SSH.
- `composer audit` — verifica vulnerabilidades nas dependências. Vale rodar periodicamente.

## Configuração

Chaves próprias da aplicação ficam em `config/spectra.php`, com variáveis `SPECTRA_*` no `.env`. Credenciais de gateway, senha de SMTP e chaves de LLM são gravadas cifradas no banco.

Em produção use `LOG_STACK=daily` e `LOG_LEVEL=info`: com `single` o arquivo de log cresce sem rotação.

## Testes

```bash
php artisan test
```

Rodam em SQLite em memória, sem tocar no banco de desenvolvimento.

## Licença

Ver [LICENSE](LICENSE).
