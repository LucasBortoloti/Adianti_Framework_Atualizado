<?php

use Adianti\Control\TAction;
use Adianti\Control\TPage;
use Adianti\Control\TWindow;
use Adianti\Database\TDatabase;
use Adianti\Database\TTransaction;
use Adianti\Widget\Base\TElement;
use Adianti\Widget\Container\TTable;
use Adianti\Widget\Dialog\TMessage;
use Adianti\Widget\Form\TDate;
use Adianti\Widget\Form\TEntry;
use Adianti\Widget\Form\TLabel;
use Adianti\Widget\Form\TRadioGroup;
use Adianti\Widget\Template\THtmlRenderer;
use Adianti\Widget\Wrapper\TDBCombo;
use Adianti\Wrapper\BootstrapFormBuilder;

class VigepiList extends TPage
{
    protected $form;     // registration form
    protected $datagrid; // listing
    protected $pageNavigation;

    use Adianti\Base\AdiantiStandardListTrait;

    /**
     * Page constructor
     */
    public function __construct()
    {
        parent::__construct();

        $this->setDatabase('vigepi');          // defines the database
        $this->setActiveRecord('Programacao');         // defines the active record
        $this->setDefaultOrder('id', 'asc');    // defines the default order
        $this->addFilterField('id', '=', 'id'); // filterField, operator, formField

        $this->form = new BootstrapFormBuilder('form_search_Programacao');
        $this->form->setFormTitle(('Programações Dengue'));

        $programacao_id = new TDBCombo('programacao_id', 'vigepi', 'Programacao', 'id', 'id');
        $pesquisa = new TRadioGroup('pesquisa');
        $output_type  = new TRadioGroup('output_type');

        $this->form->addFields([new TLabel('Programacao Id')], [$programacao_id]);
        $this->form->addFields([new TLabel('Output')],   [$output_type]);

        //$this->form->addFields([new TLabel('Id')], [$id]);

        $pesquisa->setUseButton();
        $pesquisa->setLayout('horizontal');

        $output_type->setUseButton();
        $options = ['html' => 'HTML', 'pdf' => 'PDF', 'rtf' => 'RTF', 'xls' => 'XLS'];
        $output_type->addItems($options);
        $output_type->setValue('pdf');
        $output_type->setLayout('horizontal');

        // $date_from->setMask('dd/mm/yyyy');
        // $date_to->setMask('dd/mm/yyyy');

        $this->form->addAction('Gerar', new TAction(array($this, 'onGenerate')), 'fa:download blue');

        $table = new TTable;
        $table->border = 0;
        $table->style = 'border-collapse:collapse';
        $table->width = '100%';

        parent::add($this->form);

        parent::add($table);
    }

