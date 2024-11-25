<?php

use Adianti\Control\TPage;
use Adianti\Wrapper\BootstrapFormBuilder;

/**
 * MetaAgentesViewForm Registration
 * @author Lucas Bortoloti <bortoloti91@gmail.com
 */
class MetaAgentesViewForm extends TPage
{
    protected $form; // form

    use Adianti\Base\AdiantiStandardFormTrait; // Standard form methods

    /**
     * Class constructor
     * Creates the page and the registration form
     */
    function __construct()
    {

        parent::__construct();


        $this->setDatabase('vigepi');              // defines the database
        $this->setActiveRecord('MetaAgentesView');     // defines the active record

        // creates the form
        $this->form = new BootstrapFormBuilder('form_MetaAgentesView');
        $this->form->setFormTitle('MetaAgentesView');


        // create the form fields 
        $id = new TEntry('id');
        $agravo_id = new TEntry('agravo_id');
        $atividade_tipo_id = new TEntry('atividade_tipo_id');
        $dia = new TEntry('dia');
        $agente_id = new TEntry('agente_id');
        $agente_login = new TEntry('agente_login');
        $agente_nome = new TEntry('agente_nome');
        $atividade = new TEntry('atividade');
        $normal_ou_recuperado = new TEntry('normal_ou_recuperado');
        $meta_diaria = new TEntry('meta_diaria');
        $atingiu_meta_diaria = new TEntry('atingiu_meta_diaria');


        // add the fields 
        $this->form->addFields([new TLabel('id')], [$id]);
        $this->form->addFields([new TLabel('agravo_id')], [$agravo_id]);
        $this->form->addFields([new TLabel('atividade_tipo_id')], [$atividade_tipo_id]);
        $this->form->addFields([new TLabel('dia')], [$dia]);
        $this->form->addFields([new TLabel('agente_id')], [$agente_id]);
        $this->form->addFields([new TLabel('agente_login')], [$agente_login]);
        $this->form->addFields([new TLabel('agente_nome')], [$agente_nome]);
        $this->form->addFields([new TLabel('atividade')], [$atividade]);
        $this->form->addFields([new TLabel('normal_ou_recuperado')], [$normal_ou_recuperado]);
        $this->form->addFields([new TLabel('meta_diaria')], [$meta_diaria]);
        $this->form->addFields([new TLabel('atingiu_meta_diaria')], [$atingiu_meta_diaria]);



        // if (!empty($))
        // {
        //     $->setEditable(FALSE);
        // }


        // $fieldX->addValidation( 'Field X', new TRequiredValidator ); // add validation
        // $fieldX->setSize( '100%' ); // set size


        // create the form actions
        $btn = $this->form->addAction(_t('Save'), new TAction([$this, 'onSave']), 'fa:save');
        $btn->class = 'btn btn-sm btn-primary';
        $this->form->addActionLink(_t('New'),  new TAction([$this, 'onEdit']), 'fa:eraser red');

        // vertical box container
        $container = new TVBox;
        $container->style = 'width: 100%';
        // $container->add(new TXMLBreadCrumb('menu.xml', __CLASS__));
        $container->add($this->form);


        parent::add($container);
    }
}
