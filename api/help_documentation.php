<?php
header('Content-Type: application/json');
// Determine correct path to functions.php
$functionsPath = file_exists('../includes/functions.php') ? '../includes/functions.php' : 'includes/functions.php';
require_once $functionsPath;

// Check admin authentication
AuthHelper::requireAdmin();

$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'list_docs':
            listDocumentationFiles();
            break;
        case 'get_doc':
            getDocumentationContent();
            break;
        case 'search_docs':
            searchDocumentation();
            break;
        case 'analyze_docs':
            analyzeAllDocumentation();
            break;
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error: ' . $e->getMessage()]);
}

function listDocumentationFiles()
{
    $docFiles = scanForMarkdownFiles('../');
    $analyzedDocs = [];

    foreach ($docFiles as $file) {
        $content = file_get_contents($file);
        $analysis = analyzeMarkdownContent($content, $file);
        $analyzedDocs[] = $analysis;
    }

    // Sort by category and then by title
    usort($analyzedDocs, function ($a, $b) {
        if ($a['category'] === $b['category']) {
            return strcmp($a['title'], $b['title']);
        }
        return strcmp($a['category'], $b['category']);
    });

    echo json_encode(['success' => true, 'documents' => $analyzedDocs]);
}

function getDocumentationContent()
{
    $filename = $_GET['file'] ?? '';
    if (empty($filename)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'File parameter required']);
        return;
    }

    $filepath = '../' . basename($filename);
    if (!file_exists($filepath) || !is_readable($filepath)) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'File not found']);
        return;
    }

    $content = file_get_contents($filepath);
    $analysis = analyzeMarkdownContent($content, $filepath);

    echo json_encode(['success' => true, 'document' => $analysis]);
}

function searchDocumentation()
{
    $query = trim($_GET['query'] ?? $_POST['query'] ?? '');
    if (empty($query)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Search query required']);
        return;
    }

    $docFiles = scanForMarkdownFiles('../');
    $results = [];

    foreach ($docFiles as $file) {
        $content = file_get_contents($file);
        $matches = searchInMarkdown($content, $query, $file);
        if (!empty($matches)) {
            $results = array_merge($results, $matches);
        }
    }

    // Sort by relevance (number of matches)
    usort($results, function ($a, $b) {
        return $b['relevance'] - $a['relevance'];
    });

    echo json_encode(['success' => true, 'results' => $results, 'total_matches' => count($results)]);
}

function analyzeAllDocumentation()
{
    $docFiles = scanForMarkdownFiles('../');
    $allAnalysis = [];
    $glossaryTerms = [];

    foreach ($docFiles as $file) {
        $content = file_get_contents($file);
        $analysis = analyzeMarkdownContent($content, $file);
        $allAnalysis[] = $analysis;

        // Collect glossary terms
        $glossaryTerms = array_merge($glossaryTerms, $analysis['glossary']);
    }

    // Create master glossary
    $masterGlossary = createMasterGlossary($glossaryTerms);

    echo json_encode([
        'success' => true,
        'analysis' => $allAnalysis,
        'master_glossary' => $masterGlossary,
        'statistics' => [
            'total_documents' => count($allAnalysis),
            'total_words' => array_sum(array_column($allAnalysis, 'word_count')),
            'total_sections' => array_sum(array_column($allAnalysis, 'section_count')),
            'total_glossary_terms' => count($masterGlossary)
        ]
    ]);
}

function scanForMarkdownFiles($directory)
{
    $markdownFiles = [];
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));

    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'md') {
            $markdownFiles[] = $file->getPathname();
        }
    }

    return $markdownFiles;
}

function analyzeMarkdownContent($content, $filepath)
{
    $filename = basename($filepath);
    $lines = explode("\n", $content);

    // Extract title (first H1 heading)
    $title = extractTitle($content);

    // Extract description (first paragraph after title)
    $description = extractDescription($content);

    // Extract version and dates
    $metadata = extractMetadata($content);

    // Count sections
    $sections = extractSections($content);

    // Extract key terms for glossary
    $glossary = extractGlossaryTerms($content);

    // Determine category based on filename and content
    $category = categorizeDocument($filename, $content);

    // Calculate metrics
    $wordCount = str_word_count(strip_tags($content));
    $sectionCount = count($sections);
    $lastModified = file_exists($filepath) ? filemtime($filepath) : time();

    return [
        'filename' => $filename,
        'title' => $title,
        'description' => $description,
        'category' => $category,
        'version' => $metadata['version'] ?? null,
        'last_updated' => $metadata['last_updated'] ?? date('Y-m-d', $lastModified),
        'word_count' => $wordCount,
        'section_count' => $sectionCount,
        'sections' => $sections,
        'glossary' => $glossary,
        'content' => $content,
        'file_size' => strlen($content),
        'complexity' => calculateComplexity($content)
    ];
}

