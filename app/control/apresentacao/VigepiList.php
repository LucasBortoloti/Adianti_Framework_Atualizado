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
                            ati.descricao as descricao_atividade,
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
                   ORDER BY p.id = '{$programacao_id}'";
    
            // Segunda consulta SQL
            $query2 = "SELECT p.id as programacao_id,
                            a.id as atividade_id,
                            SUM(CASE d.tratado WHEN 'N' THEN 0 WHEN 'S' THEN 1 END) as depositos_tratados,
                            SUM(CASE i.tipo_inseticida WHEN 'L' THEN i.peso_em_gramas ELSE 0 END) as qtd_larvicida_gramas,
                            SUM(CASE i.tipo_inseticida WHEN 'A' THEN i.peso_em_gramas ELSE 0 END) as qtd_adulticida_gramas,
                            am.qtd_tubitos as qtd_tubitos,
                            am.especime_qtd as qtd_amostras,
                            dt.sigla as deposito_sigla,
                            d.eliminado as depositos_eliminados,
                            rg.id as numero_imoveis
                       FROM vigepi.tratamento t
                  LEFT JOIN vigepi.deposito d ON d.id = t.deposito_id
                  LEFT JOIN vigepi.atividade a ON a.id = d.atividade_id
                  LEFT JOIN vigepi.programacao p ON p.id = a.programacao_id
                  LEFT JOIN vigepi.inseticida i ON p.id = t.inseticida_id
                  LEFT JOIN vigepi.reconhecimento_geografico rg ON rg.id = a.rg_id
                  LEFT JOIN vigepi.foco f ON f.id = p.foco_id
                  LEFT JOIN vigepi.deposito_tipo dt ON dt.id = d.deposito_tipo_id 
                  LEFT JOIN vigepi.quarteirao q ON q.id = f.quarteirao_id 
                  LEFT JOIN vigepi.analise an ON an.id = f.analise_id 
                  LEFT JOIN vigepi.amostra am ON am.id = an.amostra_id 
                      WHERE p.id = '{$programacao_id}'
                   GROUP BY t.id       
                   ORDER BY t.id";
    
            // Executa as consultas
            $rows1 = TDatabase::getData($source, $query1, null, null);
            $rows2 = TDatabase::getData($source, $query2, null, null);
    
            $data = date('d/m/Y   h:i:s');
    
            $content = '<html>
            <head> 
                <title>Ocorrencias</title>
                <link href="app/resources/sinistro.css" rel="stylesheet" type="text/css" media="screen"/>
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
    
            // Adiciona resultados da primeira consulta
            foreach ($rows1 as $row) {
                $content .= "
                    <table class='borda_tabela' style='width: 100%'>
                        <tr>
                            <td class='borda_inferior_centralizador'><b>Id</b></td> 
                            <td class='borda_inferior'><b>DescriçãoAgravo</b></td>
                            <td class='borda_inferior_centralizador'><b>Sigla</b></td>
                            <td class='borda_inferior_centralizador'><b>Descricao atividade</b></td>
                            <td class='borda_inferior_centralizador'><b>Periodo</b></td>
                        </tr>
                        <tr>
                            <td class='borda_inferior_e_direita_centralizador'>{$row['programacao_id']}</td>
                            <td class='borda_inferior_e_direita_centralizador'>{$row['descricao_agravo']}</td>
                            <td class='borda_inferior_e_direita_centralizador'>{$row['sigla_atividade_tipo']}</td>
                            <td class='borda_inferior_e_direita_centralizador'>{$row['descricao_atividade']}</td>
                            <td class='borda_inferior_e_direita_centralizador'>{$row['periodo']}</td>
                        </tr>
                        <tr>
                            <td class='borda_inferior_centralizador'><b>Concluido</b></td> 
                            <td class='borda_inferior_centralizador'><b>Imovel Sigla</b></td>
                            <td class='borda_inferior_centralizador'><b>Recuperados, Fechados ou Recusados</b></td>
                            <td class='borda_inferior_centralizador'><b>Número Imóveis</b></td>
                            <td class='borda_inferior_centralizador'><b>Número Quarteirões</b></td>
                        </tr>
                        <tr>
                            <td class='borda_direita'>{$row['concluida']}</td>
                            <td class='centralizador'>{$row['imovel_tipo_sigla']}</td>
                            <td class='borda_direita_esquerda'>{$row['recuperados_fechados_recusados']}</td>
                            <td class='borda_direita_esquerda'>{$row['numero_imoveis']}</td>
                            <td class='centralizar'>{$row['numero_quarteiroes']}</td>
                        </tr>
                    </table>
                    <br>";
            }

            foreach ($rows2 as $row2) {
                $content .= "
                    <table class='borda_tabela' style='width: 100%'>
                        <tr>
                            <td class='borda_inferior_centralizador'><b>Id Programação</b></td> 
                            <td class='borda_inferior_centralizador'><b>Id Atividade</b></td>
                            <td class='borda_inferior_centralizador'><b>Depósitos Tratados</b></td>
                            <td class='borda_inferior_centralizador'><b>Qtd Larvicida (g)</b></td>
                            <td class='borda_inferior_centralizador'><b>Qtd Adulticida (g)</b></td>
                        </tr>
                        <tr>
                            <td class='borda_inferior_e_direita_centralizador'>{$row2['programacao_id']}</td>
                            <td class='borda_inferior_e_direita_centralizador'>{$row2['atividade_id']}</td>
                            <td class='borda_inferior_e_direita_centralizador'>{$row2['depositos_tratados']}</td>
                            <td class='borda_inferior_e_direita_centralizador'>{$row2['qtd_larvicida_gramas']}</td>
                            <td class='borda_inferior_e_direita_centralizador'>{$row2['qtd_adulticida_gramas']}</td>
                        </tr>
                        <tr>
                            <td class='borda_inferior_centralizador'><b>Qtd Tubitos</b></td> 
                            <td class='borda_inferior_centralizador'><b>Qtd Amostras</b></td>
                            <td class='borda_inferior_centralizador'><b>Depósito Sigla</b></td>
                            <td class='borda_inferior_centralizador'><b>Depósitos Eliminados</b></td>
                            <td class='borda_inferior_centralizador'><b>Número Imóveis</b></td>
                        </tr>
                        <tr>
                            <td class='borda_direita'>{$row2['qtd_tubitos']}</td>
                            <td class='centralizador'>{$row2['qtd_amostras']}</td>
                            <td class='borda_direita_esquerda'>{$row2['deposito_sigla']}</td>
                            <td class='borda_direita_esquerda'>{$row2['depositos_eliminados']}</td>
                            <td class='centralizar'>{$row2['numero_imoveis']}</td>
                        </tr>
                    </table>
                    <br>";
            }
                $content .= "</body></html>";

            // Debug the final HTML content
            file_put_contents('app/output/debug.html', $content);

            // Dompdf setup
            $options = new \Dompdf\Options();
            $options->setChroot(getcwd());
            $dompdf = new \Dompdf\Dompdf($options);
            $dompdf->loadHtml($content);
            $dompdf->setPaper('A4', 'portrait');
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
