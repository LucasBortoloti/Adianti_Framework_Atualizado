<?php

use Adianti\Control\TAction;
use Adianti\Control\TPage;
use Adianti\Registry\TSession;
use Adianti\Widget\Container\TPanelGroup;
use Adianti\Widget\Container\TVBox;
use Adianti\Widget\Datagrid\TDataGrid;
use Adianti\Widget\Datagrid\TDataGridAction;
use Adianti\Widget\Datagrid\TDataGridColumn;
use Adianti\Widget\Datagrid\TPageNavigation;
use Adianti\Widget\Form\TDate;
use Adianti\Widget\Form\TEntry;
use Adianti\Widget\Form\TLabel;
use Adianti\Widget\Form\TRadioGroup;
use Adianti\Widget\Util\TDropDown;
use Adianti\Widget\Wrapper\TDBCombo;
use Adianti\Widget\Wrapper\TDBUniqueSearch;
use Adianti\Wrapper\BootstrapDatagridWrapper;
use Adianti\Wrapper\BootstrapFormBuilder;

/**
 * MetaAgentesViewList Listing
 * @author Lucas Bortoloti <bortoloti91@gmail.com
 */
class MetaAgentesViewList extends TPage
{
    protected $form;     // registration form
    protected $datagrid; // listing
    protected $pageNavigation;
    protected $formgrid;
    protected $deleteButton;

    use Adianti\base\AdiantiStandardListTrait;

    /**
     * Page constructor
     */
    public function __construct()
    {

        parent::__construct();

        $this->setDatabase('vigepi');            // defines the database
        $this->setActiveRecord('MetaAgentesView');   // defines the active record
        $this->setDefaultOrder('id', 'asc');         // defines the default order
        $this->setLimit(10);
        // $this->setCriteria($criteria) // define a standard filter

        // add the filter fields ('filterField', 'operator', 'formField') 
        $this->addFilterField('agente_id', '=', 'agente_id');
        $this->addFilterField('agravo_id', '=', 'agravo_id');
        $this->addFilterField('dia', '>=', 'data_inicial');
        $this->addFilterField('dia', '<=', 'data_final');
        $this->addFilterField('atingiu_meta_diaria', '=', 'atingiu_meta_diaria');


        // creates the form
        $this->form = new BootstrapFormBuilder('form_search_MetaAgentesView');
        $this->form->setFormTitle('MetaAgentesView');

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

        // keep the form filled during navigation with session data
        $this->form->setData(TSession::getValue(__CLASS__ . '_filter_data'));

        // add the search form actions
        $btn = $this->form->addAction(_t('Find'), new TAction([$this, 'onSearch']), 'fa:search');
        $btn->class = 'btn btn-sm btn-primary';
        $this->form->addActionLink(_t('New'), new TAction(['MetaAgentesViewForm', 'onEdit']), 'fa:plus green');

        // creates a Datagrid
        $this->datagrid = new BootstrapDatagridWrapper(new TDataGrid);
        $this->datagrid->style = 'width: 100%';
        $this->datagrid->datatable = 'true';
        // $this->datagrid->enablePopover('Popover', 'Hi <b> {name} </b>');


        // creates the datagrid columns 
        $column_id = new TDataGridColumn('id', 'Id', 'left');
        $column_agravo_id = new TDataGridColumn('agravo_id', 'Agravo Id', 'center');
        $column_atividade_tipo_id = new TDataGridColumn('atividade_tipo_id', 'Atividade Tipo', 'center');
        $column_dia = new TDataGridColumn('dia', 'Data', 'center');
        $column_agente_id = new TDataGridColumn('agente_id', 'Agente Id', 'center');
        $column_agente_login = new TDataGridColumn('agente_login', 'Agente Login', 'center');
        $column_agente_nome = new TDataGridColumn('agente_nome', 'Agente Nome', 'center');
        $column_atividade = new TDataGridColumn('atividade', 'Atividade', 'center');
        $column_normal_ou_recuperado = new TDataGridColumn('normal_ou_recuperado', 'Normal ou Recuperado', 'center');
        $column_meta_diaria = new TDataGridColumn('meta_diaria', 'Meta Diária', 'center');
        $column_atingiu_meta_diaria = new TDataGridColumn('atingiu_meta_diaria', 'Atingiu Meta Diária', 'center');


        // add the columns to the DataGrid 
        $this->datagrid->addColumn($column_id);
        $this->datagrid->addColumn($column_agravo_id);
        $this->datagrid->addColumn($column_atividade_tipo_id);
        $this->datagrid->addColumn($column_dia);
        $this->datagrid->addColumn($column_agente_id);
        $this->datagrid->addColumn($column_agente_login);
        $this->datagrid->addColumn($column_agente_nome);
        $this->datagrid->addColumn($column_atividade);
        $this->datagrid->addColumn($column_normal_ou_recuperado);
        $this->datagrid->addColumn($column_meta_diaria);
        $this->datagrid->addColumn($column_atingiu_meta_diaria);



        $action1 = new TDataGridAction(['MetaAgentesViewForm', 'onEdit'], ['id' => '{id}']);
        $action2 = new TDataGridAction([$this, 'onDelete'], ['id' => '{id}']);

        $this->datagrid->addAction($action1, _t('Edit'),   'far:edit blue');
        $this->datagrid->addAction($action2, _t('Delete'), 'far:trash-alt red');

        // create the datagrid model
        $this->datagrid->createModel();

        // creates the page navigation
        $this->pageNavigation = new TPageNavigation;
        $this->pageNavigation->setAction(new TAction([$this, 'onReload']));

        $panel = new TPanelGroup('', 'white');
        $panel->add($this->datagrid);
        $panel->addFooter($this->pageNavigation);

        // header actions
        $dropdown = new TDropDown(_t('Export'), 'fa:list');
        $dropdown->setPullSide('right');
        $dropdown->setButtonClass('btn btn-default waves-effect dropdown-toggle');
        $dropdown->addAction(_t('Save as CSV'), new TAction([$this, 'onExportCSV'], ['register_state' => 'false', 'static' => '1']), 'fa:table blue');
        $dropdown->addAction(_t('Save as PDF'), new TAction([$this, 'onExportPDF'], ['register_state' => 'false', 'static' => '1']), 'far:file-pdf red');
        $panel->addHeaderWidget($dropdown);

        // vertical box container
        $container = new TVBox;
        $container->style = 'width: 100%';
        // $container->add(new TXMLBreadCrumb('menu.xml', __CLASS__));
        $container->add($this->form);
        $container->add($panel);


        parent::add($container);
    }
}
