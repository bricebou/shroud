<?php

declare(strict_types=1);

namespace Shroud\Tests\Unit\Schema\Glossary;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shroud\Schema\Glossary\English;
use Shroud\Schema\Glossary\French;
use Shroud\Schema\Glossary\GlossaryInterface;
use Shroud\Schema\TableSchema;

final class GlossariesTest extends TestCase
{
    /**
     * @return iterable<string, array{GlossaryInterface, string, ?string}>
     */
    public static function columns(): iterable
    {
        yield 'english password' => [new English(), 'password_hash', 'new Clear()'];
        yield 'english email' => [new English(), 'contact_email', 'new Email()'];
        yield 'english first name' => [new English(), 'firstName', 'new FirstName()'];
        yield 'english birth' => [new English(), 'date_of_birth', 'new ShiftedDate()'];
        yield 'english phone' => [new English(), 'mobile_phone', 'new Phone()'];
        yield 'french courriel' => [new French(), 'courriel', 'new Email()'];
        yield 'french prenom' => [new French(), 'prenom', 'new FirstName()'];
        yield 'french ville' => [new French(), 'ville', 'new City()'];
        yield 'french naissance' => [new French(), 'date_naissance', 'new ShiftedDate()'];
        yield 'french billing address' => [new French(), 'facturation_adresse', 'new Street()'];
        yield 'french billing postal code' => [new French(), 'facturation_adresse_cp', 'new PostalCode()'];
        yield 'french sender email, not a street' => [new French(), 'adresse_envoi_email', null];
        yield 'french website address' => [new French(), 'adresse_site', 'new Url(keepHost: false)'];
        yield 'french vat number' => [new French(), 'numero_tva', 'new ShapedText()'];
        yield 'english sender email' => [new English(), 'adresse_envoi_email', 'new Email()'];
        yield 'english confirm, not a firm' => [new English(), 'confirm', null];
        yield 'english hashtags, not a hash' => [new English(), 'hashtags', null];
        yield 'english excluded mobiles, not a phone' => [new English(), 'mobile_exclus', null];
        yield 'english rank before link' => [new English(), 'rank_link', 'new Keep()'];
        yield 'english ip list' => [new English(), 'access_ips', 'new WithoutIps()'];
        yield 'english ip address' => [new English(), 'remote_addr', 'new WithoutIps()'];
        yield 'english vendor token' => [new English(), 'vosfacturesfr_token', 'new Clear()'];
        yield 'english blob to review' => [new English(), 'payment_data', 'new Keep()'];
    }

    #[DataProvider('columns')]
    public function testAColumnNameIsRecognisedOrLeftAlone(GlossaryInterface $glossary, string $column, ?string $strategy): void
    {
        $table = new TableSchema('t', [], [
            $column => 'text',
        ]);
        $found = null;
        foreach ($glossary->rules() as $rule) {
            if ($rule->matches($column, $table)) {
                $found = $rule->strategy;
                break;
            }
        }

        self::assertSame($strategy, $found);
    }

    public function testEveryRuleIsAValidPattern(): void
    {
        foreach ([new English(), new French()] as $glossary) {
            foreach ($glossary->rules() as $rule) {
                self::assertNotFalse(@preg_match($rule->pattern, 'x'), $rule->pattern);
                self::assertStringStartsWith('new ', $rule->strategy);
            }
        }
    }

    public function testAnUnknownNameMatchesNothing(): void
    {
        foreach ([new English(), new French()] as $glossary) {
            foreach ($glossary->rules() as $rule) {
                self::assertFalse($rule->matches('xyzzy', new TableSchema('t', [], [
                    'xyzzy' => 'text',
                ])));
            }
        }
    }
}
