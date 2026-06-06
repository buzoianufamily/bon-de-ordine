-- =====================================================================
--  Date demo pentru testare rapida (ruleaza DUPA schema.sql)
--  Adminul NU este aici - il creezi cu install.php (parola hash-uita corect)
-- =====================================================================
SET NAMES utf8mb4;

-- Filiala demo
INSERT INTO branches (id, name, city, country, address, timezone)
VALUES (1, 'Sediu Central', 'Bucuresti', 'Romania', 'Str. Exemplu nr. 1', 'Europe/Bucharest');

-- Servicii (ca in exemplul clinicii: prefix + culoare)
INSERT INTO services (branch_id, prefix, name, color, num_from, num_to, pad_length, allow_priority, sort_order) VALUES
(1, 'A', 'Casierie',        '#2563eb', 1, 999, 3, 1, 1),
(1, 'B', 'Informatii',      '#16a34a', 1, 999, 3, 0, 2),
(1, 'C', 'Depunere acte',   '#d97706', 1, 999, 3, 1, 3);

-- Ghisee
INSERT INTO counters (branch_id, code, name, status, all_services, priority) VALUES
(1, 'B1', 'Birou 1', 'closed', 1, 0),
(1, 'B2', 'Birou 2', 'closed', 1, 0);

-- Dispozitive (connection key = cum se leaga tableta/TV-ul de configuratie)
INSERT INTO devices (branch_id, type, name, connection_key, all_services, printer_mode) VALUES
(1, 'dispenser',     'Dispenser intrare', 'DEMO01', 1, 'browser'),
(1, 'player',        'TV sala asteptare', 'DEMO02', 1, 'none'),
(1, 'digital_ticket','Bilet digital QR',  'DEMO03', 1, 'none');

-- Setari brand / aplicatie (white-label per client)
INSERT INTO settings (k, v) VALUES
('brand_name',          'Compania Mea'),
('brand_logo',          ''),
('accent_color',        '#2563eb'),
('language',            'ro'),
('display_voice',       'ro-RO'),
('display_say_number',  '1'),
('display_say_counter', '1'),
('display_repeat',      '2'),
('ticket_footer',       'Va multumim! Pastrati biletul pana la apel.'),
('dispenser_title',     'ALEGE SERVICIUL'),
('org_name',            'Compania Mea SRL'),
('virtual_enabled',     '1');
