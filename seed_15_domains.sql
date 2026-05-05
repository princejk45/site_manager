START TRANSACTION;

INSERT INTO hosting (name, status, expiry_date, notes)
SELECT 'Client Delta', 'active', '2026-12-31', 'Seeded for domain flow demo'
WHERE NOT EXISTS (SELECT 1 FROM hosting WHERE name = 'Client Delta');

INSERT INTO hosting (name, status, expiry_date, notes)
SELECT 'Client Echo', 'active', '2026-12-31', 'Seeded for domain flow demo'
WHERE NOT EXISTS (SELECT 1 FROM hosting WHERE name = 'Client Echo');

INSERT INTO hosting (name, status, expiry_date, notes)
SELECT 'Client Foxtrot', 'active', '2026-12-31', 'Seeded for domain flow demo'
WHERE NOT EXISTS (SELECT 1 FROM hosting WHERE name = 'Client Foxtrot');

INSERT INTO websites (hosting_id, hosting_account_id, provider_id, domain, service_type, status, expiry_date, notes)
SELECT NULL, NULL, NULL, 'zenithhub.com', 'hosting_web', 'active', '2026-11-15', 'Seed baseline web service'
WHERE NOT EXISTS (
    SELECT 1 FROM websites WHERE domain = 'zenithhub.com' AND service_type = 'hosting_web'
);

INSERT INTO hosting_accounts (client_id, provider_id, cpanel_username, package_name, disk_quota_mb, bandwidth_mb, ip_address, expiry_date, auto_renew, status, notes)
SELECT h.id, 1, 'demoacct', 'starter', 5120, 102400, '192.168.10.11', '2026-12-15', 1, 'active', 'Seeded mapping for demo.com'
FROM hosting h
WHERE h.id = 1
  AND NOT EXISTS (SELECT 1 FROM hosting_accounts WHERE cpanel_username = 'demoacct');

INSERT INTO hosting_accounts (client_id, provider_id, cpanel_username, package_name, disk_quota_mb, bandwidth_mb, ip_address, expiry_date, auto_renew, status, notes)
SELECT h.id, 2, 'exampleacct', 'business', 10240, 204800, '192.168.10.12', '2026-10-20', 1, 'active', 'Seeded mapping for example.com'
FROM hosting h
WHERE h.id = 2
  AND NOT EXISTS (SELECT 1 FROM hosting_accounts WHERE cpanel_username = 'exampleacct');

INSERT INTO hosting_accounts (client_id, provider_id, cpanel_username, package_name, disk_quota_mb, bandwidth_mb, ip_address, expiry_date, auto_renew, status, notes)
SELECT h.id, 3, 'expiredacct', 'starter', 5120, 102400, '192.168.10.13', '2026-04-28', 0, 'expired', 'Seeded mapping for expired.com'
FROM hosting h
WHERE h.id = 3
  AND NOT EXISTS (SELECT 1 FROM hosting_accounts WHERE cpanel_username = 'expiredacct');

INSERT INTO hosting_accounts (client_id, provider_id, cpanel_username, package_name, disk_quota_mb, bandwidth_mb, ip_address, expiry_date, auto_renew, status, notes)
SELECT h.id, 1, 'otheracct', 'business', 10240, 204800, '192.168.10.14', '2026-09-01', 1, 'active', 'Seeded mapping for other.com'
FROM hosting h
WHERE h.name = 'Client Delta'
  AND NOT EXISTS (SELECT 1 FROM hosting_accounts WHERE cpanel_username = 'otheracct');

INSERT INTO hosting_accounts (client_id, provider_id, cpanel_username, package_name, disk_quota_mb, bandwidth_mb, ip_address, expiry_date, auto_renew, status, notes)
SELECT h.id, 2, 'testacct', 'starter', 5120, 102400, '192.168.10.15', '2026-08-20', 1, 'active', 'Seeded mapping for test.com'
FROM hosting h
WHERE h.name = 'Client Echo'
  AND NOT EXISTS (SELECT 1 FROM hosting_accounts WHERE cpanel_username = 'testacct');

INSERT INTO hosting_accounts (client_id, provider_id, cpanel_username, package_name, disk_quota_mb, bandwidth_mb, ip_address, expiry_date, auto_renew, status, notes)
SELECT h.id, 3, 'zenithacct', 'business', 10240, 204800, '192.168.10.16', '2026-11-15', 1, 'active', 'Seeded mapping for zenithhub.com'
FROM hosting h
WHERE h.name = 'Client Foxtrot'
  AND NOT EXISTS (SELECT 1 FROM hosting_accounts WHERE cpanel_username = 'zenithacct');

UPDATE websites w
LEFT JOIN hosting_accounts ha ON ha.cpanel_username = 'demoacct'
SET w.hosting_id = 1,
    w.provider_id = 1,
    w.hosting_account_id = ha.id,
    w.notes = COALESCE(w.notes, 'Web service mapped to client/provider/account')
