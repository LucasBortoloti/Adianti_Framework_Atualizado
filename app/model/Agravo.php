<?php

use Adianti\Database\TRecord;

/**
 * Agravo Active Record
 * @author Lucas Bortoloti <bortoloti91@gmail.com
 */
class Agravo extends TRecord
{
    const TABLENAME = 'agravo';
    const PRIMARYKEY = 'id';
    const IDPOLICY =  'max'; // {max, serial}


    /**
     * Constructor method
     */
    public function __construct($id = NULL, $callObjectLoad = TRUE)
    {

        parent::__construct($id, $callObjectLoad);

        parent::addAttribute('id');
        parent::addAttribute('descricao');
        parent::addAttribute('login');
        parent::addAttribute('system_user_id');
        parent::addAttribute('created_at');
        parent::addAttribute('updated_at');
    }
}
