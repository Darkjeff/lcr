<?php
/* Copyright (C) 2004-2005 Rodolphe Quiedeville <rodolphe@quiedeville.org>
 * Copyright (C) 2005-2012 Regis Houssin        <regis.houssin@capnetworks.com>
 * Copyright (C) 2010-2014 Juanjo Menent        <jmenent@2byte.es>
 * Copyright (C) 2010-2014 Laurent Destailleur  <eldy@users.sourceforge.net>
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
 *      \file       htdocs/compta/lcr/class/bonlcr.class.php
 *      \ingroup    lcr
 *      \brief      Fichier de la classe des bons de lcrs
 */

require_once DOL_DOCUMENT_ROOT.'/core/class/commonobject.class.php';
require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
require_once DOL_DOCUMENT_ROOT.'/compta/paiement/class/paiement.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/bank.lib.php';
require_once DOL_DOCUMENT_ROOT.'/compta/bank/class/account.class.php';


/**
 *	Class to manage lcr receipts
 */

class BonLcr extends CommonObject
{
    var $db;

    var $date_echeance;
    var $raison_sociale;
    var $reference_remise;

    var $bank;
    var $emetteur_code_guichet;
    var $emetteur_numero_compte;
    var $emetteur_code_banque;
    var $emetteur_number_key;

    var $emetteur_iban;
    var $emetteur_bic;
    var $emetteur_ics;

    var $total;
    var $_fetched;
    var $statut;    // 0-Wait, 1-Trans, 2-Done
    var $labelstatut=array();


    /**
     *	Constructor
     *
     *  @param		DoliDB		$db      	Database handler
     *  @param		string		$filename	Filename of lcr receipt
     */

    function __construct($db, $filename='')
    {
        global $conf,$langs;

        $error = 0;
        $this->db = $db;

        $this->filename=$filename;

        $this->date_echeance = time();
        $this->raison_sociale = "";
        $this->reference_remise = "";

        $this->fk_bank_account = null;
        $this->emetteur_code_guichet = "";
        $this->emetteur_numero_compte = "";
        $this->emetteur_code_banque = "";
        $this->emetteur_number_key = "";

        $this->emetteur_iban = "";
        $this->emetteur_bic = "";
        $this->emetteur_ics = "";

        $this->factures = array();

        $this->numero_national_emetteur = "";

        $this->methodes_trans = array();

        $this->methodes_trans[0] = "Internet";

        $this->_fetched = 0;


        $langs->load("lcr");
        $this->labelstatut[0]=$langs->trans("BankdraftStatusWaiting");
        $this->labelstatut[1]=$langs->trans("BankdraftStatusTrans");
        $this->labelstatut[2]=$langs->trans("BankdraftStatusCredited");

        return 1;
    }


    /**
     * Add facture to lcr
     *
     * @param	int		$facture_id 	id invoice to add
     * @param	int		$client_id  	id invoice customer
     * @param	string	$client_nom 	name of cliente
     * @param	int		$amount 		amount of invoice
     * @param	string	$code_banque 	code of bank 
     * @param	string	$code_guichet 	code of bank's office
     * @param	string	$number bank 	account number
     * @param	string	$number_key 	number key of account number
     * @return	int						>0 if OK, <0 if KO
     */

    function AddFacture($facture_id, $client_id, $client_nom, $amount, $code_banque, $code_guichet, $number, $number_key, $mode, $datetraite)
    {
        $result = 0;
        $line_id = 0;

        $result = $this->addline($line_id, $client_id, $client_nom, $amount, $code_banque, $code_guichet, $number, $number_key, $mode, $datetraite);

        if ($result == 0)
        {
            if ($line_id > 0)
            {
                $sql = "INSERT INTO ".MAIN_DB_PREFIX."lcr_facture (";
                $sql.= "fk_facture";
                $sql.= ",fk_lcr_lignes";
                $sql.= ") VALUES (";
                $sql.= $facture_id;
                $sql.= ", ".$line_id;
                $sql.= ")";

                if ($this->db->query($sql))
                {
                    $result = 0;
                }
                else
                {
                    $result = -1;
                    dol_syslog(get_class($this)."::AddFacture Erreur $result");
                }
            }
            else
            {
                $result = -2;
                dol_syslog(get_class($this)."::AddFacture Erreur $result");
            }
        }
        else
        {
            $result = -3;
            dol_syslog(get_class($this)."::AddFacture Erreur $result");
        }

        return $result;
        
    }


    /**
     *	Add line to lcr
     *
     *	@param	int		&$line_id 		id line to add
     *	@param	int		$client_id  	id invoice customer
     *	@param	string	$client_nom 	name of cliente
     *	@param	int		$amount 		amount of invoice
     *	@param	string	$code_banque 	code of bank
     *	@param	string	$code_guichet 	code of bank's office
     *	@param	string	$number 		bank account number
     *	@param  string	$number_key 	number key of account number
     *	@return	int						>0 if OK, <0 if KO
     */

    function addline(&$line_id, $client_id, $client_nom, $amount, $code_banque, $code_guichet, $number, $number_key, $mode, $datetraite)
    {
        $result = -1;
        $concat = 0;

        if ($concat == 1)
        {
            /*
             * Aggrege lines
             */
            $sql = "SELECT rowid";
            $sql.= " FROM  ".MAIN_DB_PREFIX."lcr_lignes";
            $sql.= " WHERE fk_lcr_bons = ".$this->id;
            $sql.= " AND fk_soc =".$client_id;
            $sql.= " AND code_banque ='".$code_banque."'";
            $sql.= " AND code_guichet ='".$code_guichet."'";
            $sql.= " AND number ='".$number."'";

            $resql=$this->db->query($sql);
            if ($resql)
            {
                $num = $this->db->num_rows($resql);
            }
            else
            {
                $result = -1;
            }
        }
        else
        {
            /*
             * No agregation
             */
            $sql = "INSERT INTO ".MAIN_DB_PREFIX."lcr_lignes (";
            $sql.= "fk_lcr_bons";
            $sql.= ", fk_soc";
            $sql.= ", client_nom";
            $sql.= ", amount";
            $sql.= ", code_banque";
            $sql.= ", code_guichet";
            $sql.= ", number";
						$sql.= ", mode";
						$sql.= ", date_traite";
            $sql.= ", cle_rib";
            $sql.= ") VALUES (";
            $sql.= $this->id;
            $sql.= ", ".$client_id;
            $sql.= ", '".$this->db->escape($client_nom)."'";
            $sql.= ", '".price2num($amount)."'";
            $sql.= ", '".$code_banque."'";
            $sql.= ", '".$code_guichet."'";
            $sql.= ", '".$number."'";
            $sql.= ", '".$mode."'";
            $sql.= ", '".substr($datetraite,0,10)."'";
            $sql.= ", '".$number_key."'";
            $sql.= ")";
            if ($this->db->query($sql))
            {
                $line_id = $this->db->last_insert_id(MAIN_DB_PREFIX."lcr_lignes");
                $result = 0;

            }
            else
            {
                dol_syslog(get_class($this)."::addline Error -2");
                $result = -2;
            }

        }

        return $result;
    }


