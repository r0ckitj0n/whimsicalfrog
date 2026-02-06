<?php

declare(strict_types=1);

/**
 * Handles item image path resolution logic
 */
class ItemImageResolver
{
    public static function resolve(string $sku): string
    {
        $sku = trim($sku);
        if ($sku === '') return '/images/items/placeholder.webp';
        
        $projectRoot = dirname(__DIR__, 2);
        $dir = $projectRoot . '/images/items';
        $bases = self::getCandidateBases($sku);
        
        foreach ($bases as $b) {
            $cands = [
                $b . '.webp', $b . '.png',
                $b . 'A.webp', $b . 'A.png', $b . 'a.webp', $b . 'a.png',
                $b . 'B.webp', $b . 'B.png', $b . 'b.webp', $b . 'b.png',
            ];
            foreach ($cands as $fn) {
                $abs = $dir . '/' . $fn;
                if (is_file($abs)) {
                    return '/images/items/' . $fn;
                }
            }
        }
        return '/images/items/placeholder.webp';
    }

    private static function getCandidateBases(string $sku): array
    {
        $bases = [];
        $push = static function(string $b) use (&$bases): void { 
            if ($b !== '' && !in_array($b, $bases, true)) $bases[] = $b; 
        };

        $push($sku);
        $push(strtoupper($sku));
        $push(strtolower($sku));
        $push(str_replace('-', '_', $sku));
        $push(str_replace('_', '-', $sku));
        $push(str_replace(['-', '_'], '', $sku));
        $push(str_replace(['-', '_'], '', strtolower($sku)));

        self::pushTrimmedVariants($sku, $push);
        self::pushTrimmedVariants(strtolower($sku), $push);

        return $bases;
    }

    private static function pushTrimmedVariants(string $s, callable $push): void
    {
        $cur = $s;
        for ($i = 0; $i < 4; $i++) {
            $m = strrpos($cur, '-');
            $n = strrpos($cur, '_');
            $idx = max($m === false ? -1 : $m, $n === false ? -1 : $n);
            if ($idx <= 0) break;
            $cur = substr($cur, 0, $idx);
            $push($cur);
            $push(strtolower($cur));
            $push(str_replace('-', '_', $cur));
            $push(str_replace('_', '-', $cur));
        }
    }
}
