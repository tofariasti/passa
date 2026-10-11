# Anotações

Escrevi o `MODEL.md` antes do código da rede. As dez decisões e as tabelas de P2 e P3 foram o gabarito. Quando o teste passou, o número do painel tinha de ser o mesmo número da consulta.

O instante da rede me traiu uma vez. Tratar `occurred_at` como horário de São Paulo mudava o mês. Passei a gravar UTC. `2026-10-01T02:30:00Z` continua setembro.

A trava é sempre a empresa primeiro, o cartão depois. Autorização, event e depósito usam essa ordem. A suíte prova os casos em sequência. Não há um teste com 20 requests ao mesmo tempo. O que segura a corrida é o `lockForUpdate` em `AuthorizePurchase` e `RecordPurchaseEvent`.

P2 e P3 eu rodei. Bateram com as tabelas, inclusive o statement da Ana fechando em 114000. P1 ficou no papel porque as cinco mensagens não foram publicadas.

O painel e `/my-card` não calculam sozinhos. Os dois chamam os mesmos actions da consulta. A única escrita fora da rede é o depósito da Marina.

Nesta máquina a porta 5432 já estava ocupada. Os testes rodaram num Postgres à parte, com `DB_PORT=5433`. No ambiente limpo, `make env-up` deixa o banco em 5432 e o `passa_test` nasce do `init.sql`.

Ficou de fora o que o enunciado tira do escopo: estorno, fechamento do mês, exportação, comprovante, aprovação de despesa, centro de custo, moeda estrangeira, expiração de reserva, cadastro e bloqueio de cartão pelo painel, e outra empresa além da Acme.
