<?php
/* Copyright (C) 2005      Rodolphe Quiedeville <rodolphe@quiedeville.org>
 * Copyright (C) 2005-2010 Laurent Destailleur  <eldy@users.sourceforge.net>
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
 *      \file       htdocs/compta/lcr/liste.php
 *      \ingroup    lcr
 *      \brief      Page liste des lcrs
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

require_once DOL_DOCUMENT_ROOT . '/core/lib/admin.lib.php';

$langs->load("banks");
$langs->load("lcr");
$langs->load("companies");
$langs->load("categories");

// Security check
$socid = GETPOST('socid','int');
if ($user->societe_id) $socid=$user->societe_id;
$result = restrictedArea($user, 'lcr','','','bons');

// Get supervariables

$mesg = '';
$action = GETPOST('action','aZ09');
$cancel = GETPOST('cancel','alpha');
$id = GETPOST('id', 'int');
$rowid = GETPOST('rowid', 'int');
$contextpage=GETPOST('contextpage','aZ')?GETPOST('contextpage','aZ'):'accountingaccountlist';   // To manage different context of search





$page = GETPOST('page','int');
$sortorder = ((GETPOST('sortorder','alpha')=="")) ? "DESC" : GETPOST('sortorder','alpha');
$sortfield = ((GETPOST('sortfield','alpha')=="")) ? "p.datec" : GETPOST('sortfield','alpha');
$search_line = GETPOST('search_ligne','alpha');
$search_bon = GETPOST('search_bon','alpha');
$search_code = GETPOST('search_code','alpha');
$search_societe = GETPOST('search_societe','alpha');
$statut = GETPOST('statut','int');





// add choice column
$arrayfields=array(
    'p.ref'=>array('label'=>$langs->trans("BankdraftReceipts"), 'checked'=>1),
    'f.facnumber'=>array('label'=>$langs->trans("BankdraftBills"), 'checked'=>1),
	's.nom'=>array('label'=>$langs->trans("BankdraftCompany"), 'checked'=>1),
	's.code_client'=>array('label'=>$langs->trans("BankdraftCustomerCode"), 'checked'=>1),
	//// to do change trans
	's.code_compta'=>array('label'=>$langs->trans("AccountingCustomerCode"), 'checked'=>1),
	'sr.iban_prefix'=>array('label'=>$langs->trans("iban"), 'checked'=>1),
	'sr.bic'=>array('label'=>$langs->trans("bic"), 'checked'=>1),
	///////
	'p.datec'=>array('label'=>$langs->trans("BankdraftDate"), 'checked'=>1),
	'pl.amount'=>array('label'=>$langs->trans("BankdraftRequestAmount"), 'checked'=>1),
	'f.total_ttc'=>array('label'=>$langs->trans("BankdraftRequestAmountTtc"), 'checked'=>1)
	
	);

//////////////


$bon=new BonLcr($db,"");
$ligne=new LigneLcr($db,$user);

$offset = $conf->liste_limit * $page ;

//actions

if (GETPOST('cancel','alpha')) { $action='list'; $massaction=''; }
if (! GETPOST('confirmmassaction','alpha')) { $massaction=''; }

$parameters=array();
$reshook=$hookmanager->executeHooks('doActions',$parameters,$object,$action);    // Note that $action and $object may have been modified by some hooks
if ($reshook < 0) setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');

include DOL_DOCUMENT_ROOT.'/core/actions_changeselectedfields.inc.php';

/**
 *  View
 */

llxHeader('',$langs->trans("BankdraftLines"));

$sql = "SELECT p.rowid, p.ref, p.statut, p.datec";
$sql.= " ,f.rowid as facid, f.facnumber, f.total_ttc";
$sql.= " , s.rowid as socid, s.nom, s.code_client, s.code_compta";
$sql.= " , pl.amount, pl.statut as statut_ligne, pl.rowid as rowid_ligne";
$sql.= " , sr.iban_prefix, sr.bic, sr.fk_soc";
$sql.= " FROM ".MAIN_DB_PREFIX."lcr_bons as p";
$sql.= " , ".MAIN_DB_PREFIX."lcr_lignes as pl";
$sql.= " , ".MAIN_DB_PREFIX."lcr_facture as pf";
$sql.= " , ".MAIN_DB_PREFIX."facture as f";
$sql.= " , ".MAIN_DB_PREFIX."societe as s";
$sql.= " , ".MAIN_DB_PREFIX."societe_rib as sr";
$sql.= " WHERE pl.fk_lcr_bons = p.rowid";
$sql.= " AND pf.fk_lcr_lignes = pl.rowid";
$sql.= " AND pf.fk_facture = f.rowid";
$sql.= " AND f.fk_soc = s.rowid";
$sql.= " AND f.fk_soc = sr.fk_soc";
$sql.= " AND f.entity = ".$conf->entity;
if ($socid) $sql.= " AND s.rowid = ".$socid;
if ($search_line)
{
    $sql.= " AND pl.rowid = '".$search_line."'";
}
if ($search_bon)
{
    $sql.= " AND p.ref LIKE '%".$search_bon."%'";
}
if ($search_invoice)
{
    $sql.= "AND f.facnumber LIKE '%".$search_invoice."%'";
}
if ($search_code)
{
    $sql.= " AND s.code_client LIKE '%".$search_code."%'";
}
if ($search_societe)
{
    $sql .= " AND s.nom LIKE '%".$search_societe."%'";
}

