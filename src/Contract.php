<?php

namespace GlpiPlugin\Contratos;

class Contract extends \CommonDBTM
{
    public static string $rightname = 'plugin_contratos';

    public static function getTypeName($nb = 0): string
    {
        return _n('Contrato', 'Contratos', $nb, 'contratos');
    }

    public static function diasParaVencimento(string $vencimento, ?string $hoje = null): int
    {
        $tz = new \DateTimeZone(date_default_timezone_get() ?: 'UTC');
        $a = new \DateTime($hoje ?? 'today', $tz);
        $a->setTime(0, 0, 0);
        $b = new \DateTime($vencimento, $tz);
        $b->setTime(0, 0, 0);
        $diff = $a->diff($b);
        return (int)$diff->format('%r%a');
    }

    public static function semaforo(int $dias): string
    {
        if ($dias < 0) {
            return 'vencido';
        }
        if ($dias < 30) {
            return 'vermelho';
        }
        if ($dias <= 60) {
            return 'laranja';
        }
        if ($dias <= 90) {
            return 'amarelo';
        }
        return 'verde';
    }

    /**
     * @param array $in campos do formulario
     * @return string[] lista de erros (vazia = valido)
     */
    public static function validate(array $in): array
    {
        $errors = [];
        $tipo = (string)($in['tipo'] ?? '');
        if (!in_array($tipo, ['internet', 'garantia', 'licenca'], true)) {
            $errors[] = 'Tipo invalido.';
        }
        $venc = (string)($in['data_vencimento'] ?? '');
        if ($venc === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $venc)) {
            $errors[] = 'Data de vencimento obrigatoria (AAAA-MM-DD).';
        }
        $ini = (string)($in['data_inicio'] ?? '');
        if ($ini !== '' && $venc !== '' && $ini > $venc) {
            $errors[] = 'Vencimento nao pode ser anterior ao inicio.';
        }
        if ($tipo === 'internet') {
            if ((int)($in['entities_id'] ?? 0) <= 0) {
                $errors[] = 'Internet exige escola/entidade.';
            }
            if (trim((string)($in['operadora'] ?? '')) === '') {
                $errors[] = 'Internet exige operadora.';
            }
        }
        if ($tipo === 'garantia') {
            $hasItem = trim((string)($in['itemtype'] ?? '')) !== '' && (int)($in['items_id'] ?? 0) > 0;
            $hasNf = trim((string)($in['numero_nf'] ?? '')) !== '';
            if (!$hasItem && !$hasNf) {
                $errors[] = 'Garantia exige equipamento vinculado ou numero da NF.';
            }
        }
        if ($tipo === 'licenca') {
            if (trim((string)($in['software_name'] ?? '')) === '') {
                $errors[] = 'Licenca exige nome do software.';
            }
            if ((int)($in['quantidade'] ?? 0) < 1) {
                $errors[] = 'Licenca exige quantidade >= 1.';
            }
        }
        $qtd = $in['quantidade'] ?? null;
        if ($qtd !== null && $qtd !== '' && (int)$qtd < 0) {
            $errors[] = 'Quantidade nao pode ser negativa.';
        }
        foreach (['valor_mensal', 'valor_total'] as $f) {
            if (isset($in[$f]) && $in[$f] !== '' && $in[$f] !== null && !is_numeric($in[$f])) {
                $errors[] = "Campo $f deve ser numerico.";
            }
        }
        return $errors;
    }

    public function prepareInputForAdd($input)
    {
        $errors = self::validate(is_array($input) ? $input : []);
        if (!empty($errors)) {
            \Session::addMessageAfterRedirect(implode(' ', $errors), false, ERROR);
            return false;
        }
        $input['date_creation'] = $input['date_creation'] ?? date('Y-m-d H:i:s');
        return $input;
    }

    public function prepareInputForUpdate($input)
    {
        $merged = array_merge($this->fields ?? [], is_array($input) ? $input : []);
        $errors = self::validate($merged);
        if (!empty($errors)) {
            \Session::addMessageAfterRedirect(implode(' ', $errors), false, ERROR);
            return false;
        }
        $input['date_mod'] = date('Y-m-d H:i:s');
        return $input;
    }

    public function showForm($ID, array $options = []): bool
    {
        $this->initForm($ID, $options);
        $f = $this->fields ?? [];
        echo "<form method='post' action='" . $this->getFormURL() . "'>";
        echo "<table class='tab_cadre_fixe'>";
        echo "<tr><th colspan='4'>" . __('Contrato', 'contratos') . "</th></tr>";
        echo "<tr><td>" . __('Nome') . "</td><td><input type='text' name='name' value='" . htmlspecialchars((string)($f['name'] ?? '')) . "' size='40'></td>";
        echo "<td>" . __('Tipo') . "</td><td><select name='tipo'>";
        foreach (['internet' => 'Internet', 'garantia' => 'Garantia', 'licenca' => 'Licença'] as $v => $l) {
            $sel = (($f['tipo'] ?? '') === $v) ? ' selected' : '';
            echo "<option value='$v'$sel>$l</option>";
        }
        echo "</select></td></tr>";
        echo "<tr><td>" . __('Entidade (escola)') . "</td><td><input type='number' name='entities_id' value='" . (int)($f['entities_id'] ?? 0) . "'></td>";
        echo "<td>" . __('Operadora') . "</td><td><input type='text' name='operadora' value='" . htmlspecialchars((string)($f['operadora'] ?? '')) . "'></td></tr>";
        echo "<tr><td>" . __('Velocidade / Circuito') . "</td><td><input type='text' name='link_velocidade' value='" . htmlspecialchars((string)($f['link_velocidade'] ?? '')) . "'> <input type='text' name='circuito_id' placeholder='circuito' value='" . htmlspecialchars((string)($f['circuito_id'] ?? '')) . "'></td>";
        echo "<td>" . __('NF / Fornecedor') . "</td><td><input type='text' name='numero_nf' value='" . htmlspecialchars((string)($f['numero_nf'] ?? '')) . "'></td></tr>";
        echo "<tr><td>" . __('Software / Chave / Qtd') . "</td><td><input type='text' name='software_name' value='" . htmlspecialchars((string)($f['software_name'] ?? '')) . "'> <input type='text' name='chave' value='" . htmlspecialchars((string)($f['chave'] ?? '')) . "'> <input type='number' name='quantidade' value='" . (int)($f['quantidade'] ?? 1) . "' style='width:70px'></td>";
        echo "<td>" . __('Nº contrato') . "</td><td><input type='text' name='numero_contrato' value='" . htmlspecialchars((string)($f['numero_contrato'] ?? '')) . "'></td></tr>";
        echo "<tr><td>" . __('Valor mensal / total / multa %') . "</td><td><input type='text' name='valor_mensal' value='" . htmlspecialchars((string)($f['valor_mensal'] ?? '')) . "' size='10'> <input type='text' name='valor_total' value='" . htmlspecialchars((string)($f['valor_total'] ?? '')) . "' size='10'> <input type='text' name='multa_percent' value='" . htmlspecialchars((string)($f['multa_percent'] ?? '')) . "' size='6'></td>";
        echo "<td>" . __('Início / Vencimento') . "</td><td><input type='date' name='data_inicio' value='" . htmlspecialchars((string)($f['data_inicio'] ?? '')) . "'> <input type='date' name='data_vencimento' value='" . htmlspecialchars((string)($f['data_vencimento'] ?? '')) . "' required></td></tr>";
        echo "<tr><td>" . __('Responsável (users_id)') . "</td><td><input type='number' name='users_id_responsavel' value='" . (int)($f['users_id_responsavel'] ?? 0) . "'> <label><input type='checkbox' name='renovacao_auto' value='1'" . (!empty($f['renovacao_auto']) ? ' checked' : '') . "> " . __('Renovação auto') . "</label></td>";
        echo "<td>" . __('Status') . "</td><td><select name='status'>";
        foreach (['ativo' => 'Ativo', 'renovado' => 'Renovado', 'encerrado' => 'Encerrado', 'cancelado' => 'Cancelado'] as $v => $l) {
            $sel = (($f['status'] ?? 'ativo') === $v) ? ' selected' : '';
            echo "<option value='$v'$sel>$l</option>";
        }
        echo "</select></td></tr>";
        echo "<tr><td>" . __('Comentário') . "</td><td colspan='3'><textarea name='comment' rows='3' style='width:100%'>" . htmlspecialchars((string)($f['comment'] ?? '')) . "</textarea></td></tr>";
        if ($ID > 0) {
            echo "<tr><td>" . __('Renovar para') . "</td><td colspan='3'><input type='date' name='data_nova'> <input type='text' name='motivo' placeholder='motivo' size='30'> <button type='submit' name='renew' value='1'>" . __('Renovar') . "</button></td></tr>";
        }
        $this->showFormButtons($options);
        echo "</table>";
        \Html::closeForm();
        return true;
    }

    public static function listForDashboard(string $tipo = '', int $entities_id = 0, string $status = ''): array
    {
        global $DB;
        if (!isset($DB) || !$DB->tableExists('glpi_plugin_contratos_contracts')) {
            return [];
        }
        $crit = ['is_deleted' => 0];
        if ($tipo !== '') {
            $crit['tipo'] = $tipo;
        }
        if ($entities_id > 0) {
            $crit['entities_id'] = $entities_id;
        }
        if ($status !== '') {
            $crit['status'] = $status;
        } else {
            $crit['status'] = 'ativo';
        }
        $rows = [];
        try {
            $it = $DB->request([
                'SELECT' => ['id', 'name', 'tipo', 'entities_id', 'data_vencimento', 'status', 'users_id_responsavel'],
                'FROM'   => 'glpi_plugin_contratos_contracts',
                'WHERE'  => $crit,
                'ORDER'  => 'data_vencimento ASC',
                'LIMIT'  => 200,
            ]);
            foreach ($it as $r) {
                $rows[] = $r;
            }
        } catch (\Throwable $e) {
            error_log('[contratos] dashboard: ' . $e->getMessage());
        }
        return $rows;
    }

    public function rawSearchOptions(): array    {
        $tab = [];
        $tab[] = ['id' => 'common', 'name' => __('Contrato', 'contratos')];
        $tab[] = ['id' => 1, 'table' => $this->getTable(), 'field' => 'name', 'name' => __('Nome'), 'datatype' => 'itemlink'];
        $tab[] = ['id' => 2, 'table' => $this->getTable(), 'field' => 'tipo', 'name' => __('Tipo'), 'datatype' => 'string'];
        $tab[] = ['id' => 3, 'table' => $this->getTable(), 'field' => 'data_vencimento', 'name' => __('Vencimento'), 'datatype' => 'date'];
        $tab[] = ['id' => 4, 'table' => $this->getTable(), 'field' => 'status', 'name' => __('Status'), 'datatype' => 'string'];
        $tab[] = ['id' => 5, 'table' => $this->getTable(), 'field' => 'entities_id', 'name' => __('Entidade'), 'datatype' => 'itemlink'];
        return $tab;
    }
}