    public function onGenerate()
    {
        try {
            $data = $this->form->getData();
            $programacao_id = $data->programacao_id;

            $this->form->setData($data);

            $source = TTransaction::open('vigepi');

            $query1 = "SELECT p.id AS programacao_id,
                            ag.descricao AS descricao_agravo,
                            ati.sigla AS sigla_atividade_tipo,
                            p.descricao AS descricao_programacao,
                            su.name as agente_nome,
                            p.datahora_inicio AS data_inicio,
                            p.datahora_fim AS data_fim,
                            p.concluida AS concluida,
                            c.semana AS semana_epi,
                            it.sigla AS imovel_tipo_sigla,
                            a.tipo_visita AS recuperados_fechados_recusados,
                            rg.id AS numero_imoveis,
                            q.descricao AS numero_quarteiroes
                       FROM vigepi.atividade a
                  LEFT JOIN vigepi.programacao p ON p.id = a.programacao_id
                  LEFT JOIN vigepi.atividade_tipo ati ON ati.id = p.atividade_tipo_id
                  LEFT JOIN vigepi.calendario c ON c.id = p.calendario_id
                  LEFT JOIN vigepi.agravo ag ON ag.id = p.agravo_id 
                  LEFT JOIN vigepi.reconhecimento_geografico rg ON rg.id = a.rg_id 
                  LEFT JOIN vigepi.imovel_tipo it ON it.id = rg.imovel_tipo_id
                  LEFT JOIN vigepi.foco f ON f.id = p.foco_id
                  LEFT JOIN vigepi.analise an ON an.id = f.analise_id
                  LEFT JOIN vigepi.amostra am ON am.id = an.amostra_id 
                  LEFT JOIN vigepi.deposito d ON d.id = am.deposito_id
                  LEFT JOIN vigepi.deposito_tipo dt ON dt.id = d.deposito_tipo_id
                  LEFT JOIN vigepi.quarteirao q ON q.id = rg.quarteirao_id 
                  LEFT JOIN permission_new.system_user su ON su.id = p.system_user_id
                      WHERE p.id = '{$programacao_id}'
                   ORDER BY p.id";

            $query2 = "SELECT p.id AS programacao_id,
                            a.id AS atividade_id,
                            CASE d.tratado WHEN 'N' THEN 0 WHEN 'S' THEN 1 END AS depositos_tratados,
                            dt.sigla AS deposito_sigla,
                            CASE d.eliminado WHEN 'N' THEN 0 WHEN 'S' THEN 1 END AS depositos_eliminados
                       FROM vigepi.deposito d
                  LEFT JOIN vigepi.atividade a ON a.id = d.atividade_id
                  LEFT JOIN vigepi.programacao p ON p.id = a.programacao_id
                  LEFT JOIN vigepi.reconhecimento_geografico rg ON rg.id = a.rg_id
                  LEFT JOIN vigepi.foco f ON f.id = p.foco_id
                  LEFT JOIN vigepi.quarteirao q ON q.id = f.quarteirao_id 
                  LEFT JOIN vigepi.analise an ON an.id = f.analise_id 
                  LEFT JOIN vigepi.amostra am ON am.id = an.amostra_id 
                  LEFT JOIN vigepi.deposito_tipo dt ON dt.id = d.deposito_tipo_id 
                      WHERE p.id = '{$programacao_id}'
                   ORDER BY p.id";

            $query3 = "SELECT p.id AS programacao_id,
		                SUM(
			                CASE i.tipo_inseticida
				                WHEN 'L' THEN i.peso_em_gramas
				                ELSE 0
			                END
		                ) AS qtd_larvicida_gramas,
		                SUM(
			                CASE i.tipo_inseticida
				                WHEN 'A' THEN i.peso_em_gramas
				                ELSE 0
			                END
		                ) AS qtd_adulticida_gramas
		                FROM vigepi.tratamento t
	                LEFT JOIN vigepi.deposito d ON d.id = t.deposito_id
	                LEFT JOIN vigepi.atividade a ON a.id = d.atividade_id
	                LEFT JOIN vigepi.programacao p ON p.id = a.programacao_id
	                LEFT JOIN vigepi.inseticida i ON i.id = t.inseticida_id
	                LEFT JOIN vigepi.foco f ON f.id = p.foco_id
	                LEFT JOIN vigepi.analise an ON an.id = f.analise_id
	                LEFT JOIN vigepi.amostra am ON am.id = an.amostra_id
		                WHERE p.id = '{$programacao_id}'
                    ORDER BY p.id";

            $query4 = "SELECT p.id AS programacao_id,
	  		                  a.qtd_tubitos AS qtd_tubitos,
	  		                  a.especime_qtd AS qtd_amostras
			            FROM vigepi.amostra a
	                LEFT JOIN vigepi.deposito d ON d.id = a.deposito_id 
	                LEFT JOIN vigepi.atividade AT ON at.id = d.atividade_id 
	                LEFT JOIN vigepi.programacao p ON p.id = at.programacao_id
		                WHERE p.id = '{$programacao_id}'
		            ORDER BY p.id";

            $query5 = "SELECT COUNT(DISTINCT rg.id) AS numero_imoveis_tratados
		                FROM vigepi.deposito d
	            LEFT JOIN vigepi.atividade a ON a.id = d.atividade_id
	            LEFT JOIN vigepi.programacao p ON p.id = a.programacao_id
	            LEFT JOIN vigepi.reconhecimento_geografico rg ON rg.id = a.rg_id
		                WHERE p.id = '{$programacao_id}'
  		            AND d.tratado = 'S'";

            $query6 = " SELECT  p.id AS programacao_id,
                                an.id AS analise_id,
                                dt.sigla AS deposito_sigla,
                                an.larvas_aedes_aegypti AS larvas_aedes_aegypti
		                FROM  vigepi.analise an
	                LEFT JOIN vigepi.amostra am ON am.id = an.amostra_id
	                LEFT JOIN vigepi.deposito d ON d.id = am.deposito_id 
	                LEFT JOIN vigepi.deposito_tipo dt ON dt.id = d.deposito_tipo_id
	                LEFT JOIN vigepi.atividade a ON a.id = d.atividade_id
	                LEFT JOIN vigepi.programacao p ON p.id = a.programacao_id
	                LEFT JOIN vigepi.foco f ON f.id = p.foco_id
		                WHERE p.id = '{$programacao_id}'
		            ORDER BY p.id";

            $query7 = " SELECT  p.id AS programacao_id,
                                an.id AS analise_id,
                                dt.sigla AS deposito_sigla,
                                an.larvas_aedes_albopictus AS larvas_aedes_albopictus
		                FROM  vigepi.analise an
	                LEFT JOIN vigepi.amostra am ON am.id = an.amostra_id
	                LEFT JOIN vigepi.deposito d ON d.id = am.deposito_id 
	                LEFT JOIN vigepi.deposito_tipo dt ON dt.id = d.deposito_tipo_id
	                LEFT JOIN vigepi.atividade a ON a.id = d.atividade_id
	                LEFT JOIN vigepi.programacao p ON p.id = a.programacao_id
	                LEFT JOIN vigepi.foco f ON f.id = p.foco_id
		                WHERE p.id = '{$programacao_id}'
		            ORDER BY p.id";

            $query8 = " SELECT  p.id AS programacao_id,
                                an.id AS analise_id,
                                dt.sigla AS deposito_sigla,
                                an.larvas_outros AS larvas_outros
		                FROM  vigepi.analise an
	                LEFT JOIN vigepi.amostra am ON am.id = an.amostra_id
	                LEFT JOIN vigepi.deposito d ON d.id = am.deposito_id 
	                LEFT JOIN vigepi.deposito_tipo dt ON dt.id = d.deposito_tipo_id
	                LEFT JOIN vigepi.atividade a ON a.id = d.atividade_id
	                LEFT JOIN vigepi.programacao p ON p.id = a.programacao_id
	                LEFT JOIN vigepi.foco f ON f.id = p.foco_id
		                WHERE p.id = '{$programacao_id}'
		            ORDER BY p.id";

            $query9 = "SELECT  p.id AS programacao_id,
        	                   an.id AS analise_id,
        	                   an.larvas_aedes_aegypti AS larvas_aedes_aegypti,
        	                   it.sigla AS imovel_tipo_sigla
		                FROM  vigepi.analise an
	                LEFT JOIN vigepi.amostra am ON am.id = an.amostra_id 
	                LEFT JOIN vigepi.deposito d ON d.id = am.deposito_id 
	                LEFT JOIN vigepi.atividade ati ON ati.id = d.atividade_id 
	                LEFT JOIN vigepi.programacao p ON p.id = ati.programacao_id 
	                LEFT JOIN vigepi.foco f ON f.id = ati.foco_id
	                LEFT JOIN vigepi.reconhecimento_geografico rg ON rg.id = ati.rg_id 
	                LEFT JOIN vigepi.imovel_tipo it ON it.id = rg.imovel_tipo_id 
		                WHERE p.id = '{$programacao_id}'
		            ORDER BY p.id";

            $query10 = "SELECT  p.id AS programacao_id,
        	                    an.id AS analise_id,
        	                    an.larvas_aedes_albopictus AS larvas_aedes_albopictus,
        	                    it.sigla AS imovel_tipo_sigla
		                FROM  vigepi.analise an
	                LEFT JOIN vigepi.amostra am ON am.id = an.amostra_id 
	                LEFT JOIN vigepi.deposito d ON d.id = am.deposito_id 
	                LEFT JOIN vigepi.atividade ati ON ati.id = d.atividade_id 
	                LEFT JOIN vigepi.programacao p ON p.id = ati.programacao_id 
	                LEFT JOIN vigepi.foco f ON f.id = ati.foco_id
	                LEFT JOIN vigepi.reconhecimento_geografico rg ON rg.id = ati.rg_id 
	                LEFT JOIN vigepi.imovel_tipo it ON it.id = rg.imovel_tipo_id 
		                WHERE p.id = '{$programacao_id}'
		            ORDER BY p.id";

            $query11 = "SELECT  p.id AS programacao_id,
        	                    an.id AS analise_id,
        	                    an.larvas_outros AS larvas_outros,
        	                    it.sigla AS imovel_tipo_sigla
		                FROM  vigepi.analise an
	                LEFT JOIN vigepi.amostra am ON am.id = an.amostra_id 
	                LEFT JOIN vigepi.deposito d ON d.id = am.deposito_id 
	                LEFT JOIN vigepi.atividade ati ON ati.id = d.atividade_id 
	                LEFT JOIN vigepi.programacao p ON p.id = ati.programacao_id 
	                LEFT JOIN vigepi.foco f ON f.id = ati.foco_id
	                LEFT JOIN vigepi.reconhecimento_geografico rg ON rg.id = ati.rg_id 
	                LEFT JOIN vigepi.imovel_tipo it ON it.id = rg.imovel_tipo_id 
		                WHERE p.id = '{$programacao_id}'
		            ORDER BY p.id";

            // Executa as consultas
            $rows1 = TDatabase::getData($source, $query1, null, null);
            $rows2 = TDatabase::getData($source, $query2, null, null);
            $rows3 = TDatabase::getData($source, $query3, null, null);
            $rows4 = TDatabase::getData($source, $query4, null, null);
            $rows5 = TDatabase::getData($source, $query5, null, null);
            $rows6 = TDatabase::getData($source, $query6, null, null);
            $rows7 = TDatabase::getData($source, $query7, null, null);
            $rows8 = TDatabase::getData($source, $query8, null, null);
            $rows9 = TDatabase::getData($source, $query9, null, null);
            $rows10 = TDatabase::getData($source, $query10, null, null);
            $rows11 = TDatabase::getData($source, $query11, null, null);

            $data = date('d/m/Y   h:i:s');

            $content = '<html>
            <head>
                <title>Ocorrencias</title>
                <link href="app/resources/vigepi.css" rel="stylesheet" type="text/css" media="screen"/>
            </head>
            <footer></footer>
            <body>
                <div class="header">
                    <table class="cabecalho" style="width:100%">
                        <tr>
                            <td><b><i>PREFEITURA MUNICIPAL DE JARAGUÁ DO SUL</i></b></td>
                        </tr>
                        <tr>
                            <td> prefeitura@jaraguadosul.com.br</td>
                        </tr>
                        <tr>
                            <td>83.102.459/0001-23</td>
                            <td class="data_hora"><b>' . $data . '</b></td>
                        </tr>
                        <tr>
                            <td>(047) 2106-8000</td>
                            <td class="cor_programacao colspan=4">Programação Id: ' . $programacao_id . '</td>
                        </tr>
                    </table>
                </div>';

            // Processa dados da primeira consulta SQL
            $dadosvigepi = [];

            foreach ($rows1 as $row) {
                $programacao_id = $row['programacao_id'];
                if (!isset($dadosvigepi[$programacao_id])) {
                    $dadosvigepi[$programacao_id] = [
                        'descricao_agravo' => $row['descricao_agravo'],
                        'sigla_atividade_tipo' => $row['sigla_atividade_tipo'],
                        'descricao_programacao' => $row['descricao_programacao'],
                        'data_inicio' => $row['data_inicio'],
                        'data_fim' => $row['data_fim'],
                        'concluida' => $row['concluida'],
                        'semana_epi' => $row['semana_epi'],
                        'imovel_tipo_sigla' => [],
                        'recuperados_fechados_recusados' => [],
                        'numero_quarteiroes' => [],
                        'agente_nome' => $row['agente_nome'],
                    ];
                }
                $dadosvigepi[$programacao_id]['imovel_tipo_sigla'][] = $row['imovel_tipo_sigla'];
                $dadosvigepi[$programacao_id]['recuperados_fechados_recusados'][] = $row['recuperados_fechados_recusados'];

                if (!in_array($row['numero_quarteiroes'], $dadosvigepi[$programacao_id]['numero_quarteiroes'])) {
                    $dadosvigepi[$programacao_id]['numero_quarteiroes'][] = $row['numero_quarteiroes'];
                }

                sort($dadosvigepi[$programacao_id]['numero_quarteiroes']);
            }

            // Processa dados da segunda consulta SQL
            $depositoSigla = [];
            $depositosTratadosTotal = 0;
            $depositosEliminadosTotal = 0;

            foreach ($rows2 as $row2) {
                $programacao_id = $row2['programacao_id'];
                $depositoSigla[] = $row2['deposito_sigla'];
                $depositosTratadosTotal += $row2['depositos_tratados'];  // Soma o valor tratado
                $depositosEliminadosTotal += $row2['depositos_eliminados'];
            }

            // Processa dados da terceira consulta SQL
            $qtdLarvicidaGramas = 0;
            $qtdAdulticidaGramas = 0;

            foreach ($rows3 as $row3) {
                $qtdLarvicidaGramas += $row3['qtd_larvicida_gramas'];
                $qtdAdulticidaGramas += $row3['qtd_adulticida_gramas'];
            }

            // Processa dados da quarta consulta SQL
            $qtdTubitos = 0;
            $qtdAmostras = 0;

            foreach ($rows4 as $row4) {
                $qtdTubitos += $row4['qtd_tubitos'];
                $qtdAmostras += $row4['qtd_amostras'];
            }

            $numeroImoveisTratados = 0;

            foreach ($rows5 as $row5) {
                $numeroImoveisTratados = $row5['numero_imoveis_tratados'];
            }

            foreach ($dadosvigepi as $row) {
                // Identifica os diferentes tipos de imovel_tipo_sigla
                $tipos_imovel = ['R', 'C', 'TB', 'PE', 'O'];
                $imovel_count = array_fill_keys($tipos_imovel, 0);
                $total_imoveis = 0;

                // Contabiliza as ocorrências de cada tipo de imóvel
                foreach ($row['imovel_tipo_sigla'] as $tipo) {
                    if (isset($imovel_count[$tipo])) {
                        $imovel_count[$tipo]++;
                    } else {
                        $imovel_count[$tipo] = 1; // Caso encontre um tipo não esperado
                    }
                    $total_imoveis++;
                }

                $totalSiglas = 0;
                $depositoSiglas = [
                    'A1' => 0,
                    'A2' => 0,
                    'B' => 0,
                    'C' => 0,
                    'D1' => 0,
                    'D2' => 0,
                    'E' => 0,
                    'MA' => 0,
                    'ARM' => 0,
                ];

                foreach ($rows2 as $row2) {
                    $sigla = $row2['deposito_sigla'];
                    if (isset($depositoSiglas[$sigla])) {
                        $depositoSiglas[$sigla]++;
                        $totalSiglas++;
                    }
                }

                $tipo_visita = [
                    'N' => 0,
                    'R' => 0,
                    'F' => 0,
                    'E' => 0,
                ];

                foreach ($rows1 as $row1) {
                    $visita = $row1['recuperados_fechados_recusados'];
                    if (isset($tipo_visita[$visita])) {
                        $tipo_visita[$visita]++;
                    }
                }

                $depositosComLarvas = [
                    'A1' => 0,
                    'A2' => 0,
                    'B' => 0,
                    'C' => 0,
                    'D1' => 0,
                    'D2' => 0,
                    'E' => 0,
                    'MA' => 0,
                    'ARM' => 0,
                ];

                $depositosComLarvas2 = [
                    'A1' => 0,
                    'A2' => 0,
                    'B' => 0,
                    'C' => 0,
                    'D1' => 0,
                    'D2' => 0,
                    'E' => 0,
                    'MA' => 0,
                    'ARM' => 0,
                ];

                $depositosComLarvas3 = [
                    'A1' => 0,
                    'A2' => 0,
                    'B' => 0,
                    'C' => 0,
                    'D1' => 0,
                    'D2' => 0,
                    'E' => 0,
                    'MA' => 0,
                    'ARM' => 0,
                ];

                foreach ($rows6 as $row6) {
                    $sigla = $row6['deposito_sigla'];
                    if (isset($depositosComLarvas[$sigla])) {
                        $hasLarvas = $row6['larvas_aedes_aegypti'] > 0;

                        if ($hasLarvas) {
                            $depositosComLarvas[$sigla]++;
                        }
                    }
                }

                foreach ($rows7 as $row7) {
                    $sigla = $row7['deposito_sigla'];
                    if (isset($depositosComLarvas2[$sigla])) {
                        $hasLarvas = $row7['larvas_aedes_albopictus'] > 0;

                        if ($hasLarvas) {
                            $depositosComLarvas2[$sigla]++;
                        }
                    }
                }

                foreach ($rows8 as $row8) {
                    $sigla = $row8['deposito_sigla'];
                    if (isset($depositosComLarvas3[$sigla])) {
                        $hasLarvas = $row8['larvas_outros'] > 0;

                        if ($hasLarvas) {
                            $depositosComLarvas3[$sigla]++;
                        }
                    }
                }

                $imoveisComLarvas = [
                    'R' => 0,
                    'C' => 0,
                    'TB' => 0,
                    'PE' => 0,
                    'O' => 0,
                ];

                $imoveisComLarvas2 = [
                    'R' => 0,
                    'C' => 0,
                    'TB' => 0,
                    'PE' => 0,
                    'O' => 0,
                ];

                $imoveisComLarvas3 = [
                    'R' => 0,
                    'C' => 0,
                    'TB' => 0,
                    'PE' => 0,
                    'O' => 0,
                ];

                foreach ($rows9 as $row9) {
                    $sigla = $row9['imovel_tipo_sigla'];
                    if (isset($imoveisComLarvas[$sigla])) {
                        $hasLarvas = $row9['larvas_aedes_aegypti'] > 0;

                        if ($hasLarvas) {
                            $imoveisComLarvas[$sigla]++;
                        }
                    }
                }

                foreach ($rows10 as $row10) {
                    $sigla = $row10['imovel_tipo_sigla'];
                    if (isset($imoveisComLarvas2[$sigla])) {
                        $hasLarvas = $row10['larvas_aedes_albopictus'] > 0;

                        if ($hasLarvas) {
                            $imoveisComLarvas2[$sigla]++;
                        }
                    }
                }

                foreach ($rows11 as $row11) {
                    $sigla = $row11['imovel_tipo_sigla'];
                    if (isset($imoveisComLarvas3[$sigla])) {
                        $hasLarvas = $row11['larvas_outros'] > 0;

                        if ($hasLarvas) {
                            $imoveisComLarvas3[$sigla]++;
                        }
                    }
                }

                $content .= "
                <table class='borda_tabela' style='width: 100%'>
                    <tr>
                        <td class='borda_inferior_centralizador_titulos'><b>Id</b></td>
                        <td class='borda_inferior_centralizador_titulos'><b>Descrição Agravo</b></td>
                        <td class='borda_inferior_centralizador_titulos'><b>Sigla</b></td>
                        <td class='borda_inferior_centralizador_titulos'><b>Descricao atividade</b></td>
                        <td class='borda_inferior_centralizador_titulos'><b>Período</b></td>
                        <td class='borda_inferior_centralizador_titulos'><b>Semana Epi.</b></td>
                        <td class='borda_inferior_centralizador_titulos'><b>Ultima atualização</b></td>
                        <td class='borda_inferior_centralizador_titulos'><b>Concluido</b></td>
                    </tr>
                    <tr>
                        <td class='borda_direita'>{$programacao_id}</td>
                        <td class='borda_direita'>{$row['descricao_agravo']}</td>
                        <td class='borda_direita'>{$row['sigla_atividade_tipo']}</td>
                        <td class='borda_direita'>{$row['descricao_programacao']}</td>
                        <td class='borda_direita'>{$row['data_inicio']} - {$row['data_fim']}</td>
                        <td class='borda_direita'>{$row['semana_epi']}</td>
                        <td class='borda_direita'>{$row['agente_nome']}</td>
                        <td class='centralizador'>{$row['concluida']}</td>
                    </tr>
                </table>
                <br>
                <table class='tabela_mae_sem_borda' style='width: 100%'>
                    <tr>
                        <td style='width: 50%; padding: 0;'>
                <table class='organizar_tabela' style='width: 100%;'>
                    <tr>
                        <td class='borda_inferior_centralizador_titulos' colspan='9'><b>Tipos de Imóvel</b></td>
                    </tr>
                    <tr>
                        <td class='borda_inferior_centralizador' colspan='2'><b>R</b></td>
                        <td class='borda_inferior_centralizador' colspan='2'><b>C</b></td>
                        <td class='borda_inferior_centralizador'><b>TB</b></td>
                        <td class='borda_inferior_centralizador'><b>PE</b></td>
                        <td class='borda_inferior_centralizador' colspan='2'><b>O</b></td>
                        <td class='borda_inferior_centralizador'><b>Total</b></td>
                    </tr>
                    <tr>
                        <td class='borda_varios_valores' colspan='2'>{$imovel_count['R']}</td>
                        <td class='borda_varios_valores' colspan='2'>{$imovel_count['C']}</td>
                        <td class='borda_varios_valores'>{$imovel_count['TB']}</td>
                        <td class='borda_varios_valores'>{$imovel_count['PE']}</td>
                        <td class='borda_varios_valores' colspan='2'>{$imovel_count['O']}</td>
                        <td class='borda_inferior_centralizador'>{$total_imoveis}</td>
                    </tr>
                    <tr>
                        <td class='borda_varios_valores' colspan='5'><b>Normal(N)</b></td>
                        <td class='borda_inferior_centralizador' colspan='4'><b>Recuperados(R)</b></td>
                    </tr>
                    <tr>
                        <td class='centralizador' colspan='5'>{$tipo_visita['N']}</td>
                        <td class='centralizador' colspan='4'>{$tipo_visita['R']}</td>
                    </tr>
                </table>
                        </td>
                        <td style='width: 50%; padding: 0;'>
                <table class='tabela_dupla' style='width: 100%;'>
                    <tr>
                        <td class='borda_inferior_centralizador_titulos' colspan=9><b>Depósitos</b></td>
                    </tr>
                    <tr>
                        <td class='borda_inferior_centralizador'><b>A1</b></td>
                        <td class='borda_inferior_centralizador'><b>A2</b></td>
                        <td class='borda_inferior_centralizador'><b>B</b></td>
                        <td class='borda_inferior_centralizador'><b>C</b></td>
                        <td class='borda_inferior_centralizador'><b>D1</b></td>
                        <td class='borda_inferior_centralizador'><b>D2</b></td>
                        <td class='borda_inferior_centralizador'><b>E</b></td>
                        <td class='borda_inferior_centralizador'><b>MA</b></td>
                        <td class='borda_inferior_centralizador'><b>ARM</b></td>
                    </tr>
                    <tr>
                        <td class='borda_varios_valores'>{$depositoSiglas['A1']}</td>
                        <td class='borda_varios_valores'>{$depositoSiglas['A2']}</td>
                        <td class='borda_varios_valores'>{$depositoSiglas['B']}</td>
                        <td class='borda_varios_valores'>{$depositoSiglas['C']}</td>
                        <td class='borda_varios_valores'>{$depositoSiglas['D1']}</td>
                        <td class='borda_varios_valores'>{$depositoSiglas['D2']}</td>
                        <td class='borda_varios_valores'>{$depositoSiglas['E']}</td>
                        <td class='borda_varios_valores'>{$depositoSiglas['MA']}</td>
                        <td class='borda_inferior_centralizador'>{$depositoSiglas['ARM']}</td>
                    </tr>
                    <tr>
                        <td class='borda_inferior_centralizador' colspan='5'><b>Fechados(F)</b></td>
                        <td class='borda_inferior_centralizador_titulos' colspan='5'><b>Recusados(E)</b></td>
                    </tr>
                    <tr>
                        <td class='centralizador' colspan='5'>{$tipo_visita['F']}</td>
                        <td class='centralizador' colspan='5'>{$tipo_visita['E']}</td>
                    </tr>
                </table>
                        </td>
                    </tr>
                </table>
                <br>";

                $content .= "<table class='tabela_mae_sem_borda' style='width: 100%'>
                    <tr>
                       <td colspan='2' style='padding: 0px;'>
                            <table class='organizar_tabela' style='width: 100%; margin-bottom: -1px'>
                                <tr>
                                    <td>
                                        <b>Tratamento</b>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td style='width: 50%; padding: 0;'>
                <table class='organizar_tabela' style='width: 100%;'>
                    <tr>
                        <td class='borda_inferior_centralizador_titulos' colspan='2'><b>Número Imóveis Tratados</b></td>
                        <td class='borda_inferior_centralizador_titulos' colspan='2'><b>Quarteirões trabalhados</b></td>
                        <td class='borda_inferior_centralizador_titulos' colspan='2'><b>Qtd Larvicidas (gramas)</b></td>
                        <td class='borda_inferior_centralizador' colspan='2'><b>Qtd Adulticidas (gramas)</b></td>
                    <tr>
                        <td class='borda_direita' colspan='2'>{$numeroImoveisTratados}</td>
                        <td class='borda_direita' colspan='2'>" . implode(', ', $row['numero_quarteiroes']) . "</td>
                        <td class='borda_direita' colspan='2'>{$qtdLarvicidaGramas}</td>
                        <td class='centralizador' colspan='2'>{$qtdAdulticidaGramas}</td>
                    </tr>
                    </table>
                        <td style='width: 50%; padding: 0;'>
                <table class='tabela_dupla' style='width: 100%;'>
                    <tr>
                    <tr>
                        <td class='borda_inferior_centralizador_titulos' colspan='2'><b>Qtd Tubitos</b></td>
                        <td class='borda_inferior_centralizador_titulos' colspan='2'><b>Qtd Amostras</b></td>
                        <td class='borda_inferior_centralizador_titulos' colspan='2'><b>Numéro de Depósitos Tratados</b></td>
                        <td class='borda_inferior_centralizador_titulos' colspan='2'><b>Numéro de Depósitos Eliminados</b></td>
                    </tr>
                    <tr>
                        <td class='centralizador' colspan='2'>{$qtdTubitos}</td>
                        <td class='borda_esquerda_direita' colspan='2'>{$qtdAmostras}</td>
                        <td class='borda_direita' colspan='2'>" . $depositosTratadosTotal . "</td>
                        <td class='centralizador' colspan='2'>" . $depositosEliminadosTotal . "</td>
                    </tr>
                </table>
                    </tr>
                </table>
                <br>
                <table class='tabela_mae_sem_borda' style='width: 100%;'>
                    <tr>
                        <td colspan='2' style='padding: 0px;'>
                            <table class='organizar_tabela' style='width: 100%; margin-bottom: -1px;'>
                                <tr>
                                    <td>
                                        <b>Laboratório</b>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td style='width: 50%; padding: 0;'>
                <table class='organizar_tabela' style='width: 100%;'>
                    <tr>
                        <td class='borda_inferior_centralizador_titulos' colspan='10'>
                            <b>Nº depósitos com espécime por tipo</b>
                        </td>
                    </tr>
                    <tr>
                        <td class='borda_varios_valores'></td>
                        <td class='borda_varios_valores'><b>A1</b></td>
                        <td class='borda_varios_valores'><b>A2</b></td>
                        <td class='borda_varios_valores'><b>B</b></td>
                        <td class='borda_varios_valores'><b>C</b></td>
                        <td class='borda_varios_valores'><b>D1</b></td>
                        <td class='borda_varios_valores'><b>D2</b></td>
                        <td class='borda_varios_valores'><b>E</b></td>
                        <td class='borda_varios_valores'><b>MA</b></td>
                        <td class='borda_inferior_centralizador'><b>ARM</b></td>
                    </tr>
                    <tr>
                        <td class='borda_varios_valores'><b>Aedes Aegypti</b></td>
                        <td class='borda_varios_valores'>{$depositosComLarvas['A1']}</td>
                        <td class='borda_varios_valores'>{$depositosComLarvas['A2']}</td>
                        <td class='borda_varios_valores'>{$depositosComLarvas['B']}</td>
                        <td class='borda_varios_valores'>{$depositosComLarvas['C']}</td>
                        <td class='borda_varios_valores'>{$depositosComLarvas['D1']}</td>
                        <td class='borda_varios_valores'>{$depositosComLarvas['D2']}</td>
                        <td class='borda_varios_valores'>{$depositosComLarvas['E']}</td>
                        <td class='borda_varios_valores'>{$depositosComLarvas['MA']}</td>
                        <td class='borda_inferior_centralizador'>{$depositosComLarvas['ARM']}</td>
                    </tr>
                    <tr>
                        <td class='borda_varios_valores'><b>Aedes Albopictus</b></td>
                        <td class='borda_varios_valores'>{$depositosComLarvas2['A1']}</td>
                        <td class='borda_varios_valores'>{$depositosComLarvas2['A2']}</td>
                        <td class='borda_varios_valores'>{$depositosComLarvas2['B']}</td>
                        <td class='borda_varios_valores'>{$depositosComLarvas2['C']}</td>
                        <td class='borda_varios_valores'>{$depositosComLarvas2['D1']}</td>
                        <td class='borda_varios_valores'>{$depositosComLarvas2['D2']}</td>
                        <td class='borda_varios_valores'>{$depositosComLarvas2['E']}</td>
                        <td class='borda_varios_valores'>{$depositosComLarvas2['MA']}</td>
                        <td class='borda_inferior_centralizador'>{$depositosComLarvas2['ARM']}</td>
                    </tr>
                    <tr>
                        <td class='borda_direita'><b>Outros</b></td>
                        <td class='borda_direita'>{$depositosComLarvas3['A1']}</td>
                        <td class='borda_direita'>{$depositosComLarvas3['A2']}</td>
                        <td class='borda_direita'>{$depositosComLarvas3['B']}</td>
                        <td class='borda_direita'>{$depositosComLarvas3['C']}</td>
                        <td class='borda_direita'>{$depositosComLarvas3['D1']}</td>
                        <td class='borda_direita'>{$depositosComLarvas3['D2']}</td>
                        <td class='borda_direita'>{$depositosComLarvas3['E']}</td>
                        <td class='borda_direita'>{$depositosComLarvas3['MA']}</td>
                        <td class='centralizador'>{$depositosComLarvas3['ARM']}</td>
                    </tr>
                </table>
                        </td>
                        <td style='width: 50%; padding: 0;'>
                <table class='tabela_dupla' style='width: 100%;'>
                    <tr>
                        <td class='borda_inferior_centralizador_titulos' colspan=6>
                            <b>Nº Imóveis com espécime por tipo</b>
                        </td>
                    </tr>
                    <tr>
                        <td class='borda_varios_valores'></td>
                        <td class='borda_varios_valores'><b>R</b></td>
                        <td class='borda_varios_valores'><b>C</b></td>
                        <td class='borda_varios_valores'><b>TB</b></td>
                        <td class='borda_varios_valores'><b>PE</b></td>
                        <td class='borda_inferior_centralizador'><b>O</b></td>
                    </tr>
                    <tr>
                        <td class='borda_varios_valores'><b>Aedes Aegypti</b></td>
                        <td class='borda_varios_valores'>{$imoveisComLarvas['R']}</td>
                        <td class='borda_varios_valores'>{$imoveisComLarvas['C']}</td>
                        <td class='borda_varios_valores'>{$imoveisComLarvas['TB']}</td>
                        <td class='borda_varios_valores'>{$imoveisComLarvas['PE']}</td>
                        <td class='borda_inferior_centralizador'>{$imoveisComLarvas['O']}</td>
                    </tr>
                    <tr>
                        <td class='borda_varios_valores'><b>Aedes Albopictus</b></td>
                        <td class='borda_varios_valores'>{$imoveisComLarvas2['R']}</td>
                        <td class='borda_varios_valores'>{$imoveisComLarvas2['C']}</td>
                        <td class='borda_varios_valores'>{$imoveisComLarvas2['TB']}</td>
                        <td class='borda_varios_valores'>{$imoveisComLarvas2['PE']}</td>
                        <td class='borda_inferior_centralizador'>{$imoveisComLarvas2['O']}</td>
                    </tr>
                    <tr>
                        <td class='borda_direita'><b>Outros</b></td>
                        <td class='borda_direita'>{$imoveisComLarvas3['R']}</td>
                        <td class='borda_direita'>{$imoveisComLarvas3['C']}</td>
                        <td class='borda_direita'>{$imoveisComLarvas3['TB']}</td>
                        <td class='borda_direita'>{$imoveisComLarvas3['PE']}</td>
                        <td class='centralizador'>{$imoveisComLarvas3['O']}</td>
                    </tr>
                </table>
                        </td>
                    </tr>
                </table>
            </body>
            </html>";
            }

            // Debug the final HTML content
            file_put_contents('app/output/debug.html', $content);

            // Dompdf setup
            $options = new \Dompdf\Options();
            $options->setChroot(getcwd());
            $dompdf = new \Dompdf\Dompdf($options);
            $dompdf->loadHtml($content);
            $dompdf->setPaper('A4', 'landscape');
            $dompdf->render();

            file_put_contents('app/output/document.pdf', $dompdf->output());

            $window = TWindow::create(('Document HTML->PDF'), 0.8, 0.8);
            $object = new TElement('object');
            $object->data = 'app/output/document.pdf';
            $object->type = 'application/pdf';
            $object->style = "width: 100%; height:calc(100% - 10px)";
            $object->add('O navegador não suporta a exibição deste conteúdo, <a style="color:#007bff;" target=_newwindow href="' . $object->data . '"> clique aqui para baixar</a>...');

            $window->add($object);
            $window->show();

            TTransaction::close();
        } catch (Exception $e) {
            new TMessage('error', $e->getMessage());
        }
    }
}
