.PHONY: prettier

.DEFAULT_GOAL := prettier

## Prettier files
prettier:
	pnpm prettier --write --no-error-on-unmatched-pattern '**/*.{yaml,php,.md}'
