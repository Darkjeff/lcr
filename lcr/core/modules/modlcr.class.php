<?php
/* Copyright (C) 2005      Rodolphe Quiedeville <rodolphe@quiedeville.org>
 * Copyright (C) 2005-2008 Laurent Destailleur  <eldy@users.sourceforge.org>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

/**
 *  \defgroup   lcr      Module calling
 *  \brief      Module pour gerer l'appel automatique
 */

/**
 *  \file       htdocs/includes/modules/modlcr.class.php
 *  \ingroup    lcr
 *  \brief      Fichier de description et activation du module de click to DialS
 */

include_once(DOL_DOCUMENT_ROOT ."/core/modules/DolibarrModules.class.php");


/**
*   \class      modCalling
*   \brief      Classe de description et activation du module de Click to Dial
*/

class modlcr extends DolibarrModules
{

    /**
     *   \brief      Constructeur. Definit les noms, constantes et boites
     *   \param      DB      handler d'acces base
     */

    function modlcr($DB)
    {
        global $langs; $langs;
        $langs->load("lcr");

        $this->db = $DB ;
        $this->numero = 98300 ;

        $this->family = "financial";
        
        $this->name = preg_replace('/^mod/i','',get_class($this));
        $this->description = "Gestion des paiements par Lcr. Inclut également la génération du fichier de prélèvement des Lcr.";

        // Version of the module lcr
        $this->version = '1.7.3';
        $this->const_name = 'MAIN_MODULE_'.strtoupper($this->name);

        // $this->special = 1;
        $this->picto='lcr.png@lcr';

        // Data directories to create when module is enabled
        $this->dirs = array("/lcr");

        // php version
        $this->phpmin = array(5, 3);

        // Dolibarr version
        $this->need_dolibarr_version = array(3, 5);

        // Dependencies
        $this->depends = array();
        $this->requiredby = array();

        $this->conflictwith = array();
        $this->langfiles = array("lcr@lcr","users");

		// Defined all module parts (triggers, login, substitutions, menus, etc...) (0=disable,1=enable)
        $this->module_parts = array('triggers' => 1,'hooks' => array('data'=>array('invoicelist'), 'entity'=>'1'));

        // tabs
        $this->tabs = array('invoice:+lcrtab:lcr:@lcr:/lcr/tab/lcr.php?facid=__ID__',);

        // Config pages
        $this->config_page_url = array(dol_buildpath('/lcr/admin/lcr.php',1 ) );

        // Constantes
        $this->const = array();

        // Boites
        $this->boxes = array();

        // Permissions
        $this->rights = array();
        $this->rights_class = 'lcr';
        $r=0;
        $r++;
        $this->rights[$r][0] = 98301;
        $this->rights[$r][1] = 'ReadBankdraft';
        $this->rights[$r][2] = 'r';
        $this->rights[$r][3] = 1;
        $this->rights[$r][4] = 'bons';
        $this->rights[$r][5] = 'lire';

        $r++;
        $this->rights[$r][0] = 98302;
        $this->rights[$r][1] = 'CreateBankdraft';
        $this->rights[$r][2] = 'w';
        $this->rights[$r][3] = 0;
        $this->rights[$r][4] = 'bons';
        $this->rights[$r][5] = 'creer';

        $r++;
        $this->rights[$r][0] = 98303;
        $this->rights[$r][1] = 'SendBankdraft';
        $this->rights[$r][2] = 'a';
        $this->rights[$r][3] = 0;
        $this->rights[$r][4] = 'bons';
        $this->rights[$r][5] = 'send';

        $r++;
        $this->rights[$r][0] = 98304;
        $this->rights[$r][1] = 'CreditBankdraft';
        $this->rights[$r][2] = 'a';
        $this->rights[$r][3] = 0;
        $this->rights[$r][4] = 'bons';
        $this->rights[$r][5] = 'credit';

        $r++;
        $this->rights[$r][0] = 98305;
        $this->rights[$r][1] = 'ConfigureBankdraft';
        $this->rights[$r][2] = 'a';
        $this->rights[$r][3] = 0;
        $this->rights[$r][4] = 'bons';
        $this->rights[$r][5] = 'configurer';


        // Main menu entries
        $this->menu = array();          // List of menus to add

        $r++;
        $this->menu[$r]=array(
                                    'fk_menu'=>'fk_mainmenu=bank',
                                    'type'=>'left',
                                    'titre'=>"BankdraftStandingOrders",
                                    'mainmenu'=>'bank',
                                    'leftmenu'=>'lcr',      // Use 1 if you also want to add left menu entries using this descriptor.
                                    'url'=>'/lcr/index.php',
                                    'langs'=>'lcr@lcr',
                                    'position'=>100,
                                    'perms'=>'$user->rights->lcr->bons->lire',
                                    'enabled'=>'$conf->lcr->enabled',
                                    'target'=>'',
                                    'user'=>2
                                    );

        $r++;
        $this->menu[$r]=array('fk_menu'=>'fk_mainmenu=bank,fk_leftmenu=lcr',
                                    'type'=>'left',
                                    'titre'=>"BankdraftNewStandingOrder",
                                    'mainmenu'=>'bank',
                                    'url'=>'/lcr/create.php',
                                    'langs'=>'lcr@lcr',
                                    'position'=>100+$r,
                                    'perms'=>'$user->rights->lcr->bons->lire',
                                    'enabled'=>'$conf->lcr->enabled',
                                    'target'=>'',
                                    'user'=>2);

        $r++;
        $this->menu[$r]=array('fk_menu'=>'fk_mainmenu=bank,fk_leftmenu=lcr',
                                    'type'=>'left',
                                    'titre'=>$langs->trans("BankdraftReceipts"),
                                    'mainmenu'=>'bank',
                                    'url'=>'/lcr/bons.php',
                                    'langs'=>'lcr@lcr',
                                    'position'=>100+$r,
                                    'perms'=>'$user->rights->lcr->bons->lire',
                                    'enabled'=>'$conf->lcr->enabled',
                                    'target'=>'',
                                    'user'=>2);

        $r++;
        $this->menu[$r]=array('fk_menu'=>'fk_mainmenu=bank,fk_leftmenu=lcr',
                                    'type'=>'left',
                                    'titre'=>$langs->trans("BankdraftList"),
                                    'mainmenu'=>'bank',
                                    'url'=>'/lcr/liste.php',
                                    'langs'=>'lcr@lcr',
                                    'position'=>100+$r,
                                    'perms'=>'$user->rights->lcr->bons->lire',
                                    'enabled'=>'$conf->lcr->enabled',
                                    'target'=>'',
                                    'user'=>2);

		$r++;
		$this->menu[$r]=array('fk_menu'=>'fk_mainmenu=bank,fk_leftmenu=lcr',
                                    'type'=>'left',
                                    'titre'=>$langs->trans("BankdraftRejects"),
                                    'mainmenu'=>'bank',
                                    'url'=>'/lcr/rejets.php',
                                    'langs'=>'lcr@lcr',
                                    'position'=>100+$r,
                                    'perms'=>'$user->rights->lcr->bons->lire',
                                    'enabled'=>'$conf->lcr->enabled',
                                    'target'=>'',
                                    'user'=>2);

        $r++;
        $this->menu[$r]=array('fk_menu'=>'fk_mainmenu=bank,fk_leftmenu=lcr',
                                    'type'=>'left',
                                    'titre'=>$langs->trans("BankdraftStatistics"),
                                    'mainmenu'=>'bank',
                                    'url'=>'/lcr/stats.php',
                                    'langs'=>'lcr@lcr',
                                    'position'=>100+$r,
                                    'perms'=>'$user->rights->lcr->bons->lire',
                                    'enabled'=>'$conf->lcr->enabled',
                                    'target'=>'',
                                    'user'=>2);

    }


