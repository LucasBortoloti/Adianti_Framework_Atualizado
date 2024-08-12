
<?php

use Adianti\Database\TRecord;

/**
 * @author Lucas Bortoloti <bortoloti91@gmail.com
 */
class Programacao extends TRecord
{
    const TABLENAME = 'programacao';
    const PRIMARYKEY = 'id';
    const IDPOLICY =  'max'; // {max, serial}

    /**
     * Constructor method
     */
    public function __construct($id = NULL, $callObjectLoad = TRUE)
    {

        parent::__construct($id, $callObjectLoad);

        parent::addAttribute('id');
        parent::addAttribute('concluida');
        parent::addAttribute('agravo_id');
        parent::addAttribute('foco_id');
    }
}
