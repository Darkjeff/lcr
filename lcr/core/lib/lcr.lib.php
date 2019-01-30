<?php
/* Copyright (C) 2010-2011 	Juanjo Menent		<jmenent@2byte.es>
 * Copyright (C) 2010		Laurent Destailleur	<eldy@users.sourceforge.net>
 * Copyright (C) 2011      	Regis Houssin		<regis.houssin@capnetworks.com>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 * or see http://www.gnu.org/
 */

/**
 *	\file       htdocs/core/lib/lcr.lib.php
 *	\brief      Ensemble de fonctions de base pour le module lcr
 *	\ingroup    propal
 */


/**
* Prepare array with list of tabs
*
* @param   Object	$object		Object related to tabs
* @return  array				Array of tabs to shoc
*/

function lcr_prepare_head($object)
{
	global $langs, $conf, $user;
	$langs->load("lcr");

	$h = 0;
	$head = array();

	$head[$h][0] = dol_buildpath('/lcr/fiche.php?id='.$object->id, 1 );
	$head[$h][1] = $langs->trans("Card");
	$head[$h][2] = 'lcr';
	$h++;

	if (! empty($conf->global->MAIN_USE_PREVIEW_TABS))
	{
		$head[$h][0] = dol_buildpath('/lcr/bon.php?id='.$object->id, 1 );
		$head[$h][1] = $langs->trans("Preview");
		$head[$h][2] = 'preview';
		$h++;
	}

	$head[$h][0] = dol_buildpath('/lcr/lignes.php?id='.$object->id, 1 );
	$head[$h][1] = $langs->trans("BankdraftLines");
	$head[$h][2] = 'lines';
	$h++;

	$head[$h][0] = dol_buildpath('/lcr/factures.php?id='.$object->id, 1);
	$head[$h][1] = $langs->trans("BankdraftBills");
	$head[$h][2] = 'invoices';
	$h++;

	$head[$h][0] = dol_buildpath('/lcr/fiche-rejet.php?id='.$object->id, 1 );
	$head[$h][1] = $langs->trans("BankdraftRejects");
	$head[$h][2] = 'rejects';
	$h++;

	$head[$h][0] = dol_buildpath('/lcr/fiche-stat.php?id='.$object->id, 1 );
	$head[$h][1] = $langs->trans("BankdraftStatistics");
	$head[$h][2] = 'statistics';
	$h++;

    // Show more tabs from modules
    // Entries must be declared in modules descriptor with line
    // $this->tabs = array('entity:+tabname:Title:@mymodule:/mymodule/mypage.php?id=__ID__');   to add new tab
    // $this->tabs = array('entity:-tabname);   												to remove a tab
    complete_head_from_modules($conf,$langs,$object,$head,$h,'lcr');

    complete_head_from_modules($conf,$langs,$object,$head,$h,'lcr','remove');

    return $head;
}


/**
*	Check need data to create standigns orders receipt file
*
*	@return    	int		-1 if ko 0 if ok
*/

function lcr_check_config()
{
	global $conf;
    if(empty($conf->global->LCR_USER)) return -1;
	if(empty($conf->global->LCR_ID_BANKACCOUNT)) return -1;
	return 0;
}

