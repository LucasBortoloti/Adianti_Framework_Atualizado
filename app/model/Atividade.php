<?php

use Adianti\Database\TRecord;

class Atividade extends TRecord
{
    const TABLENAME = 'atividade';
    const PRIMARYKEY = 'id';
    const IDPOLICY = 'max';

    public function __construct($id = NULL, $callObjectLoad = TRUE)
    {
        parent::__construct($id, $callObjectLoad);

        parent::addAttribute('id');
        parent::addAttribute('datahora_saida');
    }
}
