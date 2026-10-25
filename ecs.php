<?php

declare(strict_types=1);

use PhpCsFixer\Fixer\PhpTag\BlankLineAfterOpeningTagFixer;
use Symplify\EasyCodingStandard\Config\ECSConfig;

return ECSConfig::configure()
    ->withPreparedSets(psr12: true)
    ->withSkip([
        BlankLineAfterOpeningTagFixer::class,
    ]);
