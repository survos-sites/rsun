<?php

declare(strict_types=1);
namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class McpConnectionTest extends WebTestCase
{
    public function testPublicAgentOriginsCanInitializeWithoutAuthentication(): void
    {
        $client=self::createClient();
        foreach (['https://chatgpt.com','https://claude.ai'] as $origin) {
            $client->request('POST','/mcp',server: ['CONTENT_TYPE'=>'application/json','HTTP_ACCEPT'=>'application/json, text/event-stream','HTTP_ORIGIN'=>$origin],content: json_encode([
                'jsonrpc'=>'2.0','id'=>1,'method'=>'initialize','params'=>['protocolVersion'=>'2025-03-26','capabilities'=>(object)[],'clientInfo'=>['name'=>'test','version'=>'1']],
            ],JSON_THROW_ON_ERROR));
            self::assertResponseIsSuccessful();
            self::assertStringContainsString('VA Bills',$client->getResponse()->getContent());
        }
    }

    public function testOtherOriginsAreRejected(): void
    {
        $client=self::createClient();
        $client->request('POST','/mcp',server:['CONTENT_TYPE'=>'application/json','HTTP_ORIGIN'=>'https://unrelated.example'],content:'{}');
        self::assertResponseStatusCodeSame(403);
    }
}
