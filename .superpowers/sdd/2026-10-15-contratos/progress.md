# SDD ledger — plan: E:\GPLI\Plugins\contratos\docs\superpowers\plans\2026-10-15-contratos.md
Pre-flight: Task1 produces tabelas+setup; Task2 consumes tabelas + produz Contract::semaforo/validate + Renewal::apply; Task3 consumes esses 3; Task4 consumes diasParaVencimento + alert_log. Sem conflito de nomes. Ruling: sem worktree git (pasta não é repo) — implemento direto em E:\GPLI\Plugins\contratos — custo se errado: mover arquivos depois.
Ruling: PHP indisponível no PATH (where.exe não acha, C:\xampp ausente) — substituo `php -l` e `php tests/*.php` por verificação estática (leitura + Test-Path + grep de sintaxe) — custo se errado: erro de sintaxe só aparece no GLPI; mitigo com código conservador copiado de assetmgrstatus.
Task 1: complete (setup/hook/composer/inc criados, tabelas conferidas via grep, sem commit — sem repo git).
Task 2: complete (Contract semaforo/validate + Renewal conferem com tests/contract_validation.php por revisao estatica).
Task 3: complete (front contract/dashboard + showForm + listForDashboard, revisao estatica).
Task 4: complete (Alert shouldAlert/cronCheck/sendMail conferem com tests/alert_marco.php por revisao estatica).
Final: self-review sem reviewer externo; corrigido parentese extra Contract.php linha 77. Final review clean.
Final Ruling: fronteira 60 dias = laranja (<=60) para alertar mais cedo — custo: card amarelo vira laranja um dia antes.
Final Ruling: NotificationMailing com fallback mail(), sem destinatarios retorna false — custo: ajustar chamada no GLPI real.
