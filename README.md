# Passa

Cartão corporativo pré-pago da Acme. A rede pede autorização e depois informa o que foi capturado ou cancelado. A Marina acompanha a empresa em `/admin`. O portador acompanha o próprio cartão em `/my-card`.

As decisões e os números de P2 e P3 estão no `MODEL.md`.

## Como rodar

PHP 8.4, Composer, Node e Docker.

```bash
make env-up        # Postgres, Redis e Mailpit
composer setup     # dependências, .env, chave, migrations, seed, assets
composer dev       # servidor, fila, logs e Vite
```

A aplicação sobe em `http://127.0.0.1:8000`.

- Painel: `http://127.0.0.1:8000/admin`
- Portador: `http://127.0.0.1:8000/login` e `http://127.0.0.1:8000/my-card`

O `NETWORK_SECRET` é o do `.env.example`. O fuso da aplicação é `America/Sao_Paulo`. O servidor embutido usa 20 workers, o máximo de mensagens simultâneas da avaliação.

Antes de cada cenário, o avaliador roda `php artisan migrate:fresh --seed`.

### Seed

Senha de todos: `password`. Valores em centavos.

| Quem | E-mail | Cartão | Limite mensal | Teto por compra | Situação |
|---|---|---|---:|---:|---|
| Marina | marina@acme.test | — | — | — | só entra no `/admin` |
| Ana | ana@acme.test | tok_ana | 200000 | 80000 | ativa, MCC 7995 bloqueado |
| Bruno | bruno@acme.test | tok_bruno | 50000 | — | ativa |
| Carla | carla@acme.test | tok_carla | 100000 | — | bloqueada |
| Diego | diego@acme.test | tok_diego | 5000000 | — | ativa |

A Acme começa com um depósito de 1000000 centavos em 2026-01-01, no fuso de São Paulo. O disponível da empresa é esse saldo. O disponível de um cartão ativo é o menor entre o limite restante do mês e o disponível da empresa. O da Carla é zero.

## Como testar

Com o Postgres do `make env-up` no ar:

```bash
make test    # Pest
make check   # Rector, Pint e PHPStan, sem alterar arquivo
```

Os testes usam `.env.testing` e o banco `passa_test`, criado pelo `docker/postgres/init.sql` no mesmo servidor. A suíte cobre autorização, events, consultas, o invariante do statement, o painel e o acesso do portador.

## Suposições

- Dinheiro em centavos inteiros, só BRL. O limite restante pode ficar negativo. O disponível mostrado não fica abaixo de zero.
- A autorização e o event respondem dentro do request. A fila não fala com a rede.
- Duas escritas no mesmo saldo travam primeiro a empresa, depois o cartão.
- O depósito da Marina aceita de 1 a 100000000 centavos, grava a data de agora e não leva referência.
- `/my-card` não recebe id de cartão nem de compra. A tela pede os números de novo a cada 4 segundos.
- P1 não foi executado: o enunciado não publica as cinco mensagens. A conta esperada está na seção 4 do `MODEL.md`.
