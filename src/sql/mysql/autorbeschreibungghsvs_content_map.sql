--
-- Tabellenstruktur für Tabelle `pkuej_autorbeschreibungghsvs_content_map`
--

CREATE TABLE IF NOT EXISTS `#__autorbeschreibungghsvs_content_map` (
  `content_id` int(11) unsigned NOT NULL,
  `contact_id` varchar(12) NOT NULL,
	UNIQUE KEY `ContentContactId` (`content_id`,`contact_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Autoren aus Kontaktkategorie vs Beitrag by GHSVS';
