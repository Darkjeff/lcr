<?php
/* Copyright (C) 2005      Rodolphe Quiedeville <rodolphe@quiedeville.org>
 * Copyright (C) 2005-2009 Regis Houssin        <regis.houssin@capnetworks.com>
 * Copyright (C) 2010-2013 Juanjo Menent 		<jmenent@2byte.es>
 * Copyright (C) 2005-2012 Laurent Destailleur  <eldy@users.sourceforge.net>
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
 *      \file       htdocs/compta/lcr/rejets.php
 *      \ingroup    lcr
 *      \brief      Reject page
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

dol_include_once('/lcr/class/lignelcr.class.php');
dol_include_once('/lcr/class/rejetlcr.class.php');


$langs->load("banks");
$langs->load("categories");
$langs->load("lcr");
$langs->load("companies");

// Security check
$socid = GETPOST('socid','int');
if ($user->societe_id) $socid=$user->societe_id;
$result = restrictedArea($user, 'lcr','','','bons');

// Get supervariables
$page = GETPOST('page','int');
$sortorder = GETPOST('sortorder','alpha');
$sortfield = GETPOST('sortfield','alpha');


/**
 * View
 */

llxHeader('',$langs->trans("BankdraftRefused"));

$offset = $conf->liste_limit * $page ;
$pageprev = $page - 1;
$pagenext = $page + 1;

if ($sortorder == "") $sortorder="DESC";
if ($sortfield == "") $sortfield="p.datec";

$rej = new RejetLcr($db, $user);
$ligne = new LigneLcr($db, $user);


/**
 * List of invoices
 */

$sql = "SELECT pl.rowid, pr.motif, p.ref, pl.statut";
$sql.= " , s.rowid as socid, s.nom";
$sql.= " FROM ".MAIN_DB_PREFIX."lcr_bons as p";
$sql.= " , ".MAIN_DB_PREFIX."lcr_rejet as pr";
$sql.= " , ".MAIN_DB_PREFIX."lcr_lignes as pl";
$sql.= " , ".MAIN_DB_PREFIX."societe as s";
$sql.= " WHERE pr.fk_lcr_lignes = pl.rowid";
$sql.= " AND pl.fk_lcr_bons = p.rowid";
$sql.= " AND pl.fk_soc = s.rowid";
$sql.= " AND p.entity = ".$conf->entity;
if ($socid) $sql.= " AND s.rowid = ".$socid;
$sql .= " ORDER BY $sortfield $sortorder " . $db->plimit($conf->liste_limit+1, $offset);

$result = $db->query($sql);
if ($result)
{
	$num = $db->num_rows($result);
	$i = 0;

	print_barre_liste($langs->trans("BankdraftRefused"), $page, "rejets.php", $urladd, $sortfield, $sortorder, '', $num);
	print"\n<!-- debut table -->\n";
	print '<table class="noborder" width="100%" cellspacing="0" cellpadding="4">';
	print '<tr class="liste_titre">';
	print_liste_field_titre($langs->trans("BankdraftLine"),"rejets.php","p.ref",'',$urladd);
	print_liste_field_titre($langs->trans("BankdraftCompany"),"rejets.php","s.nom",'',$urladd);
	print_liste_field_titre($langs->trans("BankdraftReason"),"rejets.php","pr.motif","",$urladd);
	print_liste_field_titre($langs->trans("BankdraftAmount"),"rejets.php","pl.amount","",$urladd);
	print '</tr>';

	$var=True;

	$total = 0;

	while ($i < min($num,$conf->liste_limit))
	{
		$obj = $db->fetch_object($result);

		print "<tr ".$bc[$var]."><td>";
		print $ligne->LibStatut($obj->statut,2).'&nbsp;';
		print '<a href="'.dol_buildpath('/lcr/ligne.php?id='.$obj->rowid,1).'">';

		print substr('000000'.$obj->rowid, -6)."</a></td>";

		print '<td><a href="'.dol_buildpath('/comm/card.php?socid='.$obj->socid,1).'">'.stripslashes($obj->nom)."</a></td>\n";

		print '<td>'.$rej->motifs[$obj->motif].'</td>';

		print '<td>'.price($obj->amount).'</td>';
		print "</tr>\n";

		$var=!$var;
		$i++;
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
