<?php

declare(strict_types=1);

namespace Shroud\Tests\Unit\Strategy\Pseudonym;

use PHPUnit\Framework\Attributes\DataProvider;
use Shroud\Exception\InvalidValueException;
use Shroud\Exception\MissingReferenceException;
use Shroud\Generator;
use Shroud\Identity\Reference;
use Shroud\Strategy\Pseudonym\City;
use Shroud\Strategy\Pseudonym\Email;
use Shroud\Strategy\Pseudonym\Login;
use Shroud\Strategy\Pseudonym\Phone;
use Shroud\Strategy\Pseudonym\PostalCode;
use Shroud\Strategy\Pseudonym\Street;
use Shroud\Strategy\StrategyInterface;
use Shroud\Tests\Unit\Strategy\StrategyTestCase;

final class PseudonymStrategiesTest extends StrategyTestCase
{
    /**
     * @return iterable<string, array{StrategyInterface}>
     */
    public static function strategies(): iterable
    {
        yield 'email' => [new Email()];
        yield 'login' => [new Login()];
        yield 'street' => [new Street()];
        yield 'postal code' => [new PostalCode()];
        yield 'city' => [new City()];
        yield 'phone' => [new Phone()];
    }

    #[DataProvider('strategies')]
    public function testNullAndTheEmptyStringPassThrough(StrategyInterface $strategy): void
    {
        self::assertNull(self::apply($strategy, value: null, context: self::context(reference: null)));
        self::assertSame('', self::apply($strategy, '', self::context(reference: null)));
    }

    #[DataProvider('strategies')]
    public function testAValueThatIsNotAStringIsRefused(StrategyInterface $strategy): void
    {
        $this->expectException(InvalidValueException::class);

        self::apply($strategy, 42);
    }

    #[DataProvider('strategies')]
    public function testARowWithoutReferenceIsRefused(StrategyInterface $strategy): void
    {
        $this->expectException(MissingReferenceException::class);

        self::apply($strategy, 'value', self::context(reference: null));
    }

    public function testTheFieldsComeFromThePseudonymOfTheReferencedObject(): void
    {
        $generator = new Generator(self::SALT);
        $pseudonym = $generator->pseudonymFor(new Reference('person', 42));
        $context = self::context($generator);

        self::assertSame($pseudonym->email, self::apply(new Email(), 'zoe.kowalczyk@example-real.test', $context));
        self::assertSame($pseudonym->street, self::apply(new Street(), '12 rue des Lilas', $context));
        self::assertSame($pseudonym->postalCode, self::apply(new PostalCode(), '69002', $context));
        self::assertSame($pseudonym->city, self::apply(new City(), 'Lyon', $context));
    }

    /**
     * Every column of one object speaks of the same fake person, whatever the table.
     */
    public function testTwoTablesReferencingTheSameObjectAgree(): void
    {
        $generator = new Generator(self::SALT);
        $authors = self::context($generator, key: '42', reference: new Reference('person', 42));
        $contacts = self::context($generator, column: 'mail', key: '7', reference: new Reference('person', '42'));

        self::assertSame(self::apply(new Email(), 'a@example-real.test', $authors), self::apply(new Email(), 'b@example-real.test', $contacts));
        self::assertNotSame(
            self::apply(new Email(), 'a@example-real.test', $authors),
            self::apply(new Email(), 'a@example-real.test', self::context($generator, reference: new Reference('person', 43))),
        );
    }

    public function testALoginBecomesTheFakeHandle(): void
    {
        $generator = new Generator(self::SALT);

        self::assertSame(
            $generator->pseudonymFor(new Reference('person', 42))->handle,
            self::apply(new Login(), 'zkowalczyk', self::context($generator)),
        );
        self::assertMatchesRegularExpression('/^[a-z0-9-]+\\.[a-z0-9-]+-[0-9a-z]+$/', self::applyToString(new Login(), 'ZOE'));
    }

    /**
     * A number written two ways stays one: the digits come from the pseudonym, the last first.
     */
    public function testThePhoneTakesTheDigitsOfThePseudonymFromTheLast(): void
    {
        $generator = new Generator(self::SALT);
        $digits = $generator->pseudonymFor(new Reference('person', 42))->phoneDigits;
        $context = self::context($generator);
        $last = strrev(substr($digits, 0, 9));
        $national = '0' . $last[0] . ' ' . implode(' ', str_split(substr($last, 1), 2));

        self::assertMatchesRegularExpression('/^\d{15}$/', $digits);
        self::assertSame($national, self::apply(new Phone(), '06 12 34 56 78', $context), 'the trunk prefix stays');
        self::assertSame('+33 ' . substr($national, 1), self::apply(new Phone(), '+33 6 12 34 56 78', $context), 'the dialling code stays');
        self::assertSame('(' . substr($last, -3, 2) . ') ' . substr($last, -1), self::apply(new Phone(), '(12) 3', $context));
    }

    public function testAPhoneWithLettersIsRefused(): void
    {
        $this->expectException(InvalidValueException::class);
        $this->expectExceptionMessage('people.value: letters found');

        self::apply(new Phone(), '01 23 ext. 4');
    }
}
