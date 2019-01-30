--
-- Table structure for table `llx_lcr_lignes`
--

-- DROP TABLE IF EXISTS `llx_lcr_lignes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `llx_lcr_lignes` (
  `rowid` int(11) NOT NULL AUTO_INCREMENT,
  `fk_lcr_bons` int(11) DEFAULT NULL,
  `fk_soc` int(11) NOT NULL,
  `statut` smallint(6) DEFAULT '0',
  `client_nom` varchar(255) DEFAULT NULL,
  `amount` double DEFAULT '0',
  `code_banque` varchar(7) DEFAULT NULL,
  `code_guichet` varchar(6) DEFAULT NULL,
  `number` varchar(255) DEFAULT NULL,
  `cle_rib` varchar(5) DEFAULT NULL,
  `note` text,
  `date_traite` date NOT NULL,
  `mode` int(11) NOT NULL,
  PRIMARY KEY (`rowid`),
  KEY `idx_lcr_lignes_fk_lcr_bons` (`fk_lcr_bons`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `llx_lcr_lignes`
--

LOCK TABLES `llx_lcr_lignes` WRITE;
/*!40000 ALTER TABLE `llx_lcr_lignes` DISABLE KEYS */;
/*!40000 ALTER TABLE `llx_lcr_lignes` ENABLE KEYS */;
UNLOCK TABLES;