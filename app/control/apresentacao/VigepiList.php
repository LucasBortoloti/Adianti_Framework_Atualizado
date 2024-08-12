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
            $date_from = $data->date_from;
            $date_to = $data->date_to;
            $pesquisa = $data->pesquisa;

            $this->form->setData($data);

            $source = TTransaction::open('defciv');

            $query = "  SELECT      o.sinistro_id,
                                    s.descricao, 
                                    count(*) as QTDE,
                                    sum(CASE WHEN status = 'B' THEN 1 ELSE 0 END) as BAIXADAS,
                                    sum(CASE WHEN status = 'A' THEN 1 ELSE 0 END) as ABERTAS
                        from        ocorrencia o
                        left join   sinistro s on s.id = o.sinistro_id
                        where o.{$pesquisa} >= '{$date_from}' and o.{$pesquisa} <= '{$date_to}'
                        group by    o.sinistro_id, s.descricao
                        order by    s.descricao";

            $rows = TDatabase::getData($source, $query, null, null);

            $date_from_formatado = date('d/m/Y', strtotime($date_from));
            $date_to_formatado = date('d/m/Y', strtotime($date_to));
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
                            <td class="cor_ocorrencia colspan=4">Ocorrência de ' . $date_from_formatado . ' até ' . $date_to_formatado . '</td>                     
                        </tr>
                    </table>
                </div>
                <table class="borda_tabela" style="width: 100%">
                    <tr>
                        <td class="borda_inferior_centralizador"><b>Id</b></td> 
                        <td class="borda_inferior"><b>Descrição</b></td>
                        <td class="borda_inferior_centralizador"><b>Quantidade</b></td>
                        <td class="borda_inferior_centralizador"><b>Baixadas</b></td>
                        <td class="borda_inferior_centralizador"><b>Abertas</b></td>
                    </tr>';

            $totalQtde = 0;
            $totalBaixadas = 0;
            $totalAbertas = 0;

            foreach ($rows as $row) {
                $content .= "<tr>
                                <td class='borda_direita'>{$row['sinistro_id']}</td>
                                <td class='direita'>{$row['descricao']}</td>
                                <td class='borda_direita_esquerda'>{$row['QTDE']}</td>
                                <td class='borda_direita_esquerda'>{$row['BAIXADAS']}</td>
                                <td class='centralizar'>{$row['ABERTAS']}</td>
                            </tr>";

                $totalQtde += $row['QTDE'];
                $totalBaixadas += $row['BAIXADAS'];
                $totalAbertas += $row['ABERTAS'];
            }

            $content .= "<tr>
                            <td class='espaco_para_direta' colspan=2><b>Total:</b></td>
                            <td class='centralizador_com_borda_esquerda'><b>{$totalQtde}</b></td>
                            <td class='centralizador_com_borda'><b>{$totalBaixadas}</b></td>
                            <td class='centralizador_com_borda'><b>{$totalAbertas}</b></td>    
                        </tr>
                    </table>
                </body>
            </html>";

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
