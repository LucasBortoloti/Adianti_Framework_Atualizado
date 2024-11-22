<?php

use Adianti\Database\TRecord;

class Agravo extends TRecord
{
    const TABLENAME = 'agravo';
    const PRIMARYKEY = 'id';
    const IDPOLICY = 'max';

    public function __construct($id = NULL, $callObjectLoad = TRUE)
    {
        parent::__construct($id, $callObjectLoad);

        parent::addAttribute('id');
        parent::addAttribute('descricao');
    }
}
