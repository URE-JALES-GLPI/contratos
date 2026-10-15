# Contratos Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Plugin GLPI `contratos` independente para internet/garantias/licenças com alertas 30/60/90 dias via e-mail + dashboard.

**Architecture:** Tabela única `contracts` + `renewals` + `alert_log`, classes CommonDBTM em `src/`, telas em `front/`, cron diário idempotente, anexos via Documents nativo. Segue padrão `assetmgrstatus`.

**Tech Stack:** PHP 8.1+, GLPI 10–12 API (CommonDBTM, CronTask, Document_Item, Search), MySQL InnoDB, JS/CSS vanilla.

**Spec:** `E:\GPLI\Plugins\contratos\docs\superpowers\specs\2026-10-15-contratos-design.md`

## Global Constraints

- GLPI min 10.0.0, max 12.0.0 — verificado em `setup.php` via `GLPI_VERSION`.
- PSR-4 `GlpiPlugin\Contratos\` → `src/`, CSRF compliant, sem `as any`/supressões.
- Charset/collation via `DBConnection::getDefaultCharset/Collation`, PK sign via `getDefaultPrimaryKeySignOption`.
- E-mail nunca quebra cron — try/catch + error_log, idempotência via `alert_log`.

## Review Focus

- Vencimento alterado após alerta 90 já enviado deve reiniciar ciclo — espera novo alerta para novo vencimento.
- Dias negativos: vencido deve enviar só 1x + 1x/semana, não spam diário.
- Entidade filha vs recursivo: filtro deve respeitar `entities_id` + `is_recursive`.
- Garantia sem item vinculado mas com NF deve ser aceita; sem ambos deve ser rejeitada.
- Renovação com data passada deve ser rejeitada com mensagem clara.

Mapeamento: vencimento alterado → Task 4 Step 1 (novo vencimento libera novo marco); spam vencido → Task 4 Step 1 (vencido_semanal só >7d); entidade recursiva → Task 3 Step 1 (filtro entidade); garantia NF vs item → Task 2 Step 1; renovação passada → Task 2 Step 1 (`Renewal::apply` retorna false).

---

### Task 1: Esqueleto do plugin + schema

**Files:**
- Create: `E:\GPLI\Plugins\contratos\setup.php`
- Create: `E:\GPLI\Plugins\contratos\hook.php`
- Create: `E:\GPLI\Plugins\contratos\composer.json`
- Create: `E:\GPLI\Plugins\contratos\inc\menu.class.php`
- Create: `E:\GPLI\Plugins\contratos\inc\profile.class.php`

**Interfaces:**
- Consumes: nada (primeira task).
- Produces: `plugin_version_contratos(): array`, `plugin_init_contratos(): void`, `plugin_contratos_install/uninstall(): bool`, `PluginContratosProfile::install/uninstall()`, tabelas criadas.

- [ ] **Step 1: Escrever teste de fumaça (lint + schema)**

```php
// tests/smoke_install.php — roda fora do GLPI, só checa sintaxe + tabelas esperadas
// assert file_exists setup.php, hook.php; assert strpos(hook.php,'glpi_plugin_contratos_contracts')!==false
```

- [ ] **Step 2: Rodar lint para ver falhar (arquivos ainda não existem)**

Run: `php -l "E:\GPLI\Plugins\contratos\setup.php"`
Expected: FAIL "No such file"

- [ ] **Step 3: Implementar `setup.php`, `composer.json`, `inc/menu`, `inc/profile` seguindo `assetmgrstatus/setup.php`**

Assinaturas exatas: `plugin_version_contratos(): array` (name `[GEST] Contratos`, version `1.0.0`), `plugin_init_contratos(): void` (registra `menu_toadd tools => PluginContratosMenu`, css/js vazios por ora, `csrf_compliant`), `PluginContratosProfile` com `RIGHTS = [READ, UPDATE, CREATE, DELETE, PURGE]`.

- [ ] **Step 4: Implementar `hook.php` com `plugin_contratos_schema()`, `install()`, `uninstall()`, `update()`**

Tabelas exatas da spec: `glpi_plugin_contratos_contracts`, `_renewals`, `_alert_log` com colunas/índices da spec. `install()` chama schema + `PluginContratosProfile::install()` + registra CronTask `GlpiPlugin\Contratos\Alert::cronCheck` frequency 86400.

- [ ] **Step 5: Verificar lint passa**

Run: `php -l "E:\GPLI\Plugins\contratos\setup.php"; php -l "E:\GPLI\Plugins\contratos\hook.php"`
Expected: PASS "No syntax errors"

- [ ] **Step 6: Commit**

```bash
git add plugins/contratos/setup.php plugins/contratos/hook.php plugins/contratos/composer.json plugins/contratos/inc/
git commit -m "feat(contratos): esqueleto plugin + schema"
```

### Task 2: Modelo Contract + Renewal + validação

**Files:**
- Create: `E:\GPLI\Plugins\contratos\src\Contract.php`
- Create: `E:\GPLI\Plugins\contratos\src\Renewal.php`
- Test: `E:\GPLI\Plugins\contratos\tests\contract_validation.php` (script CLI puro, sem GLPI)

**Interfaces:**
- Consumes: tabelas da Task 1.
- Produces: `GlpiPlugin\Contratos\Contract::diasParaVencimento(string $venc): int`, `::semaforo(int $dias): string` (verde/amarelo/laranja/vermelho/vencido), `::validate(array $in): array $errors`, `GlpiPlugin\Contratos\Renewal::apply(int $id, string $novaData): bool`.

- [ ] **Step 1: Escrever teste failing de validação/semaforo**

```php
assert(Contract::semaforo(95)==='verde');
assert(Contract::semaforo(65)==='amarelo');
assert(!empty(Contract::validate(['tipo'=>'garantia','data_vencimento'=>'2026-01-01']))); // sem item nem NF => erro
```

- [ ] **Step 2: Rodar e ver falhar**

Run: `php "E:\GPLI\Plugins\contratos\tests\contract_validation.php"`
Expected: FAIL "Class not found"

- [ ] **Step 3: Implementar `src/Contract.php extends CommonDBTM` + `src/Renewal.php`**

Regras da spec: internet exige entities_id+operadora; garantia exige (itemtype+items_id) ou numero_nf; licença exige software_name+qtd>=1; vencimento>=inicio; renovação com data passada rejeita.

- [ ] **Step 4: Rodar teste e ver passar**

Run: `php "E:\GPLI\Plugins\contratos\tests\contract_validation.php"`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add plugins/contratos/src/ plugins/contratos/tests/
git commit -m "feat(contratos): modelo Contract/Renewal + validacao"
```

