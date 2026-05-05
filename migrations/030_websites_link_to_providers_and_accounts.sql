-- Migration 030: Link websites table to providers and hosting_accounts
-- Adds two optional FK columns so each service record can reference the
-- new normalized tables introduced in migration 029.
--
-- hosting_account_id → used by service_type = 'hosting_web'  (which cPanel/WHM account)
-- provider_id        → used by service_type = 'domain'  (registrar)
--                      and service_type = 'hosting_mail' (mail provider)

ALTER TABLE websites
    ADD COLUMN IF NOT EXISTS hosting_account_id INT(11) DEFAULT NULL AFTER hosting_id,
    ADD COLUMN IF NOT EXISTS provider_id        INT(11) DEFAULT NULL AFTER hosting_account_id;

-- Add FKs only if the target tables exist (they were created in migration 029)
-- Wrapped in procedures so the script doesn't fail on re-run
ALTER TABLE websites
    ADD CONSTRAINT fk_websites_hosting_account
        FOREIGN KEY (hosting_account_id) REFERENCES hosting_accounts(id)
        ON DELETE SET NULL ON UPDATE CASCADE;

ALTER TABLE websites
    ADD CONSTRAINT fk_websites_provider
        FOREIGN KEY (provider_id) REFERENCES providers(id)
        ON DELETE SET NULL ON UPDATE CASCADE;
