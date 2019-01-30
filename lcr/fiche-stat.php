<?php
/* Copyright (C) 2005 Rodolphe Quiedeville <rodolphe@quiedeville.org>
 * Copyright (C) 2005 Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) 2010-2012 Juanjo Menent   <jmenent@2byte.es>
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
 */

/**
 *	\file       htdocs/compta/lcr/fiche-stat.php
 *  \ingroup    lcr
 *	\brief      Lcr statistics
 */


$res = 0;
if (!$res && file_exists("../main.inc.php"))
    $res = @include("../main.inc.php");
if (!$res && file_exists("../../main.inc.php"))
    $res = @include("../../main.inc.php");
if (!$res && file_exists("../../../main.inc.php"))
    $res = @include("../../../main.inc.php");
if (!$res && file_exists("../../../../main.inc.php"))
    $res = @include("../../../../main.inc.php");
if (!$res && file_exists("../../../dolibarr/htdocs/main.inc.php"))
    $res = @include("../../../dolibarr/htdocs/main.inc.php");     // Used on dev env only
if (!$res && file_exists("../../../../dolibarr/htdocs/main.inc.php"))
    $res = @include("../../../../dolibarr/htdocs/main.inc.php");   // Used on dev env only
if (!$res && file_exists("../../../../../dolibarr/htdocs/main.inc.php"))
    $res = @include("../../../../../dolibarr/htdocs/main.inc.php");   // Used on dev env only
if (!$res)
    die("Include of main fails");


require_once DOL_DOCUMENT_ROOT.'/compta/bank/class/account.class.php';

dol_include_once('/lcr/class/bonlcr.class.php');
dol_include_once('/lcr/class/lignelcr.class.php');
dol_include_once('/lcr/core/lib/lcr.lib.php');



$langs->load("banks");
$langs->load("categories");
$langs->load("lcr");

// Security check
if ($user->societe_id > 0) accessforbidden();

// Get supervariables
$prev_id = GETPOST('id','int');
$page = GETPOST('page','int');

/*
 * View
 */
llxHeader('',$langs->trans("BankdraftReceipts"));

if ($prev_id)
{
	$bon = new BonLcr($db,"");

	if ($bon->fetch($prev_id) == 0)
	{
		$head = lcr_prepare_head($bon);
		dol_fiche_head($head, 'statistics', $langs->trans("BankdraftReceipts"), '', 'payment');

		print '<table class="border" width="100%">';

		print '<tr><td width="20%">'.$langs->trans("BankdraftRef").'</td><td>'.$bon->getNomUrl(1).'</td></tr>';
		print '<tr><td width="20%">'.$langs->trans("BankdraftDate").'</td><td>'.dol_print_date($bon->datec,'day').'</td></tr>';
		print '<tr><td width="20%">'.$langs->trans("BankdraftAmount").'</td><td>'.price($bon->amount).'</td></tr>';
        print '<tr><td width="20%">'.$langs->trans("BankdraftBank").'</td><td>'.$bon->bank.'</td></tr>';

		// Status
		print '<tr><td width="20%">'.$langs->trans('BankdraftStatus').'</td>';
		print '<td>'.$bon->getLibStatut(1).'</td>';
		print '</tr>';

		if($bon->date_trans <> 0)
		{
			$muser = new User($db);
			$muser->fetch($bon->user_trans);

			print '<tr><td width="20%">'.$langs->trans("BankdraftTransData").'</td><td>';
			print dol_print_date($bon->date_trans,'day');
			print ' '.$langs->trans("BankdraftBy").' '.$muser->getFullName($langs).'</td></tr>';
			print '<tr><td width="20%">'.$langs->trans("BankdraftTransMetod").'</td><td>';
			print $bon->methodes_trans[$bon->method_trans];
			print '</td></tr>';
		}
		if($bon->date_credit <> 0)
		{
			print '<tr><td width="20%">'.$langs->trans('BankdraftCreditDate').'</td><td>';
			print dol_print_date($bon->date_credit,'day');
			print '</td></tr>';
		}

		print '</table>';

		print '<br>';

		print '<table class="border" width="100%"><tr><td width="20%">';
		print $langs->trans("BankdraftFile").'</td><td>';
		$relativepath = 'receipts/'.$bon->ref."";
		print '[<a data-ajax="false" href="'.DOL_URL_ROOT.'/document.php?type=text/plain&amp;modulepart=lcr&amp;file='.urlencode($relativepath.".txt").'">'.$relativepath.'</a>] ';

		print '</td></tr></table>';

		dol_fiche_end();

	}
	else
	{
		$langs->load("errors");
		print $langs->trans("BankdraftError");
	}

	/*
	 * Stats
	 */
	$ligne=new LigneLcr($db,$user);

	$sql = "SELECT sum(pl.amount), pl.statut";
	$sql.= " FROM ".MAIN_DB_PREFIX."lcr_lignes as pl";
	$sql.= " WHERE pl.fk_lcr_bons = ".$prev_id;
	$sql.= " GROUP BY pl.statut";


	$resql=$db->query($sql);
	if ($resql)
	{
		$num = $db->num_rows($resql);
		$i = 0;

		print "\n<!-- debut table -->\n";
		print '<table class="noborder" width="100%" cellspacing="0" cellpadding="4">';
		print '<tr class="liste_titre">';
		print '<td>'.$langs->trans("BankdraftStatus").'</td><td align="right">'.$langs->trans("BankdraftAmount").'</td><td align="right">%</td></tr>';

		$var=false;

		while ($i < $num)
		{
			$row = $db->fetch_row($resql);

			print "<tr ".$bc[$var]."><td>";

			print $ligne->LibStatut($row[1],1);

			print '</td><td align="right">';
			print price($row[0]);

			print '</td><td align="right">';
			print round($row[0]/$bon->amount*100,2)." %";
			print '</td>';

			print "</tr>\n";

			$var=!$var;
			$i++;
		}

		print "</table>";
		$db->free($resql);
	}
	else
	{
		print $db->error() . ' ' . $sql;
	}
}

$db->close();

llxFooter();
