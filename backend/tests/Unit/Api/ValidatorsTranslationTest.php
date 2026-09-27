<?php

declare(strict_types=1);

namespace App\Tests\Unit\Api;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Yaml\Yaml;

/**
 * Every validation message written in PHP has its Spanish line in translations/validators.es.yaml; one without
 * reaches a Spanish-speaking consultant in English, under the field they got wrong.
 */
final class ValidatorsTranslationTest extends TestCase
{
    /** Where a violation's message is written: the API exception, the validator, a constraint, an error array. */
    private const SOURCES = '/(ApiValidationException::single\(|buildViolation\(|(?:->|::)fail\(\s*[^,]+,\s*|\bmessage:\s*|\'message\'\s*=>\s*)/';

    public function testEveryValidationMessageHasASpanishTranslation(): void
    {
        $translations = Yaml::parseFile(\dirname(__DIR__, 3).'/translations/validators.es.yaml');

        $missing = [];
        foreach ((new Finder())->files()->in(\dirname(__DIR__, 3).'/src')->name('*.php') as $file) {
            $source = $file->getContents();
            preg_match_all(self::SOURCES, $source, $calls, \PREG_OFFSET_CAPTURE);
            foreach ($calls[0] as [$call, $offset]) {
                $end = strpos($source, ";\n", $offset) ?: \strlen($source);
                $statement = substr($source, $offset + \strlen($call), min($end - $offset, 400));
                preg_match_all("/'((?:[^'\\\\]|\\\\.)*)'/", $statement, $literals);
                foreach ($literals[1] as $literal) {
                    $message = str_replace("\\'", "'", $literal);
                    if (1 === preg_match('/^[A-Z"]/', $message) && str_contains($message, ' ') && 'Validation failed.' !== $message
                        && !isset($translations[$message])) {
                        $missing[$message] = $file->getRelativePathname();
                    }
                }
            }
        }

        self::assertSame([], $missing, 'Add these to translations/validators.es.yaml');
    }
}
