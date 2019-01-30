<?php
/* Copyright (C) 2005      Rodolphe Quiedeville <rodolphe@quiedeville.org>
 * Copyright (C) 2005-2012 Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) 2005-2009 Regis Houssin        <regis.houssin@capnetworks.com>
 * Copyright (C) 2010-2011 Juanjo Menent        <jmenent@2byte.es>
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
 *		\file       htdocs/compta/lcr/stats.php
 *      \ingroup    lcr
 *      \brief      Page de stats des lcrs
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


/**
 * View
 */

llxHeader('',$langs->trans("BankdraftStatistics"));

print_fiche_titre($langs->trans("BankdraftStatistics"));

// Define total and nbtotal
$sql = "SELECT sum(pl.amount), count(pl.amount)";
$sql.= " FROM ".MAIN_DB_PREFIX."lcr_lignes as pl";
$sql.= ", ".MAIN_DB_PREFIX."lcr_bons as pb";
$sql.= " WHERE pl.fk_lcr_bons = pb.rowid";
$sql.= " AND pb.entity = ".$conf->entity;
$resql=$db->query($sql);
if ($resql)
{
    $num = $db->num_rows($resql);
    $i = 0;

    if ( $num > 0 )
    {
        $row = $db->fetch_row($resql);
        $total = $row[0];
        $nbtotal = $row[1];
    }
}


/**
 * Stats
 */

print '<br>';
print_titre($langs->trans("BankdraftStatistics"));

$ligne=new LigneLcr($db,$user);

$sql = "SELECT sum(pl.amount), count(pl.amount), pl.statut";
$sql.= " FROM ".MAIN_DB_PREFIX."lcr_lignes as pl";
$sql.= ", ".MAIN_DB_PREFIX."lcr_bons as pb";
$sql.= " WHERE pl.fk_lcr_bons = pb.rowid";
$sql.= " AND pb.entity = ".$conf->entity;
$sql.= " GROUP BY pl.statut";

$resql=$db->query($sql);
if ($resql)
{
	$num = $db->num_rows($resql);
	$i = 0;

	print"\n<!-- debut table -->\n";
	print '<table class="noborder" width="100%">';
	print '<tr class="liste_titre">';
	print '<td width="30%">'.$langs->trans("BankdraftStatus").'</td><td align="center">'.$langs->trans("BankdraftNumber").'</td><td align="right">%</td>';
	print '<td align="right">'.$langs->trans("BankdraftAmount").'</td><td align="right">%</td></tr>';

	$var=True;

	while ($i < $num)
	{
		$row = $db->fetch_row($resql);

		print "<tr ".$bc[$var]."><td>";

		print $ligne->LibStatut($row[2],1);
		
		print '</td><td align="center">';
		print $row[1];

		print '</td><td align="right">';
		print round($row[1]/$nbtotal*100,2)." %";

		print '</td><td align="right">';

		print price($row[0]);

		print '</td><td align="right">';
		print round($row[0]/$total*100,2)." %";
		print '</td></tr>';

		$var=!$var;
		$i++;
	}

	print '<tr class="liste_total"><td align="right">'.$langs->trans("BankdraftTotal").'</td>';
	print '<td align="center">'.$nbtotal.'</td><td>&nbsp;</td><td align="right">';
	print price($total);
	print '</td><td align="right">&nbsp;</td>';
	print "</tr></table>";
	$db->free();
}
else
{
	dol_print_error($db);
}


/**
 * Stats of rejects
 */

print '<br>';
print_titre($langs->trans("BankdraftRejectStatistics"));

// Define total and nbtotal
$sql = "SELECT sum(pl.amount), count(pl.amount)";
$sql.= " FROM ".MAIN_DB_PREFIX."lcr_lignes as pl";
$sql.= ", ".MAIN_DB_PREFIX."lcr_bons as pb";
$sql.= " WHERE pl.fk_lcr_bons = pb.rowid";
$sql.= " AND pb.entity = ".$conf->entity;
$sql.= " AND pl.statut = 3";
$resql=$db->query($sql);
if ($resql)
{
    $num = $db->num_rows($resql);
    $i = 0;

    if ( $num > 0 )
    {
        $row = $db->fetch_row($resql);
        $total = $row[0];
        $nbtotal = $row[1];
    }
}


/**
 * Stats on rejects
 */

$sql = "SELECT sum(pl.amount), count(pl.amount) as cc, pr.motif";
$sql.= " FROM ".MAIN_DB_PREFIX."lcr_lignes as pl";
$sql.= ", ".MAIN_DB_PREFIX."lcr_bons as pb";
$sql.= ", ".MAIN_DB_PREFIX."lcr_rejet as pr";
$sql.= " WHERE pl.fk_lcr_bons = pb.rowid";
$sql.= " AND pb.entity = ".$conf->entity;
$sql.= " AND pl.statut = 3";
$sql.= " AND pr.fk_lcr_lignes = pl.rowid";
$sql.= " GROUP BY pr.motif";
$sql.= " ORDER BY cc DESC";

$resql=$db->query($sql);
if ($resql)
{
	$num = $db->num_rows($resql);
	$i = 0;

	print"\n<!-- debut table -->\n";
	print '<table class="noborder" width="100%">';
	print '<tr class="liste_titre">';
	print '<td width="30%">'.$langs->trans("BankdraftStatus").'</td><td align="center">'.$langs->trans("BankdraftNumber").'</td>';
	print '<td align="right">%</td><td align="right">'.$langs->trans("BankdraftAmount").'</td><td align="right">%</td></tr>';

	$var=True;

	$Rejet = new RejetLcr($db, $user);

	while ($i < $num)
	{
		$row = $db->fetch_row($resql);

		print "<tr ".$bc[$var]."><td>";
		print $Rejet->motifs[$row[2]];

		print '</td><td align="center">'.$row[1];

		print '</td><td align="right">';
		print round($row[1]/$nbtotal*100,2)." %";

		print '</td><td align="right">';
		print price($row[0]);

		print '</td><td align="right">';
		print round($row[0]/$total*100,2)." %";


		print '</td></tr>';

		$var=!$var;
		$i++;
	}

	print '<tr class="liste_total"><td align="right">'.$langs->trans("BankdraftTotal").'</td><td align="center">'.$nbtotal.'</td>';
	print '<td>&nbsp;</td><td align="right">';
	print price($total);
	print '</td><td align="right">&nbsp;</td>';
	print "</tr></table>";
	$db->free($resql);
}
else
{
	dol_print_error($db);
}

llxFooter();

$db->close();

