<?php

use Adianti\Control\TAction;
use Adianti\Control\TPage;
use Adianti\Core\AdiantiCoreTranslator;
use Adianti\Database\TCriteria;
use Adianti\Database\TFilter;
use Adianti\Database\TRepository;
use Adianti\Database\TTransaction;
use Adianti\Registry\TSession;
use Adianti\Widget\Base\TElement;
use Adianti\Widget\Container\TPanelGroup;
use Adianti\Widget\Container\TVBox;
use Adianti\Widget\Datagrid\TDataGrid;
use Adianti\Widget\Datagrid\TDataGridAction;
use Adianti\Widget\Datagrid\TDataGridColumn;
use Adianti\Widget\Datagrid\TPageNavigation;
use Adianti\Widget\Dialog\TMessage;
use Adianti\Widget\Dialog\TQuestion;
use Adianti\Widget\Form\TDate;
use Adianti\Widget\Form\TEntry;
use Adianti\Widget\Form\TLabel;
use Adianti\Widget\Form\TRadioGroup;
use Adianti\Widget\Template\THtmlRenderer;
use Adianti\Widget\Wrapper\TDBCombo;
use Adianti\Widget\Wrapper\TDBUniqueSearch;
use Adianti\Wrapper\BootstrapDatagridWrapper;
use Adianti\Wrapper\BootstrapFormBuilder;

/**
 * MetaAgentesViewList Listing
 * @author Lucas Bortoloti <bortoloti91@gmail.com
 */
class MetaAgentesDashboard extends TPage
{
    private $form; // form
    private $datagrid; // listing
    private $pageNavigation;
    private $formgrid;
    private $loaded;
    private $deleteButton;

    public function __construct()
    {

        parent::__construct();

        // creates the form
        $this->form = new BootstrapFormBuilder('form_search_MetaAgentesView');
        $this->form->setFormTitle('MetaAgentesDashboard');
        $this->form->setProperty('style', 'width: 99.2%;');

        $data_inicial = new TDate('data_inicial');
        $data_final = new TDate('data_final');
        $agente_id = new TDBUniqueSearch('agente_id', 'vigepi', 'MetaAgentesView', 'agente_id', 'agente_nome');
        $agravo_id = new TDBCombo('agravo_id', 'vigepi', 'Agravo', 'id', 'descricao');
        $atingiu_meta_diaria = new TRadioGroup('atingiu_meta_diaria');

        // add the fields 
        $this->form->addFields([new TLabel('De')], [$data_inicial]);
        $this->form->addFields([new TLabel('Até')], [$data_final]);
        $this->form->addFields([new TLabel('Agente')], [$agente_id]);
        $this->form->addFields([new TLabel('Agravo')], [$agravo_id]);
        $this->form->addFields([new TLabel('Atingiu Meta')], [$atingiu_meta_diaria]);

        $atingiu_meta_diaria->setUseButton();
        $options = ['S' => 'Sim', 'N' => 'Não', '' => 'Sim/Não'];
        $atingiu_meta_diaria->addItems($options);
        $atingiu_meta_diaria->setLayout('horizontal');

        // keep the form filled during navigation with session data
        $this->form->setData(TSession::getValue(__CLASS__ . '_filter_data'));

        // add the search form actions
        $btn = $this->form->addAction(_t('Find'), new TAction([$this, 'onSearch']), 'fa:search');
        $btn->class = 'btn btn-sm btn-primary';

        parent::add($this->form);
    }

