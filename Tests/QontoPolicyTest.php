<?php

namespace Omnibank\Qonto\Tests;

use Omnibank\Exception\RateLimitedException;
use Omnibank\Exception\UnavailableException;
use Omnibank\Exception\UsageLimitReachedException;
use Omnibank\Model\Connection;
use Omnibank\Qonto\QontoGatewayFactory;
use Omnibank\Registry;
use Omnisense\Contracts\Policy\PolicyExport;
use Omnisense\Contracts\Policy\Scope;
use Omnisense\Contracts\Policy\UsagePolicy;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * Qonto's usage policy (1,000 requests every 10 seconds and 10,000 every 10
 * minutes, per IP address), a site's tighter limit refusing a read before it
 * is sent, and a 429: surfaced once, the next calls held unsent for the wait.
 */
final class QontoPolicyTest extends TestCase
{
    /** @var list<string> */
    private array $calls = [];

    public function testThePolicyIsDeclaredAsData(): void
    {
        $declared = (new PolicyExport())->folders(\dirname(__DIR__))->entries();
        self::assertCount(1, $declared, 'composer.json\'s extra.omnisense.policy names it');
        self::assertSame(['omnibank/qonto', 'policy.json'], [$declared[0]['package'], $declared[0]['file']]);
        self::assertArrayNotHasKey('error', $declared[0], 'it reads as the schema says');
        $policy = UsagePolicy::fromFile(__DIR__.'/../policy.json');

        self::assertSame('qonto', $policy->provider);
        self::assertSame('2026-11-10', $policy->provenance->verifiedAt);
        $limits = $policy->limitsFor('transactions');
        self::assertSame([1000, 10000], array_map(static fn ($l) => $l->max, $limits));
        self::assertSame([10, 600], array_map(static fn ($l) => $l->seconds(), $limits));
        self::assertSame(Scope::ClientIp, $limits[0]->scope);
        $registry = new Registry([new QontoGatewayFactory(new MockHttpClient())], ['treasury' => ['factory' => 'qonto', 'options' => ['login' => 'l', 'secret_key' => 's']]]);
        self::assertEquals($policy, $registry->policy('treasury'));
    }

    public function testASiteLimitRefusesTheReadBeforeItIsSent(): void
    {
        $registry = $this->registry(fn () => new MockResponse(self::organization()), ['rateLimits' => [['max' => 1, 'window' => 'minute', 'operations' => ['accounts']]]]);
        $registry->guarded('treasury')->accounts(new Connection());

        try {
            $registry->guarded('treasury')->accounts(new Connection());
            self::fail('one a minute');
        } catch (UsageLimitReachedException $e) {
            self::assertGreaterThan(0, $e->retryAfter);
        }
        self::assertCount(1, $this->calls, 'the second was not sent');
    }

    public function testA429IsSurfacedNotRetriedAndHoldsTheNextCalls(): void
    {
        $answers = [new MockResponse('', ['http_code' => 429, 'response_headers' => ['retry-after' => '20']])];
        $gateway = $this->registry(function () use (&$answers): MockResponse { return array_shift($answers) ?? new MockResponse(self::organization()); })->get('treasury');

        try {
            $gateway->accounts(new Connection());
            self::fail('a 429');
        } catch (RateLimitedException $e) {
            self::assertInstanceOf(UnavailableException::class, $e, 'a 429 is still "try again later"');
            self::assertSame(20, $e->retryAfter);
            self::assertSame('[qonto] Qonto answered HTTP 429: try again later.', $e->getMessage());
        }
        self::assertCount(1, $this->calls, 'sent once');

        $this->expectException(UsageLimitReachedException::class);
        try {
            $gateway->accounts(new Connection());
        } finally {
            self::assertCount(1, $this->calls, 'held, not sent');
        }
    }

    /**
     * @param \Closure(): MockResponse $answer
     * @param array<string, mixed>     $policy
     */
    private function registry(\Closure $answer, array $policy = []): Registry
    {
        $http = new MockHttpClient(function (string $method, string $url) use ($answer): MockResponse {
            $this->calls[] = $method.' '.$url;

            return $answer();
        });

        return new Registry([new QontoGatewayFactory($http)], ['treasury' => ['factory' => 'qonto', 'options' => ['login' => 'example-sas-1234', 'secret_key' => 'sk_qonto'], 'policy' => $policy]]);
    }

    private static function organization(): string
    {
        return (string) file_get_contents(__DIR__.'/Fixtures/organization.json');
    }
}
