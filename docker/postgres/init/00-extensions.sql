-- Runs once on first cluster init, against the POSTGRES_DB (eventos) as superuser.
-- Extensions required by the EventOS schema (architecture §11).
CREATE EXTENSION IF NOT EXISTS citext;     -- case-insensitive emails
CREATE EXTENSION IF NOT EXISTS pg_trgm;    -- trigram search / fuzzy lookup
CREATE EXTENSION IF NOT EXISTS pgcrypto;   -- gen_random_uuid(), digests