    /**
    *       \brief      Function called when module is enabled.
    *                   The init function add constants, boxes, permissions and menus (defined in constructor) into Dolibarr database.
    *                   It also creates data directories.
    *      \return     int             1 if OK, 0 if KO
    */

        function init()
        {
            global $conf;

            $sql = array();

        $this->load_tables();

            return $this->_init($sql);
        }


    /**
    *      \brief      Function called when module is disabled.
    *                  Remove from database constants, boxes and permissions from Dolibarr database.
    *                  Data directories are not deleted.
    *      \return     int             1 if OK, 0 if KO
    */

        function remove($options = '')
        {
            global $conf;
            $err=0;
            if (! $err) $err+=$this->_unactive();
            if (! $err) $err+=$this->delete_tabs();
            if (! $err) $err+=$this->delete_module_parts();
            if (! $err) $err+=$this->delete_menus();

            //  do not delete datas
            return true;
        }


    /**
    *      Create tables, keys and data required by module
    *      Files llx_table1.sql, llx_table1.key.sql llx_data.sql with create table, create keys
    *      and create data commands must be stored in directory /mymodule/sql/
    *      This function is called by this->init
    *
    *      @return     int     <=0 if KO, >0 if OK
    */

    function load_tables() {
        return $this->_load_tables('/lcr/sql/');
    }
}
?>
