# Simple Makefile helpers

.PHONY: smoke-apis
smoke-apis:
	bash scripts/dev/smoke_all_apis.sh
