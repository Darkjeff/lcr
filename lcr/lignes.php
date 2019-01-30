<?php
/* Copyright (C) 2005      Rodolphe Quiedeville <rodolphe@quiedeville.org>
 * Copyright (C) 2005      Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) 2005-2009 Regis Houssin        <regis.houssin@capnetworks.com>
 * Copyright (C) 2010-2012 Juanjo Menent        <jmenent@2byte.es>
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
 *	\file       htdocs/compta/lcr/lignes.php
 *  \ingroup    lcr
 *	\brief      Lcr lines
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



require_once DOL_DOCUMENT_ROOT.'/compta/paiement/class/paiement.class.php';
require_once DOL_DOCUMENT_ROOT.'/compta/bank/class/account.class.php';

dol_include_once('/lcr/class/bonlcr.class.php');
dol_include_once('/lcr/class/lignelcr.class.php');
dol_include_once('/lcr/class/rejetlcr.class.php');
dol_include_once('/lcr/core/lib/lcr.lib.php');

$langs->load("banks");
$langs->load("categories");

// Security check
if ($user->societe_id > 0) accessforbidden();

//$langs->load('withdrawals');
$langs->load("lcr");
$langs->load('bills');

// Get supervariables
$prev_id = GETPOST('id','int');
$socid = GETPOST('socid','int');
$page = GETPOST('page','int');
$sortorder = ((GETPOST('sortorder','alpha')=="")) ? "DESC" : GETPOST('sortorder','alpha');
$sortfield = ((GETPOST('sortfield','alpha')=="")) ? "pl.fk_soc" : GETPOST('sortfield','alpha');


/**
 * View
 */

llxHeader('',$langs->trans("BankdraftReceipts"));

if ($prev_id)
{
	$bon = new BonLcr($db,"");

	if ($bon->fetch($prev_id) == 0)
	{
		$head = lcr_prepare_head($bon);
		dol_fiche_head($head, 'lines', $langs->trans("BankdraftReceipts"), '', 'payment');

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
		dol_print_error($db);
	}
}

$ligne=new LigneLcr($db,$user);

if ($page == -1) { $page = 0 ; }

$offset = $conf->liste_limit * $page ;
$pageprev = $page - 1;
$pagenext = $page + 1;


/**
 * List of lines lcr
 */

$sql = "SELECT pl.rowid, pl.statut, pl.amount";
$sql.= ", s.rowid as socid, s.nom";
$sql.= " FROM ".MAIN_DB_PREFIX."lcr_lignes as pl";
$sql.= ", ".MAIN_DB_PREFIX."lcr_bons as pb";
$sql.= ", ".MAIN_DB_PREFIX."societe as s";
$sql.= " WHERE pl.fk_lcr_bons = ".$prev_id;
$sql.= " AND pl.fk_lcr_bons = pb.rowid";
$sql.= " AND pb.entity = ".$conf->entity;
$sql.= " AND pl.fk_soc = s.rowid";
if ($socid)	$sql.= " AND s.rowid = ".$socid;
$sql.= " ORDER BY $sortfield $sortorder ";
$sql.= $db->plimit($conf->liste_limit+1, $offset);

$result = $db->query($sql);

if ($result)
{
	$num = $db->num_rows($result);
	$i = 0;

	$urladd = "&amp;id=".$prev_id;

	print_barre_liste("", $page, "lignes.php", $urladd, $sortfield, $sortorder, '', $num);
	print"\n<!-- debut table -->\n";
	print '<table class="noborder" width="100%" cellspacing="0" cellpadding="4">';
	print '<tr class="liste_titre">';
	print_liste_field_titre($langs->trans("BankdraftLines"),"lignes.php","pl.rowid",'',$urladd);
	print_liste_field_titre($langs->trans("BankdraftThirdParty"),"lignes.php","s.nom",'',$urladd);
	print_liste_field_titre($langs->trans("BankdraftAmount"),"lignes.php","pl.amount","",$urladd,'align="center"');
	print '<td colspan="2">&nbsp;</td></tr>';

	$var=false;

	$total = 0;

	while ($i < min($num,$conf->liste_limit))
	{
		$obj = $db->fetch_object($result);

		print "<tr ".$bc[$var]."><td>";

		print $ligne->LibStatut($obj->statut,2);
		print "&nbsp;";

		print '<a href="'.dol_buildpath('/lcr/ligne.php?id='.$obj->rowid,1 ).'">';
		print substr('000000'.$obj->rowid, -6);
		print '</a></td>';

		print '<td><a href="'.dol_buildpath('/comm/card.php?socid='.$obj->socid,1 ).'">'.stripslashes($obj->nom)."</a></td>\n";

		print '<td align="center">'.price($obj->amount)."</td>\n";

		print '<td>';

		if ($obj->statut == 3)
		{
	  		print '<b>'.$langs->trans("BankdraftStatusRefused").'</b>';
		}
		else
		{
	  		print "&nbsp;";
		}

		print '</td></tr>';

		$total += $obj->total_ttc;
		$var=!$var;
		$i++;
	}

	if($socid)
	{
		print "<tr ".$bc[$var]."><td>";

		print '<td>Total</td>';

		print '<td align="center">'.price($total)."</td>\n";

		print '<td>&nbsp;</td>';

		print "</tr>\n";
	}

	print "</table>";
	$db->free($result);
}
else
{
	dol_print_error($db);
}

$db->close();

llxFooter();