WHERE w.domain = 'demo.com' AND w.service_type = 'hosting_web';

UPDATE websites w
LEFT JOIN hosting_accounts ha ON ha.cpanel_username = 'exampleacct'
SET w.hosting_id = 2,
    w.provider_id = 2,
    w.hosting_account_id = ha.id,
    w.notes = COALESCE(w.notes, 'Web service mapped to client/provider/account')
WHERE w.domain = 'example.com' AND w.service_type = 'hosting_web';

UPDATE websites w
LEFT JOIN hosting_accounts ha ON ha.cpanel_username = 'expiredacct'
SET w.hosting_id = 3,
    w.provider_id = 3,
    w.hosting_account_id = ha.id,
    w.notes = COALESCE(w.notes, 'Web service mapped to client/provider/account')
WHERE w.domain = 'expired.com' AND w.service_type = 'hosting_web';

UPDATE websites w
LEFT JOIN hosting_accounts ha ON ha.cpanel_username = 'otheracct'
LEFT JOIN hosting h ON h.name = 'Client Delta'
SET w.hosting_id = h.id,
    w.provider_id = 1,
    w.hosting_account_id = ha.id,
    w.notes = COALESCE(w.notes, 'Web service mapped to client/provider/account')
WHERE w.domain = 'other.com' AND w.service_type = 'hosting_web';

UPDATE websites w
LEFT JOIN hosting_accounts ha ON ha.cpanel_username = 'testacct'
LEFT JOIN hosting h ON h.name = 'Client Echo'
SET w.hosting_id = h.id,
    w.provider_id = 2,
    w.hosting_account_id = ha.id,
    w.notes = COALESCE(w.notes, 'Web service mapped to client/provider/account')
WHERE w.domain = 'test.com' AND w.service_type = 'hosting_web';

UPDATE websites w
LEFT JOIN hosting_accounts ha ON ha.cpanel_username = 'zenithacct'
LEFT JOIN hosting h ON h.name = 'Client Foxtrot'
SET w.hosting_id = h.id,
    w.provider_id = 3,
    w.hosting_account_id = ha.id,
    w.notes = COALESCE(w.notes, 'Web service mapped to client/provider/account')
WHERE w.domain = 'zenithhub.com' AND w.service_type = 'hosting_web';

INSERT INTO websites (hosting_id, provider_id, domain, service_type, status, expiry_date, notes)
SELECT 1, 4, 'demo.com', 'domain', 'active', '2026-12-01', 'Registrar service'
WHERE NOT EXISTS (SELECT 1 FROM websites WHERE domain='demo.com' AND service_type='domain');

INSERT INTO websites (hosting_id, provider_id, domain, service_type, status, expiry_date, notes)
SELECT 2, 5, 'example.com', 'domain', 'active', '2027-01-10', 'Registrar service'
WHERE NOT EXISTS (SELECT 1 FROM websites WHERE domain='example.com' AND service_type='domain');

INSERT INTO websites (hosting_id, provider_id, domain, service_type, status, expiry_date, notes)
SELECT 3, 4, 'expired.com', 'domain', 'warning', '2026-06-01', 'Registrar service'
WHERE NOT EXISTS (SELECT 1 FROM websites WHERE domain='expired.com' AND service_type='domain');

INSERT INTO websites (hosting_id, provider_id, domain, service_type, status, expiry_date, notes)
SELECT h.id, 5, 'other.com', 'domain', 'active', '2026-10-01', 'Registrar service'
FROM hosting h
WHERE h.name='Client Delta'
  AND NOT EXISTS (SELECT 1 FROM websites WHERE domain='other.com' AND service_type='domain');

INSERT INTO websites (hosting_id, provider_id, domain, service_type, status, expiry_date, notes)
SELECT h.id, 4, 'test.com', 'domain', 'active', '2026-09-20', 'Registrar service'
FROM hosting h
WHERE h.name='Client Echo'
  AND NOT EXISTS (SELECT 1 FROM websites WHERE domain='test.com' AND service_type='domain');

INSERT INTO websites (hosting_id, provider_id, domain, service_type, status, expiry_date, notes)
SELECT h.id, 5, 'zenithhub.com', 'domain', 'active', '2026-11-20', 'Registrar service'
FROM hosting h
WHERE h.name='Client Foxtrot'
  AND NOT EXISTS (SELECT 1 FROM websites WHERE domain='zenithhub.com' AND service_type='domain');

INSERT INTO websites (hosting_id, provider_id, domain, service_type, status, expiry_date, notes)
SELECT 1, 6, 'demo.com', 'hosting_mail', 'active', '2026-12-15', 'Mail service'
WHERE NOT EXISTS (SELECT 1 FROM websites WHERE domain='demo.com' AND service_type='hosting_mail');

