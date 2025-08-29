<?php
// Script to generate custom CSS utilities based on used Tailwind classes

$inputFile = __DIR__ . '/tailwind-classes.txt';
$outputFile = dirname(__DIR__) . '/css/generated-tailwind-utilities.css';

if (!file_exists($inputFile)) {
    echo "Input file not found: $inputFile\n";
    exit(1);
}

$classes = file($inputFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
$definitions = [];

foreach ($classes as $orig) {
    $class = trim($orig, "'\"\n");
    // Spacing utilities (margin/padding)
    if (preg_match('/^(m|p)([trblxy]?)-([0-9]+)/', $class, $m)) {
        list(, $mp, $axis, $size) = $m;
        $propName = $mp === 'm' ? 'margin' : 'padding';
        $varName = "--space-$size";
        switch ($axis) {
            case '':
                $definitions[] = ".{$class} { {$propName}: var({$varName}); }";
                break;
            case 'x':
                $definitions[] = ".{$class} { {$propName}-left: var({$varName}); {$propName}-right: var({$varName}); }";
                break;
            case 'y':
                $definitions[] = ".{$class} { {$propName}-top: var({$varName}); {$propName}-bottom: var({$varName}); }";
                break;
            case 't':
                $definitions[] = ".{$class} { {$propName}-top: var({$varName}); }";
                break;
            case 'r':
                $definitions[] = ".{$class} { {$propName}-right: var({$varName}); }";
                break;
            case 'b':
                $definitions[] = ".{$class} { {$propName}-bottom: var({$varName}); }";
                break;
            case 'l':
                $definitions[] = ".{$class} { {$propName}-left: var({$varName}); }";
                break;
        }
        continue;
    }
    // Layout utilities
    switch ($class) {
        case 'mx-auto':
            $definitions[] = ".mx-auto { margin-left: auto; margin-right: auto; }";
            break;
        case 'max-w-full':
            $definitions[] = ".max-w-full { max-width: 100%; }";
            break;
        case 'flex-grow':
            $definitions[] = ".flex-grow { flex-grow: 1; }";
            break;
        case 'container':
            $definitions[] = ".container { width: 100%; max-width: var(--breakpoint-lg); margin-left: auto; margin-right: auto; }";
            break;
        default:
            // Add more pattern-based or manual mappings as needed
            break;
    }
}
// Remove duplicates
$definitions = array_unique($definitions);
// Write output
file_put_contents($outputFile, "/* Auto-generated Tailwind utilities */\n" . implode("\n", $definitions) . "\n");
echo "Generated utilities: " . count($definitions) . " definitions to $outputFile\n";
