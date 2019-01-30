<?php
/* Copyright (C) 2004-2005 Rodolphe Quiedeville <rodolphe@quiedeville.org>
 * Copyright (C) 2005-2012 Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) 2005-2009 Regis Houssin        <regis.houssin@capnetworks.com>
 * Copyright (C) 2011      Juanjo Menent		<jmenent@2byte.es>
 * Copyright (C) 2013      Florian Henry		<florian.henry@open-concept.pro>
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
 *	\file       htdocs/compta/lcr/index.php
 *  \ingroup    lcr
 *	\brief      Lcr index page
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



dol_include_once('/lcr/class/bonlcr.class.php');
dol_include_once('/lcr/core/lib/lcr.lib.php');


require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
require_once DOL_DOCUMENT_ROOT.'/compta/bank/class/account.class.php';

$langs->load("banks");
$langs->load("categories");
$langs->load("lcr");

// Security check
$socid = GETPOST('socid','int');


/*
 * Actions
 */




/*
 * View
 */
llxHeader('',$langs->trans("BankdraftCustomersStandingOrdersArea")." Lcr");

if (lcr_check_config() < 0)
{
	$langs->load("errors");
	print '<div class="error">';
	print $langs->trans("BankdraftErrorModuleSetupNotComplete");
	print '</div>';
}

print_fiche_titre($langs->trans("BankdraftCustomersStandingOrdersArea")." Lcr");


print '<div class="fichecenter"><div class="fichethirdleft">';


$thirdpartystatic=new Societe($db);
$invoicestatic=new Facture($db);
$bprev = new BonLcr($db);
$var=true;

print '<table class="noborder" width="100%">';
print '<tr class="liste_titre"><td colspan="2">'.$langs->trans("BankdraftStatistics").'</td></tr>';
$var=!$var;
print '<tr '.$bc[$var].'><td>'.$langs->trans("NbOfInvoiceToBankdraft").'</td>';
print '<td align="right">';
print '<a href="'.dol_buildpath('/lcr/demandes.php?status=0',1).'">';
print $bprev->NbFactureAPrelever();
print '</a>';
print '</td></tr>';
$var=!$var;
print '<tr '.$bc[$var].'><td>'.$langs->trans("AmountToBankdraft").'</td>';
print '<td align="right">';
print price($bprev->SommeAPrelever(),'','',1,-1,-1,$conf->currency);
print '</td></tr></table><br>';

/*
 * Invoices waiting for lcr
 */
$sql = "SELECT f.facnumber, f.rowid, pfd.amount,pfd.date_lim_reglement,f.total_ttc, f.fk_statut, f.paye, f.type,";
$sql.= " pfd.date_demande,";
$sql.= " s.nom, s.rowid as socid";
$sql.= " FROM ".MAIN_DB_PREFIX."facture as f,";
$sql.= " ".MAIN_DB_PREFIX."societe as s";
if (!$user->rights->societe->client->voir && !$socid) $sql.= ", ".MAIN_DB_PREFIX."societe_commerciaux as sc";
$sql.= " , ".MAIN_DB_PREFIX."lcr_facture_demande as pfd";
$sql.= " WHERE s.rowid = f.fk_soc";
$sql.= " AND f.entity = ".$conf->entity;
$sql.= " AND pfd.traite = 0 AND pfd.fk_facture = f.rowid";
if (!$user->rights->societe->client->voir && !$socid) $sql.= " AND s.rowid = sc.fk_soc AND sc.fk_user = " .$user->id;
if ($socid) $sql.= " AND f.fk_soc = ".$socid;

$resql=$db->query($sql);
if ($resql)
{
    $num = $db->num_rows($resql);
    $i = 0;

    print '<table class="noborder" width="100%">';
    print '<tr class="liste_titre">';
    print '<td colspan="7">'.$langs->trans("BankdraftInvoiceWaiting").' ('.$num.')</td></tr>';
    if ($num)
    {
        $var = True;
        while ($i < $num && $i < 200)
        {
            $obj = $db->fetch_object($resql);

            $invoicestatic->id=$obj->rowid;
            $invoicestatic->ref=$obj->facnumber;
            $invoicestatic->statut=$obj->fk_statut;
            $invoicestatic->paye=$obj->paye;
            $invoicestatic->type=$obj->type;
            $alreadypayed=$invoicestatic->getSommePaiement();

            $var=!$var;
            print '<tr '.$bc[$var].'><td>';
            print $invoicestatic->getNomUrl(1,'withdrawlcr');
            print '</td>';

            print '<td>';
            $thirdpartystatic->id=$obj->socid;
            $thirdpartystatic->nom=$obj->nom;
            print $thirdpartystatic->getNomUrl(1,'customer');
            print '</td>';

			print '<td align="right">';
            print price($obj->amount);
            print '</td>';
            print '<td align="right">/ ';
            print price($obj->total_ttc);
            print '</td>';

            print '<td align="right">';
            print dol_print_date($db->jdate($obj->date_demande),'day');
            print '</td>';
			print '<td align="right">';
            print dol_print_date($db->jdate($obj->date_lim_reglement),'day');
            print '</td>';
            print '<td align="right">';
            print $invoicestatic->getLibStatut(3,$alreadypayed);
            print '</td>';
            print '</tr>';
            $i++;
        }
    }
    else
    {
        print '<tr><td colspan="7">'.$langs->trans("BankdraftNoInvoice").'</td></tr>';
    }
    print "</table><br>";
}
else
{
    dol_print_error($db);
}


print '</div><div class="fichetwothirdright"><div class="ficheaddleft">';


/*
 * Lcr receipts
 */
$limit=10;
$sql = "SELECT p.rowid, p.ref, p.amount, p.datec, p.statut";
$sql.= " FROM ".MAIN_DB_PREFIX."lcr_bons as p";
$sql.= " ORDER BY datec DESC";
$sql.= $db->plimit($limit);

$result = $db->query($sql);
if ($result)
{
    $num = $db->num_rows($result);
    $i = 0;
    $var=True;

    print"\n<!-- debut table -->\n";
    print '<table class="noborder" width="100%">';
    print '<tr class="liste_titre"><td>'.$langs->trans("LastBankdraftReceipt",$limit).'</td>';
    print '<td>'.$langs->trans("BankdraftDate").'</td>';
    print '<td align="right">'.$langs->trans("BankdraftAmount").'</td>';
    print '<td align="right">'.$langs->trans("BankdraftStatus").'</td>';
    print '</tr>';

    while ($i < min($num,$limit))
    {
        $obj = $db->fetch_object($result);
        $var=!$var;

        print "<tr ".$bc[$var].">";

        print "<td>";
        $bprev->id=$obj->rowid;
        $bprev->ref=$obj->ref;
        $bprev->statut=$obj->statut;
        print $bprev->getNomUrl(1);
        print "</td>\n";
        print '<td>'.dol_print_date($db->jdate($obj->datec),"dayhour")."</td>\n";
        print '<td align="right">'.price($obj->amount)."</td>\n";
        print '<td align="right">'.$bprev->getLibStatut(3)."</td>\n";

        print "</tr>\n";
        $i++;
    }
    print "</table><br>";
    $db->free($result);
}
else
{
    dol_print_error($db);
}


print '</div></div></div>';

llxFooter();

$db->close();
