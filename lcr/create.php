<?php
/* Copyright (C) 2005      Rodolphe Quiedeville <rodolphe@quiedeville.org>
 * Copyright (C) 2010      Laurent Destailleur  <eldy@users.sourceforge.net>
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
 *	\file       htdocs/compta/lcr/create.php
 *  \ingroup    lcr
 *	\brief      Lcr creation page
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


require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/compta/bank/class/account.class.php';

dol_include_once('/lcr/class/bonlcr.class.php');
dol_include_once('/lcr/core/lib/lcr.lib.php');


$langs->load("banks");
$langs->load("categories");
$langs->load("lcr");
$langs->load("companies");
$langs->load("bills");

// Security check
$socid = GETPOST('socid','int');
if ($user->societe_id) $socid=$user->societe_id;
$result = restrictedArea($user, 'lcr','','','bons');

// Get supervariable
$action = GETPOST('action','alpha');
$sortorder = ((GETPOST('sortorder','alpha')=="")) ? "DESC" : GETPOST('sortorder','alpha');
$sortfield = ((GETPOST('sortfield','alpha')=="")) ? "pfd.date_lim_reglement" : GETPOST('sortfield','alpha');
$contextpage= GETPOST('contextpage','aZ')?GETPOST('contextpage','Za'):'create';   // To manage different context of search
$backtopage = GETPOST('backtopage','alpha');                                            // Go back to a dedicated page
$optioncss  = GETPOST('optioncss','aZ');                    

/**
 * Actions
 */

// Change customer bank information to withdraw
if ($action == 'modify')
{
    for ($i = 1 ; $i < 9 ; $i++)
    {
        dolibarr_set_const($db, GETPOST("nom$i"), GETPOST("value$i"),'chaine',0,'',$conf->entity);
    }
}
if ($action == 'create')
{
    $bprev = new BonLcr($db);

	if (!empty($_REQUEST["date_lim_reglement"]))
		$result=$bprev->create(GETPOST("LCR_ID_BANKACCOUNT"), GETPOST("mode"),'real',$_REQUEST["date_lim_reglement"]);
	else
		$result=$bprev->create(GETPOST("LCR_ID_BANKACCOUNT"), GETPOST("mode"));
    if ($result < 0)
    {
        $mesg='<div class="error">'.$bprev->error.'</div>';
    }
    if ($result == 0)
    {
        $mesg='<div class="error">'.$langs->trans("NoInvoiceCouldBeBankdraft").'</div>';
    }
}


/**
 * View
 */

$thirdpartystatic=new Societe($db);
$bprev = new BonLcr($db);

llxHeader('', $langs->trans("BankdraftNewStandingOrder"));

if (lcr_check_config() < 0)
{
	$langs->load("errors");
	print '<div class="error">';
	print $langs->trans("ErrorModuleSetupNotComplete");
	print '</div>';
}


print load_fiche_titre($langs->trans("BankdraftNewStandingOrder"));

dol_fiche_head();

$nbdate=$bprev->NbFactureAPreleverdate();
$pricetobankdraftdate=$bprev->SommeAPreleverdate();
$nb=$bprev->NbFactureAPrelever();
$nb1=$bprev->NbFactureAPrelever(1);
$nb11=$bprev->NbFactureAPrelever(1,1);
$pricetobankdraft=$bprev->SommeAPrelever();
if ($nb < 0 || $nb1 < 0 || $nb11 < 0)
{
    dol_print_error($bprev->error);
}
if ($nb) {
    print '<div class="info">Sélectionner les Lcr à prélever puis cliquer sur le bouton "Générer fichier Lcr "</div>';
}


if ($mesg) print $mesg;

    print "<div class=\"tabsAction\">\n";
