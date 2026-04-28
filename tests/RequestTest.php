<?php
declare(strict_types=1);

namespace Tests;

use App\Core\Request;
use PHPUnit\Framework\TestCase;

final class RequestTest extends TestCase
{
    public function testUsesCloudflareConnectingIpWhenPresent(): void
    {
        $req = new Request([], [], [
            'REMOTE_ADDR' => '172.68.10.20',
            'HTTP_CF_CONNECTING_IP' => '203.0.113.10',
        ]);

        $this->assertSame('203.0.113.10', $req->ip());
    }

    public function testFallsBackToFirstValidForwardedForIp(): void
    {
        $req = new Request([], [], [
            'REMOTE_ADDR' => '172.68.10.20',
            'HTTP_X_FORWARDED_FOR' => 'bad-ip, 198.51.100.5, 198.51.100.9',
        ]);

        $this->assertSame('198.51.100.5', $req->ip());
    }

    public function testFallsBackToRemoteAddrWhenProxyHeadersMissing(): void
    {
        $req = new Request([], [], [
            'REMOTE_ADDR' => '198.51.100.15',
        ]);

        $this->assertSame('198.51.100.15', $req->ip());
    }

    public function testReturnsDefaultWhenNoValidIpExists(): void
    {
        $req = new Request([], [], [
            'REMOTE_ADDR' => 'not-an-ip',
            'HTTP_CF_CONNECTING_IP' => '',
        ]);

        $this->assertSame('0.0.0.0', $req->ip());
    }
}