    public function onSearch()
    {
        // get the search form data
        $data = $this->form->getData();

        // clear session filters

        TSession::setValue('MetaAgentesViewList_filter_id',             NULL);
        TSession::setValue('MetaAgentesViewList_filter_agravo_id',             NULL);
        TSession::setValue('MetaAgentesViewList_filter_atividade_tipo_id',             NULL);
        TSession::setValue('MetaAgentesViewList_filter_data_inicial',             NULL);
        TSession::setValue('MetaAgentesViewList_filter_data_final',             NULL);
        TSession::setValue('MetaAgentesViewList_filter_agente_id',             NULL);
        TSession::setValue('MetaAgentesViewList_filter_agente_login',             NULL);
        TSession::setValue('MetaAgentesViewList_filter_agente_nome',             NULL);
        TSession::setValue('MetaAgentesViewList_filter_atividade',             NULL);
        TSession::setValue('MetaAgentesViewList_filter_normal_ou_recuperado',             NULL);
        TSession::setValue('MetaAgentesViewList_filter_meta_diaria',             NULL);
        TSession::setValue('MetaAgentesViewList_filter_atingiu_meta_diaria',             NULL);
        if (isset($data->id) and ($data->id)) {
            $filter = new TFilter('id', '=', "{$data->id}");
            TSession::setValue('MetaAgentesViewList_filter_id', $filter);
        }

        if (isset($data->agravo_id) and ($data->agravo_id)) {
            $filter = new TFilter('agravo_id', '=', "{$data->agravo_id}");
            TSession::setValue('MetaAgentesViewList_filter_agravo_id', $filter);
        }

        if (isset($data->atividade_tipo_id) and ($data->atividade_tipo_id)) {
            $filter = new TFilter('atividade_tipo_id', '=', "{$data->atividade_tipo_id}");
            TSession::setValue('MetaAgentesViewList_filter_atividade_tipo_id', $filter);
        }

        if (isset($data->data_inicial) and ($data->data_inicial)) {
            $filter = new TFilter('dia', '>=', "{$data->data_inicial}");
            TSession::setValue('MetaAgentesViewList_filter_data_inicial', $filter);
        }

        if (isset($data->data_final) and ($data->data_final)) {
            $filter = new TFilter('dia', '<=', "{$data->data_final}");
            TSession::setValue('MetaAgentesViewList_filter_data_final', $filter);
        }

        if (isset($data->agente_id) and ($data->agente_id)) {
            $filter = new TFilter('agente_id', '=', "{$data->agente_id}");
            TSession::setValue('MetaAgentesViewList_filter_agente_id', $filter);
        }

        if (isset($data->agente_login) and ($data->agente_login)) {
            $filter = new TFilter('agente_login', '=', "{$data->agente_login}");
            TSession::setValue('MetaAgentesViewList_filter_agente_login', $filter);
        }

        if (isset($data->agente_nome) and ($data->agente_nome)) {
            $filter = new TFilter('agente_nome', '=', "{$data->agente_nome}");
            TSession::setValue('MetaAgentesViewList_filter_agente_nome', $filter);
        }

        if (isset($data->atividade) and ($data->atividade)) {
            $filter = new TFilter('atividade', '=', "{$data->atividade}");
            TSession::setValue('MetaAgentesViewList_filter_atividade', $filter);
        }

        if (isset($data->normal_ou_recuperado) and ($data->normal_ou_recuperado)) {
            $filter = new TFilter('normal_ou_recuperado', '=', "{$data->normal_ou_recuperado}");
            TSession::setValue('MetaAgentesViewList_filter_normal_ou_recuperado', $filter);
        }

        if (isset($data->meta_diaria) and ($data->meta_diaria)) {
            $filter = new TFilter('meta_diaria', '=', "{$data->meta_diaria}");
            TSession::setValue('MetaAgentesViewList_filter_meta_diaria', $filter);
        }

        if (isset($data->atingiu_meta_diaria) and ($data->atingiu_meta_diaria)) {
            $filter = new TFilter('atingiu_meta_diaria', '=', "{$data->atingiu_meta_diaria}");
            TSession::setValue('MetaAgentesViewList_filter_atingiu_meta_diaria', $filter);
        }

        // fill the form with data again
        $this->form->setData($data);

        // keep the search data in the session
        TSession::setValue(__CLASS__ . '_filter_data', $data);

        $param = array();
        $param['offset']    = 0;
        $param['first_page'] = 1;
        $this->onReload($param);
    }

