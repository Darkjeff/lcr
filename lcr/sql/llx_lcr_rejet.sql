--
-- Table structure for table `llx_lcr_rejet`
--

-- DROP TABLE IF EXISTS `llx_lcr_rejet`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `llx_lcr_rejet` (
  `rowid` int(11) NOT NULL AUTO_INCREMENT,
  `fk_lcr_lignes` int(11) DEFAULT NULL,
  `date_rejet` datetime DEFAULT NULL,
  `motif` int(11) DEFAULT NULL,
  `date_creation` datetime DEFAULT NULL,
  `fk_user_creation` int(11) DEFAULT NULL,
  `note` text,
  `afacturer` tinyint(4) DEFAULT '0',
  `fk_facture` int(11) DEFAULT NULL,
  PRIMARY KEY (`rowid`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `llx_lcr_rejet`
--

LOCK TABLES `llx_lcr_rejet` WRITE;
/*!40000 ALTER TABLE `llx_lcr_rejet` DISABLE KEYS */;
/*!40000 ALTER TABLE `llx_lcr_rejet` ENABLE KEYS */;
UNLOCK TABLES;