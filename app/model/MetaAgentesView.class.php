<?php

use Adianti\Database\TRecord;

class MetaAgentesView extends TRecord
{
    const TABLENAME = 'meta_agentes_view';
    const PRIMARYKEY = 'id';
    const IDPOLICY = 'max';

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
}