    /**
     *	Read errors
     *
     *  @param	int		$error 		id of error
     *	@return	array 				Array of errors
     */

    function ReadError($error)
    {
        $errors = array();

        $errors[1027] = "Date invalide";

        return $errors[abs($error)];
    }


    /**
     *	Get object and lines from database
     *
     *	@param	int		$rowid		id of object to load
     *	@return	int					>0 if OK, <0 if KO
     */

    function fetch($rowid)
    {
        global $conf;

        $sql = "SELECT p.rowid, p.ref, p.amount, p.note";
        $sql.= ", p.datec as dc";
        $sql.= ", p.date_trans as date_trans";
        $sql.= ", p.method_trans, p.fk_user_trans";
        $sql.= ", p.date_credit as date_credit";
        $sql.= ", p.fk_user_credit";
        $sql.= ", b.bank";
        $sql.= ", p.statut";
        $sql.= " FROM ".MAIN_DB_PREFIX."lcr_bons as p";
        $sql.= " INNER JOIN ".MAIN_DB_PREFIX."bank_account as b ON b.rowid = p.fk_bank_account";
        $sql.= " WHERE p.rowid = ".$rowid;
        $sql.= " AND p.entity = ".$conf->entity;

        dol_syslog(get_class($this)."::fetch sql=".$sql, LOG_DEBUG);
        $result=$this->db->query($sql);
        if ($result)
        {
            if ($this->db->num_rows($result))
            {
                $obj = $this->db->fetch_object($result);

                $this->id                 = $obj->rowid;
                $this->ref                = $obj->ref;
                $this->amount             = $obj->amount;
                $this->note               = $obj->note;
                $this->datec              = $this->db->jdate($obj->dc);

                $this->bank = $obj->bank;

                $this->date_trans         = $this->db->jdate($obj->date_trans);
                $this->method_trans       = $obj->method_trans;
                $this->user_trans         = $obj->fk_user_trans;

                $this->date_credit        = $this->db->jdate($obj->date_credit);
                $this->user_credit        = $obj->fk_user_credit;

                $this->statut             = $obj->statut;

                $this->_fetched = 1;

                return 0;

            }
            else
            {
                dol_syslog(get_class($this)."::Fetch Erreur aucune ligne retournee");
                return -1;
            }
        }
        else
        {
            dol_syslog(get_class($this)."::Fetch Erreur sql=".$sql, LOG_ERR);
            return -2;
        }
    }


    /**
     * Set credite and set status of linked invoices
     *
     * @return		int		<0 if KO, >0 if OK
     */

    function set_credite()
    {
        global $user,$conf;

        $error = 0;

        if ($this->db->begin())
        {
            $sql = " UPDATE ".MAIN_DB_PREFIX."lcr_bons";
            $sql.= " SET statut = 1";
            $sql.= " WHERE rowid = ".$this->id;
            $sql.= " AND entity = ".$conf->entity;

            $result=$this->db->query($sql);
            if (! $result)
            {
                dol_syslog(get_class($this)."::set_credite Erreur 1");
                $error++;
            }

            if ($error == 0)
            {
                $facs = array();
                $facs = $this->getListInvoices();


                $num=count($facs);
                for ($i = 0; $i < $num; $i++)
                {
                    /* Tag invoice as payed */
                    dol_syslog(get_class($this)."::set_credite set_paid fac ".$facs[$i]);
                    $fac = new Facture($this->db);
                    $fac->fetch($facs[$i]);
					$totalpaye  = $fac->getSommePaiement();
					$totalcreditnotes = $fac->getSumCreditNotesUsed();
					$totaldeposits = $fac->getSumDepositsUsed();
					$resteapayer = price2num(round($fac->total_ttc,2) - $totalpaye - $totalcreditnotes - $totaldeposits,'MT');

					if ($resteapayer>0.01)
						$result = $fac->set_unpaid($user);
					else
						$result = $fac->set_paid($user);
                }
            }

            if ($error == 0)
            {
                $sql = " UPDATE ".MAIN_DB_PREFIX."lcr_lignes";
                $sql.= " SET statut = 2";
                $sql.= " WHERE fk_lcr_bons = ".$this->id;

                if (! $this->db->query($sql))
                {
                    dol_syslog(get_class($this)."::set_credite Erreur 1");
                    $error++;
                }
            }

            /*
             * End of procedure
             */
            if ($error == 0)
            {
                $this->db->commit();
                return 0;
            }
            else
            {
                $this->db->rollback();
                dol_syslog(get_class($this)."::set_credite ROLLBACK ");

                return -1;
            }
        }
        else
        {
            dol_syslog(get_class($this)."::set_credite Ouverture transaction SQL impossible ");
            return -2;
        }
    }


    /**
     *	Set lcr to credited status
     *
     *	@param	User		$user		id of user
     *	@param 	timestamp	$date		date of action
     *	@return	int						>0 if OK, <0 if KO
     */

