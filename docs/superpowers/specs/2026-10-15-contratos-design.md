# Gestão de Contratos e Vencimentos — Design Spec

Data: 2026-10-15
Plugin: `contratos` (GLPI 10–12)
Pasta: `E:\GPLI\Plugins\contratos` (iniciou vazia — greenfield)
Padrão de referência: `assetmgrstatus` (setup.php, hook.php schema, Profile, CronTask, front/, ajax/, public/)

## 1. Entendimento validado

- **Objetivo:** não perder vencimentos de internet das escolas, garantias e licenças.
- **Escopo:** plugin independente com tabelas próprias (não estende Contrato nativo).
- **Alertas:** e-mail + dashboard, marcos 30/60/90 dias antes.
- **Campos extras obrigatórios:** anexos/documentos, valores e multa, responsáveis, renovação/aditivos.
- **Compatibilidade:** GLPI 10.0–12.0. Escolas = entidades GLPI (filtro por entidade).

## 2. Decisão de abordagem

Escolhida **A — Independente completo** (recomendada).

- B (integrado ao Contrato nativo) descartado: acoplamento ao core, migração complexa.
- C (MVP sem e-mail) descartado: não resolve o alerta pedido.

## 3. Arquitetura

```
contratos/
  setup.php               (version 1.0.0, min GLPI 10.0.0 max 12.0.0, menu, CSS/JS, csrf_compliant)
  hook.php                (schema install/update/uninstall, add_columns para upgrades)
  composer.json           (psr-4 GlpiPlugin\Contratos\ -> src/)
  inc/menu.class.php      (menu Ferramentas/Contratos)
  inc/profile.class.php   (direitos READ/UPDATE/CREATE/DELETE/PURGE, addtabon Profile)
  src/Contract.php        (CommonDBTM, tipos, cálculo dias_para_vencimento, status semáforo)
  src/Renewal.php         (CommonDBTM, aditivos)
  src/Alert.php           (Cron + envio e-mail + log idempotente)
  front/contract.php      (lista/search)
  front/contract.form.php (CRUD + anexos via Document_Item + renovar)
  front/dashboard.php     (semaforo, contadores, filtros, export CSV)
  ajax/                   (filtros dinâmicos, preview vencimento)
  public/css/ + public/js/
```

Registro: `Plugin::registerClass(Contract::class)`, `menu_toadd`, `CronTask` diário.
Permissões via `Session::haveRight('plugin_contratos', READ)`.

## 4. Modelo de dados

### glpi_plugin_contratos_contracts
- id, name, tipo (internet|garantia|licenca), entities_id (escola), suppliers_id, users_id_responsavel
- Internet: operadora, link_velocidade, circuito_id
- Garantia: itemtype, items_id, numero_nf, fornecedor_garantia
- Licença: software_name, chave, quantidade, versao
- numero_contrato, valor_mensal, valor_total, multa_percent, reajuste_indice
- data_inicio (DATE), data_vencimento (DATE NOT NULL, INDEX), renovacao_auto (TINYINT), status (ativo|renovado|encerrado|cancelado)
-fir comment, date_creation, date_mod, is_deleted
- INDEX (tipo, data_vencimento, entities_id, status)

### glpi_plugin_contratos_renewals
- id, contracts_id (INDEX), data_antiga, data_nova, motivo, valor_novo, users_id, date_creation

### glpi_plugin_contratos_alert_log
- id, contracts_id (INDEX), marco (90|60|30|0|vencido_semanal), date_creation
- UNIQUE (contracts_id, marco, DATE(date_creation)) lógica via código para idempotência — um envio por marco por vencimento; reenvio só se data_vencimento mudar.

Anexos: usa `glpi_documents` + `glpi_documents_items` nativo (addtabon Document), sem tabela própria de arquivo.

## 5. Fluxo de alertas

Cron diário `ContratosAlert::cronCheckVencimentos` (frequency 86400, mode 2):
1. Busca `status=ativo AND data_vencimento IS NOT NULL AND is_deleted=0`.
2. Calcula `dias = data_vencimento - hoje`.
3. Mapeia: dias==90 → marco 90; dias==60 → 60; dias==30 → 30; dias==0 → 0; dias<0 e (sem envio há 7 dias) → vencido_semanal.
4. Se já existe log para (contracts_id, marco, mesmo vencimento), pula.
5. Destinatários: users_id_responsavel + usuários com direito plugin_contratos na entidade + e-mail gestor da entidade (configurável).
6. Envia com link direto `front/contract.form.php?id=X`, assunto `[Contratos] {tipo} vence em {dias}d — {name} ({escola})`.
7. Grava alert_log. Erro de mail → error_log + continua (não quebra cron).

## 6. Dashboard

- Cards: a vencer 90/60/30, vencidos, renovação_auto ligada.
- Tabela com semáforo: verde >90, amarelo 60–90, laranja 30–60, vermelho <30, cinza vencido.
- Filtros: tipo, entidade/escola (+ recursivo), status, responsável, renovação_auto, busca texto.
- Ações: ver, renovar (cria renewal + atualiza vencimento), export CSV.
- Sem paginação pesada inicial: Search::show + limite 200, ordenado por vencimento ASC.

## 7. Renovação / aditivos

- Botão Renovar no form: pede nova data + motivo + valor opcional → insere em renewals, atualiza contracts.data_vencimento, limpa alert_log futuro (mantém histórico), muda status para renovado temporariamente ou mantém ativo com comentário.
- Encerrar/Cancelar: muda status, sai do cron.

## 8. Erros e segurança

- CSRF compliant, `Session::checkRight`, escape em e-mail/HTML.
- Validação: vencimento >= início; tipo internet exige entities_id + operadora; garantia exige itemtype/items_id ou NF; licença exige software + qtd>=1.
- Falha de e-mail não bloqueia cron; tabela alert_log evita spam.

## 9. Testes

1. Cadastrar 1 de cada tipo com vencimento em 95/65/35/5 dias e vencido.
2. Rodar cron manualmente, conferir 4 e-mails + sem duplicata na 2ª execução.
3. Mudar vencimento → novo ciclo de alertas dispara.
4. Renovar → renewal criado + dashboard atualiza.
5. Perfil sem direito não vê menu; perfil leitura não edita.
6. Anexo PDF abre via Documents.

## 10. Fora de escopo (YAGNI)

- Integração automática com operadoras/fornecedores, leitura de NF-e, BI avançado, app mobile, workflow de aprovação multi-nível, SLA de chamados. Ficam para fase 2 se necessário.

## Self-review

- Sem TBD/TODO; requisitos com valores explícitos (90/60/30, cores, frequenza cron).
- Consistência: tabelas ↔ fluxos ↔ dashboard alinhados; anexos via nativo evita duplicação.
- Escopo: cabe em um plano único (plugin novo, 3 tabelas, cron, 3 telas).
- Ambiguidade resolvida: independente (não nativo), e-mail+dashboard, todos extras incluídos, GLPI 10–12.
