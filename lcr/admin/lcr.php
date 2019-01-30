<?php
/* Copyright (C) 2005      Rodolphe Quiedeville <rodolphe@quiedeville.org>
 * Copyright (C) 2005-2010 Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) 2005-2010 Regis Houssin        <regis.houssin@capnetworks.com>
 * Copyright (C) 2010-2013 Juanjo Menent        <jmenent@2byte.es>
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
 *	\file       htdocs/admin/lcr.php
 *	\ingroup    lcr
 *	\brief      Page configuration des lcrs
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



require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/compta/bank/class/account.class.php';

dol_include_once('/lcr/class/bonlcr.class.php');

$langs->load("admin");
$langs->load("lcr");

// Security check
if (!$user->admin) accessforbidden();

$action = GETPOST('action','alpha');


/**
 * Actions
 */

if ($action == "set")
{
    $db->begin();
    for ($i = 0 ; $i < 1 ; $i++)
    {
    	$res = dolibarr_set_const($db, GETPOST("nom$i",'alpha'), GETPOST("value$i",'alpha'),'chaine',0,'',$conf->entity);
        if (! $res > 0) $error++;
    }

    $res = dolibarr_set_const($db, "LCR_ID_BANKACCOUNT", GETPOST("LCR_ID_BANKACCOUNT"),'chaine',0,'',$conf->entity);
    if (! $res > 0) $error++;

    $id=GETPOST('LCR_ID_BANKACCOUNT','int');
    $account = new Account($db, $id);

    if($account->fetch($id)>0)
    {
        $res = dolibarr_set_const($db, "LCR_ID_BANKACCOUNT", $id,'chaine',0,'',$conf->entity);
        if (! $res > 0) $error++;
        $res = dolibarr_set_const($db, "LCR_CODE_BANQUE", $account->code_banque,'chaine',0,'',$conf->entity);
        if (! $res > 0) $error++;
        $res = dolibarr_set_const($db, "LCR_CODE_GUICHET", $account->code_guichet,'chaine',0,'',$conf->entity);
        if (! $res > 0) $error++;
        $res = dolibarr_set_const($db, "LCR_NUMERO_COMPTE", $account->number,'chaine',0,'',$conf->entity);
        if (! $res > 0) $error++;
        $res = dolibarr_set_const($db, "LCR_NUMBER_KEY", $account->cle_rib,'chaine',0,'',$conf->entity);
        if (! $res > 0) $error++;
        $res = dolibarr_set_const($db, "LCR_IBAN", $account->iban,'chaine',0,'',$conf->entity);
        if (! $res > 0) $error++;
        $res = dolibarr_set_const($db, "LCR_BIC", $account->bic,'chaine',0,'',$conf->entity);
        if (! $res > 0) $error++;
        $res = dolibarr_set_const($db, "LCR_RAISON_SOCIALE", $account->proprio,'chaine',0,'',$conf->entity);
        if (! $res > 0) $error++;
    }
    else $error++;

    if (! $error)
	{
		$db->commit();
		setEventMessage($langs->trans("BankdraftSetupSaved"));
	}
	else
	{
		$db->rollback();
		setEventMessage($langs->trans("Error"),'errors');
	}
}

if ($action == "addnotif")
{
    $bon = new BonLcr($db);
    $bon->AddNotification($db,GETPOST('user','int'),$action);

    header("Location: lcr.php");
    exit;
}

if ($action == "deletenotif")
{
    $bon = new BonLcr($db);
    $bon->DeleteNotificationById(GETPOST('notif','int'));

    header("Location: lcr.php");
    exit;
}


/**
 *	View
 */

$form=new Form($db);

llxHeader('',$langs->trans("BankdraftSetup")."Lcr");

$linkback='<a href="'.DOL_URL_ROOT.'/admin/modules.php">'.$langs->trans("BackToModuleList").'</a>';

print_fiche_titre($langs->trans("BankdraftSetup")." Lcr",$linkback,'setup');
print '<br>';

print '<form method="post" action="lcr.php?action=set">';
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';

print '<table class="noborder" width="100%">';
print '<tr class="liste_titre">';
print '<td width="30%">'.$langs->trans("Parameter").'</td>';
print '<td width="40%">'.$langs->trans("Value").'</td>';
print "</tr>";

//User
print '<tr class="impair"><td>'.$langs->trans("BankdraftResponsibleUser").'</td>';
print '<td align="left">';
print '<input type="hidden" name="nom0" value="LCR_USER">';
print $form->select_dolusers($conf->global->LCR_USER,'value0',1);
print '</td>';
print '</tr>';

// Bank account (from Banks module)
print '<tr class="impair"><td>'.$langs->trans("BankdraftToReceive").'</td>';
print '<td align="left">';
print $form->select_comptes($conf->global->LCR_ID_BANKACCOUNT,'LCR_ID_BANKACCOUNT',0,"courant=1",1);
print '</td></tr>';

print '</table>';
print '<br>';

print '<center><input type="submit" class="button" value="'.$langs->trans("Save").'"></center>';

print '</form>';

dol_fiche_end();

print '<br>';




$db->close();

llxFooter();
