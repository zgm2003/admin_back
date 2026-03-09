<?php

namespace tests\Unit;

use app\dep\BaseDep;
use app\middleware\AccessControl;
use app\middleware\OperationLog;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use support\Model;

class SecurityHardeningTest extends TestCase
{
    public function testAccessControlAllowsTrustedLocalOrigins(): void
    {
        $this->assertSame('http://127.0.0.1:4173', AccessControl::resolveAllowedOrigin('http://127.0.0.1:4173'));
        $this->assertSame('https://127.0.0.1', AccessControl::resolveAllowedOrigin('https://127.0.0.1'));
        $this->assertSame('http://localhost:5173', AccessControl::resolveAllowedOrigin('http://localhost:5173'));
        $this->assertSame('https://zgm2003.cn', AccessControl::resolveAllowedOrigin('https://zgm2003.cn'));
    }

    public function testAccessControlRejectsOriginsOutsideWhitelist(): void
    {
        $this->assertNull(AccessControl::resolveAllowedOrigin('https://www.zgm2003.cn'));
        $this->assertNull(AccessControl::resolveAllowedOrigin('https://evil.example'));
    }

    public function testOperationLogMasksSensitiveRequestFieldsRecursively(): void
    {
        $sanitized = OperationLog::sanitizeForLog([
            'password' => 'secret',
            'code' => '123456',
            'nested' => [
                'refresh_token' => 'refresh-token',
                'profile' => [
                    'secret_key' => 'secret-key',
                ],
            ],
            'safe' => 'visible',
        ], true);

        $this->assertSame('******', $sanitized['password']);
        $this->assertSame('******', $sanitized['code']);
        $this->assertSame('******', $sanitized['nested']['refresh_token']);
        $this->assertSame('******', $sanitized['nested']['profile']['secret_key']);
        $this->assertSame('visible', $sanitized['safe']);
    }

    public function testOperationLogKeepsBusinessResponseCodeVisible(): void
    {
        $sanitized = OperationLog::sanitizeForLog([
            'code' => 0,
            'msg' => 'ok',
            'access_token' => 'token-value',
        ]);

        $this->assertSame(0, $sanitized['code']);
        $this->assertSame('ok', $sanitized['msg']);
        $this->assertSame('******', $sanitized['access_token']);
    }

    public function testBaseDepRejectsUnsafeCounterColumn(): void
    {
        $dep = $this->makeBaseDepWithoutConstructor();

        $this->expectException(InvalidArgumentException::class);
        $dep->decrement(1, 'stock - 1', 1);
    }

    public function testBaseDepRejectsNonPositiveCounterAmount(): void
    {
        $dep = $this->makeBaseDepWithoutConstructor();

        $this->expectException(InvalidArgumentException::class);
        $dep->increment(1, 'stock', 0);
    }

    private function makeBaseDepWithoutConstructor(): BaseDep
    {
        return (new ReflectionClass(FakeBaseDepForSecurityTest::class))->newInstanceWithoutConstructor();
    }
}

class FakeBaseDepForSecurityTest extends BaseDep
{
    protected function createModel(): Model
    {
        throw new \LogicException('createModel should not be called in this test.');
    }
}
