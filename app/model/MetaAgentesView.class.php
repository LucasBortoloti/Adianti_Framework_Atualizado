<?php

use Adianti\Database\TRecord;

/**
 * MetaAgentesView Active Record
 * @author Lucas Bortoloti <bortoloti91@gmail.com
 */
class MetaAgentesView extends TRecord
{
    const TABLENAME = 'meta_agentes_view';
    const PRIMARYKEY = 'id';
    const IDPOLICY =  'max'; // {max, serial}


    /**
     * Constructor method
     */
    public function __construct($id = NULL, $callObjectLoad = TRUE)
    {

        parent::__construct($id, $callObjectLoad);

        parent::addAttribute('id');
        parent::addAttribute('agravo_id');
        parent::addAttribute('atividade_tipo_id');
        parent::addAttribute('dia');
        parent::addAttribute('agente_id');
        parent::addAttribute('agente_login');
        parent::addAttribute('agente_nome');
        parent::addAttribute('atividade');
        parent::addAttribute('normal_ou_recuperado');
        parent::addAttribute('meta_diaria');
        parent::addAttribute('atingiu_meta_diaria');
    }

    public function set_agravo(Agravo $object)
    {
        $this->agravo = $object;
        $this->agravo_id = $object->nome;
    }

    public function get_agravo()
    {
        if (empty($this->agravo)) {
            $this->agravo = new Agravo($this->agravo_id);
        }

        return $this->agravo;
    }
}