function extractTitle($content)
{
    if (preg_match('/^#\s+(.+)$/m', $content, $matches)) {
        return trim($matches[1]);
    }
    return 'Untitled Document';
}

function extractDescription($content)
{
    $lines = explode("\n", $content);
    $foundTitle = false;
    $description = '';

    foreach ($lines as $line) {
        $line = trim($line);

        if (preg_match('/^#\s+/', $line)) {
            $foundTitle = true;
            continue;
        }

        if ($foundTitle && !empty($line) && !preg_match('/^[#*-]/', $line) && !preg_match('/^\*\*/', $line)) {
            $description = $line;
            break;
        }
    }

    return $description ?: 'No description available.';
}

function extractMetadata($content)
{
    $metadata = [];

    // Version pattern
    if (preg_match('/\*\*Version\*\*:?\s*([^\n]+)/i', $content, $matches)) {
        $metadata['version'] = trim($matches[1]);
    } elseif (preg_match('/Version[:\s]+([^\n]+)/i', $content, $matches)) {
        $metadata['version'] = trim($matches[1]);
    }

    // Last updated pattern
    if (preg_match('/\*\*Last Updated\*\*:?\s*([^\n]+)/i', $content, $matches)) {
        $metadata['last_updated'] = trim($matches[1]);
    } elseif (preg_match('/Last Updated[:\s]+([^\n]+)/i', $content, $matches)) {
        $metadata['last_updated'] = trim($matches[1]);
    }

    return $metadata;
}

function extractSections($content)
{
    $sections = [];
    if (preg_match_all('/^(#{1,6})\s+(.+)$/m', $content, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $match) {
            $level = strlen($match[1]);
            $title = trim($match[2]);
            $sections[] = [
                'level' => $level,
                'title' => $title,
                'anchor' => strtolower(preg_replace('/[^a-z0-9]+/', '-', $title))
            ];
        }
    }
    return $sections;
}

function extractGlossaryTerms($content)
{
    $terms = [];

    // Extract technical terms (capitalized words, acronyms, code blocks)
    $patterns = [
        '/\*\*([A-Z][a-zA-Z\s]+)\*\*/',  // Bold terms
        '/`([A-Za-z_][A-Za-z0-9_\.\/]+)`/',  // Code terms
        '/\b([A-Z]{2,})\b/',  // Acronyms
        '/\*([A-Z][a-zA-Z\s]+)\*/',  // Italic terms
    ];

    foreach ($patterns as $pattern) {
        if (preg_match_all($pattern, $content, $matches)) {
            foreach ($matches[1] as $term) {
                $cleanTerm = trim($term);
                if (strlen($cleanTerm) > 2 && strlen($cleanTerm) < 50) {
                    $terms[] = $cleanTerm;
                }
            }
        }
    }

    // Remove duplicates and common words
    $terms = array_unique($terms);
    $commonWords = ['The', 'And', 'For', 'With', 'This', 'That', 'From', 'System', 'API', 'PHP'];
    $terms = array_diff($terms, $commonWords);

    return array_values($terms);
}

function categorizeDocument($filename, $content)
{
    $filename = strtolower($filename);

    // Category mapping based on filename patterns
    $categories = [
        'system' => ['system', 'reference', 'architecture'],
        'authentication' => ['auth', 'login', 'security'],
        'customization' => ['customization', 'guide', 'theme'],
        'development' => ['implementation', 'technical', 'code'],
        'documentation' => ['documentation', 'readme', 'help'],
        'features' => ['feature', 'enhancement', 'upgrade'],
        'maintenance' => ['cleanup', 'migration', 'maintenance'],
        'design' => ['css', 'style', 'design', 'ui'],
        'api' => ['api', 'endpoint', 'integration']
    ];

    foreach ($categories as $category => $keywords) {
        foreach ($keywords as $keyword) {
            if (strpos($filename, $keyword) !== false) {
                return ucfirst($category);
            }
        }
    }

    // Analyze content for category hints
    $contentLower = strtolower($content);
    if (strpos($contentLower, 'authentication') !== false || strpos($contentLower, 'login') !== false) {
        return 'Authentication';
    } elseif (strpos($contentLower, 'customization') !== false || strpos($contentLower, 'branding') !== false) {
        return 'Customization';
    } elseif (strpos($contentLower, 'css') !== false || strpos($contentLower, 'style') !== false) {
        return 'Design';
    } elseif (strpos($contentLower, 'api') !== false || strpos($contentLower, 'endpoint') !== false) {
        return 'API';
    }

    return 'General';
}

