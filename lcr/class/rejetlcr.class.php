<?php
/* Copyright (C) 2005      Rodolphe Quiedeville <rodolphe@quiedeville.org>
 * Copyright (C) 2005-2009 Regis Houssin        <regis.houssin@capnetworks.com>
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
 *
 */

/**
 *  \file       htdocs/compta/lcr/class/rejetlcr.class.php
 *  \ingroup    lcr
 *  \brief      File of class to manage standing orders rejects
 */


dol_include_once('/lcr/class/facture.lcr.class.php');


/**
 *	Class to manage standing orders rejects
 */

class RejetLcr
{
	var $id;
	var $db;


	/**
	 *  Constructor
	 *
	 *  @param	DoliDb	$db			Database handler
	 *  @param 	User	$user       Objet user
	 */

	function __construct($db, $user)
	{
		global $langs;

		$this->db = $db;
		$this->user = $user;

		$this->motifs = array();
		$this->facturer = array();

		$this->motifs[0] = ""; //$langs->trans("StatusMotif0");
    	$this->motifs[1] = $langs->trans("StatusMotif1");
    	$this->motifs[2] = $langs->trans("StatusMotif2");
    	$this->motifs[3] = $langs->trans("StatusMotif3");
    	$this->motifs[4] = $langs->trans("StatusMotif4");
    	$this->motifs[5] = $langs->trans("StatusMotif5");
    	$this->motifs[6] = $langs->trans("StatusMotif6");
    	$this->motifs[7] = $langs->trans("StatusMotif7");
    	$this->motifs[8] = $langs->trans("StatusMotif8");

    	$this->facturer[0]=$langs->trans("BankdraftNoInvoiceRefused");
		$this->facturer[1]=$langs->trans("BankdraftInvoiceRefused");

	}


	/**
	 * 	Create
	 *
	 * 	@param 	User		$user				User object
	 * 	@param 	int			$id					Id
	 * 	@param 	string		$motif				Motif
	 * 	@param 	timestamp	$date_rejet			Date rejet
	 * 	@param 	int			$bonid				Bon id
	 * 	@param 	int			$facturation		Facturation
	 * 	@return	void
	 */

	function create($user, $id, $motif, $date_rejet, $bonid, $facturation=0)
	{
		global $langs,$conf;

		$error = 0;
		$this->id = $id;
		$this->bon_id = $bonid;
		$now=dol_now();

		dol_syslog("RejetLcr::Create id $id");
		$facs = $this->getListInvoices();

		$this->db->begin();

		// Insert refused line into database
		$sql = "INSERT INTO ".MAIN_DB_PREFIX."lcr_rejet (";
		$sql.= "fk_lcr_lignes";
		$sql.= ", date_rejet";
		$sql.= ", motif";
		$sql.= ", fk_user_creation";
		$sql.= ", date_creation";
		$sql.= ", afacturer";
		$sql.= ") VALUES (";
		$sql.= $id;
		$sql.= ", '".$this->db->idate($date_rejet)."'";
		$sql.= ", ".$motif;
		$sql.= ", ".$user->id;
		$sql.= ", '".$this->db->idate($now)."'";
		$sql.= ", ".$facturation;
		$sql.= ")";

		$result=$this->db->query($sql);

		if (!$result)
		{
			dol_syslog("RejetLcr::create Erreur 4");
			dol_syslog("RejetLcr::create Erreur 4 $sql");
			$error++;
		}

		// Tag the line to refused
		$sql = " UPDATE ".MAIN_DB_PREFIX."lcr_lignes ";
		$sql.= " SET statut = 3";
		$sql.= " WHERE rowid = ".$id;

		if (! $this->db->query($sql))
		{
			dol_syslog("RejetLcr::create Erreur 5");
			$error++;
		}

		$amountligne=0;
		$sql = " select amount from ".MAIN_DB_PREFIX."lcr_lignes ";
		$sql.= " WHERE rowid = ".$id;
		$resql=$this->db->query($sql);
		if ($resql)
		{
			$num = $this->db->num_rows($resql);
			if ($num > 0)
			{
				$row = $this->db->fetch_row($resql);
				$amountligne = $row[0];
			}
		}

		$num=count($facs);
		for ($i = 0; $i < $num; $i++)
		{
			$fac = new FactureLcr($this->db);
			$fac->fetch($facs[$i]);

			// Make a negative payment
			$pai = new Paiement($this->db);

			$pai->amounts = array();


			/*
			 * We replace the comma with a point otherwise some
			 * PHP installs sends only the part integer negative
			 */

			$pai->amounts[$facs[$i]] = !empty($amountligne)?price2num($amountligne * -1):price2num($fac->total_ttc * -1);
			$pai->datepaye = $date_rejet;
			$pai->paiementid = 52; // type of payment: lcr
			$pai->num_paiement = $fac->ref;

			if ($pai->create($this->user) < 0)  // we call with no_commit
			{
				$error++;
				dol_syslog("RejetLcr::Create Error creation payment invoice ".$facs[$i]);
			}
			else
			{
                // $bankaccount = $conf->global->LCR_ID_BANKACCOUNT;
                $sqlBon = "SELECT * FROM ".MAIN_DB_PREFIX."lcr_bons WHERE rowid = " . $this->bon_id;
                $resql = $this->db->query($sqlBon);
                $result = $this->db->fetch_row($resql);
                $bankaccount = intval($result[13]);
                $this->db->free($resql);
				$result=$pai->addPaymentToBank($user,'payment','(BankdraftInvoiceRefused)',$bankaccount, '', '');
				if ($result < 0)
				{
					dol_syslog("RejetLcr::Create AddPaymentToBan Error");
					$error++;
				}

				// Payment validation
				if ($pai->valide() < 0)
				{
					$error++;
					dol_syslog("RejetLcr::Create Error payment validation");
				}

			}
			//Tag invoice as unpaid
			dol_syslog("RejetLcr::Create set_unpaid fac ".$fac->ref);
			$fac->set_unpaid($user);

			//TODO: Must be managed by notifications module
			// Send email to sender of the standing order request
			// $this->_send_email($fac);
		}

		if ($error == 0)
		{
			dol_syslog("RejetLcr::Create Commit");
			$this->db->commit();
		}
		else
		{
			dol_syslog("RejetLcr::Create Rollback");
			$this->db->rollback();
		}

	}