$sql.=$db->order($sortfield,$sortorder);
$sql.=$db->plimit($conf->liste_limit+1, $offset);

$result = $db->query($sql);
if ($result)
{
    $num = $db->num_rows($result);
    $i = 0;

    $urladd = "&amp;statut=".$statut;
    $urladd .= "&amp;search_bon=".$search_bon;

    print_barre_liste($langs->trans("BankdraftLines"), $page, "liste.php", $urladd, $sortfield, $sortorder, '', $num);

	$varpage=empty($contextpage)?$_SERVER["PHP_SELF"]:$contextpage;
    $selectedfields=$form->multiSelectArrayWithCheckbox('selectedfields', $arrayfields, $varpage);
	
	
	print '<form method="POST" id="searchFormList" action="' . $_SERVER["PHP_SELF"] . '">';
	if ($optioncss != '') print '<input type="hidden" name="optioncss" value="'.$optioncss.'">';
	print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
	print '<input type="hidden" name="formfilteraction" id="formfilteraction" value="list">';
	print '<input type="hidden" name="action" value="list">';
	print '<input type="hidden" name="sortfield" value="'.$sortfield.'">';
	print '<input type="hidden" name="sortorder" value="'.$sortorder.'">';
	print '<input type="hidden" name="page" value="'.$page.'">';
	print '<input type="hidden" name="contextpage" value="'.$contextpage.'">';
	
    print"\n<!-- debut table -->\n";
    print '<table class="tagtable liste'.($moreforfilter?" listwithfilterbefore":"").'">'."\n";

    print '<tr class="liste_titre_filter">';
    if (! empty($arrayfields['p.ref']['checked']))				print '<td class="liste_titre">'.$langs->trans("BankdraftLine").'</td>';
    if (! empty($arrayfields['p.ref']['checked']))				print_liste_field_titre($langs->trans("BankdraftReceipts"),$_SERVER["PHP_SELF"],"p.ref");
    if (! empty($arrayfields['f.facnumber']['checked']))		print_liste_field_titre($langs->trans("BankdraftBills"),$_SERVER["PHP_SELF"],"f.facnumber",'',$urladd);
    if (! empty($arrayfields['s.nom']['checked']))				print_liste_field_titre($langs->trans("BankdraftCompany"),$_SERVER["PHP_SELF"],"s.nom");
    if (! empty($arrayfields['s.code_client']['checked']))		print_liste_field_titre($langs->trans("BankdraftCustomerCode"),$_SERVER["PHP_SELF"],"s.code_client",'','','align="center"');
	if (! empty($arrayfields['s.code_compta']['checked']))		print_liste_field_titre($langs->trans("AccountingCustomerCode"),$_SERVER["PHP_SELF"],"s.code_compta",'','','align="center"');
    if (! empty($arrayfields['sr.iban_prefix']['checked']))		print_liste_field_titre($langs->trans("iban"),$_SERVER["PHP_SELF"],"sr.iban_prefix",'','','align="left"');
    if (! empty($arrayfields['sr.bic']['checked']))				print_liste_field_titre($langs->trans("bic"),$_SERVER["PHP_SELF"],"sr.bic",'','','align="left"');
    if (! empty($arrayfields['p.datec']['checked']))			print_liste_field_titre($langs->trans("BankdraftDate"),$_SERVER["PHP_SELF"],"p.datec","","",'align="center"');
    if (! empty($arrayfields['pl.amount']['checked']))			print_liste_field_titre($langs->trans("BankdraftRequestAmount"),$_SERVER["PHP_SELF"],"pl.amount","","",'align="right"');
	if (! empty($arrayfields['f.total_ttc']['checked']))		print_liste_field_titre($langs->trans("BankdraftRequestAmountTtc"),$_SERVER["PHP_SELF"],"f.total_ttc","","",'align="right"');
    print '<td class="liste_titre">&nbsp;</td>';
	print_liste_field_titre($selectedfields, $_SERVER["PHP_SELF"],"",'','','align="center"',$sortfield,$sortorder,'maxwidthsearch ');
    print '</tr>';

    //print '<form action="liste.php" method="GET">';
    print '<tr class="liste_titre">';
    if (! empty($arrayfields['p.ref']['checked']))				print '<td class="liste_titre"><input type="text" class="flat" name="search_ligne" value="'. $search_line.'" size="6"></td>';
    if (! empty($arrayfields['p.ref']['checked']))				print '<td class="liste_titre"><input type="text" class="flat" name="search_bon" value="'. $search_bon.'" size="8"></td>';
    if (! empty($arrayfields['f.facnumber']['checked']))		print '<td class="liste_titre"><input type="text" class="flat" name="search_invoice" value="'. $search_invoice.'" size="8"></td>';
	if (! empty($arrayfields['s.nom']['checked']))				print '<td class="liste_titre"><input type="text" class="flat" name="search_societe" value="'. $search_societe.'" size="12"></td>';
    if (! empty($arrayfields['s.code_client']['checked']))		print '<td class="liste_titre" align="center"><input type="text" class="flat" name="search_code" value="'. $search_code.'" size="8"></td>';
	if (! empty($arrayfields['s.code_compta']['checked']))		print '<td class="liste_titre" align="center"><input type="text" class="flat" name="search_code_compta" value="'. $search_code_compta.'" size="8"></td>';
    if (! empty($arrayfields['sr.iban_prefix']['checked']))		print '<td class="liste_titre">&nbsp;</td>';
    if (! empty($arrayfields['sr.bic']['checked']))				print '<td class="liste_titre">&nbsp;</td>';
    if (! empty($arrayfields['p.datec']['checked']))			print '<td class="liste_titre">&nbsp;</td>';
	if (! empty($arrayfields['pl.amount']['checked']))			print '<td class="liste_titre">&nbsp;</td>';
    if (! empty($arrayfields['f.total_ttc']['checked']))		print '<td class="liste_titre">&nbsp;</td>';
	print '<td class="liste_titre">&nbsp;</td>';
    //print '<td class="liste_titre" align="right"><input type="image" class="liste_titre" src="'.img_picto($langs->trans("Search"),'search.png','','',1).'" name="button_search" value="'.dol_escape_htmltag($langs->trans("Search")).'" title="'.dol_escape_htmltag($langs->trans("Search")).'"></td>';
    print '<td align="right" class="liste_titre">';
	$searchpicto=$form->showFilterAndCheckAddButtons($massactionbutton?1:0, 'checkforselect', 1);
	print $searchpicto;
	print '</td>';
	
	print '</tr>';
    print '</form>';

    $var=True;

    while ($i < min($num,$conf->liste_limit))
    {
        $obj = $db->fetch_object($result);

        $var=!$var;
		
		print '<tr class="oddeven">';

		// reference
		if (! empty($arrayfields['p.ref']['checked']))
		{
			print "<td>";
		
		
        print "<tr ".$bc[$var]."><td>";

        print $ligne->LibStatut($obj->statut_ligne,2);
        print "&nbsp;";
        print '<a href="'.dol_buildpath('/lcr/ligne.php?id='.$obj->rowid_ligne,1).'">';
        print substr('000000'.$obj->rowid_ligne, -6);
        print '</a></td>';
		}
		
		// Bordereau

        print '<td>';

        print $bon->LibStatut($obj->statut,2);
        print "&nbsp;";

        print '<a href="fiche.php?id='.$obj->rowid.'">'.$obj->ref."</a></td>\n";

        print '<td><a href="'.DOL_URL_ROOT.'/compta/facture/card.php?facid='.$obj->facid.'">';
        print img_object($langs->trans("ShowBill"),"bill");
        print '&nbsp;<a href="'.DOL_URL_ROOT.'/compta/facture/card.php?facid='.$obj->facid.'">'.$obj->facnumber."</a></td>\n";
        print '</a></td>';

        print '<td><a href="'.dol_buildpath('/comm/card.php?socid='.$obj->socid,1).'">'.$obj->nom."</a></td>\n";

        print '<td align="center"><a href="'.dol_buildpath('/comm/card.php?socid='.$obj->socid,1).'">'.$obj->code_client."</a></td>\n";
		print '<td align="left">'.$obj->code_compta."</td>\n";
		print '<td align="left">'.$obj->iban_prefix."</td>\n";
		print '<td align="left">'.$obj->bic."</td>\n";

        print '<td align="center">'.dol_print_date($db->jdate($obj->datec),'day')."</td>\n";

        print '<td align="right">'.price($obj->amount)."</td>\n";
		print '<td align="right">'.price($obj->total_ttc)."</td>\n";

        print '<td>&nbsp;</td>';

        print "</tr>\n";
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