$form=new Form($db);
if ($nb)
{
	print '<form method="GET" action="create.php">';
    print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';


        /**
         * Invoices waiting for lcr
         */

        $sql = "SELECT f.facnumber, f.rowid, f.total_ttc,pfd.amount,pfd.date_lim_reglement, s.nom, s.rowid as socid,";
        $sql.= " pfd.rowid as facdem_id, pfd.date_demande, pfd.mode, pfd.code_banque ";
        $sql.= " FROM ".MAIN_DB_PREFIX."facture as f,";
        $sql.= " ".MAIN_DB_PREFIX."societe as s,";
        $sql.= " ".MAIN_DB_PREFIX."lcr_facture_demande as pfd";
        $sql.= " WHERE s.rowid = f.fk_soc";
        $sql.= " AND f.entity = ".$conf->entity;
        $sql.= " AND pfd.traite = 0";
        $sql.= " AND pfd.fk_facture = f.rowid";
        if ($socid) $sql.= " AND f.fk_soc = ".$socid;
        $sql.=$db->order($sortfield,$sortorder);
    
        $resql=$db->query($sql);
        if ($resql)
        {
            $num = $db->num_rows($resql);
            $i = 0;

            print_fiche_titre($langs->trans("BankdraftInvoiceWaiting").($num > 0?' ('.$num.')':''),'','');

            print '
                <script language="javascript" type="text/javascript">
                jQuery(document).ready(function()
                {
                    jQuery("#checkall_lcr").click(function(e) {
                        e.preventDefault();
                        jQuery(".lcr_field_checkbox").prop(\'checked\', true);
                    });
                    jQuery("#checknone_lcr").click(function(e) {
                        e.preventDefault();
                        jQuery(".lcr_field_checkbox").prop(\'checked\', false);
                    });
                });
                </script>
                ';

            print '<table class="noborder" width="100%">';
            print '<tr class="liste_titre">';
            print_liste_field_titre($langs->trans("BankdraftBills"),$_SERVER["PHP_SELF"],"f.facnumber",'',$urladd);
            print_liste_field_titre($langs->trans("BankdraftDateMaxPayment"),$_SERVER["PHP_SELF"],"pfd.date_lim_reglement","","",'align="left"',$sortfield, $sortorder);
            print_liste_field_titre($langs->trans("BankdraftCompany"),$_SERVER["PHP_SELF"],"s.nom");
            print_liste_field_titre($langs->trans("BankdraftRequestAmount"),$_SERVER["PHP_SELF"],"pfd.amount","","",'align="left"',$sortfield,$sortorder);
            print_liste_field_titre($langs->trans("BankdraftRequestAmountTtc"),$_SERVER["PHP_SELF"],"f.total_ttc","","",'align="left"',$sortfield,$sortorder);
            print '<td align="center" width="100px">'.$langs->trans("Select")."<br>";
            if ($conf->use_javascript_ajax)
                print '<a href="#" id="checkall_lcr">'.
                    $langs->trans("All").
                    '</a> / <a href="#" id="checknone_lcr">'.$langs->trans("None").'</a>';
            print '</td>';
            print '</tr>';

            if ($num)
            {
                $var = True;
                while ($i < $num && $i < 200)
                {
                    $obj = $db->fetch_object($resql);

                    $var=!$var;
                    print '<tr '.$bc[$var].'>';

                    print '<td align="left">';
                    print "<a href='" . dol_buildpath('/compta/facture/card.php?facid='.$obj->rowid, 1) . "'>".$obj->facnumber."</a>";
                    print '</td>';

                    print '<td align="left">';
                    print dol_print_date($db->jdate($obj->date_lim_reglement),'day');
                    print '</td>';

                    print '<td align="left">';
                    $thirdpartystatic->id=$obj->socid;
                    $thirdpartystatic->nom=$obj->nom;
                    print $thirdpartystatic->getNomUrl(1,'customer');
                    print '</td>';

                    print '<td align="left">';
                    print price($obj->amount,0,$langs,0,0,-1,$conf->currency);
                    print '</td>';
                    print '<td align="left">';
                    print price($obj->total_ttc,0,$langs,0,0,-1,$conf->currency);
                    print '</td>';

                    print '<td align="center">';
                    print '<input id="lcr_field_'.$obj->rowid.'" class="flat lcr_field_checkbox" checked type="checkbox" name="lcrItemsSelected[]" value="'.$obj->facdem_id.'">';
                    print '</td>' ;

                    print '</tr>';
                    $i++;
                }
            }
            else print '<tr><td colspan="7">'.$langs->trans("None").'</td></tr>';

            // footer
            print '<td colspan="3" align="right"><b>Montant total des Lcr à prélever</b></td>';
            print '<td align="right">' . price($pricetobankdraft, 0, $langs, 0, 0, -1, $conf->currency) . '</td>';
            print '<td colspan="2"></td>';

            print "</table>";
            print "<br>\n";
        }
        else
        {
            dol_print_error($db);
        }

	print $langs->trans("BankdraftType").":";
	print '<select name="mode">';
    print '<option value="3">'.$langs->trans('BankdraftImmediat').'</option>';
    print '<option value="1">'.$langs->trans('BankdraftEscompte') .'</option>';
    print '</select> ';
   	print $langs->trans("BankdraftToReceive").":";
	print $form->select_comptes($conf->global->LCR_ID_BANKACCOUNT,'LCR_ID_BANKACCOUNT',0,"courant=1",0);

    if ($pricetobankdraft) print '<button type="submit" class="butAction" name="action" value="create">'.$langs->trans("Bankdraftdemande")."</button>\n";
    else print '<a class="butActionRefused" href="#">'.$langs->trans("BankdraftCreateAll")."</a>\n";

	print '</form>';

}
else
{
    print '<a class="butActionRefused" href="#" title="'.dol_escape_htmltag($langs->transnoentitiesnoconv("BankdraftNoInvoice")).'">'.$langs->trans("BankdraftCreateAll")."</a>\n";
}

print "</div>\n";
print '<br />';

print '</div>';


/**
 * List of last lcr
 */

$limit=10;

print_fiche_titre($langs->trans("BankdraftLastReceipts",$limit),'','');

$sql = "SELECT p.rowid, p.ref, p.amount, p.statut";
$sql.= ", p.datec";
$sql.= " FROM ".MAIN_DB_PREFIX."lcr_bons as p";
$sql.= " WHERE p.entity = ".$conf->entity;
$sql.= " ORDER BY datec DESC";
$sql.=$db->plimit($limit);

$result = $db->query($sql);
if ($result)
{
    $num = $db->num_rows($result);
    $i = 0;

    $urladd= "&amp;statut=".$statut;
   
    print"\n<!-- debut table -->\n";
    print '<table class="noborder" width="100%">';
    print '<tr class="liste_titre"><td>'.$langs->trans("BankdraftReceipts").'</td>';
    print '<td align="center">'.$langs->trans("BankdraftDate").'</td><td align="right">'.$langs->trans("BankdraftAmount").'</td>';
    print '</tr>';

    $var=True;

    while ($i < min($num,$limit))
    {
        $obj = $db->fetch_object($result);
        $var=!$var;

        print "<tr ".$bc[$var]."><td>";
        print $bprev->LibStatut($obj->statut,2);
        print "&nbsp;";
        print '<a href="fiche.php?id='.$obj->rowid.'">'.$obj->ref."</a></td>\n";
        
        print "</td>\n";

        print '<td align="center">'.dol_print_date($db->jdate($obj->datec),'day')."</td>\n";

        print '<td align="right">'.price($obj->amount,0,$langs,0,0,-1,$conf->currency)."</td>\n";

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

$db->close();

llxFooter();
