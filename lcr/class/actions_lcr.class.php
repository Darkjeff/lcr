<?php
/* Copyright (C) ---Put here your own copyright and developer email---
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

/**
 * \file    htdocs/modulebuilder/template/class/actions_mymodule.class.php
 * \ingroup mymodule
 * \brief   Example hook overload.
 *
 * Put detailed description here.
 */

/**
 * Class Actionslcr
 */
class Actionslcr
{
    /**
     * @var DoliDB Database handler.
     */
    public $db;
    /**
     * @var string Error
     */
    public $error = '';
    /**
     * @var array Errors
     */
    public $errors = array();
	/**
	 * @var array Hook results. Propagated to $hookmanager->resArray for later reuse
	 */
	public $results = array();
	/**
	 * @var string String displayed by executeHook() immediately after return
	 */
	public $resprints;


	/**
	 * Constructor
	 *
	 *  @param		DoliDB		$db      Database handler
	 */
	public function __construct($db)
	{
	    $this->db = $db;
	}

	/**
	 * Overloading the printFieldListOption function : replacing the parent's function with the one below
	 *
	 * @param   array           $parameters     Hook metadatas (context, etc...)
	 * @param   CommonObject    $object         The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
	 * @param   string          $action         Current action (if set). Generally create or edit or null
	 * @param   HookManager     $hookmanager    Hook manager propagated to allow calling another hook
	 * @return  int                             < 0 on error, 0 on success, 1 to replace standard code
	 */
	public function printFieldListOption($parameters, &$object, &$action, $hookmanager)
	{
		global $conf, $user, $langs;

		$error = 0; // Error counter

		//print_r($parameters); print_r($object); echo "action: " . $action; 
		if (in_array($parameters['currentcontext'], array('invoicelist')))
		{
			if ($user->rights->lcr->bons->lire) {
				$this->resprints = '<td class="liste_titre">'.''.'</td>';
			} else {
				$this->resprints = '';
			}
		}

		if (! $error) {
			return 0;
		} else {
			$this->errors[] = 'Error message';
			return -1;
		}
	}

	/**
	 * Overloading the printFieldListTitle function : replacing the parent's function with the one below
	 *
	 * @param   array           $parameters     Hook metadatas (context, etc...)
	 * @param   CommonObject    $object         The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
	 * @param   string          $action         Current action (if set). Generally create or edit or null
	 * @param   HookManager     $hookmanager    Hook manager propagated to allow calling another hook
	 * @return  int                             < 0 on error, 0 on success, 1 to replace standard code
	 */
	public function printFieldListTitle($parameters, &$object, &$action, $hookmanager)
	{
		global $conf, $user, $langs;

		$error = 0; // Error counter

		//print_r($parameters); print_r($object); echo "action: " . $action; 
		if (in_array($parameters['currentcontext'], array('invoicelist')))
		{
			if ($user->rights->lcr->bons->lire) {
				$this->resprints = '<th class="liste_titre">'.$langs->trans('LcrInWallet').'</th>';
			} else {
				$this->resprints = '';
			}
		}

		if (! $error) {
			return 0;
		} else {
			$this->errors[] = 'Error message';
			return -1;
		}
	}

	/**
	 * Overloading the printFieldListValue function : replacing the parent's function with the one below
	 *
	 * @param   array           $parameters     Hook metadatas (context, etc...)
	 * @param   CommonObject    $object         The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
	 * @param   string          $action         Current action (if set). Generally create or edit or null
	 * @param   HookManager     $hookmanager    Hook manager propagated to allow calling another hook
	 * @return  int                             < 0 on error, 0 on success, 1 to replace standard code
	 */
	public function printFieldListValue($parameters, &$object, &$action, $hookmanager)
	{
		global $conf, $user, $langs;

		$error = 0; // Error counter
		$errors = array();
		$lcrWallet = 0.0;

		//print_r($parameters); print_r($object); echo "action: " . $action; 
		if (in_array($parameters['currentcontext'], array('invoicelist')))
		{
			$this->resprints = '<td></td>';
			$objectId = $parameters['obj']->id;
			$objectModeReglement = $parameters['obj']->fk_mode_reglement;
			if ($user->rights->lcr->bons->lire && !empty($objectId) && $objectModeReglement == 52) {
				$sql = 'SELECT count(*), SUM(amount) as amount ';
				$sql.= ' FROM '.MAIN_DB_PREFIX.'lcr_facture_demande';
				$sql.= ' WHERE fk_facture = '.$objectId;
				$resql=$this->db->query($sql);
				if ($resql)
				{
					if ($this->db->num_rows($resql))
					{
						$obj = $this->db->fetch_object($resql);
						$lcrWallet = $obj->amount;
					}
					$this->resprints = '<td>'.price2num($lcrWallet, 'MT').'</td>';
				}
			}
		}

		if (! $error) {
			return 0;
		} else {
			$this->errors[] = 'Error message';
			return -1;
		}
	}

}