### Task 3: CRUD + dashboard

**Files:**
- Create: `E:\GPLI\Plugins\contratos\front\contract.php`
- Create: `E:\GPLI\Plugins\contratos\front\contract.form.php`
- Create: `E:\GPLI\Plugins\contratos\front\dashboard.php`
- Create: `E:\GPLI\Plugins\contratos\public\css\contratos.css`

**Interfaces:**
- Consumes: `Contract::semaforo()`, `Contract::validate()`, `Renewal::apply()`.
- Produces: telas navegáveis, renovação cria `renewals` e atualiza vencimento.

- [ ] **Step 1: Teste manual checklist (sem automação UI)**

```text
- abrir dashboard => cards 90/60/30/vencidos aparecem
- filtrar tipo=internet + entidade => só internet daquela escola
- renovar => renewals +1 e vencimento muda
```

- [ ] **Step 2: Implementar `front/contract.form.php` (CRUD + Document_Item tab + botão Renovar)**

Segue `assetmgrstatus/front/maintenance.form.php`: `Html::header`, `$item->display()`, `Html::footer`, checkRight.

- [ ] **Step 3: Implementar `front/dashboard.php` (cards + tabela semáforo + export CSV `?export=csv`)**

Ordenação vencimento ASC, limite 200, cores da spec.

- [ ] **Step 4: Lint + revisão visual**

Run: `php -l front/dashboard.php && php -l front/contract.form.php`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add plugins/contratos/front/ plugins/contratos/public/
git commit -m "feat(contratos): CRUD + dashboard semaforo"
```

### Task 4: Cron de alertas 30/60/90

**Files:**
- Create: `E:\GPLI\Plugins\contratos\src\Alert.php`
- Modify: `E:\GPLI\Plugins\contratos\hook.php` (garante CronTask registrado)

**Interfaces:**
- Consumes: `Contract::diasParaVencimento()`, tabela `alert_log`.
- Produces: `GlpiPlugin\Contratos\Alert::cronCheck(CronTask $task): int` (retorna 1 ok, 0 erro), `::shouldAlert(array $row, int $dias): ?string marco`.

- [ ] **Step 1: Escrever teste de marco**

```php
assert(Alert::shouldAlert(['id'=>1],90)==='90');
assert(Alert::shouldAlert(['id'=>1],61)===null);
assert(Alert::shouldAlert(['id'=>1],30)==='30');
assert(Alert::shouldAlert(['id'=>1],-8)==='vencido_semanal'); // com mock de último envio >7d
```

- [ ] **Step 2: Rodar e ver falhar**

Run: `php tests/alert_marco.php`
Expected: FAIL

- [ ] **Step 3: Implementar `Alert::cronCheck` com idempotência + envio via `NotificationMailing`/`mail` + log**

Destinos: responsável + users com direito na entidade. Assunto `[Contratos] {tipo} vence em {dias}d`.

- [ ] **Step 4: Rodar teste e lint**

Run: `php tests/alert_marco.php && php -l src/Alert.php`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add plugins/contratos/src/Alert.php
git commit -m "feat(contratos): cron alertas 30/60/90"
```