    function set_infocredit($user, $date)
    {
        global $conf,$langs;

        $error = 0;

        if ($this->_fetched == 1)
        {
            if ($date >= $this->date_trans)
            {
                if ($this->db->begin())
                {
                    $sql = " UPDATE ".MAIN_DB_PREFIX."lcr_bons ";
                    $sql.= " SET fk_user_credit = ".$user->id;
                    $sql.= ", statut = 2";
                    $sql.= ", date_credit = '".$this->db->idate($date)."'";
                    $sql.= " WHERE rowid=".$this->id;
                    $sql.= " AND entity = ".$conf->entity;
                    $sql.= " AND statut = 1";

                    if ($this->db->query($sql))
                    {

                        $langs->load('lcr');
                        $subject = $langs->trans("InfoCreditSubject", $this->ref);
                        $message = $langs->trans("InfoCreditMessage", $this->ref, dol_print_date($date,'dayhour'));

                        // Add payment of lcr into bank
                        $sqlBon = "SELECT * FROM ".MAIN_DB_PREFIX."lcr_bons WHERE rowid = " . $this->id;
                        $resql = $this->db->query($sqlBon);
                        $result = $this->db->fetch_row($resql);
                        $bankaccount = intval($result[13]);
                        $this->db->free($resql);
                      
                        //$bankaccount = $conf->global->PRELEVEMENT_ID_BANKACCOUNT;
                        $facs = array();
                        $amounts = array();
                        $amountsperthirdparty = array();

                        $facs = $this->getListInvoices(1);
   
                        $num=count($facs);
                        for ($i = 0; $i < $num; $i++)
                        {
                            $fac = new Facture($this->db);
                            $fac->fetch($facs[$i][0]);
                            $amounts[$fac->id] = $facs[$i][1];
                            $amountsperthirdparty[$fac->socid][$fac->id] = $facs[$i][1];

                            $totalpaye  = $fac->getSommePaiement();
                            $totalcreditnotes = $fac->getSumCreditNotesUsed();
                            $totaldeposits = $fac->getSumDepositsUsed();
                            $alreadypayed = $totalpaye + $totalcreditnotes + $totaldeposits;

                            if (price2num($alreadypayed + $facs[$i][1], 'MT') == $fac->total_ttc) {
                                $result = $fac->set_paid($user);
                            }
                        }

                        // Make one payment per customer
                        foreach ($amountsperthirdparty as $thirdpartyid => $cursoramounts)
                        {
                            $paiement = new Paiement($this->db);
                            $paiement->datepaye     = $date;
                            $paiement->amounts      = $cursoramounts;       // Array with detail of dispatching of payments for each invoice
                            $paiement->paiementid   = 52;                    //
                            $paiement->num_paiement = $this->ref;           // Set ref of direct debit note
                            $paiement->id_prelevement = $this->id;

                            $paiement_id = $paiement->create($user);
                            if ($paiement_id < 0)
                            {
                                dol_syslog(get_class($this)."::set_infocredit AddPayment Error");
                                $error++;
                            }
                            else
                            {
                                $result=$paiement->addPaymentToBank($user,'payment','(BankdraftPayment)',$bankaccount,'','');
                                if ($result < 0)
                                {
                                    dol_syslog(get_class($this)."::set_infocredit AddPaymentToBank Error");
                                    $error++;
                                }
                            }
                          
                        }
                        
                        // Update lcr line
                        $sql = " UPDATE ".MAIN_DB_PREFIX."lcr_lignes";
                        $sql.= " SET statut = 2";
                        $sql.= " WHERE fk_lcr_bons = ".$this->id;

                        if (! $this->db->query($sql))
                        {
                            dol_syslog(get_class($this)."::set_credite Update lines Error");
                            $error++;
                        }

                    }
                    else
                    {
                        dol_syslog(get_class($this)."::set_infocredit Update Bons Error");
                        $error++;
                    }

                    /*
                     * End of procedure
                     */
                    if ($error == 0)
                    {
                        $this->db->commit();
                        return 0;
                    }
                    else
                    {
                        $this->db->rollback();
                        dol_syslog("Lcr::set_infocredit ROLLBACK ");
                        return -1;
                    }
                }
                else
                {
                    dol_syslog(get_class($this)."::set_infocredit 1025 Open SQL transaction impossible ");
                    return -1025;
                }
            }
            else
            {
                dol_syslog("Lcr::set_infocredit 1027 Date de credit < Date de trans ");
                return -1027;
            }
        }
        else
        {
            return -1026;
        }
    }


    /**
     *	Set Lcr to transmited status
     *
     *	@param	User		$user		id of user
     *	@param 	timestamp	$date		date of action
     *	@param	string		$method		method of transmision to bank
     *	@return	int						>0 if OK, <0 if KO
     */

    function set_infotrans($user, $date, $method)
    {
        global $conf,$langs;

        $error = 0;

        dol_syslog(get_class($this)."::set_infotrans Start",LOG_INFO);
        if ($this->db->begin())
        {
            $sql = "UPDATE ".MAIN_DB_PREFIX."lcr_bons ";
            $sql.= " SET fk_user_trans = ".$user->id;
            $sql.= " , date_trans = '".$this->db->idate($date)."'";
            $sql.= " , method_trans = ".$method;
            $sql.= " , statut = 1";
            $sql.= " WHERE rowid = ".$this->id;
            $sql.= " AND entity = ".$conf->entity;
            $sql.= " AND statut = 0";

            if ($this->db->query($sql))
            {
                $this->method_trans = $method;
                $langs->load('lcr');
                $subject = $langs->trans("InfoTransSubject", $this->ref);
                $message = $langs->trans("InfoTransMessage", $this->ref, dolGetFirstLastname($user->firstname, $user->lastname));
                $message .=$langs->trans("InfoTransData", price($this->amount), $this->methodes_trans[$this->method_trans], dol_print_date($date,'day'));

             // TODO Call trigger to create a notification using notification module
            }
            else
           {
                dol_syslog(get_class($this)."::set_infotrans Erreur 1", LOG_ERR);
                dol_syslog($this->db->error());
                $error++;
            }

            if ($error == 0)
            {
                $this->db->commit();
                return 0;
            }
            else
            {
                $this->db->rollback();
                dol_syslog(get_class($this)."::set_infotrans ROLLBACK", LOG_ERR);

                return -1;
            }
        }
        else
        {

            dol_syslog(get_class($this)."::set_infotrans Ouverture transaction SQL impossible", LOG_CRIT);
            return -2;
        }
    }


    /**
     *	Get invoice list
     *
     *  @param 	int		$amounts 	If you want to get the amount of the order for each invoice
     *	@return	array 				Id of invoices
     */

