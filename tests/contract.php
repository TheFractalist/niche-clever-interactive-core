<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../src/autoload.php';

use NicheCleverInteractive\Core\Api\V1\Contracts\ProfileResolverV1;
use NicheCleverInteractive\Core\Api\V1\Support\CompletedTurnEligibilityV1;
use NicheCleverInteractive\Core\Api\V1\Support\ExtensionRegistryV1;
use NicheCleverInteractive\Core\Api\V1\Value\ApprovedEvidenceV1;
use NicheCleverInteractive\Core\Api\V1\Value\CacheKeyV1;
use NicheCleverInteractive\Core\Api\V1\Value\ChronologyFactV1;
use NicheCleverInteractive\Core\Api\V1\Value\CompletedTurnV1;
use NicheCleverInteractive\Core\Api\V1\Value\ProfileIdV1;
use NicheCleverInteractive\Core\Api\V1\Value\ResolvedProfileV1;
use NicheCleverInteractive\Core\Api\V1\Value\WorkspaceContextV1;
use NicheCleverInteractive\Core\Api\V1\Value\WorkspaceScreenV1;

$checks = 0;

$assert = static function (bool $condition, string $message) use (&$checks): void {
    ++$checks;
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$throws = static function (callable $callback, string $exception) use (&$checks): void {
    ++$checks;
    try {
        $callback();
    } catch (Throwable $caught) {
        if ($caught instanceof $exception) {
            return;
        }

        throw new RuntimeException(sprintf('Expected %s, received %s.', $exception, get_class($caught)));
    }

    throw new RuntimeException(sprintf('Expected %s to be thrown.', $exception));
};

$profile = new ProfileIdV1('profile_1');
$assert($profile->value() === 'profile_1', 'Profile ID preserves an explicit valid ID.');
$throws(static fn (): ProfileIdV1 => new ProfileIdV1(''), InvalidArgumentException::class);
$throws(static fn (): ProfileIdV1 => new ProfileIdV1('DefaultProfile'), InvalidArgumentException::class);

new ChronologyFactV1('2026-10-12', str_repeat('L', 160), str_repeat('S', 500), 'source-r1');
$throws(
    static fn (): ChronologyFactV1 => new ChronologyFactV1('2026-02-30', 'Label', 'Summary', 'source-r1'),
    InvalidArgumentException::class
);
$throws(
    static fn (): ChronologyFactV1 => new ChronologyFactV1('2026-10-12', str_repeat('L', 161), 'Summary', 'source-r1'),
    InvalidArgumentException::class
);

$makeCacheKey = static function (string $changed = '') use ($profile): CacheKeyV1 {
    $parts = [
        'policy' => 'policy-r1',
        'corpus' => 'corpus-r1',
        'approval' => 'approval-r1',
        'access' => 'access-r1',
        'preset' => 'balanced',
    ];
    if ($changed !== '') {
        $parts[$changed] .= '-changed';
    }

    return new CacheKeyV1(
        hash('sha256', 'question'),
        $profile,
        $parts['policy'],
        $parts['corpus'],
        $parts['approval'],
        $parts['access'],
        $parts['preset']
    );
};
$baselineKey = $makeCacheKey();
foreach (['policy', 'corpus', 'approval', 'access', 'preset'] as $field) {
    $assert($baselineKey->identity() !== $makeCacheKey($field)->identity(), sprintf('Cache identity includes %s.', $field));
}

new ApprovedEvidenceV1('Approved evidence', 'https://example.test/evidence', 'approval-r1');
$throws(
    static fn (): ApprovedEvidenceV1 => new ApprovedEvidenceV1('Evidence', 'http://example.test', 'approval-r1'),
    InvalidArgumentException::class
);
$throws(
    static fn (): ApprovedEvidenceV1 => new ApprovedEvidenceV1('Evidence', 'https://user:pass@example.test', 'approval-r1'),
    InvalidArgumentException::class
);

$resolver = new class implements ProfileResolverV1 {
    public function resolve(ProfileIdV1 $profileId): ?ResolvedProfileV1
    {
        return null;
    }
};
$registry = new ExtensionRegistryV1();
$assert(
    $registry->knownSeams() === [
        'profile_resolution',
        'supplementary_chronology',
        'eligible_answer_cache',
        'evidence_metadata',
        'completed_public_turn',
        'workspace_extension',
    ],
    'The registry exposes exactly the six provisional Core seams.'
);
$registry->register('profile_resolution', $resolver);
$assert($registry->has('profile_resolution'), 'The explicitly registered profile seam is available.');
$throws(static fn (): mixed => $registry->register('profile_resolution', $resolver), LogicException::class);
$throws(static fn (): mixed => $registry->register('unknown_seam', $resolver), InvalidArgumentException::class);
$throws(static fn (): mixed => $registry->register('eligible_answer_cache', $resolver), InvalidArgumentException::class);

foreach (['cache', 'provider'] as $outcome) {
    $assert(CompletedTurnEligibilityV1::shouldRecord($outcome), sprintf('%s turns are eligible for recorder invocation.', $outcome));
}
foreach (['deterministic', 'refusal', 'diagnostic', 'validation_failure', 'delivery_failure'] as $outcome) {
    $assert(!CompletedTurnEligibilityV1::shouldRecord($outcome), sprintf('%s turns are not eligible for recorder invocation.', $outcome));
}

new CompletedTurnV1(
    'conversation-1',
    $profile,
    new DateTimeImmutable('2026-10-12T00:00:00+00:00'),
    'Question',
    'Answer',
    '0.0.0-foundation',
    null,
    'corpus-r1',
    hash('sha256', 'answer'),
    hash('sha256', 'evidence'),
    'provider'
);
$throws(
    static fn (): CompletedTurnV1 => new CompletedTurnV1(
        'conversation-1',
        $profile,
        new DateTimeImmutable(),
        'Question',
        'Answer',
        '0.0.0-foundation',
        null,
        'corpus-r1',
        hash('sha256', 'answer'),
        hash('sha256', 'evidence'),
        'refusal'
    ),
    InvalidArgumentException::class
);

new WorkspaceScreenV1('archive');
$throws(static fn (): WorkspaceScreenV1 => new WorkspaceScreenV1('settings'), InvalidArgumentException::class);
new WorkspaceContextV1(true, 'core-issued-nonce');
$throws(static fn (): WorkspaceContextV1 => new WorkspaceContextV1(false, 'core-issued-nonce'), InvalidArgumentException::class);

$root = dirname(__DIR__);
$plugin = file_get_contents($root . '/niche-clever-interactive-core.php');
$assert($plugin !== false && str_contains($plugin, 'NCLI_CORE_API_MAJOR = 1'), 'The bootstrap declares API major 1.');

$source = '';
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/src'));
foreach ($iterator as $file) {
    if ($file->isFile() && $file->getExtension() === 'php') {
        $content = file_get_contents($file->getPathname());
        $source .= $content === false ? '' : $content;
    }
}
$assert(substr_count($source, 'register_rest_route') === 2, 'C3 must contain one guarded registration for Core’s sole REST route.');
$assert(str_contains($source, "register_rest_route('ncli/v1', '/chat'"), 'C3 must register only the Core chat route namespace.');
foreach (['OpenAIProvider', 'Responses API'] as $forbidden) {
    $assert(!str_contains($source, $forbidden), sprintf('Core source does not implement live %s in mock-only C3.', $forbidden));
}

echo sprintf("PASS: %d pure contract checks\n", $checks);
