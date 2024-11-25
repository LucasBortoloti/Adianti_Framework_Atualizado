<?php

use Adianti\Control\TAction;
use Adianti\Control\TPage;
use Adianti\Core\AdiantiCoreTranslator;
use Adianti\Database\TCriteria;
use Adianti\Database\TFilter;
use Adianti\Database\TRepository;
use Adianti\Database\TTransaction;
use Adianti\Registry\TSession;
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
use Adianti\Widget\Wrapper\TDBCombo;
use Adianti\Widget\Wrapper\TDBUniqueSearch;
use Adianti\Wrapper\BootstrapDatagridWrapper;
use Adianti\Wrapper\BootstrapFormBuilder;

/**
 * MetaAgentesViewList Listing
 * @author Lucas Bortoloti <bortoloti91@gmail.com
 */
class MetaAgentesViewListComplete extends TPage
{
    private $form; // form
    private $datagrid; // listing
    private $pageNavigation;
    private $formgrid;
    private $loaded;
    private $deleteButton;

    /**
     * Class constructor
     * Creates the page, the form and the listing
     */
    public function __construct()
    {

        parent::__construct();

        // creates the form
        $this->form = new BootstrapFormBuilder('form_search_MetaAgentesView');
        $this->form->setFormTitle('MetaAgentesView');


        $data_inicial = new TDate('data_inicial');
        $data_final = new TDate('data_final');
        $agente_id = new TDBUniqueSearch('agente_id', 'vigepi', 'MetaAgentesView', 'agente_id', 'agente_nome');
        $agravo_id = new TDBCombo('agravo_id', 'vigepi', 'Agravo', 'id', 'descricao');
        $atingiu_meta_diaria = new TEntry('atingiu_meta_diaria');


        // add the fields 
        $this->form->addFields([new TLabel('De')], [$data_inicial]);
        $this->form->addFields([new TLabel('Até')], [$data_final]);
        $this->form->addFields([new TLabel('Agente')], [$agente_id]);
        $this->form->addFields([new TLabel('Agravo')], [$agravo_id]);
        $this->form->addFields([new TLabel('Atingiu Meta')], [$atingiu_meta_diaria]);

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

        $action1 = new TDataGridAction(['MetaAgentesViewForm', 'onEdit'], ['id' => '{$id}']);
        $action2 = new TDataGridAction([$this, 'onDelete'], ['id' => '{$id}']);

        $this->datagrid->addAction($action1, _t('Edit'),   'far:edit blue');
        $this->datagrid->addAction($action2, _t('Delete'), 'far:trash-alt red');

        // create the datagrid model
        $this->datagrid->createModel();

        // creates the page navigation
        $this->pageNavigation = new TPageNavigation;
        $this->pageNavigation->setAction(new TAction([$this, 'onReload']));
        $this->pageNavigation->setWidth($this->datagrid->getWidth());

        // vertical box container
        $container = new TVBox;
        $container->style = 'width: 100%';
        // $container->add(new TXMLBreadCrumb('menu.xml', __CLASS__));
        $container->add($this->form);
        $container->add(TPanelGroup::pack('', $this->datagrid, $this->pageNavigation));


        parent::add($container);
    }

    /**
     * Inline record editing
     * @param $param Array containing:
     *              key: object ID value
     *              field name: object attribute to be updated
     *              value: new attribute content 
     */
    public function onInlineEdit($param)
    {
        try {
            // get the parameter $key
            $field = $param['field'];
            $key   = $param['key'];
            $value = $param['value'];

            TTransaction::open('vigepi'); // open a transaction with database
            $object = new MetaAgentesView($key); // instantiates the Active Record
            $object->{$field} = $value;
            $object->store(); // update the object in the database
            TTransaction::close(); // close the transaction

            $this->onReload($param); // reload the listing
            new TMessage('info', "Record Updated");
        } catch (Exception $e) // in case of exception
        {
            new TMessage('error', $e->getMessage()); // shows the exception error message
            TTransaction::rollback(); // undo all pending operations
        }
    }

    /**
     * Register the filter in the session
     */
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
            $filter = new TFilter('dia', '<=', "{$data->data_inicial}");
            TSession::setValue('MetaAgentesViewList_filter_data_inicial', $filter);
        }

        if (isset($data->data_final) and ($data->data_final)) {
            $filter = new TFilter('dia', '>=', "{$data->data_final}");
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

    /**
     * Load the datagrid with data
     */
    public function onReload($param = NULL)
    {
        try {
            // open a transaction with database 'vigepi'
            TTransaction::open('vigepi');

            // creates a repository for MetaAgentesView
            $repository = new TRepository('MetaAgentesView');
            $limit = 10;
            // creates a criteria
            $criteria = new TCriteria;

            // default order
            if (empty($param['order'])) {
                $param['order'] = '';
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



            // load the objects according to criteria
            $objects = $repository->load($criteria, FALSE);

            if (is_callable($this->transformCallback)) {
                call_user_func($this->transformCallback, $objects, $param);
            }

            $this->datagrid->clear();
            if ($objects) {
                // iterate the collection of active records
                foreach ($objects as $object) {
                    // add the object inside the datagrid
                    $this->datagrid->addItem($object);
                }
            }

            // reset the criteria for record count
            $criteria->resetProperties();
            $count = $repository->count($criteria);

            $this->pageNavigation->setCount($count); // count of records
            $this->pageNavigation->setProperties($param); // order, page
            $this->pageNavigation->setLimit($limit); // limit

            // close the transaction
            TTransaction::close();
            $this->loaded = true;
        } catch (Exception $e) {
            new TMessage('error', $e->getMessage());
            TTransaction::rollback();
        }
    }

    /**
     * Ask before deletion
     */
    public static function onDelete($param)
    {
        // define the delete action
        $action = new TAction([__CLASS__, 'Delete']);
        $action->setParameters($param); // pass the key parameter ahead

        // shows a dialog to the user
        new TQuestion(AdiantiCoreTranslator::translate('Do you really want to delete ?'), $action);
    }

    /**
     * Delete a record
     */
    public static function Delete($param)
    {
        try {
            $key = $param['key']; // get the parameter $key
            TTransaction::open('vigepi'); // open a transaction with database
            $object = new MetaAgentesView($key, FALSE); // instantiates the Active Record
            $object->delete(); // deletes the object from the database
            TTransaction::close(); // close the transaction

            $pos_action = new TAction([__CLASS__, 'onReload']);
            new TMessage('info', AdiantiCoreTranslator::translate('Record deleted'), $pos_action); // success message
        } catch (Exception $e) // in case of exception
        {
            new TMessage('error', $e->getMessage()); // shows the exception error message
            TTransaction::rollback(); // undo all pending operations
        }
    }

    /**
     * method show()
     * Shows the page
     */
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
