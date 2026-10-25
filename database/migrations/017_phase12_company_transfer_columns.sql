-- Phase 12 clean-install schema reconciliation for company transfer settings.
-- All earlier migrations (001..016) lack these columns although CompanyService needs them.
ALTER TABLE companies
  ADD COLUMN company_role ENUM('agent','master') NOT NULL DEFAULT 'agent' AFTER name,
  ADD COLUMN transfer_percent DECIMAL(6,3) NOT NULL DEFAULT 0.000 AFTER company_role;