	/**
	 *  Envoi mail
	 *
	 * 	@param	Facture		$fac			Invoice object
	 * 	@return	void
	 */

	function _send_email($fac)
	{
		global $langs;

		$userid = 0;

		$sql = "SELECT fk_user_demande";
		$sql.= " FROM ".MAIN_DB_PREFIX."lcr_facture_demande as pfd";
		$sql.= " WHERE pfd.fk_lcr_bons = ".$this->bon_id;
		$sql.= " AND pfd.fk_facture = ".$fac->id;

		$resql=$this->db->query($sql);
		if ($resql)
		{
			$num = $this->db->num_rows($resql);
			if ($num > 0)
			{
				$row = $this->db->fetch_row($resql);
				$userid = $row[0];
			}
		}
		else
		{
			dol_syslog("RejetLcr::_send_email Erreur lecture user");
		}

		if ($userid > 0)
		{
			$emuser = new User($this->db);
			$emuser->fetch($userid);

			$soc = new Societe($this->db);
			$soc->fetch($fac->socid);

			require_once DOL_DOCUMENT_ROOT.'/core/class/CMailFile.class.php';

			$subject = $langs->trans("InfoRejectSubject");
			$sendto = $emuser->getFullName($langs)." <".$emuser->email.">";
			$from = $this->user->getFullName($langs)." <".$this->user->email.">";
			$msgishtml=0;

			$arr_file = array();
			$arr_mime = array();
			$arr_name = array();
			$facref = $fac->ref;
			$socname = $soc->nom;
			$amount = price($fac->total_ttc);
			$userinfo = $this->user->getFullName($langs);

			$message = $langs->trans("InfoRejectMessage",$facref,$socname, $amount, $userinfo);

			$mailfile = new CMailFile($subject,$sendto,$from,$message,$arr_file,$arr_mime,$arr_name,'', '', 0, $msgishtml,$this->user->email);

			$result=$mailfile->sendfile();
			if ($result)
			{
				dol_syslog("RejetLcr::_send_email email envoye");
			}
			else
			{
				dol_syslog("RejetLcr::_send_email Erreur envoi email");
			}
		}
		else
		{
			dol_syslog("RejetLcr::_send_email Userid invalide");
		}
	}


	/**
	 *    Retrieve the list of invoices
	 *
	 *    @return	void
	 */

	private function getListInvoices()
	{
		global $conf;

		$arr = array();

		//Returns all invoices of lcr
		$sql = "SELECT f.rowid as facid";
		$sql.= " FROM ".MAIN_DB_PREFIX."lcr_facture as pf";
		$sql.= ", ".MAIN_DB_PREFIX."facture as f";
		$sql.= " WHERE pf.fk_lcr_lignes = ".$this->id;
		$sql.= " AND pf.fk_facture = f.rowid";
		$sql.= " AND f.entity = ".$conf->entity;

		$resql=$this->db->query($sql);
		if ($resql)
		{
			$num = $this->db->num_rows($resql);

			if ($num)
			{
				$i = 0;
				while ($i < $num)
				{
					$row = $this->db->fetch_row($resql);
					$arr[$i] = $row[0];
					$i++;
				}
			}
			$this->db->free($resql);
		}
		else
		{
			dol_syslog("RejetLcr Erreur");
		}

		return $arr;

	}


	/**
	 *    Retrieve withdrawal object
	 *
	 *    @param    int		$rowid       id of invoice to retrieve
	 *    @return	void
	 */

	function fetch($rowid)
	{

		$sql = "SELECT pr.date_rejet as dr, motif, afacturer";
		$sql.= " FROM ".MAIN_DB_PREFIX."lcr_rejet as pr";
		$sql.= " WHERE pr.fk_lcr_lignes =".$rowid;

		$resql=$this->db->query($sql);
		if ($resql)
		{
			if ($this->db->num_rows($resql))
			{
				$obj = $this->db->fetch_object($resql);

				$this->id             = $rowid;
				$this->date_rejet     = $this->db->jdate($obj->dr);
				$this->motif          = $this->motifs[$obj->motif];
				$this->invoicing	  =	$this->facturer[$obj->afacturer];

				$this->db->free($resql);

				return 0;
			}
			else
			{
				dol_syslog("RejetLcr::Fetch Erreur rowid=$rowid numrows=0");
				return -1;
			}
		}
		else
		{
			dol_syslog("RejetLcr::Fetch Erreur rowid=$rowid");
			return -2;
		}
	}

}