    public function onReload($param = NULL)
    {
        try {
            // open a transaction with database 'vigepi'
            TTransaction::open('vigepi');

            // creates a repository for MetaAgentesView
            $repository = new TRepository('MetaAgentesView');
            $limit = 20;
            // creates a criteria
            $criteria = new TCriteria;

            // default order
            if (empty($param['order'])) {
                $param['order'] = 'id';
                $param['direction'] = 'asc';
            }
            $criteria->setProperties($param); // order, offset
            $criteria->setProperty('limit', $limit);

            // add the session filters 
            if (TSession::getValue('MetaAgentesViewList_filter_id')) {
                $criteria->add(TSession::getValue('MetaAgentesViewList_filter_id'));
            }

            if (TSession::getValue('MetaAgentesViewList_filter_agravo_id')) {
                $criteria->add(TSession::getValue('MetaAgentesViewList_filter_agravo_id'));
            }

            if (TSession::getValue('MetaAgentesViewList_filter_atividade_tipo_id')) {
                $criteria->add(TSession::getValue('MetaAgentesViewList_filter_atividade_tipo_id'));
            }

            if (TSession::getValue('MetaAgentesViewList_filter_data_inicial')) {
                $criteria->add(TSession::getValue('MetaAgentesViewList_filter_data_inicial'));
            }

            if (TSession::getValue('MetaAgentesViewList_filter_data_final')) {
                $criteria->add(TSession::getValue('MetaAgentesViewList_filter_data_final'));
            }

            if (TSession::getValue('MetaAgentesViewList_filter_agente_id')) {
                $criteria->add(TSession::getValue('MetaAgentesViewList_filter_agente_id'));
            }

            if (TSession::getValue('MetaAgentesViewList_filter_agente_login')) {
                $criteria->add(TSession::getValue('MetaAgentesViewList_filter_agente_login'));
            }

            if (TSession::getValue('MetaAgentesViewList_filter_agente_nome')) {
                $criteria->add(TSession::getValue('MetaAgentesViewList_filter_agente_nome'));
            }

            if (TSession::getValue('MetaAgentesViewList_filter_atividade')) {
                $criteria->add(TSession::getValue('MetaAgentesViewList_filter_atividade'));
            }

            if (TSession::getValue('MetaAgentesViewList_filter_normal_ou_recuperado')) {
                $criteria->add(TSession::getValue('MetaAgentesViewList_filter_normal_ou_recuperado'));
            }

            if (TSession::getValue('MetaAgentesViewList_filter_meta_diaria')) {
                $criteria->add(TSession::getValue('MetaAgentesViewList_filter_meta_diaria'));
            }

            if (TSession::getValue('MetaAgentesViewList_filter_atingiu_meta_diaria')) {
                $criteria->add(TSession::getValue('MetaAgentesViewList_filter_atingiu_meta_diaria'));
            }

            $html = new THtmlRenderer('app/resources/google_pie_chart.html');

            //daqui ele pega os dados para imprimir no gráfico
            $objects = $repository->load($criteria, FALSE);

            $atingidas = 0;
            $nao_atingidas = 0;

            foreach ($objects as $meta) {
                if ($meta->atingiu_meta_diaria === 'S') {
                    $atingidas++;
                } elseif ($meta->atingiu_meta_diaria === 'N') {
                    $nao_atingidas++;
                }
            }

            // Dados do gráfico
            $dados = [
                ['Status', 'Quantidade'],
                ['Atingidas', $atingidas],
                ['Não Atingidas', $nao_atingidas]
            ];

            $div = new TElement('div');
            $div->id = 'container';
            $div->style = 'width:700px;height:605px;';
            $div->add($html);

            $html->enableSection('main', [
                'data' => json_encode($dados),
                'width' => '95%',
                'height' => '610px',
                'xtitle' => 'Meta',
                'ytitle' => 'Atingido/Não Atingido',
                'title' => 'Metas dos Agentes'
            ]);

            // Adicionando o gráfico ao container
            $container = new TPanelGroup;
            $container->style = 'display: inline-block; width: 44%; vertical-align: top;'; // Ajuste o estilo para exibir lado a lado
            $container->add($div);

            parent::add($container); // Adiciona o gráfico

            $html2 = new THtmlRenderer('app/resources/google_column_chart.html');

            $metas = $repository->load($criteria, FALSE);

            $dados = [['Nome', 'Normal/Recuperado', 'Meta']];

            foreach ($metas as $meta) {
                $dados[] = [
                    $meta->agente_nome . ' (' . TDate::convertToMask($meta->dia, 'yyyy-mm-dd', 'dd/mm/yyyy') . ')',
                    (float)$meta->normal_ou_recuperado,
                    (float)$meta->meta_diaria
                ];
            }

            $div2 = new TElement('div');
            $div2->id = 'container';
            $div2->style = 'width:920px;height:605px;';
            $div2->add($html2);

            $html2->enableSection('main', [
                'data' => json_encode($dados),
                'width' => '91%',
                'height' => '610px',
                'xtitle' => 'Agente',
                'ytitle' => 'Normal/Recuperado',
                'title' => 'Metas dos Agentes'
            ]);

            $container2 = new TPanelGroup;
            $container2->style = 'display: inline-block; width: 55%; vertical-align: top;'; // Ajuste o estilo para exibir lado a lado
            $container2->add($div2);

            parent::add($container2); // Adiciona o segundo gráfico

            // $html3 = new THtmlRenderer('app/resources/google_column_chart.html');

            // $infos = $repository->load($criteria, FALSE);

            // $dados = [['Nome', 'Normal/Recuperado', 'Meta']];

            // foreach ($infos as $info) {
            //     $dados[] = [
            //         $info->agente_nome . ' (' . TDate::convertToMask($info->dia, 'yyyy-mm-dd', 'dd/mm/yyyy') . ')',
            //         (float)$info->normal_ou_recuperado,
            //         (float)$info->meta_diaria
            //     ];
            // }

            // $div3 = new TElement('div');
            // $div3->id = 'container';
            // $div3->style = 'width:1200px;height:1250px;';
            // $div3->add($html3);

            // $html3->enableSection('main', [
            //     'data' => json_encode($dados),
            //     'width' => '100%',
            //     'height' => '1000px',
            //     'title' => 'Metas dos Agentes',
            //     'xtitle' => 'Agente',
            //     'ytitle' => 'Normal/Recuperado'
            // ]);

            // $container3 = new TVBox;
            // $container3->style = 'width: 100%';
            // $container3->add($div3);

            // parent::add($container3); // Adiciona o segundo gráfico

            if (is_callable($this->transformCallback)) {
                call_user_func($this->transformCallback, $objects, $param);
            }

            // reset the criteria for record count
            $criteria->resetProperties();
            $count = $repository->count($criteria);

            // close the transaction
            TTransaction::close();
            $this->loaded = true;
        } catch (Exception $e) {
            new TMessage('error', $e->getMessage());
            TTransaction::rollback();
        }
    }

    public function show()
    {
        // check if the datagrid is already loaded
        if (!$this->loaded and (!isset($_GET['method']) or !(in_array($_GET['method'],  array('onReload', 'onSearch'))))) {
            if (func_num_args() > 0) {
                $this->onReload(func_get_arg(0));
            } else {
                $this->onReload();
            }
        }

        parent::show();
    }
}
