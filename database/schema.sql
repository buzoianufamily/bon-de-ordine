-- =====================================================================
--  Sistem de Bon de Ordine (Queue Management) - Schema baza de date
--  MySQL 5.7+ / MariaDB 10.2+  -  charset utf8mb4
--  Aceasta este schema pentru O instanta (un client/tenant).
--  Pentru multi-tenant: cate o baza de date separata per client,
--  toate folosesc aceasta schema identica.
-- =====================================================================
SET NAMES utf8mb4;
SET time_zone = '+00:00';

-- ---------- Setari (chei-valoare: branding, limba, voce TTS, etc.) ----------
CREATE TABLE IF NOT EXISTS settings (
  k  VARCHAR(64)  NOT NULL PRIMARY KEY,
  v  TEXT         NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- Locatii / filiale ----------
CREATE TABLE IF NOT EXISTS branches (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  name       VARCHAR(120) NOT NULL,
  city       VARCHAR(80)  NULL,
  country    VARCHAR(80)  NULL DEFAULT 'Romania',
  address    VARCHAR(255) NULL,
  timezone   VARCHAR(64)  NOT NULL DEFAULT 'Europe/Bucharest',
  active     TINYINT(1)   NOT NULL DEFAULT 1,
  created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- Servicii (ex: CARDIOLOGIE 'C', GHISEU 1 'A') ----------
CREATE TABLE IF NOT EXISTS services (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  branch_id       INT NOT NULL,
  prefix          VARCHAR(3)   NOT NULL,            -- ex: 'A', 'C'
  name            VARCHAR(120) NOT NULL,
  abbreviation    VARCHAR(40)  NULL,
  description     VARCHAR(255) NULL,
  color           VARCHAR(20)  NOT NULL DEFAULT '#2563eb',
  status          ENUM('active','inactive') NOT NULL DEFAULT 'active',
  num_from        INT NOT NULL DEFAULT 1,           -- primul numar din serie
  num_to          INT NOT NULL DEFAULT 999,         -- ultimul numar (apoi reseteaza)
  include_zeros   TINYINT(1) NOT NULL DEFAULT 1,    -- C001 vs C1
  pad_length      INT NOT NULL DEFAULT 3,           -- cate cifre (001 = 3)
  allow_priority  TINYINT(1) NOT NULL DEFAULT 0,    -- permite bilet prioritar
  terminate_on_call TINYINT(1) NOT NULL DEFAULT 0,  -- inchide automat la apel
  kpi_wait_sec    INT NOT NULL DEFAULT 600,         -- tinta timp asteptare (sec)
  kpi_service_sec INT NOT NULL DEFAULT 300,         -- tinta timp servire (sec)
  max_queued      INT NOT NULL DEFAULT 0,           -- 0 = nelimitat
  sort_order      INT NOT NULL DEFAULT 0,
  active_hours    VARCHAR(255) NULL,                -- optional program (json)
  form_id         INT NULL,                          -- formular la emiterea bonului
  appt_enabled    TINYINT(1) NOT NULL DEFAULT 0,      -- permite programari
  appt_slot_min   INT NOT NULL DEFAULT 15,            -- durata slot (min)
  appt_capacity   INT NOT NULL DEFAULT 1,             -- locuri per slot
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_services_branch FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE CASCADE,
  INDEX idx_services_branch (branch_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- Ghisee / birouri ----------
CREATE TABLE IF NOT EXISTS counters (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  branch_id    INT NOT NULL,
  code         VARCHAR(10)  NOT NULL,               -- ex: B1
  name         VARCHAR(80)  NOT NULL,               -- ex: Birou 1
  status       ENUM('open','paused','closed') NOT NULL DEFAULT 'closed',
  all_services TINYINT(1) NOT NULL DEFAULT 1,       -- 1 = toate serviciile filialei
  priority     INT NOT NULL DEFAULT 0,              -- prioritate ghiseu (tie-break)
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_counters_branch FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE CASCADE,
  INDEX idx_counters_branch (branch_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- Ce servicii deserveste fiecare ghiseu (cand all_services=0) ----------
CREATE TABLE IF NOT EXISTS counter_services (
  counter_id INT NOT NULL,
  service_id INT NOT NULL,
  PRIMARY KEY (counter_id, service_id),
  CONSTRAINT fk_cs_counter FOREIGN KEY (counter_id) REFERENCES counters(id) ON DELETE CASCADE,
  CONSTRAINT fk_cs_service FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- Utilizatori (operatori/admini) ----------
CREATE TABLE IF NOT EXISTS users (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  name          VARCHAR(120) NOT NULL,
  email         VARCHAR(160) NOT NULL UNIQUE,
  username      VARCHAR(80)  NULL,
  password_hash VARCHAR(255) NOT NULL,
  role          ENUM('admin','manager','agent') NOT NULL DEFAULT 'agent',
  pin           VARCHAR(12)  NULL,
  notify_browser TINYINT(1) NOT NULL DEFAULT 0,    -- notificari browser la terminalul operatorului
  active        TINYINT(1) NOT NULL DEFAULT 1,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- Dispozitive (dispenser, player/TV, bilet digital, launcher) ----------
CREATE TABLE IF NOT EXISTS devices (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  branch_id      INT NOT NULL,
  type           ENUM('dispenser','player','widget_player','digital_ticket','launcher') NOT NULL,
  name           VARCHAR(120) NOT NULL,
  connection_key VARCHAR(12) NOT NULL UNIQUE,        -- ex: 5F67ZD
  all_services   TINYINT(1) NOT NULL DEFAULT 1,
  config         LONGTEXT NULL,                      -- JSON: culori, texte, layout, widget-uri
  printer_mode   ENUM('browser','network','android','none') NOT NULL DEFAULT 'browser',
  printer_ip     VARCHAR(64) NULL,                   -- pt printare in retea (Bixolon Ethernet)
  printer_port   INT NOT NULL DEFAULT 9100,
  last_seen      DATETIME NULL,
  created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_devices_branch FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE CASCADE,
  INDEX idx_devices_branch (branch_id),
  INDEX idx_devices_key (connection_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS device_services (
  device_id  INT NOT NULL,
  service_id INT NOT NULL,
  PRIMARY KEY (device_id, service_id),
  CONSTRAINT fk_ds_device FOREIGN KEY (device_id) REFERENCES devices(id) ON DELETE CASCADE,
  CONSTRAINT fk_ds_service FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- Secventa de numerotare (reset zilnic, atomic) ----------
CREATE TABLE IF NOT EXISTS ticket_sequences (
  service_id  INT  NOT NULL,
  seq_date    DATE NOT NULL,
  last_number INT  NOT NULL DEFAULT 0,
  PRIMARY KEY (service_id, seq_date),
  CONSTRAINT fk_seq_service FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- Bilete (tichete) ----------
CREATE TABLE IF NOT EXISTS tickets (
  id            BIGINT AUTO_INCREMENT PRIMARY KEY,
  branch_id     INT NOT NULL,
  service_id    INT NOT NULL,
  number        INT NOT NULL,
  label         VARCHAR(16) NOT NULL,               -- ex: C001
  priority      TINYINT(1) NOT NULL DEFAULT 0,
  status        ENUM('waiting','called','serving','served','no_show','cancelled','transferred')
                NOT NULL DEFAULT 'waiting',
  counter_id    INT NULL,
  agent_id      INT NULL,
  channel       ENUM('paper','qr','web','sms','appointment') NOT NULL DEFAULT 'paper',
  customer_phone VARCHAR(32) NULL,
  public_token  VARCHAR(40) NULL,                   -- pt link bilet digital (telefon)
  recall_count  INT NOT NULL DEFAULT 0,
  note          VARCHAR(255) NULL,
  form_data     LONGTEXT NULL,                       -- raspunsuri formular (JSON)
  issued_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  called_at     DATETIME NULL,
  served_at     DATETIME NULL,
  finished_at   DATETIME NULL,
  CONSTRAINT fk_tickets_branch  FOREIGN KEY (branch_id)  REFERENCES branches(id)  ON DELETE CASCADE,
  CONSTRAINT fk_tickets_service FOREIGN KEY (service_id) REFERENCES services(id)  ON DELETE CASCADE,
  CONSTRAINT fk_tickets_counter FOREIGN KEY (counter_id) REFERENCES counters(id)  ON DELETE SET NULL,
  CONSTRAINT fk_tickets_agent   FOREIGN KEY (agent_id)   REFERENCES users(id)     ON DELETE SET NULL,
  INDEX idx_tickets_status (branch_id, status),
  INDEX idx_tickets_service_day (service_id, issued_at),
  INDEX idx_tickets_token (public_token),
  INDEX idx_tickets_called (branch_id, called_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- Sesiuni ghiseu (un operator activ per ghiseu) ----------
CREATE TABLE IF NOT EXISTS counter_sessions (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  counter_id INT NOT NULL,
  user_id    INT NOT NULL,
  started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ended_at   DATETIME NULL,
  CONSTRAINT fk_sess_counter FOREIGN KEY (counter_id) REFERENCES counters(id) ON DELETE CASCADE,
  CONSTRAINT fk_sess_user    FOREIGN KEY (user_id)    REFERENCES users(id)    ON DELETE CASCADE,
  INDEX idx_sess_counter (counter_id, ended_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- Feedback client (optional, dupa servire) ----------
CREATE TABLE IF NOT EXISTS feedback (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  ticket_id  BIGINT NOT NULL,
  rating     TINYINT NOT NULL,                      -- 1..5
  comment    VARCHAR(500) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_fb_ticket FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- Multimedia (logo-uri/imagini afisate pe ecrane) ----------
CREATE TABLE IF NOT EXISTS media (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  branch_id  INT NULL,
  filename   VARCHAR(255) NOT NULL,
  path       VARCHAR(255) NOT NULL,
  mime       VARCHAR(80)  NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- Formulare (campuri colectate la emiterea bonului) ----------
CREATE TABLE IF NOT EXISTS forms (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  branch_id  INT NULL,
  name       VARCHAR(120) NOT NULL,
  fields     LONGTEXT NULL,                       -- JSON: [{key,label,type,required,options}]
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- Programari (appointments) ----------
CREATE TABLE IF NOT EXISTS appointments (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  branch_id     INT NOT NULL,
  service_id    INT NOT NULL,
  customer_name  VARCHAR(120) NULL,
  customer_phone VARCHAR(32)  NULL,
  customer_email VARCHAR(160) NULL,
  slot_start    DATETIME NOT NULL,
  slot_end      DATETIME NULL,
  status        ENUM('booked','checked_in','cancelled','no_show') NOT NULL DEFAULT 'booked',
  ticket_id     BIGINT NULL,
  public_token  VARCHAR(40) NULL,
  note          VARCHAR(255) NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_appt_branch  FOREIGN KEY (branch_id)  REFERENCES branches(id) ON DELETE CASCADE,
  CONSTRAINT fk_appt_service FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE CASCADE,
  INDEX idx_appt_slot (service_id, slot_start),
  INDEX idx_appt_token (public_token),
  INDEX idx_appt_day (branch_id, slot_start)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