function calculateComplexity($content)
{
    $complexity = 0;

    // Code blocks increase complexity
    $complexity += substr_count($content, '```') * 2;

    // Technical terms increase complexity
    $complexity += substr_count($content, '`') * 0.5;

    // Headings indicate structure
    $complexity += substr_count($content, '#') * 0.3;

    // Length factor
    $wordCount = str_word_count($content);
    $complexity += $wordCount / 1000;

    return round($complexity, 1);
}

function searchInMarkdown($content, $query, $filepath)
{
    $filename = basename($filepath);
    $lines = explode("\n", $content);
    $matches = [];
    $queryLower = strtolower($query);

    foreach ($lines as $lineNumber => $line) {
        $lineLower = strtolower($line);
        if (strpos($lineLower, $queryLower) !== false) {
            $matches[] = [
                'filename' => $filename,
                'line_number' => $lineNumber + 1,
                'line_content' => trim($line),
                'context' => getContextLines($lines, $lineNumber, 2),
                'relevance' => calculateRelevance($line, $query)
            ];
        }
    }

    return $matches;
}

function getContextLines($lines, $centerLine, $contextSize)
{
    $start = max(0, $centerLine - $contextSize);
    $end = min(count($lines) - 1, $centerLine + $contextSize);

    $context = [];
    for ($i = $start; $i <= $end; $i++) {
        $context[] = [
            'line_number' => $i + 1,
            'content' => trim($lines[$i]),
            'is_match' => ($i === $centerLine)
        ];
    }

    return $context;
}

function calculateRelevance($line, $query)
{
    $relevance = 1;

    // Exact match gets higher score
    if (strpos(strtolower($line), strtolower($query)) !== false) {
        $relevance += 2;
    }

    // Header lines get higher score
    if (preg_match('/^#+\s/', $line)) {
        $relevance += 3;
    }

    // Bold or code terms get higher score
    if (preg_match('/\*\*.*' . preg_quote($query, '/') . '.*\*\*/i', $line) ||
        preg_match('/`.*' . preg_quote($query, '/') . '.*`/i', $line)) {
        $relevance += 2;
    }

    return $relevance;
}

function createMasterGlossary($allTerms)
{
    $termCounts = array_count_values($allTerms);
    $masterGlossary = [];

    foreach ($termCounts as $term => $count) {
        if ($count >= 2) { // Only include terms that appear in multiple documents
            $masterGlossary[] = [
                'term' => $term,
                'frequency' => $count,
                'definition' => generateDefinition($term)
            ];
        }
    }

    // Sort by frequency
    usort($masterGlossary, function ($a, $b) {
        return $b['frequency'] - $a['frequency'];
    });

    return $masterGlossary;
}

function generateDefinition($term)
{
    // Basic definition generation based on common patterns
    $definitions = [
        'API' => 'Application Programming Interface - a set of protocols and tools for building software applications',
        'CSS' => 'Cascading Style Sheets - used for styling web pages',
        'PHP' => 'PHP: Hypertext Preprocessor - server-side scripting language',
        'SQL' => 'Structured Query Language - used for managing databases',
        'JSON' => 'JavaScript Object Notation - lightweight data interchange format',
        'CRUD' => 'Create, Read, Update, Delete - basic database operations',
        'UI' => 'User Interface - the visual elements users interact with',
        'UX' => 'User Experience - overall experience when using a product',
        'Admin' => 'Administrator - user with full system access and permissions',
        'Database' => 'Organized collection of structured information or data',
        'Authentication' => 'Process of verifying user identity',
        'Authorization' => 'Process of determining user permissions',
        'Session' => 'Temporary data storage for user interactions',
        'Modal' => 'Dialog box or popup window that requires user interaction',
        'Component' => 'Reusable piece of code or UI element',
        'Endpoint' => 'URL where API calls are made to access specific functionality'
    ];

    return $definitions[$term] ?? "Technical term related to $term functionality in the system";
}
?> 