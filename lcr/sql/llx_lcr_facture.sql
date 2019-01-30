--
-- Table structure for table `llx_lcr_facture`
--

-- DROP TABLE IF EXISTS `llx_lcr_facture`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `llx_lcr_facture` (
  `rowid` int(11) NOT NULL AUTO_INCREMENT,
  `fk_facture` int(11) NOT NULL,
  `fk_lcr_lignes` int(11) NOT NULL,
  PRIMARY KEY (`rowid`),
  KEY `idx_lcr_facture_fk_lcr_lignes` (`fk_lcr_lignes`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `llx_lcr_facture`
--

LOCK TABLES `llx_lcr_facture` WRITE;
/*!40000 ALTER TABLE `llx_lcr_facture` DISABLE KEYS */;
/*!40000 ALTER TABLE `llx_lcr_facture` ENABLE KEYS */;
UNLOCK TABLES;