    private function getListInvoices($amounts=0)
    {
        global $conf;

        $arr = array();

        /*
         * Send all invoice in bon lcr 
         */
        $sql = "SELECT fk_facture";
        if ($amounts) $sql .= ", SUM(pl.amount)";
        $sql.= " FROM ".MAIN_DB_PREFIX."lcr_bons as p";
        $sql.= " , ".MAIN_DB_PREFIX."lcr_lignes as pl";
        $sql.= " , ".MAIN_DB_PREFIX."lcr_facture as pf";
        $sql.= " WHERE pf.fk_lcr_lignes = pl.rowid";
        $sql.= " AND pl.fk_lcr_bons = p.rowid";
        $sql.= " AND p.rowid = ".$this->id;
        $sql.= " AND p.entity = ".$conf->entity;
        if ($amounts) $sql.= " GROUP BY fk_facture";

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
                    if (!$amounts) $arr[$i] = $row[0];
                    else
                    {
                        $arr[$i] = array(
                            $row[0],
                            $row[1]
                        );
                    }
                    $i++;
                }
            }
            $this->db->free($resql);
        }
        else
        {
            dol_syslog(get_class($this)."::getListInvoices Erreur");
        }

        return $arr;
    }


	/**
     *	Returns amount of Lcr
     *
     *	@return		double	 	Total amount
     */

    function SommeAPreleverdate()
    {
        global $conf;

        $sql = "SELECT sum(pfd.amount) as nb,pfd.date_lim_reglement";
        $sql.= " FROM ".MAIN_DB_PREFIX."facture as f,";
        $sql.= " ".MAIN_DB_PREFIX."lcr_facture_demande as pfd";
        $sql.= " WHERE f.fk_statut = 1";
        $sql.= " AND f.entity = ".$conf->entity;
        $sql.= " AND f.rowid = pfd.fk_facture";
        $sql.= " AND f.paye = 0";
        $sql.= " AND pfd.traite = 0";
		$sql.= " AND f.total_ttc > 0";
		$sql.= " group by pfd.date_lim_reglement";
		$sql.= " order by pfd.date_lim_reglement asc ";


        $resql = $this->db->query($sql);
        $nb=array();
        if ( $resql )
        {
			if ($this->db->num_rows($resql))
			{
				while ( $obj = $this->db->fetch_object($resql))
				{
					$nb[$obj->date_lim_reglement]=$obj->nb;

				}


				return $nb;
			}
			else
				dol_syslog("NbFactureAPrelever");



            $this->db->free($resql);
        }
        else
        {
            $error = 1;
            dol_syslog(get_class($this)."::SommeAPrelever Erreur -1");
            dol_syslog($this->db->error());
        }
    }


    /**
     *	Returns amount of lcr
     *
     *	@return		double	 	Total amount
     */

    function SommeAPrelever()
    {
        global $conf;

        $sql = "SELECT sum(pfd.amount) as nb";
        $sql.= " FROM ".MAIN_DB_PREFIX."facture as f,";
        $sql.= " ".MAIN_DB_PREFIX."lcr_facture_demande as pfd";
        $sql.= " WHERE f.fk_statut = 1";
        $sql.= " AND f.entity = ".$conf->entity;
        $sql.= " AND f.rowid = pfd.fk_facture";
        $sql.= " AND f.paye = 0";
        $sql.= " AND pfd.traite = 0";
		$sql.= " AND f.total_ttc > 0";

        $resql = $this->db->query($sql);
        if ( $resql )
        {
            $obj = $this->db->fetch_object($resql);

            return $obj->nb;

            $this->db->free($resql);
        }
        else
        {
            $error = 1;
            dol_syslog(get_class($this)."::SommeAPrelever Erreur -1");
            dol_syslog($this->db->error());
        }
    }


	/**
     *	Get number of invoices to lcr
     *	TODO delete params banque and agence when not necesary
     *
     *	@param	int		$banque		dolibarr mysoc bank
     *	@param	int		$agence		dolibarr mysoc agence
     *	@return	int					<O if KO, number of invoices if OK
     */

    function NbFactureAPreleverdate($banque=0,$agence=0)
    {
        global $conf;

        $sql = "SELECT count(f.rowid) as nb,pfd.date_lim_reglement";
        $sql.= " FROM ".MAIN_DB_PREFIX."facture as f";
        $sql.= ", ".MAIN_DB_PREFIX."lcr_facture_demande as pfd";
        $sql.= " WHERE f.fk_statut = 1";
        $sql.= " AND f.entity = ".$conf->entity;
        $sql.= " AND f.rowid = pfd.fk_facture";
        $sql.= " AND f.paye = 0";
        $sql.= " AND pfd.traite = 0";
        $sql.= " AND f.total_ttc > 0";
		$sql.= " group by pfd.date_lim_reglement";
		$sql.= " order by pfd.date_lim_reglement asc";
        $resql = $this->db->query($sql);
		$nb=array();
        if ( $resql )
        {
			if ($this->db->num_rows($resql))
			{
				while ( $obj = $this->db->fetch_object($resql))
				{
					$nb[$obj->date_lim_reglement]=$obj->nb;

				}


				return $nb;
			}
			else
				dol_syslog("NbFactureAPrelever");
			$this->db->free($resql);
        }
        else
        {
            $this->error=get_class($this)."::SommeAPrelever Erreur -1 sql=".$this->db->error();
            dol_syslog($this->error, LOG_ERR);
            return -1;
        }
    }


    /**
     *	Get number of invoices to lcr
     *	TODO delete params banque and agence when not necesary
     *
     *	@param	int		$banque		dolibarr mysoc bank
     *	@param	int		$agence		dolibarr mysoc agence
     *	@return	int					<O if KO, number of invoices if OK
     */

    function NbFactureAPrelever($banque=0,$agence=0)
    {
        global $conf;

        $sql = "SELECT count(f.rowid) as nb";
        $sql.= " FROM ".MAIN_DB_PREFIX."facture as f";
        $sql.= ", ".MAIN_DB_PREFIX."lcr_facture_demande as pfd";
        $sql.= " WHERE f.fk_statut = 1";
        $sql.= " AND f.entity = ".$conf->entity;
        $sql.= " AND f.rowid = pfd.fk_facture";
        $sql.= " AND f.paye = 0";
        $sql.= " AND pfd.traite = 0";
        $sql.= " AND f.total_ttc > 0";
        

        $resql = $this->db->query($sql);

        if ( $resql )
        {
            $obj = $this->db->fetch_object($resql);

            $this->db->free($resql);

            return $obj->nb;
        }
        else
        {
            $this->error=get_class($this)."::SommeAPrelever Erreur -1 sql=".$this->db->error();
            dol_syslog($this->error, LOG_ERR);
            return -1;
        }
    }


    /**
     *	Create a lcr
     *  TODO delete params banque and agence when not necesary
     *
     *	@param 	int		$banque		dolibarr mysoc bank
     *	@param	int		$agence		dolibarr mysoc bank office (guichet)
     *	@param	string	$mode		real=do action, simu=test only
     *	@return	int					<0 if KO, nbre of invoice lcr if OK
     */

    function create($banque=0,$modetype = 3, $mode='real',$date_lim_reglement='')
    {
        global $conf,$langs;

        dol_syslog(get_class($this)."::Create banque=$banque agence=$agence");

        require_once (DOL_DOCUMENT_ROOT."/compta/facture/class/facture.class.php");
        require_once (DOL_DOCUMENT_ROOT."/societe/class/societe.class.php");

        $error = 0;

        $lcrItemsId = $_REQUEST['lcrItemsSelected'];
        if (count($lcrItemsId) == 0) {
            $error = 1;
            // lcr invoices in factures_prev array
            $out = "Aucun LCR n'a été sélectionné !";
            //print $out."\n";
            dol_syslog($out);
        } else {
            $lcrIds = implode(",", $lcrItemsId);
        }

        $datetimeprev = time();
        $chosenDate = DateTime::createFromFormat('d/m/Y', $_REQUEST['re']);
        if (!empty($_REQUEST['re'])) {
            $datetimeprev = strtotime($chosenDate->format('Y-m-d'));
        }

		$day = strftime("%d", $datetimeprev);
        $month = strftime("%m", $datetimeprev);
        $year = strftime("%Y", $datetimeprev);

        $puser = new User($this->db, $conf->global->LCR_USER);

        /*
         * Read invoices
         */
        $factures = array();
        $factures_prev = array();
        $factures_result = array();

        if (! $error)
        {
            $sql = "SELECT f.rowid, pfd.rowid as pfdrowid, f.fk_soc";
            $sql.= ", pfd.code_banque, pfd.code_guichet, pfd.number, pfd.cle_rib";
            $sql.= ", pfd.amount";
            $sql.= ", s.nom, pfd.mode, pfd.date_lim_reglement ";
            $sql.= " FROM ".MAIN_DB_PREFIX."facture as f";
            $sql.= ", ".MAIN_DB_PREFIX."societe as s";
            $sql.= ", ".MAIN_DB_PREFIX."lcr_facture_demande as pfd";
            $sql.= " WHERE f.rowid = pfd.fk_facture";
            $sql.= " AND f.entity = ".$conf->entity;
            $sql.= " AND s.rowid = f.fk_soc";
            $sql.= " AND f.fk_statut = 1";
            $sql.= " AND f.paye = 0";
            $sql.= " AND pfd.traite = 0";
            $sql.= " AND f.total_ttc > 0";
            $sql.= " AND pfd.rowid IN (".$lcrIds.")";

            
			//echo $sql;
            dol_syslog(get_class($this)."::Create sql=".$sql, LOG_DEBUG);
            $resql = $this->db->query($sql);
            if ($resql)
            {
                $num = $this->db->num_rows($resql);
                $i = 0;

                while ($i < $num)
                {
                    $row = $this->db->fetch_row($resql);
                    $factures[$i] = $row;	// All fields
                    $i++;
                }
                $this->db->free($resql);
                dol_syslog($i." invoices to withdraw");
            }
            else
            {
                $error = 1;
                dol_syslog("Erreur -1");
                dol_syslog($this->db->error());
            }
        }

        if (! $error)
        {
            require_once DOL_DOCUMENT_ROOT . '/societe/class/companybankaccount.class.php';
            $soc = new Societe($this->db);

        	// Check RIB
            $i = 0;
            dol_syslog("Start RIB check");

            if (count($factures) > 0)
            {
                foreach ($factures as $key => $fac)
                {
                    $fact = new Facture($this->db);
                    if ($fact->fetch($fac[0]) >= 0)		// Field 0 of $fac is rowid of invoice
                    {
                        if ($soc->fetch($fact->socid) >= 0)
                        {
                        	$bac = new CompanyBankAccount($this->db);
                        	$bac->fetch(0,$soc->id);
                            if ($bac->verif() >= 1)
                            {
                                $factures_prev[$i] = $fac;
                                /* second table necessary for BonLcr */
                                $factures_prev_id[$i] = $fac[0];
                                $i++;
                            }
                            else
							{
								dol_syslog("Error on default bank number RIB/IBAN for thirdparty reported by verif() ".$fact->socid." ".$soc->nom, LOG_ERR);
                                $facture_errors[$fac[0]]="Error on default bank number RIB/IBAN for thirdparty reported by function verif() ".$fact->socid." ".$soc->nom;
                            }
                        }
                        else
						{
                            dol_syslog("Failed to read company", LOG_ERR);
                        }
                    }
                    else
					{
                        dol_syslog("Failed to read invoice", LOG_ERR);
                    }
                }
            }
            else
			{
                dol_syslog("No invoice to process");
            }
        }

        $ok=0;

        // lcr invoices in factures_prev array
        $out=count($factures_prev)." invoices will be withdrawn.";
        dol_syslog($out);


        if (count($factures_prev) > 0)
        {
            if ($mode=='real')
            {
                $ok=1;
            }
            else
            {
                print $langs->trans("ModeWarning"); //"Option for real mode was not set, we stop after this simulation\n";
            }
        }


        if ($ok)
        {

            /*
             * We are in real mode.
             * We create withdraw receipt and build lcr into disk
             */
            $this->db->begin();

            $now=dol_now();

            if (!$error)
            {
                $ref = "T".substr($year,-2).$month;//.$day

                $sql = "SELECT count(*)";
                $sql.= " FROM ".MAIN_DB_PREFIX."lcr_bons";
                $sql.= " WHERE ref LIKE '".$ref."%'";
                $sql.= " AND entity = ".$conf->entity;

                dol_syslog(get_class($this)."::Create sql=".$sql, LOG_DEBUG);
                $resql = $this->db->query($sql);

                if ($resql)
                {
                    $row = $this->db->fetch_row($resql);
                }
                else
                {
                    $error++;
                    dol_syslog("Erreur recherche reference");
                }

                // chek if exist
                $ref = $ref . substr("00".($row[0]+1), -2) . '-' . rand(100, 999);

                $filebonprev = $ref;

                // Create lcr receipt in database
                $sql = "INSERT INTO ".MAIN_DB_PREFIX."lcr_bons (";
                $sql.= " ref, entity,fk_bank_account, datec";
                $sql.= ") VALUES (";
                $sql.= "'".$ref."'";
                $sql.= ", ".$conf->entity;
				$sql.= ", ".(!empty($banque)?$banque:$conf->global->LCR_ID_BANKACCOUNT);
                $sql.= ", '". (new \Datetime('now'))->format('Y-m-d')."'";
                $sql.= ")";

                dol_syslog(get_class($this)."::Create sql=".$sql, LOG_DEBUG);
                $resql = $this->db->query($sql);

                if ($resql)
                {
                    $prev_id = $this->db->last_insert_id(MAIN_DB_PREFIX."lcr_bons");

                    $dir=$conf->lcr->dir_output.'/receipts';
                    $file=$filebonprev;
                    if (! is_dir($dir)) dol_mkdir($dir);

                    $bonprev = new BonLcr($this->db, $dir."/".$file);
                    $bonprev->id = $prev_id;
                }
                else
                {
                    $error++;
                    dol_syslog("Erreur creation du bon de lcr");
                }
            }


            /*
             * Create lcr receipt
             */
            if (!$error)
            {
                if (count($factures_prev) > 0)
                {
                    foreach ($factures_prev as $fac)
                    {
                        // Fetch invoice
                        $fact = new Facture($this->db);
                        $fact->fetch($fac[0]);

                        $ri = $bonprev->AddFacture(
                            $fac[0],
                            $fac[2],
                            $fac[8],
                            $fac[7],
                            $fac[3],
                            $fac[4],
                            $fac[5],
                            $fac[6],$modetype,
                            $fac[10]
                        );
                        if ($ri != 0)
                            $error++;

                        /*
                         * Update orders
                         */
                        $sql = "UPDATE ".MAIN_DB_PREFIX."lcr_facture_demande";
                        $sql.= " SET traite = 1";
                        $sql.= ", date_traite = '".$this->db->idate($now)."'";
                        $sql.= ", fk_lcr_bons = ".$prev_id;
                        $sql.= ", mode = " . $modetype;
                        $sql.= " WHERE rowid = ".$fac[1];

                        dol_syslog(get_class($this)."::Create sql=".$sql, LOG_DEBUG);
                        $resql=$this->db->query($sql);
                        if (! $resql)
                        {
                            $error++;
                            dol_syslog("Erreur mise a jour des demandes");
                            dol_syslog($this->db->error());
                        }
                    }
                }
            }

            if (!$error)
            {

                /*
                 * lcr receipt
                 */
                dol_syslog("Debut lcr - Nombre de factures ".count($factures_prev));

                if (count($factures_prev) > 0)
                {
                    $bonprev->date_echeance = $datetimeprev;
                    $bonprev->reference_remise = $ref;

					$account = new Account($this->db);
					$account->fetch(!empty($banque)?$banque:$conf->global->LCR_ID_BANKACCOUNT);
					$bonprev->raison_sociale              = $account->proprio;
					$bonprev->bank        				  = $account->bank;
                    $bonprev->emetteur_code_banque 		  =$account->code_banque;
                    $bonprev->emetteur_code_guichet       =$account->code_guichet;
                    $bonprev->emetteur_numero_compte      =$account->number;
                    $bonprev->emetteur_number_key		  =$account->cle_rib;
                    $bonprev->emetteur_iban               =$account->iban;
                    $bonprev->emetteur_bic                =$account->bic;
                    $bonprev->factures = $factures_prev_id;
                    $bonprev->generate( $modetype );
                }
                dol_syslog($filebonprev);
                dol_syslog("Fin lcr");
            }


            /*
             * Update total
             */
            $sql = "UPDATE ".MAIN_DB_PREFIX."lcr_bons";
            $sql.= " SET amount = ".price2num($bonprev->total);
            $sql.= " WHERE rowid = ".$prev_id;
            $sql.= " AND entity = ".$conf->entity;

            dol_syslog(get_class($this)."::Create sql=".$sql, LOG_DEBUG);
            $resql=$this->db->query($sql);
            if (! $resql)
            {
                $error++;
                dol_syslog("Erreur mise a jour du total - $sql");
            }


            /*
             * Rollback or Commit
             */
            if (!$error)
            {
                $this->db->commit();
            }
            else
            {
                $this->db->rollback();
                dol_syslog("Error",LOG_ERR);
            }

            return count($factures_prev);
        }
        else
        {
            return 0;
        }
    }


    /**
     *	Get object and lines from database
     *
     *	@return	int					>0 if OK, <0 if KO
     */

    function delete()
    {
    	$this->db->begin();

    	$sql = "DELETE FROM ".MAIN_DB_PREFIX."lcr_facture WHERE fk_lcr_lignes IN (SELECT rowid FROM ".MAIN_DB_PREFIX."lcr_lignes WHERE fk_lcr_bons = '".$this->id."')";
    	$resql1=$this->db->query($sql);
    	if (! $resql1) dol_print_error($this->db);

    	$sql = "DELETE FROM ".MAIN_DB_PREFIX."lcr_lignes WHERE fk_lcr_bons = '".$this->id."'";
    	$resql2=$this->db->query($sql);
    	if (! $resql2) dol_print_error($this->db);

    	$sql = "DELETE FROM ".MAIN_DB_PREFIX."lcr_bons WHERE rowid = '".$this->id."'";
    	$resql3=$this->db->query($sql);
		if (! $resql3) dol_print_error($this->db);

    	$sql = "UPDATE ".MAIN_DB_PREFIX."lcr_facture_demande SET fk_lcr_bons = NULL, traite = 0, date_traite = NULL, mode = 99 WHERE fk_lcr_bons = '".$this->id."'";
    	$resql4=$this->db->query($sql);
		if (! $resql4) dol_print_error($this->db);

		if ($resql1 && $resql2 && $resql3)
		{
			$this->db->commit();
			return 1;
		}
		else
		{
			$this->db->rollback();
			return -1;
		}
    }


    /**
     *	Returns clickable name (with picto)
     *
     *	@param	int		$withpicto	link with picto
     *	@param	string	$option		link target
     *	@return	string				URL of target
     */

    function getNomUrl($withpicto=0,$option='')
    {
        global $langs;

        $result='';

        $lien = '<a href="'.dol_buildpath('/lcr/fiche.php?id='.$this->id,1).'">';
        $lienfin='</a>';

        if ($option == 'xxx')
        {
            $lien = '<a href="'.dol_buildpath('/lcr/fiche.php?id='.$this->id,1).'">';
            $lienfin='</a>';
        }

        if ($withpicto) $result.=($lien.img_object($langs->trans("ShowBankdraft"),'payment').$lienfin.' ');
        $result.=$lien.$this->ref.$lienfin;
        return $result;
    }


    /**
     *	Delete a notification def by id
     *
     *	@param	int		$rowid		id of notification
     *	@return	int					0 if OK, <0 if KO
     */

    function DeleteNotificationById($rowid)
    {
        $result = 0;

        $sql = "DELETE FROM ".MAIN_DB_PREFIX."notify_def";
        $sql.= " WHERE rowid = '".$rowid."'";

        if ($this->db->query($sql))
        {
            return 0;
        }
        else
        {
            return -1;
        }
    }


    /**
     *	Delete a notification
     *
     *	@param	User	$user		notification user
     *	@param	string	$action		notification action
     *	@return	int					>0 if OK, <0 if KO
     */

    function DeleteNotification($user, $action)
    {
        $result = 0;

        $sql = "DELETE FROM ".MAIN_DB_PREFIX."notify_def";
        $sql .= " WHERE fk_user=".$user." AND fk_action='".$action."'";

        if ($this->db->query($sql))
        {
            return 0;
        }
        else
        {
            return -1;
        }
    }


    /**
     *	Add a notification
     *
     *	@param	DoliDB	$db			database handler
     *	@param	User	$user		notification user
     *	@param	string	$action		notification action
     *	@return	int					0 if OK, <0 if KO
     */

    function AddNotification($db, $user, $action)
    {
        $result = 0;

        if ($this->DeleteNotification($user, $action) == 0)
        {
        	$now=dol_now();

            $sql = "INSERT INTO ".MAIN_DB_PREFIX."notify_def (datec,fk_user, fk_soc, fk_contact, fk_action)";
            $sql .= " VALUES (".$db->idate($now).",".$user.", 'NULL', 'NULL', '".$action."')";

            dol_syslog("adnotiff: ".$sql);
            if ($this->db->query($sql))
            {
                $result = 0;
            }
            else
            {
                $result = -1;
                dol_syslog(get_class($this)."::AddNotification Error $result");
            }
        }

        return $result;
    }


    /**
     *	Generate a lcr file. Generation Formats:
     *   France: CFONB
     *   Spain:  AEB19 (if external module EsAEB is enabled)
     *   Others: Warning message
     *	File is generated with name this->filename
     *
     *	@return		int			0 if OK, <0 if KO
     */

    //TODO: Optimize code to read lines in a single function
    function Generate( $modetype = 3 )
    {
        global $conf,$langs,$mysoc;

        $result = 0;

        dol_syslog(get_class($this)."::Generate build file ".$this->filename);

        $this->file[0] = fopen($this->filename.".txt","w");

        // TODO Move code for es and fr into an external module file with selection into setup of lcr module
        $found=0;

        // Build file for European countries
        if (! $found && $mysoc->isInEEC())
        {
        	$found++;

					/**
					* Section creation lcr file
					*/

					// lcr Initialisation
					$this->NewLine();
					$CrLf = "\r\n";
					$date_actu_now = dol_now();
					$date_actu=$this->date_echeance;
					$dateTime_YMD  = dol_print_date($date_actu, '%Y%m%d');
					$dateTime_YMDHMS = dol_print_date($date_actu, '%Y%m%d%H%M%S');
					$dateTime_ECMA = dol_print_date($date_actu, '%Y-%m-%dT%H:%M:%S');
					$dateTime_now_YMD  = dol_print_date($date_actu_now, '%Y%m%d');
					$dateTime_now_YMDHMS = dol_print_date($date_actu_now, '%Y%m%d%H%M%S');
					$dateTime_now_ECMA = dol_print_date($date_actu_now, '%Y-%m-%dT%H:%M:%S');
					$fileDebiteurSection = array('0'=>'','1'=>'');
					$fileEmetteurSection = array('0'=>'','1'=>'');


					$i = 0;
					$this->total = 0;
					$counttt=$totalt=array('0'=>0,'1'=>0);
					$custo=array();

					/*
					* section Debiteur (lcr Debiteurs bloc lines)
					*/
					$sql = "SELECT pl.rowid, soc.code_client as code, soc.address, soc.zip, soc.town, soc.datec, p.code as country_code,";
					$sql.= " pl.client_nom as nom, pl.code_banque as cb, pl.code_guichet as cg, pl.number as cc, rib.code_banque as cb0, rib.code_guichet as cg0, rib.number as cc0, pl.amount as somme,";
					$sql.= " f.facnumber , pf.fk_facture as idfac, rib.iban_prefix as iban, rib.bic as bic, rib.rowid as drum,f.fk_soc, pl.date_traite as date_lim_reglement, pl.mode ";
					$sql.= " FROM";
					$sql.= " ".MAIN_DB_PREFIX."lcr_lignes as pl ";
					$sql.= " LEFT JOIN  ".MAIN_DB_PREFIX."lcr_facture as pf ON (pl.rowid = pf.fk_lcr_lignes) ";
					$sql.= " LEFT JOIN ".MAIN_DB_PREFIX."facture as f ON (pf.fk_facture = f.rowid) ";
					$sql.= " LEFT JOIN ".MAIN_DB_PREFIX."societe as soc ON (soc.rowid = f.fk_soc) ";
					$sql.= " LEFT JOIN ".MAIN_DB_PREFIX."c_country as p ON (soc.fk_pays = p.rowid) ";
					$sql.= " LEFT JOIN ".MAIN_DB_PREFIX."societe_rib as rib ON (rib.fk_soc = f.fk_soc) ";
					$sql.= " WHERE pl.fk_lcr_bons = ".$this->id;
					$sql.= " AND rib.default_rib = 1";

					$filefirst=array();

					$resql=$this->db->query($sql);
					dol_syslog(get_class($this)."::Create sql=".$sql, LOG_DEBUG);
					if ($resql)
					{	$num = $this->db->num_rows($resql);
						while ($i < $num)
						{

							$obj = $this->db->fetch_object($resql);
							if (!isset($custo[$obj->fk_soc]))
								$custo[$obj->fk_soc]=0;
							if (empty($custo[$obj->fk_soc]))
								$t=0;
							else
								$t=1;
							if ($t==0)
							{

								$filefirst[$obj->rowid]=$obj;
							}
							$this->total = $this->total + round($obj->somme,2);
							$totalt[$t] +=  round($obj->somme,2);

							$i++;
						}

						if (!empty($filefirst))
						foreach($filefirst as $kew=>$obj)
						{
							$counttt[0]++;


							$fileDebiteurSection[0].= $this->EnregDestinataire($counttt[0], $obj->nom,($obj->cb!=''?$obj->cb:$obj->cb0),($obj->cg!=''?$obj->cg:$obj->cg0),($obj->cc!=''?$obj->cc:$obj->cc0),round($obj->somme,2), $obj->facnumber, $obj->idfac,  $obj->date_lim_reglement,$CrLf, $obj->mode);
						}
					}
					else
					{
						fputs($this->file[0], 'ERREUR DEBITEUR '.$sql.$CrLf);
						$result = -2;
					}

					/*
					* section Emetteur (lcr Emetteur bloc lines)
					*/
					if ($result != -2)
					{
						$t=0;
						$fileEmetteurSection[$t] .= $this->EnregEmetteur($conf, $date_actu, $CrLf, $modetype);
					}
					else
					{
						fputs($this->file[0], 'ERREUR DEBITEUR '.$CrLf);
					}



					$t=0;


						// lcr file Emetteur
						if ($result != -2)
						{
							fputs($this-> file[$t], $fileEmetteurSection[$t]);
						}
						// lcr file Debiteurs
						if ($result != -2)
						{
							fputs($this-> file[$t], $fileDebiteurSection[$t]);
						}
						fputs($this-> file[$t],$this->EnregTotal($totalt[$t],$counttt[$t],  $CrLf));
				}


				// Build file for Other Countries with unknow format
				if (! $found)
				{
						$this->total = 0;
						$sql = "SELECT pl.amount";
						$sql.= " FROM";
						$sql.= " ".MAIN_DB_PREFIX."lcr_lignes as pl,";
						$sql.= " ".MAIN_DB_PREFIX."facture as f,";
						$sql.= " ".MAIN_DB_PREFIX."lcr_facture as pf";
						$sql.= " WHERE pl.fk_lcr_bons = ".$this->id;
						$sql.= " AND pl.rowid = pf.fk_lcr_lignes";
						$sql.= " AND pf.fk_facture = f.rowid";

						//Lines
						$i = 0;
						$resql=$this->db->query($sql);
						if ($resql)
						{
								$num = $this->db->num_rows($resql);

								while ($i < $num)
								{
										$obj = $this->db->fetch_object($resql);
										$this->total = $this->total + $obj->amount;
										$i++;
								}
						}
						else
						{
								$result = -2;
						}
						$langs->load('lcr');
						fputs($this->file, $langs->trans('BankdraftFileNotCapable'));
				}

					fclose($this->file[0]);

				if (!empty($conf->global->MAIN_UMASK))
				{
					@chmod($this->file[0], octdec($conf->global->MAIN_UMASK));

				}


				return $result;
		}


    /**
     *	Write recipient of request (customer)
     *
     *	@param	int		$rowid			id of line
     *	@param	string	$client_nom		name of customer
     *	@param	string	$rib_banque		code of bank
     *	@param	string	$rib_guichet 	code of bank office
     *	@param	string	$rib_number		bank account
     *	@param	float	$amount			amount
     *	@param	string	$facnumber		ref of invoice
     *	@param	int		$facid			id of invoice
	 *  @param	string	$rib_dom		rib domiciliation
     *	@return	void
     */

    function EnregDestinataire($rowid, $client_nom, $rib_banque, $rib_guichet, $rib_number, $amount, $facnumber, $facid, $date_lim_reglement,$CrLf, $mode =
0)
   {

				$this->FormatLine(0,"06");
				// Lcr ordinaire
				$this->FormatLine(2,"60");
				// numline
				$this->FormatLine(4,substr("00000000".($rowid+1),-8));
				// facid
				$this->FormatLine(20,substr("0000000000".$facid,-10) );
				// nom societe
				$this->FormatLine(30,strtoupper($this->accent($client_nom)) );
				// type Escompte / encaissement
				$this->FormatLine(78,$mode == 3 ? 0 : $mode);
				// RIB
				$this->FormatLine(81,substr($rib_banque,-5) .
substr($rib_guichet,-5) . substr($rib_number,-11) );
				// amount
				$this->FormatLine(102, substr("000000000000".round(100*
$amount),-12) );

				// date
				$this->FormatLine(118,date("dmy", strtotime($date_lim_reglement.'
00:00:00')));
				// date
				$this->FormatLine(124,date("dmy"));

				return $this->GetLine();
   }


    /**
     *	Write sender of request (me)
     *
     *	@return	void
     */

    function EnregEmetteur($conf, $date_actu,  $CrLf, $modetype = 3)
    {
		//"036000000001000000      060315VIRAL S.A.R.L           BNP PARIBAS            "
				$this->FormatLine(0,"03");
				// Lcr ordinaire
				$this->FormatLine(2,"60");
				// num line
				$this->FormatLine(4,"00000001");

				$this->FormatLine(12,"000000");
				// date
				$this->FormatLine(24,date("dmy"));
				// nom societe
				$this->FormatLine(30,strtoupper($this->accent($this->raison_sociale)) );
				// nom banque
				$this->FormatLine(54,strtoupper($this->accent($this->bank)) );
				// type Escompte / encaissement
				$this->FormatLine(78,$modetype);

				$this->FormatLine(79,'0');
				// code emeteur
				$this->FormatLine(80,'E');
				// RIB
				$this->FormatLine(81,substr($this->emetteur_code_banque,-5) . substr($this->emetteur_code_guichet,-5) . substr($this->emetteur_numero_compte,-11) );
				// date
				$this->FormatLine(118,date("dmy",($date_actu)));
				// Siren
				$this->FormatLine(134,$conf->global->MAIN_INFO_SIREN );
				// Zone Reservee F
				$this->FormatLine(149,$this->reference_remise );

				return $this->GetLine();
    }


    /**
     *	Write end
     *
     *	@param	int		$total	total amount
     *	@return	void
     */

    function EnregTotal($total,$rowid, $CrLf)
    {

				$this->FormatLine(0,"08");
				// Lcr ordinaire
				$this->FormatLine(2,"60");
				// numline
				$this->FormatLine(4,substr("00000000".($rowid+2),-8));

				// Total  Amount
				$this->FormatLine(102,substr("00000000".round($total*100),-12) );

				return $this->GetLine();
    }


	/**
	*   @fn accent($str)
	*	@brief clean accent
	*	@param $str
	*	@return $str
    */

	private function accent($str)
	{
		if (!empty($str))
		{
			$str = strtr($str, 'ÁÀÂÄÃÅÇÉÈÊËÍÏÎÌÑÓÒÔÖÕÚÙÛÜÝ', 'AAAAAACEEEEEIIIINOOOOOUUUUY');
			$str = strtr($str, 'áàâäãåçéèêëíìîïñóòôöõúùûüýÿ', 'aaaaaaceeeeiiiinooooouuuuyy');
			$str = strtr($str, utf8_decode('ÁÀÂÄÃÅÇÉÈÊËÍÏÎÌÑÓÒÔÖÕÚÙÛÜÝ'), 'AAAAAACEEEEEIIIINOOOOOUUUUY');
			$str = strtr($str, utf8_decode('áàâäãåçéèêëíìîïñóòôöõúùûüýÿ'), 'aaaaaaceeeeiiiinooooouuuuyy');
			$str = strtr($str, utf8_encode('ÁÀÂÄÃÅÇÉÈÊËÍÏÎÌÑÓÒÔÖÕÚÙÛÜÝ'), 'AAAAAACEEEEEIIIINOOOOOUUUUY');
			$str = strtr($str, utf8_encode('áàâäãåçéèêëíìîïñóòôöõúùûüýÿ'), 'aaaaaaceeeeiiiinooooouuuuyy');
		}
		return $str;
	}


    /**
	*	@fn NewLine($limit = 160)
	*	@brief Internal methode for init new line
	*	@param $limit int default : 160
	*	@return none
    */

    private function NewLine($limit = 160)
    {
			$this->newline = str_repeat(' ', $limit);
    }


    /**
	*	@fn FormatLine( $pos = 0 ,$string='')
	*	@brief Internal methode for add item in current line
	*	@param $pos int position of cursor
	*	@param $string string for add
	*	@return none
    */

    private function FormatLine( $pos = 0 ,$string='')
    {

			if($pos === 0 )
				$line = '';
			else
				$line = substr($this->newline, 0, $pos );

			$line.= $string;
			$line.= substr($this->newline, ( $pos + strlen($string ) ) );

			$this->newline = $line;
    }


    /**
	*	@fn GetLine($new =true, $CrLf = "\r\n")
	*	@brief Internal methode for get current line and instanciate new line
	*	@param $new bool treu for new init line or false; default is true
	*	@param $CrLf string  end of line
	*	@return current line
    */

    private function GetLine($new =true, $CrLf = "\r\n")
    {
			$line = $this->newline . $CrLf;

			if($new)
				$this->NewLine();

			return $line;
    }


    /**
     *    Return status label of object
     *
     *    @param    int		$mode   0=Label, 1=Picto + label, 2=Picto, 3=Label + Picto
     * 	  @return	string     		Label
     */

    function getLibStatut($mode=0)
    {
        return $this->LibStatut($this->statut,$mode);
    }


    /**
     *    Return status label for a status
     *
     *    @param	int		$statut     id statut
     *    @param	int		$mode   	0=Label, 1=Picto + label, 2=Picto, 3=Label + Picto
     * 	  @return	string  		    Label
     */

    function LibStatut($statut,$mode=0)
    {
        global $langs;

        if ($mode == 0)
        {
            return $langs->trans($this->labelstatut[$statut]);
        }

        if ($mode == 1)
        {
            if ($statut==0) return img_picto($langs->trans($this->labelstatut[$statut]),'statut0').' '.$langs->trans($this->labelstatut[$statut]);
            if ($statut==1) return img_picto($langs->trans($this->labelstatut[$statut]),'statut1').' '.$langs->trans($this->labelstatut[$statut]);
            if ($statut==2) return img_picto($langs->trans($this->labelstatut[$statut]),'statut6').' '.$langs->trans($this->labelstatut[$statut]);
        }
        if ($mode == 2)
        {
            if ($statut==0) return img_picto($langs->trans($this->labelstatut[$statut]),'statut0');
            if ($statut==1) return img_picto($langs->trans($this->labelstatut[$statut]),'statut1');
            if ($statut==2) return img_picto($langs->trans($this->labelstatut[$statut]),'statut6');
        }

        if ($mode == 3)
        {
            if ($statut==0) return $langs->trans($this->labelstatut[$statut]).' '.img_picto($langs->trans($this->labelstatut[$statut]),'statut0');
            if ($statut==1) return $langs->trans($this->labelstatut[$statut]).' '.img_picto($langs->trans($this->labelstatut[$statut]),'statut1');
            if ($statut==2) return $langs->trans($this->labelstatut[$statut]).' '.img_picto($langs->trans($this->labelstatut[$statut]),'statut6');
        }
    }

}