INSERT INTO websites (hosting_id, provider_id, domain, service_type, status, expiry_date, notes)
SELECT 2, 7, 'example.com', 'hosting_mail', 'active', '2026-10-20', 'Mail service'
WHERE NOT EXISTS (SELECT 1 FROM websites WHERE domain='example.com' AND service_type='hosting_mail');

INSERT INTO websites (hosting_id, provider_id, domain, service_type, status, expiry_date, notes)
SELECT 3, 6, 'expired.com', 'hosting_mail', 'warning', '2026-05-25', 'Mail service'
WHERE NOT EXISTS (SELECT 1 FROM websites WHERE domain='expired.com' AND service_type='hosting_mail');

INSERT INTO websites (hosting_id, provider_id, domain, service_type, status, expiry_date, notes)
SELECT h.id, 7, 'other.com', 'hosting_mail', 'active', '2026-09-01', 'Mail service'
FROM hosting h
WHERE h.name='Client Delta'
  AND NOT EXISTS (SELECT 1 FROM websites WHERE domain='other.com' AND service_type='hosting_mail');

INSERT INTO websites (hosting_id, provider_id, domain, service_type, status, expiry_date, notes)
SELECT h.id, 6, 'test.com', 'hosting_mail', 'active', '2026-08-20', 'Mail service'
FROM hosting h
WHERE h.name='Client Echo'
  AND NOT EXISTS (SELECT 1 FROM websites WHERE domain='test.com' AND service_type='hosting_mail');

INSERT INTO websites (hosting_id, provider_id, domain, service_type, status, expiry_date, notes)
SELECT h.id, 7, 'zenithhub.com', 'hosting_mail', 'active', '2026-11-15', 'Mail service'
FROM hosting h
WHERE h.name='Client Foxtrot'
  AND NOT EXISTS (SELECT 1 FROM websites WHERE domain='zenithhub.com' AND service_type='hosting_mail');

INSERT INTO websites (hosting_id, provider_id, domain, service_type, status, expiry_date, notes)
SELECT 1, 4, 'clientonly-one.com', 'domain', 'active', '2026-12-10', 'Client assigned, no web/mail yet'
WHERE NOT EXISTS (SELECT 1 FROM websites WHERE domain='clientonly-one.com');

INSERT INTO websites (hosting_id, provider_id, domain, service_type, status, expiry_date, notes)
SELECT 2, 5, 'clientonly-two.com', 'domain', 'active', '2026-12-11', 'Client assigned, no web/mail yet'
WHERE NOT EXISTS (SELECT 1 FROM websites WHERE domain='clientonly-two.com');

INSERT INTO websites (hosting_id, provider_id, domain, service_type, status, expiry_date, notes)
SELECT 3, 4, 'clientonly-three.com', 'domain', 'active', '2026-12-12', 'Client assigned, no web/mail yet'
WHERE NOT EXISTS (SELECT 1 FROM websites WHERE domain='clientonly-three.com');

INSERT INTO websites (hosting_id, provider_id, domain, service_type, status, expiry_date, notes)
SELECT h.id, 5, 'clientonly-four.com', 'domain', 'active', '2026-12-13', 'Client assigned, no web/mail yet'
FROM hosting h
WHERE h.name='Client Delta'
  AND NOT EXISTS (SELECT 1 FROM websites WHERE domain='clientonly-four.com');

INSERT INTO websites (hosting_id, provider_id, domain, service_type, status, expiry_date, notes)
SELECT NULL, NULL, 'parked-one.com', 'domain', 'active', '2027-01-01', 'Unassigned domain'
WHERE NOT EXISTS (SELECT 1 FROM websites WHERE domain='parked-one.com');

INSERT INTO websites (hosting_id, provider_id, domain, service_type, status, expiry_date, notes)
SELECT NULL, NULL, 'parked-two.com', 'domain', 'active', '2027-01-02', 'Unassigned domain'
WHERE NOT EXISTS (SELECT 1 FROM websites WHERE domain='parked-two.com');

INSERT INTO websites (hosting_id, provider_id, domain, service_type, status, expiry_date, notes)
SELECT NULL, NULL, 'parked-three.com', 'domain', 'active', '2027-01-03', 'Unassigned domain'
WHERE NOT EXISTS (SELECT 1 FROM websites WHERE domain='parked-three.com');

INSERT INTO websites (hosting_id, provider_id, domain, service_type, status, expiry_date, notes)
SELECT NULL, NULL, 'parked-four.com', 'domain', 'active', '2027-01-04', 'Unassigned domain'
WHERE NOT EXISTS (SELECT 1 FROM websites WHERE domain='parked-four.com');

INSERT INTO websites (hosting_id, provider_id, domain, service_type, status, expiry_date, notes)
SELECT NULL, NULL, 'parked-five.com', 'domain', 'active', '2027-01-05', 'Unassigned domain'
WHERE NOT EXISTS (SELECT 1 FROM websites WHERE domain='parked-five.com');

COMMIT;
