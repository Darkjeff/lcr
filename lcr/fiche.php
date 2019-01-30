<?php
/* Copyright (C) 2005      Rodolphe Quiedeville <rodolphe@quiedeville.org>
 * Copyright (C) 2005-2010 Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) 2010-2012 Juanjo Menent 		<jmenent@2byte.es>
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
 *	\file       htdocs/compta/lcr/fiche.php
 *	\ingroup    lcr
 *	\brief      Fiche lcr
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
dol_include_once('/lcr/core/lib/lcr.lib.php');


$langs->load("banks");
$langs->load("categories");
if (!$user->rights->lcr->bons->lire) accessforbidden();
$langs->load("bills");
$langs->load("lcr");


// Security check
if ($user->societe_id > 0) accessforbidden();

// Get supervariables
$action = GETPOST('action','alpha');
$id = GETPOST('id','int');


/*
 * Actions
 */
if ( $action == 'confirm_delete' )
{
	$bon = new BonLcr($db,"");
	$bon->fetch($id);

	$res=$bon->delete();
	if ($res > 0)
	{
		header("Location: index.php");
		exit;
	}
}

if ( $action == 'confirm_credite' && GETPOST('confirm','alpha') == 'yes')
{
	$bon = new BonLcr($db,"");
	$bon->fetch($id);

	$bon->set_credite();

	header("Location: fiche.php?id=".$id);
	exit;
}

if ($action == 'infotrans' && $user->rights->lcr->bons->send)
{
	require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';

	$bon = new BonLcr($db,"");
	$bon->fetch($id);

	$dt = dol_mktime(12,0,0,GETPOST('remonth','int'),GETPOST('reday','int'),GETPOST('reyear','int'));

	$error = $bon->set_infotrans($user, $dt, GETPOST('methode','alpha'));

	if ($error)
	{
		header("Location: fiche.php?id=".$id."&error=$error");
		exit;
	}
}

if ($action == 'infocredit' && $user->rights->lcr->bons->credit)
{
	$bon = new BonLcr($db,"");
	$bon->fetch($id);
	$dt = dol_mktime(12,0,0,GETPOST('remonth','int'),GETPOST('reday','int'),GETPOST('reyear','int'));

	$error = $bon->set_infocredit($user, $dt);

	if ($error)
	{
		header("Location: fiche.php?id=".$id."&error=$error");
		exit;
	}
}


/*
 * View
 */
$bon = new BonLcr($db,"");
$form = new Form($db);

llxHeader('',$langs->trans("BankdraftReceipts"));

if ($id > 0)
{
	$bon->fetch($id);

	$head = lcr_prepare_head($bon);
	dol_fiche_head($head, 'lcr', $langs->trans("BankdraftReceipts"), '', 'payment');

	if (GETPOST('error','alpha')!='')
	{
		print '<div class="error">'.$bon->ReadError(GETPOST('error','alpha')).'</div>';
	}

	print '<table class="border" width="100%">';
	print '<tr><td width="20%">'.$langs->trans("Ref").'</td><td>'.$bon->getNomUrl(1).'</td></tr>';
	print '<tr><td width="20%">'.$langs->trans("Date").'</td><td>'.dol_print_date($bon->datec,'day').'</td></tr>';
	print '<tr><td width="20%">'.$langs->trans("Amount").'</td><td>'.price($bon->amount).'</td></tr>';
    print '<tr><td width="20%">'.$langs->trans("Bank").'</td><td>'.$bon->bank.'</td></tr>';

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
		print ' '.$langs->trans("By").' '.$muser->getFullName($langs).'</td></tr>';
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
	// rights for charging bankdraft file
	print '[<a data-ajax="false" href="'.DOL_URL_ROOT.'/document.php?type=text/plain&amp;modulepart=lcr&amp;perm=bons&amp;subperm=lire&amp;file='.urlencode($relativepath.".txt").'">'.$relativepath.'</a>] ';

	print '</td></tr></table>';

	dol_fiche_end();


	if (empty($bon->date_trans) && $user->rights->lcr->bons->send && $action=='settransmitted')
	{
		print '<form method="post" name="userfile" action="fiche.php?id='.$bon->id.'" enctype="multipart/form-data">';
		print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
		print '<input type="hidden" name="action" value="infotrans">';
		print '<table class="border" width="100%">';
		print '<tr class="liste_titre">';
		print '<td colspan="3">'.$langs->trans("BankdraftNotifyTransmision").'</td></tr>';
		print '<tr><td width="20%">'.$langs->trans("BankdraftTransData").'</td><td>';
		print $form->select_date('','','','','',"userfile",1,1);
		print '</td></tr>';
		print '<tr><td width="20%">'.$langs->trans("BankdraftTransMetod").'</td><td>';
		print $form->selectarray("methode",$bon->methodes_trans);
		print '</td></tr>';
		print '</table><br>';
		print '<center><input type="submit" class="button" value="'.dol_escape_htmltag($langs->trans("BankdraftSetToStatusSent")).'">';
		print '</form>';
	}

	if (! empty($bon->date_trans) && $bon->date_credit == 0 && $user->rights->lcr->bons->credit && $action=='setcredited')
	{
		print '<form name="infocredit" method="post" action="fiche.php?id='.$bon->id.'">';
		print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
		print '<input type="hidden" name="action" value="infocredit">';
		print '<table class="border" width="100%">';
		print '<tr class="liste_titre">';
		print '<td colspan="3">'.$langs->trans("BankdraftNotifyCredit").'</td></tr>';
		print '<tr><td width="20%">'.$langs->trans('BankdraftCreditDate').'</td><td>';
		print $form->select_date('','','','','',"infocredit",1,1);
		print '</td></tr>';
		print '</table>';
		print '<br>'.$langs->trans("ThisWillAlsoAddPaymentOnInvoice");
		print '<center><input type="submit" class="button" value="'.dol_escape_htmltag($langs->trans("ClassCredited")).'">';
		print '</form>';
	}


	// Actions
	if ($action != 'settransmitted' && $action != 'setcredited')
	{
		print "\n<div class=\"tabsAction\">\n";

		if (empty($bon->date_trans) && $user->rights->lcr->bons->send)
		{
			print "<a class=\"butAction\" href=\"fiche.php?action=settransmitted&id=".$bon->id."\">".$langs->trans("BankdraftSetToStatusSent")."</a>";
		}

		if (! empty($bon->date_trans) && $bon->date_credit == 0)
		{
			print "<a class=\"butAction\" href=\"fiche.php?action=setcredited&id=".$bon->id."\">".$langs->trans("ClassCredited")."</a>";
		}

		print "<a class=\"butActionDelete\" href=\"fiche.php?action=confirm_delete&id=".$bon->id."\">".$langs->trans("Delete")."</a>";

		print "</div>";
	}
}


llxFooter();

$db->close();
