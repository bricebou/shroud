<?php

declare(strict_types=1);

namespace Shroud\Tests\Unit\Strategy\Wrapper;

use Shroud\Exception\InvalidConfigurationException;
use Shroud\Exception\InvalidValueException;
use Shroud\Strategy\Pseudonym\Email;
use Shroud\Strategy\Pseudonym\Login;
use Shroud\Strategy\Value\Clear;
use Shroud\Strategy\Value\Constant;
use Shroud\Strategy\Wrapper\Fallback;
use Shroud\Strategy\Wrapper\Hashed;
use Shroud\Tests\Unit\Strategy\StrategyTestCase;

final class HashedTest extends StrategyTestCase
{
    public function testADigestBecomesTheDigestOfTheSource(): void
    {
        self::assertSame(md5('fake'), self::apply(new Hashed(new Constant('fake')), md5('real')));
        self::assertSame(hash('sha256', 'fake'), self::apply(new Hashed(new Constant('fake'), 'sha256'), hash('sha256', 'real')));
        self::assertSame(strtoupper(sha1('fake')), self::apply(new Hashed(new Constant('fake'), 'sha1'), strtoupper(sha1('real'))), 'the case is kept');
    }

    /**
     * A column that stores the md5 of the email keeps its relation to the fake email.
     */
    public function testTheRelationToTheHashedColumnIsKept(): void
    {
        $email = self::applyToString(new Email(), 'zoe.kowalczyk@example-real.test');

        self::assertSame(md5($email), self::apply(new Hashed(new Email(), 'md5'), md5('zoe.kowalczyk@example-real.test')));
    }

    public function testAValueThatIsNotADigestOfTheAlgorithmIsRefused(): void
    {
        foreach (['zkowalczyk', sha1('real'), md5('real') . 'z', 42] as $value) {
            try {
                self::apply(new Hashed(new Constant('fake'), 'md5'), $value);
                self::fail('refused: ' . get_debug_type($value));
            } catch (InvalidValueException $exception) {
                self::assertSame('md5 digest expected', $exception->reason);
            }
        }
    }

    public function testAColumnMixingDigestsAndLoginsFallsBack(): void
    {
        $login = new Fallback(new Hashed(new Email(), 'md5'), new Login());

        self::assertSame(md5(self::applyToString(new Email(), 'x')), self::apply($login, md5('zoe.kowalczyk@example-real.test')));
        self::assertSame(self::apply(new Login(), 'zkowalczyk'), self::apply($login, 'zkowalczyk'));
    }

    public function testNullAndTheEmptyStringPassThrough(): void
    {
        self::assertNull(self::apply(new Hashed(new Constant('fake')), value: null));
        self::assertSame('', self::apply(new Hashed(new Constant('fake')), ''));
    }

    public function testASourceThatReturnsNoStringIsRefused(): void
    {
        $this->expectException(InvalidValueException::class);
        $this->expectExceptionMessage('the hashed strategy returned int');

        self::apply(new Hashed(new Constant(0)), md5('real'));
    }

    public function testAnUnknownAlgorithmIsRefusedAtDeclaration(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('unknown hash algorithm: md6');

        new Hashed(new Clear(), 'md6');
    }
}
