-- One-off data cleanup (2026-10-04), approved by the site owner: removes test and
-- sample data that was created on production while building/QA-ing the site.
-- Every row is matched by id AND its name, so on any other database (or if an id
-- was reused) nothing unrelated is touched. Re-running is harmless.
--
-- Soft-delete (Admin → Bin, restorable for 15 days): corridor, team members, testimonials.
-- Hard delete (these tables have no Bin): sample leads, sample saved searches —
-- their follow-ups go with them (ON DELETE CASCADE). The real lead "yuvika salar"
-- (enquiry 15) and its AI call are NOT touched.
-- The 6 test properties are moved to the Bin by scripts/load-projects-2026-10.php.
-- Backup taken before this ran: D:\Kutumb\db-backups\prod-pre-release-20261004.sql
SET NAMES utf8mb4;

-- Junk corridor (shows in the site footer).
UPDATE corridors SET deleted_at = NOW() WHERE id = 13 AND name = 'Fffzs' AND deleted_at IS NULL;

-- Test team profiles (4 duplicates created from the admin form, and 2 QA advisors).
UPDATE team_members SET deleted_at = NOW(), active = 0
 WHERE deleted_at IS NULL AND (
       (id IN (3, 4, 5, 6) AND name = 'Nishant' AND role_title = 'Product desiner')
    OR (id = 7 AND name = 'Test Advisor One')
    OR (id = 8 AND name = 'Test Advisor Two'));

-- Sample testimonials with made-up names (seeded 2026-08-26).
UPDATE testimonials SET deleted_at = NOW()
 WHERE deleted_at IS NULL AND (
       (id = 8 AND client_name = 'Karan Mehta')
    OR (id = 9 AND client_name = 'Simran Kaur')
    OR (id = 10 AND client_name = 'Ajay Krishnan'));

-- Test leads ("test", "nishant") and sample leads with @example.com addresses.
DELETE FROM enquiries
 WHERE (id = 2 AND name = 'test')
    OR (id = 3 AND name = 'nishant')
    OR (id = 7 AND name = 'Kavita Joshi')
    OR (id = 8 AND name = 'Manoj Tiwari')
    OR (id = 9 AND name = 'Sanjana Rao')
    OR (id = 10 AND name = 'Arvind Bhatia')
    OR (id = 11 AND name = 'Neha Chawla')
    OR (id = 12 AND name = 'Ramesh Iyer')
    OR (id = 13 AND name = 'Pooja Agarwal');

-- Sample saved searches (@example.com).
DELETE FROM saved_searches
 WHERE (id = 3 AND name = 'Priya Sharma')
    OR (id = 4 AND name = 'Rohit Malhotra')
    OR (id = 5 AND name = 'Anjali & Vikram Kapoor')
    OR (id = 6 AND name = 'Suresh Nair')
    OR (id = 7 AND name = 'Deepa Reddy');

-- What's left, for the release log.
SELECT 'enquiries' AS t, COUNT(*) AS n FROM enquiries
UNION ALL SELECT 'saved_searches', COUNT(*) FROM saved_searches
UNION ALL SELECT 'follow_ups', COUNT(*) FROM follow_ups
UNION ALL SELECT 'team_members (active)', COUNT(*) FROM team_members WHERE deleted_at IS NULL
UNION ALL SELECT 'testimonials (live)', COUNT(*) FROM testimonials WHERE deleted_at IS NULL
UNION ALL SELECT 'corridors (live)', COUNT(*) FROM corridors WHERE deleted_at IS NULL;
