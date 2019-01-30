<?php
/* Copyright (C) 2005      Rodolphe Quiedeville <rodolphe@quiedeville.org>
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
 *
 */

/**
 *  \file       htdocs/compta/lcr/class/lignelcr.class.php
 *  \ingroup    lcr
 *  \brief      Fichier de la classe des lignes de lcrs
 */


require_once DOL_DOCUMENT_ROOT .'/compta/facture/class/facture.class.php';


class FactureLcr
    extends Facture 
    {

	/**
 	 *	Create a lcr request for a standing order
 	 *
 	 *	@param      User	$user       User asking standing order
 	 *  @param		Montant
 	 *  @param 		Date
 	 *  @param 		mode
 	 *	@return     int         		<0 if KO, >0 if OK
 	 */

	function demande_lcr($user, $montant, $mode = '')
	{
		dol_syslog(get_class($this)."::demande_lcr", LOG_DEBUG);

		if ($this->statut > 0 && $this->paye == 0)
		{
	        require_once DOL_DOCUMENT_ROOT . '/societe/class/companybankaccount.class.php';
	        $bac = new CompanyBankAccount($this->db);
	        $bac->fetch(0,$this->socid);
			
        	$sql = 'SELECT count(*), SUM(amount) as amount ';
			$sql.= ' FROM '.MAIN_DB_PREFIX.'lcr_facture_demande';
			$sql.= ' WHERE fk_facture = '.$this->id;
			$sql.= ' AND traite = 0';

			dol_syslog(get_class($this)."::demande_lcr sql=".$sql);
			$resql=$this->db->query($sql);
			if ($resql)
			{
				$row = $this->db->fetch_row($resql);
				if ($row[0] == 0 || $row[1] < $this->total_ttc )
				{
					$now=dol_now();

					$resteapayer = $montant;
				
					if ($this->cond_reglement_code=='3060JOURS'&round($resteapayer,2)==round($this->total_ttc,2))
					{
						$sql = 'INSERT INTO '.MAIN_DB_PREFIX.'lcr_facture_demande';
						$sql .= ' (fk_facture, amount, date_demande, fk_user_demande, code_banque, code_guichet, number, cle_rib,date_lim_reglement, mode)';
						$sql .= ' VALUES ('.$this->id;
						$resteapayer1=round($resteapayer/2,2);
						$resteapayer=round($resteapayer,2)-$resteapayer1;
						$sql .= ",'".price2num($resteapayer1)."'";
                        $sql .= ",NULL";
						$sql .= ",".$user->id;
						$sql .= ",'".$bac->code_banque."'";
						$sql .= ",'".$bac->code_guichet."'";
						$sql .= ",'".$bac->number."'";
						$sql .= ",'".$bac->cle_rib."'";
						$sql .= ",'".date('Y-m-d H:i:s',((date('n',$this->date_lim_reglement)*1==2&date('t',$this->date_lim_reglement)*1==date('j',$this->date_lim_reglement)*1)?strtotime("-".(date('t',$this->date_lim_reglement)+1)."day",$this->date_lim_reglement):
								((date('n',$this->date_lim_reglement)*1==3&date('t',strtotime("-31day",$this->date_lim_reglement))*1<date('j',$this->date_lim_reglement)*1)?strtotime("-".(date('j',$this->date_lim_reglement))."day",$this->date_lim_reglement):strtotime("-1month",$this->date_lim_reglement))))."'";
                        $sql .= ",99)";
						dol_syslog(get_class($this)."::demande_lcr sql=".$sql);
						if ($this->db->query($sql))
						{
						}
						else
						{
							$this->error=$this->db->lasterror();
							dol_syslog(get_class($this).'::demandelcr Erreur');
							return -1;
						}
						$sql = 'INSERT INTO '.MAIN_DB_PREFIX.'lcr_facture_demande';
						$sql .= ' (fk_facture, amount, date_demande, fk_user_demande, code_banque, code_guichet, number, cle_rib, date_lim_reglement, mode)';
						$sql .= ' VALUES ('.$this->id;
						$sql .= ",'".price2num($resteapayer)."'";
						$sql .= ",'".$this->db->idate($now)."'";
						$sql .= ",".$user->id;
						$sql .= ",'".$bac->code_banque."'";
						$sql .= ",'".$bac->code_guichet."'";
						$sql .= ",'".$bac->number."'";
						$sql .= ",'".$bac->cle_rib."'";
                        $sql .= ",'".date('Y-m-d H:i:s',$this->date_lim_reglement)."'";
	                    $sql .= ",99)";
                        dol_syslog(get_class($this)."::demande_lcr sql=".$sql);
						if ($this->db->query($sql))
						{
						}
						else
						{
							$this->error=$this->db->lasterror();
							dol_syslog(get_class($this).'::demandelcr Erreur');
							return -1;
						}
					}
					else
					{						
						$sql = 'INSERT INTO '.MAIN_DB_PREFIX.'lcr_facture_demande';
						$sql .= ' (fk_facture, amount, date_demande, fk_user_demande, code_banque, code_guichet, number, cle_rib,date_lim_reglement, mode)';
						$sql .= ' VALUES ('.$this->id;
						$sql .= ",'".price2num($resteapayer)."'";
						$sql .= ",'".$this->db->idate($now)."'";
						$sql .= ",".$user->id;
						$sql .= ",'".$bac->code_banque."'";
						$sql .= ",'".$bac->code_guichet."'";
						$sql .= ",'".$bac->number."'";
						$sql .= ",'".$bac->cle_rib."'";
                        $sql .= ",'". $this->db->idate(dol_mktime(0,0,0, $_POST["remonth"], $_POST["reday"],$_POST["reyear"]))."'";
                        $sql .= ",99)";

						dol_syslog(get_class($this)."::demande_lcr sql=".$sql);
						if ($this->db->query($sql))
						{
							return 1;
						}
						else
						{
							$this->error=$this->db->lasterror();
							dol_syslog(get_class($this).'::demandelcr Erreur');
							return -1;
						}
					}
                }
                else
                {
                    $this->error="A request already exists";
                    dol_syslog(get_class($this).'::demandelcr Impossible de creer une demande, demande deja en cours');
                }
            }
            else
            {
                $this->error=$this->db->error();
                dol_syslog(get_class($this).'::demandelcr Erreur -2');
                return -2;
            }
        }
        else
        {
            $this->error="Status of invoice does not allow this";
            dol_syslog(get_class($this)."::demandelcr ".$this->error." $this->statut, $this->paye, $this->mode_reglement_id");
            return -3;
        }
    }


	/**
	 *  Delete Lcr request
	 *
	 *  @param  Use		$user       user who create request
	 *  @param  int		$did        id of request
	 *  @return	int					<0 if OK, >0 if KO
	 */

	function demande_lcr_delete($user, $did)
	{
		$sql = 'DELETE FROM '.MAIN_DB_PREFIX.'lcr_facture_demande';
		$sql .= ' WHERE rowid = '.$did;
		$sql .= ' AND traite = 0';
		if ( $this->db->query($sql) )
		{
			return 0;
		}
		else
		{
			$this->error=$this->db->lasterror();
			dol_syslog(get_class($this).'::demande_lcr_delete Error '.$this->error);
			return -1;
		}
	}
}

?>
