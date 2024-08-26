<?php

use Adianti\Control\TPage;
use Adianti\Database\TDatabase;
use Adianti\Database\TTransaction;
use Adianti\Widget\Form\TDate;
use Adianti\Widget\Form\TEntry;
use Adianti\Widget\Form\TRadioGroup;
use Adianti\Widget\Template\THtmlRenderer;

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
    
            // Primeira consulta SQL
            $query1 = "SELECT p.id as programacao_id,
                            ag.descricao as descricao_agravo,
                            ati.sigla as sigla_atividade_tipo,
                            p.descricao as descricao_programacao,
                            a.created_at as periodo,
                            p.concluida as concluida,
                            it.sigla as imovel_tipo_sigla,
                            a.tipo_visita as recuperados_fechados_recusados,
                            rg.id as numero_imoveis,
                            q.descricao as numero_quarteiroes
                       FROM vigepi.atividade a
                  LEFT JOIN vigepi.programacao p ON p.id = a.programacao_id
                  LEFT JOIN vigepi.atividade_tipo ati ON ati.id = p.atividade_tipo_id 
                  LEFT JOIN vigepi.agravo ag ON ag.id = p.agravo_id 
                  LEFT JOIN vigepi.reconhecimento_geografico rg ON rg.id = a.rg_id 
                  LEFT JOIN vigepi.imovel_tipo it ON it.id = rg.imovel_tipo_id
                  LEFT JOIN vigepi.foco f ON f.id = p.foco_id
                  LEFT JOIN vigepi.analise an ON an.id = f.analise_id
                  LEFT JOIN vigepi.amostra am ON am.id = an.amostra_id 
                  LEFT JOIN vigepi.deposito d ON d.id = am.deposito_id
                  LEFT JOIN vigepi.deposito_tipo dt ON dt.id = d.deposito_tipo_id
                  LEFT JOIN vigepi.quarteirao q ON q.id = rg.quarteirao_id 
                      WHERE p.id = '{$programacao_id}'
                   ORDER BY p.id";
    
            // Segunda consulta SQL
            $query2 = "SELECT p.id as programacao_id,
                            a.id as atividade_id,
                            CASE d.tratado WHEN 'N' THEN 0 WHEN 'S' THEN 1 END as depositos_tratados,
                            dt.sigla as deposito_sigla,
                            CASE d.eliminado WHEN 'N' THEN 0 WHEN 'S' THEN 1 END as depositos_eliminados
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

            $query3 = "select p.id as programacao_id,
		                sum(
			                case i.tipo_inseticida
				                when 'L' then i.peso_em_gramas
				                else 0
			                end
		                ) as qtd_larvicida_gramas,
		                sum(
			                case i.tipo_inseticida
				                when 'A' then i.peso_em_gramas
				                else 0
			                end
		                ) as qtd_adulticida_gramas
		                from vigepi.tratamento t
	                left join vigepi.deposito d on d.id = t.deposito_id
	                left join vigepi.atividade a on a.id = d.atividade_id
	                left join vigepi.programacao p on p.id = a.programacao_id
	                left join vigepi.inseticida i on i.id = t.inseticida_id
	                left join vigepi.foco f on f.id = p.foco_id
	                left join vigepi.analise an on an.id = f.analise_id
	                left join vigepi.amostra am on am.id = an.amostra_id
		                WHERE p.id = '{$programacao_id}'
                    ORDER BY p.id";

            $query4 = "select p.id as programacao_id,
	  		                  a.qtd_tubitos as qtd_tubitos,
	  		                  a.especime_qtd as qtd_amostras
			            from vigepi.amostra a
	                left join vigepi.deposito d on d.id = a.deposito_id 
	                left join vigepi.atividade at on at.id = d.atividade_id 
	                left join vigepi.programacao p on p.id = at.programacao_id
		                where p.id = '{$programacao_id}'
		            order by p.id";
    
            // Executa as consultas
            $rows1 = TDatabase::getData($source, $query1, null, null);
            $rows2 = TDatabase::getData($source, $query2, null, null);
            $rows3 = TDatabase::getData($source, $query3, null, null);
            $rows4 = TDatabase::getData($source, $query4, null, null);
    
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
                            <td class="cor_ocorrencia colspan=4">Programação Id: ' . $programacao_id . '</td>
                        </tr>
                    </table>
                </div>';
    
            // Processa dados da primeira consulta SQL
            $dadosvigepi = [];
            $numeroImoveisTotal = 0;

            foreach ($rows1 as $row) {
                $programacao_id = $row['programacao_id'];
                if (!isset($dadosvigepi[$programacao_id])) {
                    $dadosvigepi[$programacao_id] = [
                        'descricao_agravo' => $row['descricao_agravo'],
                        'sigla_atividade_tipo' => $row['sigla_atividade_tipo'],
                        'descricao_programacao' => $row['descricao_programacao'],
                        'periodo' => $row['periodo'],
                        'concluida' => $row['concluida'],
                        'imovel_tipo_sigla' => [],
                        'recuperados_fechados_recusados' => [],
                        'numero_imoveis' => 0,
                        'numero_quarteiroes' => [],
                    ];
                }
                $dadosvigepi[$programacao_id]['imovel_tipo_sigla'][] = $row['imovel_tipo_sigla'];
                $dadosvigepi[$programacao_id]['recuperados_fechados_recusados'][] = $row['recuperados_fechados_recusados'];
                $dadosvigepi[$programacao_id]['numero_imoveis'] += 1;
                
                if (!in_array($row['numero_quarteiroes'], $dadosvigepi[$programacao_id]['numero_quarteiroes'])) {
                    $dadosvigepi[$programacao_id]['numero_quarteiroes'][] = $row['numero_quarteiroes'];
                }

                sort($dadosvigepi[$programacao_id]['numero_quarteiroes']);

                $numeroImoveisTotal++;
            }
            
            // Processa dados da segunda consulta SQL
            $depositoSigla = [];
            $depositosTratadosTotal = 0;
            $depositosEliminadosTotal = 0;
            $numeroImoveis = [];

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

                foreach ($rows1 as $row1){
                    $visita = $row1['recuperados_fechados_recusados'];
                    if(isset($tipo_visita[$visita])){
                        $tipo_visita[$visita]++;
                    }
                }
            
                $content .= "
                <table class='borda_tabela' style='width: 100%'>
                    <tr>
                        <td class='borda_inferior_centralizador'><b>Id</b></td>
                        <td class='borda_inferior_centralizador'><b>Descrição Agravo</b></td>
                        <td class='borda_inferior_centralizador'><b>Sigla</b></td>
                        <td class='borda_inferior_centralizador'><b>Descricao atividade</b></td>
                        <td class='borda_inferior_centralizador'><b>Periodo</b></td>
                        <td class='borda_inferior_centralizador'><b>Concluido</b></td>
                    </tr>
                    <tr>
                        <td class='borda_inferior_e_direita_centralizador'>{$programacao_id}</td>
                        <td class='borda_inferior_e_direita_centralizador'>{$row['descricao_agravo']}</td>
                        <td class='borda_inferior_e_direita_centralizador'>{$row['sigla_atividade_tipo']}</td>
                        <td class='borda_inferior_e_direita_centralizador'>{$row['descricao_programacao']}</td>
                        <td class='borda_inferior_e_direita_centralizador'>{$row['periodo']}</td>
                        <td class='borda_inferior_e_direita_centralizador'>{$row['concluida']}</td>
                    </tr>
            </table>
            <br>";
            
            $content .= "
                <table class='borda_tabela' style='width: 100%'>
                    <tr>
                        <td class='borda_inferior_centralizador' colspan='6'><b>Tipos de Imóvel</b></td>
                    </tr>
                    <tr>
                        <td class='centralizador'><b>R</b></td>
                        <td class='centralizador'><b>C</b></td>
                        <td class='centralizador'><b>TB</b></td>
                        <td class='centralizador'><b>PE</b></td>
                        <td class='centralizador'><b>O</b></td>
                        <td class='centralizador'><b>Total</b></td>
                    </tr>
                    <tr>
                        <td class='borda_inferior_centralizador'>{$imovel_count['R']}</td>
                        <td class='borda_inferior_centralizador'>{$imovel_count['C']}</td>
                        <td class='borda_inferior_centralizador'>{$imovel_count['TB']}</td>
                        <td class='borda_inferior_centralizador'>{$imovel_count['PE']}</td>
                        <td class='borda_inferior_centralizador'>{$imovel_count['O']}</td>
                        <td class='borda_inferior_centralizador'>{$total_imoveis}</td>
                    </tr>
                    <tr>
                        <td class='borda_inferior_centralizador'><b>Normal(N)</b></td>
                        <td class='borda_inferior_centralizador'><b>Recuperados(R)</b></td>
                        <td class='borda_inferior_centralizador'><b>Fechados(F)</b></td>
                        <td class='borda_inferior_centralizador'><b>Recusados(E)</b></td>
                        <td class='borda_inferior_centralizador'><b>Número Imóveis Tratados</b></td>
                        <td class='borda_inferior_centralizador'><b>Número Quarteirões</b></td>
                    </tr>
                    <tr>
                        <td class='borda_direita_esquerda'>{$tipo_visita['N']}</td>
                        <td class='borda_direita_esquerda'>{$tipo_visita['R']}</td>
                        <td class='borda_direita_esquerda'>{$tipo_visita['F']}</td>
                        <td class='borda_direita_esquerda'>{$tipo_visita['E']}</td>
                        <td class='borda_direita_esquerda'>{$row['numero_imoveis']}</td>
                        <td class='borda_direita_esquerda'>" . implode(', ', $row['numero_quarteiroes']) . "</td>
                    </tr>
            </table>
            <br>";
    
            $content .= "
                <table class='borda_tabela' style='width: 100%'>
                    <tr>
                        <td class='borda_inferior_centralizador' colspan=9><b>Depósito Sigla</b></td>
                    </tr>
                    <tr>
                        <td class='centralizador'><b>A1</b></td>
                        <td class='centralizador'><b>A2</b></td>
                        <td class='centralizador'><b>B</b></td>
                        <td class='centralizador'><b>C</b></td>
                        <td class='centralizador'><b>D1</b></td>
                        <td class='centralizador'><b>D2</b></td>
                        <td class='centralizador'><b>E</b></td>
                        <td class='centralizador'><b>MA</b></td>
                        <td class='centralizador'><b>ARM</b></td>
                    </tr>
                    <tr>
                        <td class='borda_inferior_centralizador'>{$depositoSiglas['A1']}</td>
                        <td class='borda_inferior_centralizador'>{$depositoSiglas['A2']}</td>
                        <td class='borda_inferior_centralizador'>{$depositoSiglas['B']}</td>
                        <td class='borda_inferior_centralizador'>{$depositoSiglas['C']}</td>
                        <td class='borda_inferior_centralizador'>{$depositoSiglas['D1']}</td>
                        <td class='borda_inferior_centralizador'>{$depositoSiglas['D2']}</td>
                        <td class='borda_inferior_centralizador'>{$depositoSiglas['E']}</td>
                        <td class='borda_inferior_centralizador'>{$depositoSiglas['MA']}</td>
                        <td class='borda_inferior_centralizador'>{$depositoSiglas['ARM']}</td>
                    </tr>
                    <tr>
                        <td class='borda_inferior_centralizador' colspan='4'><b>Depósitos Tratados</b></td>
                        <td class='borda_inferior_centralizador' colspan='5'><b>Depósitos Eliminados</b></td>
                    </tr>
                    <tr>
                        <td class='borda_direita_esquerda' colspan='4'>" . $depositosTratadosTotal . "</td>
                        <td class='borda_direita_esquerda' colspan='5'>" . $depositosEliminadosTotal . "</td>
                    </tr>
                        </table>
                    <br>
                <table class='borda_tabela' style='width: 100%'>
                    <tr>
                        <td class='borda_inferior_centralizador'><b>Qtd Larvicidas (gramas)</b></td>
                        <td class='borda_inferior_centralizador'><b>Qtd Adulticidas (gramas)</b></td>
                    </tr>
                    <tr>
                        <td class='borda_inferior_e_direita_centralizador'>{$qtdLarvicidaGramas}</td>
                        <td class='borda_inferior_e_direita_centralizador'>{$qtdAdulticidaGramas}</td>
                    </tr>
                </table>
                <br>
                <table class='borda_tabela' style='width: 100%'>
                    <tr>
                        <td class='borda_inferior_centralizador'><b>Qtd Tubitos</b></td>
                        <td class='borda_inferior_centralizador'><b>Qtd Amostras</b></td>
                    </tr>
                    <tr>
                        <td class='borda_inferior_e_direita_centralizador'>{$qtdTubitos}</td>
                        <td class='borda_inferior_e_direita_centralizador'>{$qtdAmostras}</td>
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
