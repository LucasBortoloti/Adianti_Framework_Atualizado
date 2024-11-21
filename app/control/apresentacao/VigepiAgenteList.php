<?php

use Adianti\Control\TAction;
use Adianti\Control\TPage;
use Adianti\Control\TWindow;
use Adianti\Database\TDatabase;
use Adianti\Database\TTransaction;
use Adianti\Widget\Base\TElement;
use Adianti\Widget\Container\TTable;
use Adianti\Widget\Container\TVBox;
use Adianti\Widget\Dialog\TMessage;
use Adianti\Widget\Form\TDate;
use Adianti\Widget\Form\TEntry;
use Adianti\Widget\Form\TLabel;
use Adianti\Widget\Form\TRadioGroup;
use Adianti\Widget\Template\THtmlRenderer;
use Adianti\Widget\Util\TXMLBreadCrumb;
use Adianti\Widget\Wrapper\TDBCombo;
use Adianti\Widget\Wrapper\TDBUniqueSearch;
use Adianti\Wrapper\BootstrapFormBuilder;

class VigepiAgenteList extends TPage
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
        $this->setActiveRecord('Atividade');         // defines the active record
        $this->setDefaultOrder('id', 'asc');    // defines the default order
        $this->addFilterField('id', '=', 'id'); // filterField, operator, formField

        $this->addFilterField('date', '>=', 'date_from', function ($value) {
            return TDate::convertToMask($value, 'dd/mm/yyyy', 'yyyy-mm-dd');
        });

        $this->addFilterField('date', '<=', 'date_to', function ($value) {
            return TDate::convertToMask($value, 'dd/mm/yyyy', 'yyyy-mm-dd');
        });

        $this->form = new BootstrapFormBuilder('form_search_Atividade');
        $this->form->setFormTitle(('Atividade Agentes'));

        // $id = new TEntry('id');
        $data_inicial = new TDate('data_inicial');
        $data_final = new TDate('data_final');

        $atividade_id = new TDBCombo('atividade_id', 'vigepi', 'Atividade', 'id', 'id');
        $servidor_id = new TDBUniqueSearch('servidor_id', 'permission_new', 'Servidor', 'id', 'name');
        $pesquisa = new TRadioGroup('pesquisa');
        $output_type  = new TRadioGroup('output_type');

        // $this->form->addFields([new TLabel('De')], [$data_inicial]);
        $this->form->addFields([new TLabel('De')], [$data_inicial]);
        $this->form->addFields([new TLabel('Até')], [$data_final]);
        $this->form->addFields([new TLabel('Servidor')], [$servidor_id]);
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
            $data_inicial = $data->data_inicial;
            $data_final = $data->data_final;
            $servidor_id = $data->servidor_id;

            $this->form->setData($data);

            $source = TTransaction::open('vigepi');

            TDatabase::execute($source, "SET lc_time_names = 'pt_BR';");

            $query = "SELECT   c.semana 		   AS semana_epi,
							DATE(a.datahora_saida) AS data,
							DATE_FORMAT(a.datahora_saida, '%W') AS dia_da_semana,
							su.name 			AS agente_nome,
							b.nome 				AS bairro_nome,
							q.descricao 		AS numero_quarteiroes,
							ati.sigla 			AS sigla_atividade_tipo,
							sum(
                    			CASE a.tipo_visita 
                    				WHEN 'N' THEN 1
                    				ELSE 0
                    			END
                       		) AS normal,
                       		sum(
                    			CASE a.tipo_visita 
                    				WHEN 'R' THEN 1
                    				ELSE 0
                    			END
                       		) AS recuperado,
							sum(
                    			CASE a.tipo_visita 
                    				WHEN 'F' THEN 1
                    				ELSE 0
                    			END
                       		) AS fechado,                            
							sum(
                    			CASE a.tipo_visita 
                    				WHEN 'E' THEN 1
                    				ELSE 0
                    			END
                       		) AS recusado,
                       		an.larvas_outros 	AS outras_larvas,
                       		f.analise_id 		AS focos_aedes,
                       		sum(
                       			case d.tratado 
                       				when 'S' then 1
                       				else 0
                       			end
                       		) as depositos_tratados              
                       FROM vigepi.atividade a
                  LEFT JOIN vigepi.programacao 					p 	ON p.id 	= a.programacao_id
                  LEFT JOIN vigepi.atividade_tipo 				ati ON ati.id 	= p.atividade_tipo_id
                  LEFT JOIN vigepi.calendario 					c 	ON c.id 	= p.calendario_id
                  LEFT JOIN vigepi.reconhecimento_geografico 	rg 	ON rg.id 	= a.rg_id
                  LEFT JOIN vigepi.bairro 						b 	ON b.id 	= rg.bairro_id 
                  LEFT JOIN vigepi.quarteirao 					q 	ON q.id 	= rg.quarteirao_id
                  LEFT JOIN vigepi.deposito						d   on d.atividade_id 	= a.id
                  LEFT JOIN vigepi.amostra 						am 	on am.deposito_id = d.id
                  LEFT JOIN vigepi.analise 						an 	on an.id = am.analise_id
                  LEFT JOIN vigepi.foco 						f	on f.id  = a.foco_id
                  LEFT JOIN permission_new.system_user 			su 	ON su.id 	= a.system_user_id
                      	WHERE a.datahora_saida >='$data_inicial 00:00' and a.datahora_saida <='$data_final 23:59'";

            //caso não seja filtrado o servidor_id ele puxa só pela data
            if (isset($data->servidor_id) and $data->servidor_id) {

                $query .= "and su.id = '$servidor_id'";
            }

            $query .= "GROUP BY DATE(a.datahora_saida),su.id 
                  ORDER BY a.datahora_saida";

            $rows1 = TDatabase::getData($source, $query, null, null);

            if (empty($rows1)) {
                throw new Exception('Nenhum dado encontrado para o período especificado.');
            }

            $data = date('d/m/y h:i:s');

            $content = '<html>
            <head>
                <title>Agentes</title>
                <link href="app/resources/vigepiagentes.css" rel="stylesheet" type="text/css" media="screen"/>
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
                        </tr>
                        <tr>
                            <td>(047) 2106-8000</td>
                        </tr>
                        <tr>
                            <td><b>Relatório de produção de agentes</b></td>
                            <td class="data_hora_com_cor"><b>' . $data . '</b></td>
                        </tr>
                    </table>
                </div>';

            // $dadosvigepi = [];

            $dados = [];

            foreach ($rows1 as $row) {
                $data_inicial = $row['data'];
                $dataFormatada = date('d/m/Y', strtotime($data_inicial));
                $dados[$data_inicial][] = [
                    'semana_epi' => $row['semana_epi'] ?? 0,
                    'data' => $dataFormatada,
                    'dia_da_semana' => $row['dia_da_semana'],
                    'agente_nome' => $row['agente_nome'],
                    'bairro_nome' => $row['bairro_nome'],
                    'numero_quarteiroes' => $row['numero_quarteiroes'],
                    'sigla_atividade_tipo' => $row['sigla_atividade_tipo'],
                    'normal' => $row['normal'] ?? 0,
                    'fechado' => $row['fechado'] ?? 0,
                    'recuperado' => $row['recuperado'] ?? 0,
                    'recusado' => $row['recusado'] ?? 0,
                    'outras_larvas' => $row['outras_larvas'] ?? 0,
                    'focos_aedes' => $row['focos_aedes'] ?? 0,
                    'depositos_tratados' => $row['depositos_tratados'] ?? 0
                ];
            }

            foreach ($dados as $data_inicial => $rows) {
                $content .= "
            <table class='borda_tabela' style='width: 100%'>
                <tr>
                    <td class='borda_inferior_centralizador_titulos'><b>Semana Epi.</b></td>
                    <td class='borda_inferior_centralizador_titulos'><b>Data</b></td>
                    <td class='borda_inferior_centralizador_titulos'><b>Dia Semana</b></td>
                    <td class='borda_inferior_centralizador_titulos'><b>Servidor</b></td>
                    <td class='borda_inferior_centralizador_titulos'><b>Bairro</b></td>
                    <td class='borda_inferior_centralizador_titulos'><b>Quarteirão</b></td>
                    <td class='borda_inferior_centralizador_titulos'><b>Atividade</b></td>
                    <td class='borda_inferior_centralizador_titulos'><b>Normal</b></td>
                    <td class='borda_inferior_centralizador_titulos'><b>Recuperado</b></td>
                    <td class='borda_inferior_centralizador_titulos'><b>Fechado</b></td>
                    <td class='borda_inferior_centralizador_titulos'><b>Recusado</b></td>
                    <td class='borda_inferior_centralizador_titulos'><b>Outras Larvas</b></td>
                    <td class='borda_inferior_centralizador_titulos'><b>Focos Aedes</b></td>
                    <td class='borda_inferior_centralizador_titulos'><b>Depósito Tratados</b></td>
                </tr>";

                $totalOutrasLarvas = 0;
                $totalFocosAedes = 0;
                $totalDepositosTratados = 0;
                $totalNormal = 0;
                $totalRecuperado = 0;
                $totalFechado = 0;
                $totalRecusados = 0;
                $total1 = 0;
                $total2 = 0;

                foreach ($rows as $dado) {
                    $content .= "
                <tr>
                    <td class='borda_inferior_centralizador_direita'>{$dado['semana_epi']}</td>
                    <td class='borda_inferior_centralizador_direita'>{$dado['data']}</td>
                    <td class='borda_inferior_centralizador_direita'>{$dado['dia_da_semana']}</td>
                    <td class='borda_inferior_centralizador_direita'>{$dado['agente_nome']}</td>
                    <td class='borda_inferior_centralizador_direita'>{$dado['bairro_nome']}</td>
                    <td class='borda_inferior_centralizador_direita'>{$dado['numero_quarteiroes']}</td>
                    <td class='borda_inferior_centralizador_direita'>{$dado['sigla_atividade_tipo']}</td>
                    <td class='borda_inferior_centralizador_direita'>{$dado['normal']}</td>
                    <td class='borda_inferior_centralizador_direita'>{$dado['recuperado']}</td>
                    <td class='borda_inferior_centralizador_direita'>{$dado['fechado']}</td>
                    <td class='borda_inferior_centralizador_direita'>{$dado['recusado']}</td>
                    <td class='borda_inferior_centralizador_direita'>{$dado['outras_larvas']}</td>
                    <td class='borda_inferior_centralizador_direita'>{$dado['focos_aedes']}</td>
                    <td class='borda_inferior_centralizador'>{$dado['depositos_tratados']}</td>
                </tr>";

                    $totalNormal += $dado['normal'];
                    $totalRecuperado += $dado['recuperado'];
                    $totalFechado += $dado['fechado'];
                    $totalRecusados += $dado['recusado'];
                    $totalOutrasLarvas += $dado['outras_larvas'];
                    $totalFocosAedes += $dado['focos_aedes'];
                    $totalDepositosTratados += $dado['depositos_tratados'];

                    $total1 = $totalNormal + $totalRecuperado;
                    $total2 = $totalFechado + $totalRecusados;
                }

                $content .= "
                <tr>
                        <td class='borda_inferior_centralizador_direita' colspan='7'><b>Subtotal:</b></td>
                        <td class='borda_inferior_centralizador_direita'>$totalNormal</td>
                        <td class='borda_inferior_centralizador_direita'>$totalRecuperado</b></td>
                        <td class='borda_inferior_centralizador_direita'>$totalFechado</td>
                        <td class='borda_inferior_centralizador_direita'>$totalRecusados</td>
                        <td class='borda_inferior_centralizador_direita'>$totalOutrasLarvas</td>
                        <td class='borda_inferior_centralizador_direita'>$totalFocosAedes</td>
                        <td class='borda_inferior_centralizador'>$totalDepositosTratados</td>
                </tr>
                <tr>
                        <td class='borda_direita' colspan='7'><b>Total:</b></td>
                        <td class='borda_direita' colspan='2'><b>$total1</b></td>
                        <td class='borda_direita' colspan='2'><b>$total2</b></td>
                        <td class='borda_direita'><b>{$dado['outras_larvas']}</b></td>
                        <td class='borda_direita'><b>{$dado['focos_aedes']}</b></td>
                        <td class='centralizador'><b>{$dado['depositos_tratados']}</b></td>
                </tr>
                </table>
                <br>";
            }

            $content .= "</body>
                </html>";

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
