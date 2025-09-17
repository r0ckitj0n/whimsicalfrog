-- Drop legacy database-driven CSS tables
-- Safe to re-run; guarded with IF EXISTS

DROP TABLE IF EXISTS `css_variables`;
DROP TABLE IF EXISTS `global_css_rules`;
