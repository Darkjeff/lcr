<?php
/* Copyright (C) 2005 	   Rodolphe Quiedeville <rodolphe@quiedeville.org>
 * Copyright (C) 2005 	   Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) 2010-2012 Juanjo Menent 	    <jmenent@2byte.es>
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
 *      \file       htdocs/compta/lcr/bon.php
 *      \ingroup    lcr
 *      \brief      Fiche apercu du bon de lcr
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

    
    
require_once DOL_DOCUMENT_ROOT.'/core/lib/lcr.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
require_once DOL_DOCUMENT_ROOT.'/compta/lcr/class/bonlcr.class.php';
require_once DOL_DOCUMENT_ROOT.'/compta/bank/class/account.class.php';

$langs->load("banks");
$langs->load("categories");
$langs->load("bills");
$langs->load("categories");
$langs->load("lcr");

// Security check
$socid=0;
$id = GETPOST('id','int');
$ref = GETPOST('ref','alpha');
if ($user->societe_id) $socid=$user->societe_id;
$result = restrictedArea($user, 'lcr', $id);


/**
 * View
 */

llxHeader('','Bordereau de lcr');

$form = new Form($db);

if ($id > 0 || ! empty($ref))
{
	$object = new BonLcr($db,"");

	if ($object->fetch($id) == 0)
    {
		$head = lcr_prepare_head($object);
		dol_fiche_head($head, 'preview', 'Lcr : '. $object->ref);

		print '<table class="border" width="100%">';

		print '<tr><td width="20%">'.$langs->trans("Ref").'</td><td>'.$object->ref.'</td></tr>';
		print '<tr><td width="20%">'.$langs->trans("Amount").'</td><td>'.price($object->amount).'</td></tr>';
		print '<tr><td width="20%">'.$langs->trans("File").'</td><td>';
	
		$relativepath = 'receipts/'.$bon->ref."";
		print '[<a data-ajax="false" href="'.DOL_URL_ROOT.'/document.php?type=text/plain&amp;modulepart=lcr&amp;file='.urlencode($relativepath.".txt").'">'.$relativepath.'</a>] ';
	
		print '</td></tr>';
		print '</table><br>';

		$fileimage = $conf->lcr->dir_output.'/receipts/'.$object->ref.'.ps.png.0';
		$fileps = $conf->lcr->dir_output.'/receipts/'.$object->ref.'.ps';

		// Conversion PDF in picture png if file png is not exist
		if (!file_exists($fileimage))
        {
			if (class_exists("Imagick"))
			{
				$ret = dol_convert_file($file,'png',$fileimage);
				if ($ret < 0) $error++;
			}
			else
			{
				$langs->load("errors");
				print '<font class="error">'.$langs->trans("ErrorNoImagickReadimage").'</font>';
			}
		}

		if (file_exists($fileimage))
		{
			print '<img src="'.DOL_URL_ROOT.'/viewimage.php?modulepart=lcr&file='.urlencode(basename($fileimage)).'">';

		}

		dol_fiche_end();
	}
	else
	{
		dol_print_error($db);
    }
}

llxFooter();
