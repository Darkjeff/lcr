--
-- Table structure for table `llx_lcr_facture_demande`
--

-- DROP TABLE IF EXISTS `llx_lcr_facture_demande`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `llx_lcr_facture_demande` (
  `rowid` int(11) NOT NULL AUTO_INCREMENT,
  `fk_facture` int(11) NOT NULL,
  `amount` double NOT NULL,
  `date_demande` datetime NOT NULL,
  `traite` smallint(6) DEFAULT '0',
  `date_traite` datetime DEFAULT NULL,
  `fk_lcr_bons` int(11) DEFAULT NULL,
  `fk_user_demande` int(11) NOT NULL,
  `code_banque` varchar(7) DEFAULT NULL,
  `code_guichet` varchar(6) DEFAULT NULL,
  `number` varchar(255) DEFAULT NULL,
  `cle_rib` varchar(5) DEFAULT NULL,
  `date_lim_reglement` datetime NOT NULL DEFAULT '1970-01-01 00:00:00',
  `mode` int(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`rowid`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `llx_lcr_facture_demande`
--

LOCK TABLES `llx_lcr_facture_demande` WRITE;
/*!40000 ALTER TABLE `llx_lcr_facture_demande` DISABLE KEYS */;
/*!40000 ALTER TABLE `llx_lcr_facture_demande` ENABLE KEYS */;
UNLOCK TABLES;