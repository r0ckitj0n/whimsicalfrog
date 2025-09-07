# Simple Makefile helpers

.PHONY: smoke-apis
smoke-apis:
	bash scripts/dev/smoke-all-apis.sh
