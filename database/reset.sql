-- ============================================================================
--  RESET COMPLET — sterge TOATE tabelele aplicatiei „Bon de ordine".
--  De rulat in phpMyAdmin (tab SQL) pe baza de date a clientului cand vrei sa
--  iei totul de la 0. Dupa rulare, deschide site-ul o data: aplicatia re-creeaza
--  automat schema + datele demo + contul admin implicit (admin@example.ro / 123456).
--
--  De ce e nevoie de acest fisier: tabelele au CHEI STRAINE intre ele, asa ca un
--  simplu „Drop" pe toate din phpMyAdmin da eroare („Cannot delete or update a
--  parent row" / „a foreign key constraint fails"). Dezactivam verificarea cheilor
--  straine pe durata stergerii, apoi o reactivam.
--
--  ALTERNATIVA si mai simpla (recomandata pe cPanel): din „MySQL Databases" sterge
--  baza de date si creeaz-o la loc goala, apoi deschide site-ul (se reinstaleaza).
-- ============================================================================

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `api_rate`;
DROP TABLE IF EXISTS `appointment_waitlist`;
DROP TABLE IF EXISTS `appointments`;
DROP TABLE IF EXISTS `audit_log`;
DROP TABLE IF EXISTS `branch_closures`;
DROP TABLE IF EXISTS `counter_services`;
DROP TABLE IF EXISTS `counter_sessions`;
DROP TABLE IF EXISTS `device_services`;
DROP TABLE IF EXISTS `devices`;
DROP TABLE IF EXISTS `feedback`;
DROP TABLE IF EXISTS `forms`;
DROP TABLE IF EXISTS `media`;
DROP TABLE IF EXISTS `password_resets`;
DROP TABLE IF EXISTS `ticket_sequences`;
DROP TABLE IF EXISTS `tickets`;
DROP TABLE IF EXISTS `user_status_log`;
DROP TABLE IF EXISTS `webhook_log`;
DROP TABLE IF EXISTS `counters`;
DROP TABLE IF EXISTS `service_groups`;
DROP TABLE IF EXISTS `services`;
DROP TABLE IF EXISTS `branches`;
DROP TABLE IF EXISTS `users`;
DROP TABLE IF EXISTS `settings`;

SET FOREIGN_KEY_CHECKS = 1;

-- Gata. Deschide acum site-ul (ex: https://bonordine.ro) — schema si contul admin
-- se creeaza automat la prima accesare.
