#!/bin/bash
# ════════════════════════════════════════════════════════════════════
# Dual-role model for Row-Level Security (architecture §4.1 / §11).
#
#   eventos_migrator  → owns tables, BYPASSRLS. Runs migrations + seeders.
#                       (RLS is FORCEd, which applies to owners too, so the
#                        migrator needs BYPASSRLS to administer all tenants.)
#   eventos_app       → normal login role, NO bypass. The app connects as this
#                       at runtime, so every query is constrained by the
#                       tenant_isolation policy keyed on app.current_organization.
#
# NOTE: superusers ALWAYS bypass RLS, so the app role must NOT be a superuser.
# ════════════════════════════════════════════════════════════════════
set -e

psql -v ON_ERROR_STOP=1 --username "$POSTGRES_USER" --dbname "$POSTGRES_DB" <<-EOSQL
  DO \$\$
  BEGIN
    IF NOT EXISTS (SELECT FROM pg_roles WHERE rolname = 'eventos_migrator') THEN
      CREATE ROLE eventos_migrator LOGIN PASSWORD '${EVENTOS_MIGRATOR_PASSWORD}' BYPASSRLS;
    END IF;
    IF NOT EXISTS (SELECT FROM pg_roles WHERE rolname = 'eventos_app') THEN
      CREATE ROLE eventos_app LOGIN PASSWORD '${EVENTOS_APP_PASSWORD}';
    END IF;
  END
  \$\$;

  -- The migrator owns the database + schema so it can create/alter everything.
  ALTER DATABASE ${POSTGRES_DB} OWNER TO eventos_migrator;
  GRANT CONNECT ON DATABASE ${POSTGRES_DB} TO eventos_migrator, eventos_app;
  GRANT ALL    ON SCHEMA public TO eventos_migrator;
  GRANT USAGE  ON SCHEMA public TO eventos_app;

  -- App role: DML on everything the migrator has already created...
  GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES    IN SCHEMA public TO eventos_app;
  GRANT USAGE, SELECT                  ON ALL SEQUENCES  IN SCHEMA public TO eventos_app;

  -- ...and on everything the migrator creates in the FUTURE (every migration).
  ALTER DEFAULT PRIVILEGES FOR ROLE eventos_migrator IN SCHEMA public
    GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO eventos_app;
  ALTER DEFAULT PRIVILEGES FOR ROLE eventos_migrator IN SCHEMA public
    GRANT USAGE, SELECT ON SEQUENCES TO eventos_app;
EOSQL

echo "[init] eventos_migrator + eventos_app roles ready."
