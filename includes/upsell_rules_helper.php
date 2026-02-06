<?php

declare(strict_types=1);

/**
 * Upsell Rules Helper (Conductor)
 * Handles cart upsells by delegating to specialized modular components.
 */

require_once __DIR__ . '/../api/config.php';
require_once __DIR__ . '/stock_manager.php';
require_once __DIR__ . '/upsell/ItemImageResolver.php';
require_once __DIR__ . '/upsell/RuleGenerator.php';
require_once __DIR__ . '/upsell/UpsellOrchestrator.php';

function wf_resolve_item_image_path(string $sku): string
{
    return ItemImageResolver::resolve($sku);
}

function wf_generate_cart_upsell_rules(): array
{
    return RuleGenerator::generate();
}

function wf_resolve_cart_upsells(array $skus, int $limit = 4): array
{
    return UpsellOrchestrator::resolve($skus, $limit);
}
