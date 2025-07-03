<?php 

declare(strict_types=1);

use Symplify\EasyCodingStandard\Config\ECSConfig;
use Symplify\EasyCodingStandard\ValueObject\Set\SetList;

return static function (ECSConfig $ecsConfig): void {
    $ecsConfig->sets([SetList::PSR_12]);

    $ecsConfig->skip([
        PhpCsFixer\Fixer\PhpTag\BlankLineAfterOpeningTagFixer::class => null,
    ]);

};